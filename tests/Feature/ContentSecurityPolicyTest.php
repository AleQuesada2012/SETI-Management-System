<?php

// Platform (#221): the Content-Security-Policy of every page (SecurityHeaders, App\Support\Http\ContentSecurityPolicy).

use App\Enums\SystemRole;
use App\Support\Http\ContentSecurityPolicy;
use Illuminate\Testing\TestResponse;

/** The nonce the policy allows, read from the response's header. */
function policyNonce(TestResponse $response, string $header = 'Content-Security-Policy-Report-Only'): string
{
    expect($response->headers->get($header))->toMatch("/script-src [^;]*'nonce-[^']+'/");
    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get($header), $matches);

    return $matches[1];
}

test('a page is sent a report-only policy by default', function () {
    $policy = $this->get(route('login'))
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy')
        ->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'")
        ->toContain('connect-src \'self\' wss://'.parse_url(config('app.url'), PHP_URL_HOST))
        ->toContain("style-src-attr 'unsafe-inline'")
        ->not->toContain('unsafe-eval')
        ->not->toMatch("/(script-src|style-src) [^;]*'unsafe-inline'/");
});

test('CSP_ENFORCE sends the policy as enforced', function () {
    config(['csp.enforce' => true]);

    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('Content-Security-Policy')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

test('every script and style that Vite, Livewire and Flux print carries the nonce of the policy', function () {
    actingAsRole(SystemRole::Reportes);

    $response = $this->get(route('dashboard'))->assertOk();
    $nonce = policyNonce($response);

    preg_match_all('/<(script|style)\b[^>]*>/', $response->getContent(), $tags);

    expect(implode("\n", $tags[0]))
        ->toContain('/build/assets/')
        ->toContain('/flux/flux')
        ->toContain('livewire');

    foreach ($tags[0] as $tag) {
        expect($tag)->toContain("nonce=\"{$nonce}\"");
    }
});

test('each request gets a new nonce', function () {
    expect(policyNonce($this->get(route('login'))))->not->toBe(policyNonce($this->get(route('login'))));
});

test('only APP_ENV=local allows the Vite dev server and the local Reverb port', function () {
    $production = (new ContentSecurityPolicy('nonce'))->header();
    $local = (new ContentSecurityPolicy('nonce', local: true))->header();

    expect($production)->not->toContain('localhost:5173')->not->toContain('ws://localhost:8080')
        ->and($local)->toContain('script-src \'self\' \'nonce-nonce\' http://localhost:5173')
        ->and($local)->toContain('ws://localhost:8080')
        ->and($local)->toContain('ws://localhost:5173');
});
