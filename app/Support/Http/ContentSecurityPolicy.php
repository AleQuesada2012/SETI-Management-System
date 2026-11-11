<?php

namespace App\Support\Http;

/**
 * The Content-Security-Policy of every web page (config/csp.php).
 *
 * Scripts and style sheets run only from this site or with the request's nonce, which Vite, Livewire and Flux print
 * on their tags (`Vite::useCspNonce()` in SecurityHeaders). Livewire's CSP-safe build (`livewire.csp_safe`) evaluates
 * Alpine expressions without `eval`, so 'unsafe-eval' is not needed.
 *
 * Inline style *attributes* are allowed: Flux's modals toggle `style="display: …"` on their focus-trap placeholders
 * with setAttribute, which a stricter policy would block. A style attribute cannot run script, and img-src keeps a
 * `url()` in one from reaching another site.
 *
 * Frames: only the Power BI report of RF_61 (the origin of `reports.powerbi_embed_url`), when one is configured.
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
            'style-src-attr' => ["'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'blob:', ...$vite],
            'font-src' => ["'self'", ...$vite],
            'connect-src' => ["'self'", config('csp.websocket'), ...($this->local ? [
                config('csp.local.websocket'),
                ...$vite,
                'ws://'.config('csp.local.vite'),
            ] : [])],
            'frame-src' => ["'self'", $this->origin(config('reports.powerbi_embed_url'))],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', array_filter($sources)))
            ->implode('; ');
    }

    /** `https://host[:port]` of a URL, or null when there is none. */
    private function origin(?string $url): ?string
    {
        $parts = parse_url((string) $url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
