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

        $approvalMap = self::mapApprovalsByStatus($codes, $approvals);

        $items = [];
        $currentStatus = (int) ($risk->c_risk_status ?? 0);

        foreach ($codes as $code) {
            $approval = $approvalMap[$code] ?? null;

            if ($code === 0) {
                $at = self::carbon($risk->d_entry);
                $actor = self::employeeNameByIdOrNik((int) ($risk->i_entry ?? 0));
                $role = 'Creator';
            } else {
                $at = self::carbon($approval?->d_entry);
                $actor = trim((string) ($approval?->n_emp ?? ''));

                if ($actor === '') {
                    $actor = self::employeeNameByIdOrNik((int) ($approval?->i_entry ?? 0));
                }

                $role = trim((string) (
                    $approval?->role?->n_role
                    ?? $approval?->role?->v_role
                    ?? $approval?->role?->name
                    ?? ''
                ));
            }

            if (! $at && $code === $currentStatus && $code !== 0) {
                $at = self::carbon($risk->d_update)
                    ?? self::carbon($risk->updated_at)
                    ?? self::carbon($risk->latestApproval?->d_entry);
            }

            $passed = $code === 0 || $at !== null;
            $current = $code === $currentStatus;

            $items[] = [
                'code' => $code,
                'phase' => self::phaseFor($code),
                'label' => self::plainStatusLabel($code),
                'at_raw' => $at?->toIso8601String(),
                'at_label' => $at?->format('d M Y H:i'),
                'actor' => $actor,
                'role' => $role,
                'current' => $current,
                'passed' => $passed,
                'clickable' => $passed || $current,
                'hint' => self::statusHints()[$code] ?? null,
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

        $currentIndex = collect($items)->search(
            fn (array $item): bool => $item['code'] === $currentStatus
        );

        if ($currentIndex === false) {
            $currentIndex = 0;
        }

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
     * @param  array<int, int>  $codes
     * @return array<int, Tmriskapprove>
     */
    protected static function mapApprovalsByStatus(array $codes, Collection $approvals): array
    {
        $approvalCodes = array_values(array_filter($codes, fn (int $code): bool => $code !== 0));

        $mapped = [];

        foreach ($approvalCodes as $index => $code) {
            $approval = $approvals->get($index);

            if ($approval) {
                $mapped[$code] = $approval;
            }
        }

        return $mapped;
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
            // ignore
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