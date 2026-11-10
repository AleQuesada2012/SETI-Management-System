<?php

namespace App\Support\Http;

/**
 * The Content-Security-Policy of every web page (config/csp.php).
 *
 * Scripts and styles run only from this site or with the request's nonce, which Vite, Livewire and Flux print on
 * their tags (`Vite::useCspNonce()` in SecurityHeaders). Livewire's CSP-safe build (`livewire.csp_safe`) evaluates
 * Alpine expressions without `eval`, so 'unsafe-eval' is not needed; inline style attributes are not allowed either
 * (Alpine and Chart.js set styles through the DOM, which a policy does not restrict).
 */
class ContentSecurityPolicy
{
    public function __construct(private string $nonce, private bool $local = false) {}

    public function headerName(): string
    {
        return config('csp.enforce') ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
    }

    public function header(): string
    {
        $nonce = "'nonce-{$this->nonce}'";
        $vite = $this->local ? ['http://'.config('csp.local.vite')] : [];

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", $nonce, ...$vite],
            'style-src' => ["'self'", $nonce, ...$vite],
            'img-src' => ["'self'", 'data:', 'blob:', ...$vite],
            'font-src' => ["'self'", ...$vite],
            'connect-src' => ["'self'", config('csp.websocket'), ...($this->local ? [
                config('csp.local.websocket'),
                ...$vite,
                'ws://'.config('csp.local.vite'),
            ] : [])],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', array_filter($sources)))
            ->implode('; ');
    }
}
