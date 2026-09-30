<?php

use App\Actions\Spaces\ChangeSpaceStatus;
use App\Models\Space;
use App\Models\SpaceStatus;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * RF_44 (and its duplicate RF_58) · "Cambiar estado" on the space page (pages/spaces/show). Only users who may update
 * this space see it (★). Picking a status that no longer allows reservations while confirmed reservations are still
 * ahead opens a warning first; the reservations stay confirmed until someone cancels them.
 */
new class extends Component {
    #[Locked]
    public Space $space;

    /** The status waiting for the user to confirm the warning. */
    public ?int $pendingStatusId = null;

    public int $affectedCount = 0;

    /** @return Collection<int, SpaceStatus> */
    #[Computed]
    public function statuses(): Collection
    {
        return SpaceStatus::query()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function pendingStatus(): ?SpaceStatus
    {
        return $this->pendingStatusId === null ? null : SpaceStatus::query()->find($this->pendingStatusId);
    }

    public function choose(int $statusId): void
    {
        $this->authorize('update', $this->space);

        $status = SpaceStatus::query()->findOrFail($statusId);

        if ($status->id === $this->space->space_status_id) {
            return;
        }

        $this->affectedCount = ChangeSpaceStatus::affectedReservations($this->space, $status);

        if ($this->affectedCount > 0) {
            $this->pendingStatusId = $status->id;
            Flux::modal('confirm-space-status')->show();

            return;
        }

        $this->apply($status, confirmed: false);
    }

    public function confirm(): void
    {
        $this->authorize('update', $this->space);

        $this->apply(SpaceStatus::query()->findOrFail($this->pendingStatusId), confirmed: true);
    }

    private function apply(SpaceStatus $status, bool $confirmed): void
    {
        $space = app(ChangeSpaceStatus::class)->handle(auth()->user(), $this->space, $status, $confirmed);

        Flux::toast(variant: 'success', text: __('spaces.status.done', ['space' => $space->name, 'status' => $status->name]));

        $this->redirectRoute('spaces.show', $space, navigate: true);
    }
}; ?>

<div>
    @can('update', $space)
        <flux:dropdown position="bottom" align="end">
            <flux:button icon="arrow-path" icon:trailing="chevron-down" size="sm" data-test="change-status-button">{{ __('spaces.status.button') }}</flux:button>

            <flux:menu>
                @foreach ($this->statuses as $status)
                    <flux:menu.item wire:click="choose({{ $status->id }})" :disabled="$status->id === $space->space_status_id">
                        <flux:badge size="sm" :color="$status->color_group->badgeColor()">{{ $status->name }}</flux:badge>
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>

        <flux:modal name="confirm-space-status" class="w-full max-w-md">
            <div class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('spaces.status.confirm_title', ['status' => $this->pendingStatus?->name]) }}</flux:heading>

                <flux:callout variant="warning" icon="exclamation-triangle" data-test="status-warning">
                    <flux:callout.text>{{ trans_choice('spaces.status.affected', $affectedCount, ['count' => $affectedCount]) }}</flux:callout.text>
                </flux:callout>

                <flux:error name="status" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('spaces.create.cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="confirm" variant="primary" class="w-full sm:w-auto" data-test="confirm-status">{{ __('spaces.status.confirm') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</div>
