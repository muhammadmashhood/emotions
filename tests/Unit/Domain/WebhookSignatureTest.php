<?php

use App\Domain\Typeform\WebhookSignature;

test('known body and secret produce the known signature', function () {
    $body = '{"event_id":"1","event_type":"form_response"}';
    $secret = 'top-secret';
    $expected = 'sha256='.base64_encode(hash_hmac('sha256', $body, $secret, true));

    expect(WebhookSignature::sign($body, $secret))->toBe($expected)
        ->and(WebhookSignature::verify($body, $secret, $expected))->toBeTrue();
});

test('tampered body, wrong secret, wrong prefix and missing header fail', function () {
    $body = '{"a":1}';
    $sig = WebhookSignature::sign($body, 's');

    expect(WebhookSignature::verify('{"a":2}', 's', $sig))->toBeFalse()
        ->and(WebhookSignature::verify($body, 'other', $sig))->toBeFalse()
        ->and(WebhookSignature::verify($body, 's', 'sha1='.substr($sig, 7)))->toBeFalse()
        ->and(WebhookSignature::verify($body, 's', substr($sig, 7)))->toBeFalse()
        ->and(WebhookSignature::verify($body, 's', null))->toBeFalse()
        ->and(WebhookSignature::verify($body, 's', ''))->toBeFalse();
});

test('verification uses the raw body, so re-encoded JSON would not match', function () {
    $raw = '{"b": 1, "a": 2}';
    $sig = WebhookSignature::sign($raw, 's');

    expect(WebhookSignature::verify(json_encode(json_decode($raw, true)), 's', $sig))->toBeFalse()
        ->and(WebhookSignature::verify($raw, 's', $sig))->toBeTrue();
});
