<?php

namespace App\Filament\Resources\Taxonomies\Pages;

use App\Filament\Resources\Taxonomies\TaxonomyResource;
use App\Models\Tmtaxonomyscale;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;

class EditTaxonomy extends EditRecord
{
    protected static string $resource = TaxonomyResource::class;

    /** @var array<int> */
    protected array $selectedScaleIds = [];

    protected bool $isLevel5 = false;

    private function normalizeScaleIds(mixed $raw): array
    {
        $ids = is_array($raw) ? $raw : ($raw === null ? [] : [$raw]);

        return collect($ids)
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $level = (int) ($data['c_taxonomy_level'] ?? 0);

        $data['i_id_scale'] = [];

        if ($level === 5) {
            $taxonomyId = (int) ($this->record?->getKey() ?? 0);

            if ($taxonomyId > 0) {
                $data['i_id_scale'] = Tmtaxonomyscale::query()
                    ->where('i_id_taxonomy', $taxonomyId)
                    ->orderBy('i_id_taxonomyscale')
                    ->pluck('i_id_scale')
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn ($v) => $v > 0)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $state = method_exists($this->form, 'getRawState')
            ? $this->form->getRawState()
            : $this->form->getState();

        $level = (int) ($state['c_taxonomy_level'] ?? 0);
        $this->isLevel5 = ($level === 5);

        $this->selectedScaleIds = $this->normalizeScaleIds($state['i_id_scale'] ?? []);

        if ($this->isLevel5 && count($this->selectedScaleIds) < 1) {
            Notification::make()
                ->danger()
                ->title('Skala wajib dipilih')
                ->body('Untuk level 5, pilih minimal 1 Skala sebelum menyimpan.')
                ->send();

            throw new Halt();
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['i_id_scale']);

        return $data;
    }

    protected function afterSave(): void
    {
        $taxonomyId = (int) ($this->record?->getKey() ?? 0);
        if ($taxonomyId <= 0) {
            return;
        }

        $userId = (int) (auth()->id() ?? 0);
        $now = now();

        DB::transaction(function () use ($taxonomyId, $userId, $now) {
            Tmtaxonomyscale::query()
                ->where('i_id_taxonomy', $taxonomyId)
                ->delete();

            if (! $this->isLevel5) {
                return;
            }

            if (empty($this->selectedScaleIds)) {
                return;
            }

            $rows = [];
            foreach ($this->selectedScaleIds as $scaleId) {
                $rows[] = [
                    'i_id_taxonomy' => $taxonomyId,
                    'i_id_scale'    => (int) $scaleId,
                    'i_entry'       => $userId ?: null,
                    'd_entry'       => $now,
                    'i_update'      => $userId ?: null,
                    'd_update'      => $now,
                ];
            }

            DB::table('tmtaxonomyscale')->insert($rows);
        });
    }
}
