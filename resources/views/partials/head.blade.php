<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
{{-- Echo sends it when it subscribes to the user's private channel (/broadcasting/auth, ADR-0006). --}}
<meta name="csrf-token" content="{{ csrf_token() }}" />

{{-- Page titles may be translation keys (#[Title('assets.index.title')]) or kit strings: both go through __(). --}}
<title>
    {{ filled($title ?? null) ? __($title).' · '.config('app.name') : config('app.name') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

{{-- The Vite dev server reads the nonce here for the styles it injects (App\Support\Http\ContentSecurityPolicy). --}}
@if (Vite::isRunningHot())
    <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}" />
@endif

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance(['nonce' => Vite::cspNonce()])
