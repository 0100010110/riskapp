<?php

namespace App\Support;

use App\Models\Tmrisk;
use App\Models\Tmriskapprove;
use App\Services\EmployeeCacheService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class RiskStatusTracker
{
    protected const MAIN_FLOW = [0, 1, 2, 3, 4, 6, 7, 8, 9, 10, 11, 12, 13];
    protected const DELETE_FLOW = [0, 1, 2, 3, 4, 5];
    protected const LED_FLOW = [14, 15, 16, 17];

    public static function for(Tmrisk $risk): array
    {
        $risk->loadMissing(['approvals.role', 'latestApproval']);

        $codes = self::visibleCodesFor($risk);

        $approvals = $risk->approvals
            ->sortBy(fn (Tmriskapprove $row) => self::carbon($row->d_entry)?->timestamp ?? 0)
            ->values();

        $activities = Activity::query()
            ->with('causer')
            ->where('subject_type', Tmrisk::class)
            ->where('subject_id', $risk->getKey())
            ->orderBy('created_at')
            ->get();

        $statusEntries = self::mapStatusEntries($risk, $codes, $approvals, $activities);

        $currentStatus = (int) ($risk->c_risk_status ?? 0);
        $currentIndex = array_search($currentStatus, $codes, true);

        if ($currentIndex === false) {
            $currentIndex = 0;
        }

        $items = [];

        foreach ($codes as $index => $code) {
            $entry = $statusEntries[$code] ?? null;

            $isCurrent = $index === $currentIndex;
            $isPassed = $index < $currentIndex;
            $isFuture = $index > $currentIndex;

            $at = $isFuture ? null : self::carbon($entry['at_raw'] ?? null);
            $actor = $isFuture ? '' : trim((string) ($entry['actor'] ?? ''));
            $role = $isFuture ? '' : trim((string) ($entry['role'] ?? ''));

            $items[] = [
                'code' => $code,
                'phase' => self::phaseFor($code),
                'label' => self::plainStatusLabel($code),
                'at_raw' => $at?->toIso8601String(),
                'at_label' => $at?->format('d M Y H:i'),
                'actor' => $actor,
                'role' => $role,
                'current' => $isCurrent,
                'passed' => $isPassed,
                'future' => $isFuture,
                'clickable' => ! $isFuture,
                'hint' => $isFuture
                    ? 'Menunggu tahap sebelumnya selesai.'
                    : (self::statusHints()[$code] ?? null),
                'history' => [],
            ];
        }

        foreach ($items as $index => &$item) {
            if (! $item['clickable']) {
                $item['history'] = [];
                continue;
            }

            $start = $item['code'] === 0
                ? self::carbon($risk->d_entry)
                : self::carbon($item['at_raw']);

            $end = null;

            for ($next = $index + 1; $next < count($items); $next++) {
                if (! empty($items[$next]['at_raw'])) {
                    $end = self::carbon($items[$next]['at_raw']);
                    break;
                }
            }

            $item['history'] = self::activityHistoryForWindow($activities, $start, $end);
        }
        unset($item);

        return [
            'record_no' => trim((string) ($risk->i_risk ?? '')) ?: 'Draft',
            'current_status_code' => $currentStatus,
            'current_phase' => self::phaseFor($currentStatus),
            'current_status_label' => self::plainStatusLabel($currentStatus),
            'current_message' => self::statusHints()[$currentStatus] ?? 'Ikuti workflow approval berikutnya.',
            'items' => $items,
            'current_index' => $currentIndex,
        ];
    }

    protected static function visibleCodesFor(Tmrisk $risk): array
    {
        $status = (int) ($risk->c_risk_status ?? 0);

        if ($status === 5) {
            return self::DELETE_FLOW;
        }

        if ($status >= 14) {
            return array_merge(self::MAIN_FLOW, self::LED_FLOW);
        }

        return self::MAIN_FLOW;
    }

    protected static function phaseFor(int $code): string
    {
        return match (true) {
            $code === 0 => 'Draft',
            $code >= 1 && $code <= 4 => '1',
            $code === 5 => 'Hapus',
            $code >= 6 && $code <= 9 => '2',
            $code >= 10 && $code <= 13 => '3',
            $code >= 14 && $code <= 17 => 'LED',
            default => '-',
        };
    }

    protected static function plainStatusLabel(int $code): string
    {
        return trim((string) (Tmrisk::statusOptions()[$code] ?? (string) $code));
    }

    /**
     * @param array<int, int> $codes
     * @return array<int, array<string, string|null>>
     */
    protected static function mapStatusEntries(
        Tmrisk $risk,
        array $codes,
        Collection $approvals,
        Collection $activities
    ): array {
        $mapped = [
            0 => [
                'at_raw' => self::carbon($risk->d_entry)?->toIso8601String(),
                'actor' => self::employeeNameByIdOrNik((int) ($risk->i_entry ?? 0)),
                'role' => 'Creator',
            ],
        ];

        foreach ($activities as $activity) {
            if (! $activity instanceof Activity) {
                continue;
            }

            $newStatus = data_get($activity, 'properties.attributes.c_risk_status');

            if ($newStatus === null || $newStatus === '') {
                continue;
            }

            $code = (int) $newStatus;

            if (! in_array($code, $codes, true) || $code === 0) {
                continue;
            }

            $actor = trim((string) (
                $activity->causer?->name
                ?? $activity->causer?->nik
                ?? ''
            ));

            if ($actor === '') {
                $actor = self::employeeNameByIdOrNik((int) ($activity->causer_id ?? 0));
            }

            $mapped[$code] = [
                'at_raw' => self::carbon($activity->created_at)?->toIso8601String(),
                'actor' => $actor,
                'role' => self::roleLabelForStatus($code),
            ];
        }

        foreach ($approvals as $approval) {
            if (! $approval instanceof Tmriskapprove) {
                continue;
            }

            $at = self::carbon($approval->d_entry);
            if (! $at) {
                continue;
            }

            $actor = trim((string) ($approval->n_emp ?? ''));
            if ($actor === '') {
                $actor = self::employeeNameByIdOrNik((int) ($approval->i_entry ?? 0));
            }

            $roleName = strtolower(trim((string) (
                $approval->role?->n_role
                ?? $approval->role?->v_role
                ?? $approval->role?->name
                ?? ''
            )));

            $candidateCodes = self::candidateCodesFromRole($roleName);

            foreach ($candidateCodes as $candidateCode) {
                if (! in_array($candidateCode, $codes, true)) {
                    continue;
                }

                if (! isset($mapped[$candidateCode])) {
                    $mapped[$candidateCode] = [
                        'at_raw' => $at->toIso8601String(),
                        'actor' => $actor,
                        'role' => self::roleLabelForStatus($candidateCode),
                    ];
                    break;
                }
            }
        }

        $currentStatus = (int) ($risk->c_risk_status ?? 0);

        if ($currentStatus !== 0 && ! isset($mapped[$currentStatus])) {
            $mapped[$currentStatus] = [
                'at_raw' => self::carbon($risk->d_update)?->toIso8601String()
                    ?? self::carbon($risk->updated_at)?->toIso8601String()
                    ?? self::carbon($risk->latestApproval?->d_entry)?->toIso8601String(),
                'actor' => self::employeeNameByIdOrNik((int) ($risk->i_update ?? 0)),
                'role' => self::roleLabelForStatus($currentStatus),
            ];
        }

        return $mapped;
    }

    /**
     * @return array<int>
     */
    protected static function candidateCodesFromRole(string $roleName): array
    {
        if ($roleName === '') {
            return [];
        }

        if (str_contains($roleName, 'approval') && str_contains($roleName, 'grc')) {
            return [4, 9, 13];
        }

        if (str_contains($roleName, 'admin') && str_contains($roleName, 'grc')) {
            return [3, 8, 12];
        }

        if (str_contains($roleName, 'kadiv') || str_contains($roleName, 'kepala div')) {
            return [2, 7, 11, 15];
        }

        if (str_contains($roleName, 'officer') && str_contains($roleName, 'led')) {
            return [14];
        }

        if (str_contains($roleName, 'officer')) {
            return [1, 6, 10];
        }

        if (str_contains($roleName, 'approval') && str_contains($roleName, 'led')) {
            return [17];
        }

        if (str_contains($roleName, 'admin') && str_contains($roleName, 'led')) {
            return [16];
        }

        return [];
    }

    protected static function roleLabelForStatus(int $code): string
    {
        return match ($code) {
            1, 6, 10 => 'Risk Officer',
            2, 7, 11 => 'Kadiv',
            3, 8, 12 => 'Admin GRC',
            4, 9, 13 => 'Approval GRC',
            5 => 'Delete Request',
            14 => 'Officer LED',
            15 => 'Kadiv LED',
            16 => 'Admin LED',
            17 => 'Approval LED',
            default => '',
        };
    }

    protected static function statusHints(): array
    {
        return [
            0 => 'Lengkapi identitas risiko, deskripsi risiko, nilai dampak, dan primary risk sebelum diajukan.',
            1 => 'Menunggu review Kadiv tahap 1 untuk RSA + Primary Risk.',
            2 => 'Menunggu pengajuan admin tahap 1 (RSA).',
            3 => 'Menunggu approval tahap 1 (RSA).',
            4 => 'Lengkapi kolom KRI, Threshold, Periode Paparan, Efektivitas Kontrol, dan Eksisting Kontrol.',
            5 => 'Risk Register sedang diajukan untuk dihapus.',
            6 => 'Menunggu review Kadiv tahap 2 untuk Risk Register + Profil Risiko.',
            7 => 'Menunggu pengajuan admin tahap 2.',
            8 => 'Menunggu approval tahap 2.',
            9 => 'Lengkapi data Realisasi Risiko bila kejadian sudah terjadi.',
            10 => 'Menunggu review Kadiv tahap 3 untuk Realisasi Risiko.',
            11 => 'Menunggu pengajuan admin tahap 3.',
            12 => 'Menunggu approval tahap 3.',
            13 => 'Tahap Realisasi Risiko selesai. Lanjutkan proses LED bila diperlukan.',
            14 => 'Menunggu review Kadiv LED.',
            15 => 'Menunggu pengajuan admin LED.',
            16 => 'Menunggu approval LED.',
            17 => 'Workflow LED selesai.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function activityHistoryForWindow(Collection $activities, ?Carbon $start, ?Carbon $end): array
    {
        return $activities
            ->filter(function (Activity $activity) use ($start, $end): bool {
                $at = self::carbon($activity->created_at);

                if (! $at) {
                    return false;
                }

                if ($start && $at->lt($start)) {
                    return false;
                }

                if ($end && $at->gte($end)) {
                    return false;
                }

                return true;
            })
            ->map(fn (Activity $activity) => self::formatActivityEntry($activity))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function formatActivityEntry(Activity $activity): ?array
    {
        $description = strtolower(trim((string) ($activity->description ?? '')));

        if ($description === 'created' || $description === 'deleted') {
            return null;
        }

        $changes = self::extractChanges($activity);

        if ($changes === []) {
            return null;
        }

        $actor = trim((string) (
            $activity->causer?->name
            ?? $activity->causer?->nik
            ?? ''
        ));

        if ($actor === '') {
            $actor = self::employeeNameByIdOrNik((int) ($activity->causer_id ?? 0));
        }

        return [
            'kind' => 'activity',
            'title' => 'Perubahan data',
            'actor' => $actor,
            'role' => '',
            'at_label' => self::carbon($activity->created_at)?->format('d M Y H:i'),
            'changes' => $changes,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected static function extractChanges(Activity $activity): array
    {
        $attributes = (array) data_get($activity, 'properties.attributes', []);
        $old = (array) data_get($activity, 'properties.old', []);

        $keys = array_unique(array_merge(array_keys($attributes), array_keys($old)));

        $ignored = [
            'i_id_risk',
            'c_risk_status',
            'updated_at',
            'created_at',
            'i_entry',
            'd_entry',
            'i_update',
            'd_update',
        ];

        $changes = [];

        foreach ($keys as $key) {
            if (in_array($key, $ignored, true)) {
                continue;
            }

            $oldValue = $old[$key] ?? null;
            $newValue = $attributes[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[] = [
                'field' => self::fieldLabels()[$key] ?? $key,
                'old' => self::formatValue($key, $oldValue),
                'new' => self::formatValue($key, $newValue),
            ];
        }

        return $changes;
    }

    /**
     * @return array<string, string>
     */
    protected static function fieldLabels(): array
    {
        return [
            'i_id_taxonomy' => 'Taxonomy',
            'c_risk_year' => 'Tahun Risiko',
            'i_risk' => 'Nomor Risiko',
            'c_org_owner' => 'Owner Organization',
            'c_org_impact' => 'Impacted Organization',
            'e_risk_event' => 'Risk Event',
            'e_risk_cause' => 'Risk Cause',
            'e_risk_impact' => 'Risk Impact (Uraian)',
            'v_risk_impact' => 'Risk Impact Value',
            'c_risk_impactunit' => 'Impact Unit',
            'f_risk_primary' => 'Primary Risk',
            'e_kri' => 'KRI',
            'c_kri_unit' => 'KRI Unit',
            'c_kri_operator' => 'KRI Operator',
            'v_threshold_safe' => 'Threshold Safe',
            'v_threshold_caution' => 'Threshold Caution',
            'v_threshold_danger' => 'Threshold Danger',
            'd_exposure_period' => 'Exposure Period',
            'c_control_effectiveness' => 'Control Effectiveness',
            'e_exist_ctrl' => 'Eksisting Kontrol',
        ];
    }

    protected static function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return '-';
        }

        if ($field === 'f_risk_primary') {
            return (bool) $value ? 'Yes' : 'No';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
        }

        if ($value instanceof Carbon) {
            return $value->format('d M Y H:i');
        }

        $string = trim((string) $value);

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $string)) {
                return Carbon::parse($string)->format('d M Y H:i');
            }
        } catch (\Throwable) {
        }

        return $string;
    }

    protected static function employeeNameByIdOrNik(int $id): string
    {
        static $cache = [];

        if ($id <= 0) {
            return '';
        }

        if (array_key_exists($id, $cache)) {
            return (string) $cache[$id];
        }

        $name = '';

        try {
            $svc = app(EmployeeCacheService::class);

            $row = $svc->findById($id);
            if (is_array($row)) {
                $name = trim((string) ($row['nama'] ?? $row['name'] ?? $row['n_name'] ?? ''));
            }

            if ($name === '') {
                $nik = (string) $id;

                if (method_exists($svc, 'findByNik')) {
                    $row2 = $svc->findByNik($nik);
                    if (is_array($row2)) {
                        $name = trim((string) ($row2['nama'] ?? $row2['name'] ?? $row2['n_name'] ?? ''));
                    }
                }

                if ($name === '') {
                    foreach ($svc->data() as $r) {
                        if (! is_array($r)) {
                            continue;
                        }

                        if ((string) ($r['nik'] ?? '') === $nik) {
                            $name = trim((string) ($r['nama'] ?? $r['name'] ?? $r['n_name'] ?? ''));
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            $name = '';
        }

        $cache[$id] = $name;

        return $name;
    }

    protected static function carbon(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return $value instanceof Carbon ? $value : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}