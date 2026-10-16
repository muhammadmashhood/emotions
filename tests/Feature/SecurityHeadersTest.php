<?php

test('every response carries the security headers and a CSP', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain('https://fonts.googleapis.com')
        ->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval'");
});

test('HSTS is sent on secure requests only', function () {
    $this->get('/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $this->get('http://emotion.test/login')->assertHeaderMissing('Strict-Transport-Security');
});

test('session cookie is http-only and same-site lax', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});

test('error pages render with the platform styling', function () {
    $this->actingAs(viewer())->get('/evaluations/does-not-exist')->assertNotFound()->assertSee('Page not found');
    $this->actingAs(viewer())->get('/admin')->assertForbidden()->assertSee("You don't have access to this page", false);
});
