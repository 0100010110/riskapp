<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="w-full lg:flex-1">
                {{ $this->form }}
            </div>

            <div class="flex gap-2">
                <x-filament::button
                    type="button"
                    wire:click="apply"
                    icon="heroicon-o-check"
                >
                    Apply
                </x-filament::button>

                <x-filament::button
                    type="button"
                    wire:click="resetSimulation"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                >
                    Reset
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
