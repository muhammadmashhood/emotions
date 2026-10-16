<?php

namespace App\Domain\Typeform;

/**
 * Typeform webhook signatures: "sha256=" + base64(HMAC-SHA256(raw_body, secret)). Pure.
 */
final class WebhookSignature
{
    public const HEADER = 'Typeform-Signature';

    public static function sign(string $rawBody, string $secret): string
    {
        return 'sha256='.base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
    }

    public static function verify(string $rawBody, string $secret, ?string $header): bool
    {
        if ($header === null || $header === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals(self::sign($rawBody, $secret), $header);
    }
}
