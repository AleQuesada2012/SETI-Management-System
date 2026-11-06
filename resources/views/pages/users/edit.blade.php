<?php

use App\Actions\Users\UpdateUser;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Editar usuario (RF_93): the Superusuario changes a user's name and email through App\Actions\Users\UpdateUser.
 * Owner: users (@Rubiocai). The route checks `update` (users.update). Roles and account status are changed on the
 * user page (RF_85–RF_88); passwords only by their owner.
 */
new #[Title('users.edit.title')] class extends Component
{
    public User $user;

    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        $this->name = $this->user->name;
        $this->email = $this->user->email;
    }

    public function save(UpdateUser $updateUser): void
    {
        $this->authorize('update', $this->user);

        $emailChanged = mb_strtolower(trim($this->email)) !== $this->user->email;
        $invitationPending = $this->user->password === null;

        $updateUser->handle(auth()->user(), $this->user, ['name' => $this->name, 'email' => $this->email]);

        $this->user->refresh();
        $this->name = $this->user->name;
        $this->email = $this->user->email;

        Flux::toast(variant: 'success', text: match (true) {
            ! $emailChanged => __('users.edit.saved', ['user' => $this->user->name]),
            $invitationPending => __('users.edit.saved_invitation', ['email' => $this->user->email]),
            default => __('users.edit.saved_verification', ['email' => $this->user->email]),
        });
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading size="lg" level="2">{{ __('users.edit.heading', ['user' => $user->name]) }}</flux:heading>
        <flux:button icon="arrow-left" :href="route('users.show', $user)" wire:navigate>{{ __('users.edit.back') }}</flux:button>
    </div>

    <flux:card class="max-w-xl">
        <form wire:submit="save" class="flex flex-col gap-5">
            <flux:error name="form" />

            <flux:input wire:model="name" :label="__('users.fields.name')" maxlength="150" required autocomplete="off" />

            <flux:input
                wire:model="email"
                type="email"
                :label="__('users.fields.email')"
                :description:trailing="$user->password === null ? __('users.edit.email_help_invited') : __('users.edit.email_help')"
                required
                autocomplete="off"
            />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('users.show', $user)" wire:navigate>{{ __('users.edit.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('users.edit.submit') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
