<?php

namespace App\Filament\Resources\Risks\Pages;

use App\Filament\Resources\Risks\RiskResource;
use App\Models\Tmrisk;
use App\Services\RiskRegisterExportService;
use App\Support\RiskApprovalWorkflow;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class ListRisks extends ListRecords
{
    protected static string $resource = RiskResource::class;

    public bool $printMode = false;

    /**
     * @var array<int|string>
     */
    public array $printSelectedRecordIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => RiskResource::canCreate()),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $query = RiskApprovalWorkflow::applyRiskRegisterScope($query);

        return $query
            ->orderByDesc('c_risk_year')
            ->orderByDesc('i_id_risk');
    }

    public function handlePrintToolbarAction()
    {
        if (! $this->printMode) {
            $this->printMode = true;

            $this->printSelectedRecordIds = [];

            if (method_exists($this, 'deselectAllTableRecords')) {
                $this->deselectAllTableRecords();
            }

            return null;
        }

        $idsRaw = $this->printSelectedRecordIds ?? [];
        $ids = array_values(array_unique(array_map('intval', is_array($idsRaw) ? $idsRaw : [])));
        $ids = array_values(array_filter($ids, fn ($v) => $v > 0));

        if (count($ids) === 0) {
            Notification::make()
                ->title('Gagal print')
                ->body('Belum ada data yang dipilih.')
                ->danger()
                ->send();

            return null;
        }

        try {
            $keyName = (new Tmrisk())->getKeyName();

            /** @var Collection<int, Tmrisk> $risks */
            $risks = Tmrisk::query()
                ->with('taxonomy')
                ->whereIn($keyName, $ids)
                ->get();

            if ($risks->isEmpty()) {
                Notification::make()
                    ->title('Gagal print')
                    ->body('Data tidak ditemukan (hasil query kosong).')
                    ->danger()
                    ->send();

                return null;
            }

            $response = app(RiskRegisterExportService::class)->download($risks);

            $this->printMode = false;
            $this->printSelectedRecordIds = [];

            if (method_exists($this, 'deselectAllTableRecords')) {
                $this->deselectAllTableRecords();
            }

            return $response;
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Gagal print')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }
}
