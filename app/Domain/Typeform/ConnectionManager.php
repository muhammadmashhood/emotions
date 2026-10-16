<?php

namespace App\Domain\Typeform;

use App\Domain\Audit\AuditLogger;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Models\TypeformConnection;
use App\Models\User;

/**
 * Creates / verifies the (single, in the MVP) Typeform connection.
 */
class ConnectionManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function current(): ?TypeformConnection
    {
        return TypeformConnection::query()->orderBy('id')->first();
    }

    /**
     * Verify the token with GET /me and persist it encrypted. Throws TypeformException on failure (nothing saved).
     */
    public function save(string $label, string $baseUrl, string $token, User $by): TypeformConnection
    {
        $candidate = $this->current() ?? new TypeformConnection;
        $candidate->fill(['label' => $label, 'base_url' => rtrim($baseUrl, '/')]);
        $candidate->access_token = $token;

        $me = TypeformClient::for($candidate)->me();

        $candidate->account_alias = $me['alias'] ?? ($me['email'] ?? null);
        $candidate->last_verified_at = now();
        $candidate->last_error = null;
        $candidate->save();

        $this->audit->log('typeform.connection_saved', $candidate, ['label' => $label, 'base_url' => $baseUrl, 'token_suffix' => substr($token, -4)], null, $by);

        return $candidate;
    }

    /**
     * Re-check the stored token; records the outcome on the connection.
     */
    public function test(TypeformConnection $connection, ?User $by = null): bool
    {
        try {
            $me = TypeformClient::for($connection)->me();
            $connection->forceFill([
                'account_alias' => $me['alias'] ?? $connection->account_alias,
                'last_verified_at' => now(),
                'last_error' => null,
            ])->save();

            return true;
        } catch (TypeformException $e) {
            $connection->forceFill(['last_error' => $e->getMessage()])->save();
            $this->audit->log('typeform.connection_failed', $connection, ['error' => $e->getMessage()], null, $by);

            return false;
        }
    }
}
