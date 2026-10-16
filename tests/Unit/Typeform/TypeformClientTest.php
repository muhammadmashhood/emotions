<?php

use App\Domain\Typeform\Exceptions\TypeformAuthException;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Domain\Typeform\Exceptions\TypeformNotFoundException;
use App\Domain\Typeform\Exceptions\TypeformRateLimitException;
use App\Domain\Typeform\TypeformClient;
use App\Models\TypeformConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

function connection(string $baseUrl = 'https://api.typeform.com'): TypeformConnection
{
    return new TypeformConnection(['label' => 'Test', 'base_url' => $baseUrl, 'access_token' => 'tfp_secret_token_1234']);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
});

test('me sends a bearer token to the connection base url', function () {
    Http::fake(['https://api.eu.typeform.com/me' => Http::response(['alias' => 'acme', 'email' => 'a@b.c'])]);

    $me = (new TypeformClient(connection('https://api.eu.typeform.com')))->me();

    expect($me['alias'])->toBe('acme');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.eu.typeform.com/me'
        && $r->hasHeader('Authorization', 'Bearer tfp_secret_token_1234'));
});

test('forms list passes paging, search and workspace parameters', function () {
    Http::fake(['https://api.typeform.com/forms*' => Http::response([
        'total_items' => 1, 'page_count' => 1,
        'items' => [['id' => 'abc', 'title' => 'Survey', 'last_updated_at' => '2026-01-01T00:00:00Z']],
    ])]);

    $page = (new TypeformClient(connection()))->forms(page: 2, pageSize: 50, search: 'ittefaq', workspaceId: 'ws1');

    expect($page->totalItems)->toBe(1)
        ->and($page->items[0]->id)->toBe('abc')
        ->and($page->items[0]->title)->toBe('Survey');
    Http::assertSent(function (Request $r) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

        return $q['page'] === '2' && $q['page_size'] === '50' && $q['search'] === 'ittefaq' && $q['workspace_id'] === 'ws1';
    });
});

test('webhook put sends url, secret, enabled and verify_ssl', function () {
    Http::fake(['https://api.typeform.com/forms/F1/webhooks/emotion-1234' => Http::response(['id' => 'wh', 'tag' => 'emotion-1234', 'url' => 'https://x/y', 'enabled' => true])]);

    $info = (new TypeformClient(connection()))->putWebhook('F1', 'emotion-1234', 'https://x/y', 's3cret');

    expect($info->enabled)->toBeTrue()->and($info->url)->toBe('https://x/y');
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
        && $r['url'] === 'https://x/y' && $r['secret'] === 's3cret' && $r['enabled'] === true && $r['verify_ssl'] === true);
});

test('delete webhook ignores 404', function () {
    Http::fake(['https://api.typeform.com/forms/F1/webhooks/t' => Http::response(null, 404)]);

    (new TypeformClient(connection()))->deleteWebhook('F1', 't');

    Http::assertSentCount(1);
});

test('responses passes before, since and completed and parses the page', function () {
    Http::fake(['https://api.typeform.com/forms/F1/responses*' => Http::response([
        'total_items' => 2, 'page_count' => 1,
        'items' => [['token' => 't1', 'submitted_at' => '2026-01-01T00:00:00Z', 'answers' => []], ['token' => 't2', 'answers' => []]],
    ])]);

    $page = (new TypeformClient(connection()))->responses('F1', ['before' => 'tok', 'since' => '2026-01-01T00:00:00Z', 'completed' => 'true']);

    expect($page->items)->toHaveCount(2)->and($page->lastToken())->toBe('t2');
    Http::assertSent(function (Request $r) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

        return $q['before'] === 'tok' && $q['page_size'] === '1000' && $q['completed'] === 'true';
    });
});

test('401 raises an auth exception without retrying', function () {
    Http::fake(['*' => Http::response(['code' => 'AUTHENTICATION_FAILED'], 401)]);

    expect(fn () => (new TypeformClient(connection()))->me())->toThrow(TypeformAuthException::class);
    Http::assertSentCount(1);
});

test('404 raises a not found exception', function () {
    Http::fake(['*' => Http::response(['code' => 'NOT_FOUND'], 404)]);

    expect(fn () => (new TypeformClient(connection()))->form('nope'))->toThrow(TypeformNotFoundException::class);
});

test('429 is retried with backoff honouring Retry-After, then succeeds', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push(['code' => 'RATE_LIMIT'], 429, ['Retry-After' => '3'])
            ->push(['alias' => 'ok']),
    ]);

    $me = (new TypeformClient(connection()))->me();

    expect($me['alias'])->toBe('ok');
    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => $duration->totalSeconds >= 3, 1);
});

test('429 exhausting retries raises a rate limit exception', function () {
    Http::fake(['*' => Http::response(['code' => 'RATE_LIMIT'], 429)]);

    expect(fn () => (new TypeformClient(connection()))->me())->toThrow(TypeformRateLimitException::class);
    Http::assertSentCount(4); // initial + 3 retries
});

test('5xx is retried with exponential backoff and then fails', function () {
    Http::fake(['*' => Http::response('oops', 503)]);

    expect(fn () => (new TypeformClient(connection()))->me())->toThrow(TypeformException::class);
    Http::assertSentCount(4);
    Sleep::assertSequence([Sleep::for(1)->second(), Sleep::for(2)->seconds(), Sleep::for(4)->seconds()]);
});

test('requests are logged without the token', function () {
    Log::spy();
    Http::fake(['*' => Http::response(['alias' => 'x'])]);

    (new TypeformClient(connection()))->me();

    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) {
        return $message === 'typeform.request'
            && ! str_contains(json_encode($context), 'tfp_secret_token_1234')
            && $context['status'] === 200
            && $context['endpoint'] === 'GET /me';
    })->once();
});
