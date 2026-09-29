<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * The signed-in user's own profile. Only the display name can be changed here: the email is the login and only the
 * Superusuario changes it (RF_93), and accounts are never deleted, only deactivated (RF_88).
 */
new #[Title('Profile settings')] class extends Component {
    public string $name = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    public function updateProfileInformation(): void
    {
        $validated = $this->validate(['name' => ['required', 'string', 'max:255']]);

        Auth::user()->update($validated);

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('security.profile.subheading')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <flux:input :value="auth()->user()->email" :label="__('Email')" type="email" readonly disabled :description:trailing="__('security.profile.email_readonly')" />

            <flux:field>
                <flux:label>{{ __('security.profile.roles') }}</flux:label>
                <div class="flex flex-wrap gap-1">
                    @foreach (auth()->user()->getRoleNames() as $role)
                        <flux:badge size="sm" color="blue">{{ $role }}</flux:badge>
                    @endforeach
                </div>
            </flux:field>

            <flux:button variant="primary" type="submit" data-test="update-profile-button">
                {{ __('Save') }}
            </flux:button>
        </form>
    </x-pages::settings.layout>
</section>
