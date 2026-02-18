<?php

namespace App\Observers;

use App\Models\Tmrisk;
use App\Services\RiskNumberGeneratorService;
use Illuminate\Support\Facades\Log;
use Throwable;

class TmriskObserver
{
    public function updated(Tmrisk $risk): void
    {
        if (! $risk->wasChanged('c_risk_status')) {
            return;
        }

        $newStatus = (int) ($risk->c_risk_status ?? 0);

        if ($newStatus !== 4) {
            return;
        }

        $cur = trim((string) ($risk->i_risk ?? ''));
        if ($cur !== '' && strtolower($cur) !== 'null') {
            return;
        }

        try {
            $code = app(RiskNumberGeneratorService::class)->generateFor($risk);

            Log::info('Risk number generated', [
                'risk_id' => (int) $risk->getKey(),
                'prefix'  => strtoupper(trim((string) ($risk->c_org_owner ?? ''))) . (string) ($risk->c_risk_year ?? ''),
                'new_code'=> $code,
            ]);

            $risk->updateQuietly([
                'i_risk' => $code,
            ]);
        } catch (Throwable $e) {
            Log::error('Risk number generation failed', [
                'risk_id'     => (int) $risk->getKey(),
                'status'      => $newStatus,
                'c_org_owner' => (string) ($risk->c_org_owner ?? ''),
                'c_risk_year' => (string) ($risk->c_risk_year ?? ''),
                'i_risk'      => (string) ($risk->i_risk ?? ''),
                'error'       => $e->getMessage(),
            ]);

            report($e);
        }
    }
}
