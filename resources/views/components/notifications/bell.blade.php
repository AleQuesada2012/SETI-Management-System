<?php

use App\Models\User;
use App\Notifications\InboxEntry;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The bell in the top bar: the signed-in user's inbox (RF_47, RF_48; ADR-0006, Agent-DB.md §10). Reverb pushes each
 * new notification on the private channel App.Models.User.{id} and the bell re-reads the database, which stays the
 * source of truth. Every action goes through the user's own notifications() relation, so nobody touches another
 * user's inbox.
 */
new class extends Component {
    /** How many of the latest notifications the bell lists. */
    private const LATEST = 10;

    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        return [
            'echo-private:App.Models.User.'.Auth::id().',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => '$refresh',
        ];
    }

    #[Computed]
    public function unreadCount(): int
    {
        return $this->user()->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, InboxEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        return $this->user()->notifications()
            ->latest()
            ->limit(self::LATEST)
            ->get()
            ->map(fn (DatabaseNotification $notification): InboxEntry => InboxEntry::from($notification))
            ->toBase();
    }

    /** Marks the notification as read and opens what it is about. */
    public function open(string $id): void
    {
        $notification = $this->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = InboxEntry::from($notification)->url;

        if ($url !== null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}; ?>

<div>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" class="relative" :aria-label="__('notifications.open')" data-test="notification-bell">
            <flux:icon.bell variant="outline" class="size-5" />

            @if ($this->unreadCount > 0)
                <span class="absolute -end-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand px-1 text-xs font-medium text-brand-foreground" data-test="notification-count">
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80 max-w-[calc(100vw-2rem)]">
            <div class="flex items-center justify-between gap-2 px-2 py-1.5">
                <div>
                    <flux:heading>{{ __('notifications.heading') }}</flux:heading>
                    @if ($this->unreadCount > 0)
                        <flux:text size="sm">{{ trans_choice('notifications.unread', $this->unreadCount, ['count' => $this->unreadCount]) }}</flux:text>
                    @endif
                </div>

                @if ($this->unreadCount > 0)
                    <flux:button size="xs" variant="ghost" wire:click="markAllAsRead">{{ __('notifications.mark_all_read') }}</flux:button>
                @endif
            </div>

            <flux:menu.separator />

            @forelse ($this->entries as $entry)
                <flux:menu.item wire:key="notification-{{ $entry->id }}" wire:click="open('{{ $entry->id }}')" class="h-auto! items-start! whitespace-normal! py-2!">
                    <span class="mt-1.5 me-2 size-2 shrink-0 rounded-full {{ $entry->isUnread ? 'bg-brand' : '' }}"></span>
                    <span class="grid gap-0.5 text-start">
                        <span class="font-medium text-ink">{{ $entry->title }}</span>
                        <span class="text-xs text-ink-muted">{{ $entry->body }}</span>
                        <span class="text-xs text-ink-muted">{{ $entry->receivedAt->setTimezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                    </span>
                </flux:menu.item>
            @empty
                <flux:text class="px-2 py-3">{{ __('notifications.empty') }}</flux:text>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
