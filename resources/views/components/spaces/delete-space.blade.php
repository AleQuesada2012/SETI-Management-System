<?php

use App\Actions\Spaces\DeleteSpace;
use App\Models\Space;
use Flux\Flux;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * RF_34 · "Eliminar" on the space page (pages/spaces/show). Only users who may delete this space see it (★). The button
 * opens a window: if the space still holds assets or active reservations it says why it cannot be deleted; otherwise it
 * asks for confirmation. It keeps the space's id, so a space deleted meanwhile (another tab) is reported, not a crash.
 */
new class extends Component {
    #[Locked]
    public int $spaceId;

    #[Locked]
    public string $spaceLabel;

    /** @var list<string> Why the space cannot be deleted right now; empty when it can. */
    public array $blockers = [];

    public function mount(Space $space): void
    {
        $this->spaceId = $space->id;
        $this->spaceLabel = "{$space->code} · {$space->name}";
    }

    /** The space, or null when it was deleted meanwhile. */
    #[Computed]
    public function space(): ?Space
    {
        return Space::query()->find($this->spaceId);
    }

    public function open(): void
    {
        $space = $this->space;

        if ($space === null) {
            $this->gone();

            return;
        }

        $this->authorize('delete', $space);

        $assets = DeleteSpace::assetsHeld($space);
        $reservations = DeleteSpace::activeReservations($space);

        $this->blockers = array_values(array_filter([
            $assets > 0 ? trans_choice('spaces.delete.has_assets', $assets, ['count' => $assets]) : null,
            $reservations > 0 ? trans_choice('spaces.delete.has_reservations', $reservations, ['count' => $reservations]) : null,
        ]));

        Flux::modal('delete-space')->show();
    }

    public function delete(DeleteSpace $deleteSpace): void
    {
        $space = $this->space;

        if ($space === null) {
            $this->gone();

            return;
        }

        $this->authorize('delete', $space);

        try {
            $deleteSpace->handle(auth()->user(), $space);
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        } catch (ValidationException $exception) {
            $this->blockers = array_slice($exception->errors()['space'] ?? [], 1);

            return;
        }

        Flux::toast(variant: 'success', text: __('spaces.delete.done', ['space' => $this->spaceLabel]));

        $this->redirectRoute('spaces.index', navigate: true);
    }

    private function gone(): void
    {
        Flux::toast(variant: 'danger', text: __('spaces.delete.not_found', ['space' => $this->spaceLabel]));

        $this->redirectRoute('spaces.index', navigate: true);
    }
}; ?>

<div>
    @if ($this->space !== null && auth()->user()->can('delete', $this->space))
        <flux:button variant="danger" icon="trash" size="sm" wire:click="open" data-test="delete-space-button">{{ __('spaces.delete.button') }}</flux:button>

        <flux:modal name="delete-space" class="w-full max-w-md">
            <div class="flex flex-col gap-5">
                @if ($blockers !== [])
                    <flux:heading size="lg">{{ __('spaces.delete.blocked_title', ['space' => $spaceLabel]) }}</flux:heading>
                    <flux:callout variant="warning" icon="exclamation-triangle" data-test="delete-space-blocked">
                        <flux:callout.text>{{ __('spaces.delete.refused') }}</flux:callout.text>
                        <ul class="mt-2 list-disc ps-5 text-sm">
                            @foreach ($blockers as $blocker)
                                <li>{{ $blocker }}</li>
                            @endforeach
                        </ul>
                    </flux:callout>
                    <flux:text>{{ __('spaces.delete.how_to_free') }}</flux:text>
                    <div class="flex justify-end">
                        <flux:modal.close>
                            <flux:button class="w-full sm:w-auto">{{ __('spaces.delete.understood') }}</flux:button>
                        </flux:modal.close>
                    </div>
                @else
                    <div>
                        <flux:heading size="lg">{{ __('spaces.delete.title', ['space' => $spaceLabel]) }}</flux:heading>
                        <flux:text class="mt-2">{{ __('spaces.delete.description') }}</flux:text>
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <flux:modal.close>
                            <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('spaces.create.cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" wire:click="delete" class="w-full sm:w-auto" data-test="confirm-delete-space">{{ __('spaces.delete.confirm') }}</flux:button>
                    </div>
                @endif
            </div>
        </flux:modal>
    @endif
</div>
