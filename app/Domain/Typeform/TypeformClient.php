<?php

namespace App\Domain\Typeform;

use App\Domain\Typeform\Data\FormListPage;
use App\Domain\Typeform\Data\ResponsesPage;
use App\Domain\Typeform\Data\WebhookInfo;
use App\Domain\Typeform\Data\Workspace;
use App\Domain\Typeform\Exceptions\TypeformAuthException;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Domain\Typeform\Exceptions\TypeformNotFoundException;
use App\Domain\Typeform\Exceptions\TypeformRateLimitException;
use App\Models\TypeformConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * All HTTP calls to the Typeform API. Verified against developer.typeform.com (Create / Responses / Webhooks APIs).
 *
 * - connect timeout 5 s, total 20 s
 * - retries on 429 / 5xx / connection errors with 1 s, 2 s, 4 s backoff (Retry-After honoured)
 * - throttled per connection to config('emotion.typeform.rate_limit_per_second')
 * - never logs the token or headers; logs endpoint, status and duration only
 */
class TypeformClient
{
    public function __construct(private readonly TypeformConnection $connection) {}

    public static function for(TypeformConnection $connection): self
    {
        return new self($connection);
    }

    public function connection(): TypeformConnection
    {
        return $this->connection;
    }

    /** @return array<string, mixed> */
    public function me(): array
    {
        return $this->request('GET', '/me')->json() ?? [];
    }

    /** @return list<Workspace> */
    public function workspaces(): array
    {
        $items = [];
        $page = 1;
        do {
            $data = $this->request('GET', '/workspaces', ['page' => $page, 'page_size' => 200])->json() ?? [];
            foreach ($data['items'] ?? [] as $item) {
                $items[] = Workspace::fromApi($item);
            }
            $pageCount = (int) ($data['page_count'] ?? 1);
            $page++;
        } while ($page <= $pageCount && $page <= 20);

        return $items;
    }

    public function forms(int $page = 1, int $pageSize = 200, ?string $search = null, ?string $workspaceId = null, ?string $workspaceName = null): FormListPage
    {
        $query = array_filter([
            'page' => $page,
            'page_size' => $pageSize,
            'search' => $search,
            'workspace_id' => $workspaceId,
        ], fn ($v) => $v !== null && $v !== '');

        return FormListPage::fromApi($this->request('GET', '/forms', $query)->json() ?? [], $workspaceId, $workspaceName);
    }

    /** @return array<string, mixed> full form definition */
    public function form(string $formId): array
    {
        return $this->request('GET', '/forms/'.rawurlencode($formId))->json() ?? [];
    }

    public function putWebhook(string $formId, string $tag, string $url, string $secret): WebhookInfo
    {
        $response = $this->request('PUT', '/forms/'.rawurlencode($formId).'/webhooks/'.rawurlencode($tag), body: [
            'url' => $url,
            'enabled' => true,
            'secret' => $secret,
            'verify_ssl' => true,
        ]);

        return WebhookInfo::fromApi($response->json() ?? []);
    }

    public function getWebhook(string $formId, string $tag): WebhookInfo
    {
        return WebhookInfo::fromApi($this->request('GET', '/forms/'.rawurlencode($formId).'/webhooks/'.rawurlencode($tag))->json() ?? []);
    }

    public function deleteWebhook(string $formId, string $tag): void
    {
        try {
            $this->request('DELETE', '/forms/'.rawurlencode($formId).'/webhooks/'.rawurlencode($tag));
        } catch (TypeformNotFoundException) {
            // Already gone.
        }
    }

    /**
     * @param  array<string, scalar|null>  $query  since / until / before / after / completed …
     */
    public function responses(string $formId, array $query = []): ResponsesPage
    {
        $query = array_filter(array_merge(['page_size' => 1000], $query), fn ($v) => $v !== null && $v !== '');

        return ResponsesPage::fromApi($this->request('GET', '/forms/'.rawurlencode($formId).'/responses', $query)->json() ?? []);
    }

    /**
     * Download an uploaded file (answer `file_url`) with the bearer token.
     */
    public function download(string $fileUrl): Response
    {
        return $this->send(fn (PendingRequest $http) => $http->get($fileUrl), 'GET '.parse_url($fileUrl, PHP_URL_PATH));
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $url = rtrim($this->connection->base_url, '/').$path;
        $endpoint = $method.' '.$path;

        return $this->send(function (PendingRequest $http) use ($method, $url, $query, $body) {
            if ($query !== []) {
                $http = $http->withQueryParameters($query);
            }

            return match ($method) {
                'GET' => $http->get($url),
                'PUT' => $http->put($url, $body ?? []),
                'POST' => $http->post($url, $body ?? []),
                'DELETE' => $http->delete($url),
                default => throw new \InvalidArgumentException("Unsupported method {$method}"),
            };
        }, $endpoint);
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     */
    private function send(callable $send, string $endpoint): Response
    {
        $this->throttle();
        $started = microtime(true);
        $maxRetries = (int) config('emotion.typeform.max_retries', 3);

        $http = Http::withToken((string) $this->connection->access_token)
            ->acceptJson()
            ->connectTimeout((int) config('emotion.typeform.connect_timeout', 5))
            ->timeout((int) config('emotion.typeform.timeout', 20))
            ->retry(
                $maxRetries + 1, // Laravel counts total attempts: 1 initial + N retries
                function (int $attempt, \Throwable $exception): int {
                    $retryAfter = $exception instanceof RequestException ? $exception->response->header('Retry-After') : null;
                    if (is_string($retryAfter) && $retryAfter !== '' && is_numeric($retryAfter)) {
                        return max(1000, (int) $retryAfter * 1000);
                    }

                    return 1000 * (2 ** ($attempt - 1));
                },
                fn (\Throwable $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false,
            );

        try {
            $response = $send($http);
        } catch (ConnectionException $e) {
            $this->log($endpoint, null, $started);
            throw new TypeformException('Could not reach Typeform: '.$e->getMessage(), null, $endpoint, $e);
        }

        $this->log($endpoint, $response->status(), $started);

        return $this->assertSuccessful($response, $endpoint);
    }

    private function assertSuccessful(Response $response, string $endpoint): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $detail = $response->json('description') ?? $response->json('code') ?? $response->reason();
        $message = "Typeform responded {$status} for {$endpoint}: {$detail}";

        throw match (true) {
            $status === 401 || $status === 403 => new TypeformAuthException($message, $status, $endpoint),
            $status === 404 => new TypeformNotFoundException($message, $status, $endpoint),
            $status === 429 => new TypeformRateLimitException($message, $status, $endpoint),
            default => new TypeformException($message, $status, $endpoint),
        };
    }

    private function throttle(): void
    {
        $perSecond = max(1, (int) config('emotion.typeform.rate_limit_per_second', 2));
        $key = 'typeform:'.($this->connection->id ?? 'new');

        for ($i = 0; $i < 10; $i++) {
            if (! RateLimiter::tooManyAttempts($key, $perSecond)) {
                RateLimiter::hit($key, 1);

                return;
            }
            Sleep::for(max(1, RateLimiter::availableIn($key)))->seconds();
        }
    }

    private function log(string $endpoint, ?int $status, float $started): void
    {
        Log::info('typeform.request', [
            'endpoint' => $endpoint,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'connection_id' => $this->connection->id,
        ]);
    }
}
