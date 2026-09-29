{{-- RF_00_1 · Inicio de sesión (mockup "Iniciar sesión"). Email + password; no "remember me" (sessions last 120 min). --}}
<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in')" :description="__('security.login.subtitle')" />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="username"
                placeholder="usuario@tec.ac.cr"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                {{ __('security.login.submit') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
