<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-ink antialiased">
        {{-- Dark navy sidebar in every appearance, as in the mockups. `dark` makes Flux render its dark variant inside it. --}}
        <flux:sidebar sticky collapsible="mobile" class="dark border-e border-sidebar-line bg-sidebar">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            {{-- One entry per module, shown only to users who may open it: the same checks as the routes (RF_89). --}}
            <flux:sidebar.nav>
                @can(App\Enums\PermissionName::DashboardsView)
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('app.nav.dashboard') }}
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', App\Models\Asset::class)
                    <flux:sidebar.item icon="computer-desktop" :href="route('assets.index')" :current="request()->routeIs('assets.*')" wire:navigate>
                        {{ __('app.nav.assets') }}
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', App\Models\Space::class)
                    <flux:sidebar.item icon="building-office-2" :href="route('spaces.index')" :current="request()->routeIs('spaces.*')" wire:navigate>
                        {{ __('app.nav.spaces') }}
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', App\Models\Reservation::class)
                    <flux:sidebar.item icon="calendar-days" :href="route('reservations.index')" :current="request()->routeIs('reservations.*')" wire:navigate>
                        {{ __('app.nav.reservations') }}
                    </flux:sidebar.item>
                @endcan

                @can(App\Enums\PermissionName::ReportsView)
                    <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                        {{ __('app.nav.reports') }}
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', App\Models\User::class)
                    <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*', 'roles.*')" wire:navigate>
                        {{ __('app.nav.users') }}
                    </flux:sidebar.item>
                @elsecan('viewAny', App\Models\Role::class)
                    <flux:sidebar.item icon="users" :href="route('roles.index')" :current="request()->routeIs('roles.*')" wire:navigate>
                        {{ __('app.nav.users') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>
                    {{ __('app.nav.settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{-- Top bar: page title on the left, the notification bell and the user menu on the right (mockups, ADR-0006). --}}
        <flux:header class="border-b border-line bg-surface">
            <flux:sidebar.toggle class="me-2 lg:hidden" icon="bars-2" inset="left" :aria-label="__('app.nav.open_menu')" />

            <flux:heading size="lg" level="1" class="truncate">{{ filled($title ?? null) ? __($title) : config('app.name') }}</flux:heading>

            <flux:spacer />

            <livewire:notifications.bell />

            <x-user-menu />
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
