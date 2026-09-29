<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    {{-- Mockup "Iniciar sesión": a white card centered on the navy background, in every appearance. --}}
    <body class="min-h-screen bg-backdrop antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div class="w-full max-w-sm rounded-xl bg-surface p-8 text-ink shadow-xl ring-1 ring-line">
                <a href="{{ route('home') }}" class="mb-5 flex size-10 items-center justify-center rounded-lg bg-brand text-brand-foreground" wire:navigate>
                    <x-app-logo-icon class="size-6" />
                    <span class="sr-only">{{ config('app.name') }}</span>
                </a>

                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
