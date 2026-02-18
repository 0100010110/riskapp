<?php

namespace App\Filament\Resources\RiskApprovals\Pages;

use App\Filament\Resources\RiskApprovals\RiskApprovalResource;
use App\Models\Tmrisk;
use App\Models\Tmriskapprove;
use App\Models\Tmriskinherent;
use App\Models\Tmriskmitigation;
use App\Models\Tmriskrealization;
use App\Support\RiskApprovalWorkflow;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateRiskApproval extends CreateRecord
{
    protected static string $resource = RiskApprovalResource::class;

    private const SESSION_LOCK_RISK_KEY     = 'risk_approval.locked_risk_id';
    private const SESSION_LOCK_DECISION_KEY = 'risk_approval.locked_decision';

    private ?int $resolvedRiskIdCache = null;
    private ?string $resolvedDecisionCache = null;

    private function resolveRiskId(): int
    {
        if ($this->resolvedRiskIdCache !== null) {
            return $this->resolvedRiskIdCache;
        }

        $riskId = 0;

        // 1) GET query param
        try {
            $riskId = (int) request()->query('risk', 0);
        } catch (\Throwable) {
            $riskId = 0;
        }

        try {
            if (request()->isMethod('GET') && $riskId <= 0) {
                session()->forget(self::SESSION_LOCK_RISK_KEY);
            }
        } catch (\Throwable) {
        }

        if ($riskId > 0) {
            try {
                session()->put(self::SESSION_LOCK_RISK_KEY, $riskId);
            } catch (\Throwable) {
            }

            return $this->resolvedRiskIdCache = $riskId;
        }

        // 2) session lock 
        try {
            $riskId = (int) session()->get(self::SESSION_LOCK_RISK_KEY, 0);
        } catch (\Throwable) {
            $riskId = 0;
        }

        if ($riskId > 0) {
            return $this->resolvedRiskIdCache = $riskId;
        }

        // 3) fallback form state
        try {
            $state = $this->form?->getState() ?? [];
            $riskId = (int) ($state['i_id_risk'] ?? 0);
        } catch (\Throwable) {
            $riskId = 0;
        }

        return $this->resolvedRiskIdCache = $riskId;
    }

    private function resolveDecision(): string
    {
        if ($this->resolvedDecisionCache !== null) {
            return $this->resolvedDecisionCache;
        }

        $decision = '';

        // 1) GET query param
        try {
            $decision = strtolower(trim((string) request()->query('decision', '')));
        } catch (\Throwable) {
            $decision = '';
        }

        try {
            if (request()->isMethod('GET') && $decision === '') {
                session()->forget(self::SESSION_LOCK_DECISION_KEY);
            }
        } catch (\Throwable) {
        }

        if ($decision !== '') {
            if (! in_array($decision, ['approve', 'reject', 'delete'], true)) {
                $decision = 'approve';
            }

            try {
                session()->put(self::SESSION_LOCK_DECISION_KEY, $decision);
            } catch (\Throwable) {
            }

            return $this->resolvedDecisionCache = $decision;
        }

        // 2) session lock
        try {
            $decision = strtolower(trim((string) session()->get(self::SESSION_LOCK_DECISION_KEY, 'approve')));
        } catch (\Throwable) {
            $decision = 'approve';
        }

        if (! in_array($decision, ['approve', 'reject', 'delete'], true)) {
            $decision = 'approve';
        }

        return $this->resolvedDecisionCache = $decision;
    }

    private function resolveApproverEmpId(array $data): int
    {
        $raw = trim((string) ($data['i_emp'] ?? ''));
        if ($raw !== '') {
            $n = (int) preg_replace('/\D+/', '', $raw);
            if ($n > 0) return $n;
        }

        $nik = RiskApprovalWorkflow::currentUserNik();
        $n = (int) preg_replace('/\D+/', '', (string) $nik);
        if ($n > 0) return $n;

        $user = Filament::auth()->user() ?? auth()->user();
        $uid = (int) ($user?->getAuthIdentifier() ?? 0);
        return $uid > 0 ? $uid : 0;
    }

    private function resolveApproverName(array $data): string
    {
        $raw = trim((string) ($data['n_emp'] ?? ''));
        return $raw !== '' ? $raw : RiskApprovalWorkflow::currentUserName();
    }

    private function riskReadyForOfficerStage2(Tmrisk $risk): bool
    {
        if (
            $risk->v_threshold_safe === null ||
            $risk->v_threshold_caution === null ||
            $risk->v_threshold_danger === null
        ) {
            return false;
        }

        $ri = Tmriskinherent::query()
            ->select(['i_id_riskinherent', 'i_id_risk'])
            ->where('i_id_risk', (int) $risk->getKey())
            ->first();

        if (! $ri) return false;

        return Tmriskmitigation::query()
            ->where('i_id_riskinherent', (int) $ri->i_id_riskinherent)
            ->exists();
    }

    private function riskReadyForOfficerStage3(Tmrisk $risk): bool
    {
        $ri = Tmriskinherent::query()
            ->select(['i_id_riskinherent', 'i_id_risk'])
            ->where('i_id_risk', (int) $risk->getKey())
            ->first();

        if (! $ri) return false;

        return Tmriskrealization::query()
            ->where('i_id_riskinherent', (int) $ri->i_id_riskinherent)
            ->exists();
    }

    protected function getFormActions(): array
    {
        $decision = $this->resolveDecision();
        $riskId = $this->resolveRiskId();

        $risk = $riskId > 0
            ? Tmrisk::query()->select(['i_id_risk', 'c_risk_status'])->find($riskId)
            : null;

        $status = (int) ($risk?->c_risk_status ?? -1);

        $label = match ($decision) {
            'reject' => 'Reject',
            'delete' => 'Request Delete',
            default  => ($status === 5 ? 'Approve Delete' : 'Approve'),
        };

        $color = match ($decision) {
            'reject' => 'danger',
            'delete' => 'danger',
            default  => 'success',
        };

        $icon = match ($decision) {
            'reject' => 'heroicon-o-x-circle',
            'delete' => 'heroicon-o-trash',
            default  => ($status === 5 ? 'heroicon-o-trash' : 'heroicon-o-check-circle'),
        };

        return [
            $this->getCreateFormAction()
                ->label($label)
                ->color($color)
                ->icon($icon),

            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        try {
            session()->forget(self::SESSION_LOCK_RISK_KEY);
            session()->forget(self::SESSION_LOCK_DECISION_KEY);
        } catch (\Throwable) {
        }

        return RiskApprovalResource::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $riskId = $this->resolveRiskId();
        if ($riskId > 0) {
            $data['i_id_risk'] = $riskId;
        }

        $data['i_id_role'] = (int) (RiskApprovalWorkflow::currentApproverRoleId() ?? ($data['i_id_role'] ?? 0));

        $data['i_emp'] = (string) $this->resolveApproverEmpId($data);
        $data['n_emp'] = $this->resolveApproverName($data);

        $user = Filament::auth()->user() ?? auth()->user();
        $data['i_entry'] = (int) ($user?->getAuthIdentifier() ?? 0);
        $data['d_entry'] = now();

        return $data;
    }

    protected function beforeCreate(): void
    {
        $decision = $this->resolveDecision();
        $riskId = $this->resolveRiskId();

        if ($riskId <= 0) {
            Notification::make()
                ->danger()
                ->title('Risk tidak valid')
                ->body('Parameter risk tidak ditemukan.')
                ->send();
            $this->halt();
        }

        $risk = Tmrisk::query()->find($riskId);
        if (! $risk) {
            Notification::make()
                ->danger()
                ->title('Risk tidak ditemukan')
                ->send();
            $this->halt();
        }

        $scopeCheck = Tmrisk::query()->whereKey($risk->getKey());
        RiskApprovalWorkflow::applyApprovalListScope($scopeCheck);

        if (! $scopeCheck->exists()) {
            Notification::make()
                ->danger()
                ->title('Tidak punya akses')
                ->body('Anda tidak memiliki akses untuk approve/reject risk ini.')
                ->send();
            $this->halt();
        }

        $status = (int) $risk->c_risk_status;
        $ctx = RiskApprovalWorkflow::context();
        $roleType = (string) ($ctx['role_type'] ?? '');

        if ($decision === 'delete') {
            if (! RiskApprovalWorkflow::canRequestDeleteForCurrentUser($status)) {
                Notification::make()
                    ->danger()
                    ->title('Tidak bisa request delete')
                    ->body('Hanya Admin GRC bisa request delete saat status = 2.')
                    ->send();
                $this->halt();
            }
            return;
        }

        if ($decision === 'approve') {
            if (! RiskApprovalWorkflow::canApproveStatusForCurrentUser($status)) {
                Notification::make()
                    ->danger()
                    ->title('Tidak bisa approve')
                    ->body('Status risk saat ini tidak sesuai dengan workflow / role Anda.')
                    ->send();
                $this->halt();
            }

            if ($roleType === RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER) {
                if ($status === 4 && ! $this->riskReadyForOfficerStage2($risk)) {
                    Notification::make()
                        ->danger()
                        ->title('Tidak bisa approve ke Tahap 2')
                        ->body('Pastikan Threshold terisi, Risk Profile (Risk Inherent) sudah dibuat, dan Risk Mitigation sudah diinput.')
                        ->send();
                    $this->halt();
                }

                if ($status === 9 && ! $this->riskReadyForOfficerStage3($risk)) {
                    Notification::make()
                        ->danger()
                        ->title('Tidak bisa approve ke Tahap 3')
                        ->body('Pastikan Risk Realization sudah diinput terlebih dahulu.')
                        ->send();
                    $this->halt();
                }
            }

            return;
        }

        if ($decision === 'reject') {
            if (! RiskApprovalWorkflow::canRejectStatusForCurrentUser($status)) {
                Notification::make()
                    ->danger()
                    ->title('Tidak bisa reject')
                    ->body('Status risk saat ini tidak bisa direject oleh role Anda.')
                    ->send();
                $this->halt();
            }
            return;
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $decision = $this->resolveDecision();

        $user = Filament::auth()->user() ?? auth()->user();
        $userId = (int) ($user?->getAuthIdentifier() ?? 0);

        $riskId = (int) ($data['i_id_risk'] ?? 0);

        $empId = $this->resolveApproverEmpId($data);
        $empName = $this->resolveApproverName($data);

        $roleId = (int) ($data['i_id_role'] ?? (RiskApprovalWorkflow::currentApproverRoleId() ?? 0));

        if ($riskId <= 0 || $empId <= 0) {
            throw new \RuntimeException('Data approval tidak valid (risk/employee kosong).');
        }

        $ctx = RiskApprovalWorkflow::context();
        $roleType = (string) ($ctx['role_type'] ?? '');

        /** @var Tmriskapprove $approval */
        $approval = DB::transaction(function () use (
            $riskId, $empId, $empName, $roleId, $userId, $decision, $roleType
        ) {
            /** @var Tmrisk $risk */
            $risk = Tmrisk::query()
                ->where('i_id_risk', $riskId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = (int) $risk->c_risk_status;

            if ($decision === 'delete') {
                if (! RiskApprovalWorkflow::canRequestDeleteForCurrentUser($currentStatus)) {
                    throw new \RuntimeException('Tidak bisa request delete pada status ini.');
                }
            } elseif ($decision === 'approve') {
                if (! RiskApprovalWorkflow::canApproveStatusForCurrentUser($currentStatus)) {
                    throw new \RuntimeException('Tidak bisa approve: status risk tidak sesuai workflow/role Anda.');
                }

                if ($roleType === RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER) {
                    if ($currentStatus === 4 && ! $this->riskReadyForOfficerStage2($risk)) {
                        throw new \RuntimeException('Tidak bisa approve ke Tahap 2: prasyarat belum lengkap (Threshold/Profile/Mitigation).');
                    }
                    if ($currentStatus === 9 && ! $this->riskReadyForOfficerStage3($risk)) {
                        throw new \RuntimeException('Tidak bisa approve ke Tahap 3: Risk Realization belum ada.');
                    }
                }
            } elseif ($decision === 'reject') {
                if (! RiskApprovalWorkflow::canRejectStatusForCurrentUser($currentStatus)) {
                    throw new \RuntimeException('Tidak bisa reject: status risk tidak sesuai workflow/role Anda.');
                }
            }

            $approval = Tmriskapprove::query()->updateOrCreate(
                [
                    'i_id_risk' => $riskId,
                    'i_emp'     => $empId,
                ],
                [
                    'i_id_role' => $roleId,
                    'n_emp'     => $empName,
                    'i_entry'   => $userId,
                    'd_entry'   => now(),
                ]
            );

            if ($decision === 'delete') {
                $risk->c_risk_status = 5;
                $risk->save();
                return $approval;
            }

            if ($decision === 'reject') {
                $next = RiskApprovalWorkflow::nextStatusOnRejectForCurrentUser($currentStatus);
                if ($next === null) {
                    throw new \RuntimeException('Reject tidak valid untuk status ini.');
                }

                $risk->c_risk_status = $next;
                $risk->save();
                return $approval;
            }

            if ($currentStatus === 5) {
                if (! RiskApprovalWorkflow::canApproveDeleteRequestForCurrentUser($currentStatus)) {
                    throw new \RuntimeException('Tidak bisa approve delete request.');
                }

                try {
                    $risk->delete();
                } catch (Throwable $e) {
                    throw new \RuntimeException('Gagal menghapus risk (masih direferensikan FK/relasi lain).');
                }

                return $approval;
            }

            $next = RiskApprovalWorkflow::nextStatusOnApproveForCurrentUser($currentStatus);
            if ($next === null) {
                throw new \RuntimeException('Approve tidak valid untuk status ini.');
            }

            $risk->c_risk_status = $next;
            $risk->save();

            return $approval;
        });

        return $approval;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->success()
            ->title('Berhasil')
            ->body('Approval berhasil diproses.')
            ->send();
    }
}
