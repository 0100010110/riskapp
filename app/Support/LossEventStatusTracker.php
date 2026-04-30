<?php

namespace App\Support;

use App\Models\Tmlostevent;
use App\Services\EmployeeCacheService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class LossEventStatusTracker
{
    protected const MAIN_FLOW = [0, 14, 15, 16, 17];
    protected const DELETE_FLOW = [0, 14, 15, 5];

    public static function for(Tmlostevent $record): array
    {
        $record->loadMissing('taxonomy');

        $codes = self::visibleCodesFor($record);

        $activities = Activity::query()
            ->with('causer')
            ->where('subject_type', Tmlostevent::class)
            ->where('subject_id', $record->getKey())
            ->orderBy('created_at')
            ->get();

        $statusEntries = self::mapStatusEntries($record, $activities);

        $currentStatus = (int) ($record->c_lostevent_status ?? 0);
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
                ? self::carbon($record->d_entry)
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
            'record_no' => 'LED #' . (string) $record->getKey(),
            'event_title' => trim((string) ($record->e_lost_event ?? '')) ?: '-',
            'taxonomy_code' => trim((string) ($record->taxonomy?->c_taxonomy ?? '')) ?: '-',
            'current_status_code' => $currentStatus,
            'current_phase' => self::phaseFor($currentStatus),
            'current_status_label' => self::plainStatusLabel($currentStatus),
            'current_message' => self::statusHints()[$currentStatus] ?? 'Ikuti workflow approval berikutnya.',
            'items' => $items,
            'current_index' => $currentIndex,
        ];
    }

    protected static function visibleCodesFor(Tmlostevent $record): array
    {
        $status = (int) ($record->c_lostevent_status ?? 0);

        if ($status === 5) {
            return self::DELETE_FLOW;
        }

        return self::MAIN_FLOW;
    }

    protected static function phaseFor(int $code): string
    {
        return match (true) {
            $code === 0 => 'Draft',
            $code === 5 => 'Hapus',
            default => 'LED',
        };
    }

    protected static function plainStatusLabel(int $code): string
    {
        return trim((string) (Tmlostevent::statusOptions()[$code] ?? (string) $code));
    }

    protected static function mapStatusEntries(Tmlostevent $record, Collection $activities): array
    {
        $mapped = [
            0 => [
                'at_raw' => self::carbon($record->d_entry)?->toIso8601String(),
                'actor'  => self::resolveUserName((int) ($record->i_entry ?? 0)),
                'role'   => 'Creator',
            ],
        ];

        foreach ($activities as $activity) {
            if (! $activity instanceof Activity) {
                continue;
            }

            $newStatus = data_get($activity, 'properties.attributes.c_lostevent_status');

            if ($newStatus === null || $newStatus === '') {
                continue;
            }

            $code = (int) $newStatus;

            if (! in_array($code, [5, 14, 15, 16, 17], true)) {
                continue;
            }

            $actor = trim((string) (
                $activity->causer?->name
                ?? $activity->causer?->nik
                ?? ''
            ));

            if ($actor === '') {
                $actor = self::resolveUserName((int) ($activity->causer_id ?? 0));
            }

            $mapped[$code] = [
                'at_raw' => self::carbon($activity->created_at)?->toIso8601String(),
                'actor'  => $actor,
                'role'   => self::roleLabelForStatus($code),
            ];
        }

        $currentStatus = (int) ($record->c_lostevent_status ?? 0);

        if ($currentStatus !== 0 && ! isset($mapped[$currentStatus])) {
            $mapped[$currentStatus] = [
                'at_raw' => self::carbon($record->d_update)?->toIso8601String(),
                'actor'  => self::resolveUserName((int) ($record->i_update ?? 0)),
                'role'   => self::roleLabelForStatus($currentStatus),
            ];
        }

        return $mapped;
    }

    protected static function roleLabelForStatus(int $code): string
    {
        return match ($code) {
            14 => 'Officer',
            15 => 'Kadiv',
            16 => 'Admin LED',
            17 => 'Approval LED',
            5  => 'Delete Request',
            default => '',
        };
    }

    protected static function statusHints(): array
    {
        return [
            0  => 'Loss event masih draft. Lengkapi taxonomy, tanggal kejadian, nominal kerugian, dan deskripsi event sebelum diproses.',
            14 => 'Loss event sudah di-approve Officer LED dan menunggu review Kadiv.',
            15 => 'Loss event sudah di-approve Kadiv LED dan menunggu pengajuan Admin LED.',
            16 => 'Loss event sudah diajukan Admin LED dan menunggu approval final.',
            17 => 'Workflow approval LED selesai.',
            5  => 'Loss event sedang diajukan untuk dihapus. Approve delete akan menghapus data secara permanen.',
        ];
    }

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
            $actor = self::resolveUserName((int) ($activity->causer_id ?? 0));
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

    protected static function extractChanges(Activity $activity): array
    {
        $attributes = (array) data_get($activity, 'properties.attributes', []);
        $old = (array) data_get($activity, 'properties.old', []);

        $keys = array_unique(array_merge(array_keys($attributes), array_keys($old)));

        $ignored = [
            'i_id_lostevent',
            'c_lostevent_status',
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

    protected static function fieldLabels(): array
    {
        return [
            'i_id_taxonomy' => 'Taxonomy',
            'd_lost_event' => 'Event Date',
            'e_lost_event' => 'Loss Event',
            'v_lost_event' => 'Kerugian',
        ];
    }

    protected static function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return '-';
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

        if ($field === 'v_lost_event' && is_numeric($string)) {
            return number_format((int) $string, 0, '.', ',');
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $string)) {
                return Carbon::parse($string)->format('d M Y H:i');
            }
        } catch (\Throwable) {
        }

        return $string;
    }

    protected static function resolveUserName(int $userId): string
    {
        static $cache = [];

        if ($userId <= 0) {
            return '';
        }

        if (array_key_exists($userId, $cache)) {
            return (string) $cache[$userId];
        }

        $name = '';

        try {
            $svc = app(EmployeeCacheService::class);

            $row = null;
            try {
                $row = $svc->findById($userId);
            } catch (\Throwable) {
                $row = null;
            }

            if (! is_array($row)) {
                $nik = (string) $userId;

                foreach ($svc->data() as $r) {
                    if (! is_array($r)) {
                        continue;
                    }

                    if (trim((string) ($r['nik'] ?? '')) === $nik) {
                        $row = $r;
                        break;
                    }
                }
            }

            $name = is_array($row)
                ? trim((string) ($row['nama'] ?? $row['name'] ?? $row['n_name'] ?? ''))
                : '';
        } catch (\Throwable) {
            $name = '';
        }

        $cache[$userId] = $name;

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