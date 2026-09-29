@php($user = auth()->user())

<flux:dropdown position="bottom" align="end">
    <flux:profile :name="$user->name" :initials="$user->initials()" icon:trailing="chevron-down" class="max-sm:[&>span]:hidden" data-test="user-menu-button" />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar :name="$user->name" :initials="$user->initials()" />

            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                <flux:text class="truncate">{{ $user->email }}</flux:text>
            </div>
        </div>

        <div class="flex flex-wrap gap-1 px-1 pb-2">
            @foreach ($user->getRoleNames() as $role)
                <flux:badge size="sm" color="blue">{{ $role }}</flux:badge>
            @endforeach
        </div>

        <flux:menu.separator />

        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
            {{ __('app.nav.settings') }}
        </flux:menu.item>

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
