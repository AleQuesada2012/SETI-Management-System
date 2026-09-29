{{--
    Shared layout of the Spanish error pages: errors/<status>.blade.php includes it with its status code.
    The request id helps the team find the matching log lines and audit entries.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __("app.errors.$code.title")])
    </head>
    <body class="min-h-screen bg-backdrop antialiased">
        <main class="flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div class="w-full max-w-md rounded-xl bg-surface p-8 shadow-xl ring-1 ring-line">
                <div class="mb-5 flex size-10 items-center justify-center rounded-lg bg-brand text-brand-foreground">
                    <x-app-logo-icon class="size-6" />
                </div>

                <p class="text-sm font-semibold text-brand-ink">{{ __('app.errors.code', ['code' => $code]) }}</p>
                <h1 class="mt-1 text-xl font-semibold text-ink">{{ __("app.errors.$code.title") }}</h1>
                <p class="mt-2 text-sm text-ink-muted">{{ __("app.errors.$code.message") }}</p>

                <a href="{{ url('/') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-brand px-4 py-2 text-sm font-medium text-brand-foreground hover:bg-brand-hover">
                    {{ __('app.errors.home') }}
                </a>

                @if ($requestId = Illuminate\Support\Facades\Context::get(App\Support\Audit\AuditContext::REQUEST_ID))
                    <p class="mt-4 text-center text-xs text-ink-muted">{{ __('app.errors.request_id', ['id' => $requestId]) }}</p>
                @endif
            </div>
        </main>
    </body>
</html>
