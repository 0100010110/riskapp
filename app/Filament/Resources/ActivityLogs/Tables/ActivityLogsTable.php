<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\Trmenu;
use App\Services\EmployeeCacheService;
use Filament\Actions;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    /** @var array<string,string>|null */
    protected static ?array $menuLookup = null;

    /** @var array<string,array<string,string>> */
    protected static array $fieldLabelMapCache = [];

    /** @var array<string,string> */
    protected static array $hardMenuLabelMap = [
        // Risk module
        'tmrisk'        => 'Risk Register',
        'tmriskapprove' => 'Risk Approval',
        'tmtaxonomy'    => 'Risk Taxonomy',
        'tmrealization' => 'Risk Realization',
        'tmriskrealization' => 'Risk Realization',

        // Settings module
        'trmenu'     => 'Menu',
        'trrole'     => 'Role',
        'trrolemenu' => 'Role Menu',
        'truserrole' => 'User Roles',

        // subject_type base names
        'Tmrisk'        => 'Risk Register',
        'Tmriskapprove' => 'Risk Approval',
        'Tmtaxonomy'    => 'Risk Taxonomy',
        'Trmenu'        => 'Menu',
        'Trrole'        => 'Role',
        'Trrolemenu'    => 'Role Menu',
        'Truserrole'    => 'User Roles',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->formatStateUsing(fn ($state, Activity $record) => self::menuLabelForRecord($record))
                    ->tooltip(fn (Activity $record) => (string) ($record->log_name ?? ''))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn ($state) => match (strtolower((string) $state)) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->formatStateUsing(function ($state, Activity $record) {
                        $desc = trim((string) ($state ?? ''));
                        if ($desc === '') {
                            return '-';
                        }

                        // Rapikan: "Trrole updated" -> "Role updated", "Tmrisk created" -> "Risk Register created"
                        $menu = self::menuLabelForRecord($record);

                        // ganti awalan kata pertama (biasanya class/table name)
                        $desc = preg_replace('/^[A-Za-z0-9_\\\\]+\\s+/', $menu . ' ', $desc) ?: $desc;

                        return $desc;
                    })
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->formatStateUsing(function ($state, Activity $record) {
                        $menu = self::menuLabelForRecord($record);
                        $id = $record->subject_id ?? null;

                        return $id ? ($menu . ' #' . $id) : $menu;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('causer_id')
                    ->label('Causer')
                    ->formatStateUsing(fn ($state, Activity $record) => self::causerLabel($record))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('changes_summary')
                    ->label('Changes')
                    ->state(fn (Activity $record) => self::prettyChangesSummary($record))
                    ->wrap()
                    ->searchable(query: function ($query, string $search) {
                        $search = trim($search);
                        if ($search === '') {
                            return $query;
                        }

                        // Search juga lewat json properties
                        return $query->whereRaw("CAST(properties as text) ILIKE ?", ['%' . $search . '%']);
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->emptyStateActions([])
            ->recordActions([
                Actions\Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (Activity $record) => 'Log #' . (string) ($record->id ?? '-'))
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (Activity $record) => self::viewModalContent($record)),
            ])
            ->defaultSort('id', 'desc');
    }

    private static function causerLabel(Activity $record): string
    {
        $id = $record->causer_id;

        if (! is_numeric($id) || (int) $id <= 0) {
            return '-';
        }

        $raw = (string) app(EmployeeCacheService::class)->labelForId((int) $id);

        // biasanya: "180144 | 1749 | RACKA PRATAMA P S | racka@..."
        $parts = array_values(array_filter(array_map('trim', explode('|', $raw))));

        $nik  = $parts[0] ?? (string) ((int) $id);
        $name = $parts[2] ?? ($parts[1] ?? 'Unknown');

        return trim($nik . ' | ' . $name);
    }

    private static function viewModalContent(Activity $record): HtmlString
    {
        $props = self::propertiesToArray($record->properties);

        $attrs = (array) Arr::get($props, 'attributes', []);
        $old   = (array) Arr::get($props, 'old', []);
        $meta  = (array) Arr::get($props, 'meta', []);

        $keys = array_unique(array_merge(array_keys($attrs), array_keys($old)));
        $keys = array_values(array_filter($keys, fn ($k) => is_string($k) && $k !== ''));
        sort($keys);

        $menu = self::menuLabelForRecord($record);
        $subject = $menu . ($record->subject_id ? (' #' . $record->subject_id) : '');
        $causer  = self::causerLabel($record);

        $ip  = (string) ($meta['ip'] ?? '-');
        $url = (string) ($meta['url'] ?? '-');
        $ua  = (string) ($meta['user_agent'] ?? '-');

        $html = '<div class="space-y-4">';

        $html .= '
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="rounded-xl border border-gray-700/50 p-3">
                    <div class="text-xs text-gray-400">Subject</div>
                    <div class="font-medium">' . e($subject) . '</div>
                </div>
                <div class="rounded-xl border border-gray-700/50 p-3">
                    <div class="text-xs text-gray-400">Causer</div>
                    <div class="font-medium">' . e($causer) . '</div>
                </div>
                <div class="rounded-xl border border-gray-700/50 p-3">
                    <div class="text-xs text-gray-400">Log / Event</div>
                    <div class="font-medium">' . e($menu) . ' / ' . e((string) ($record->event ?? '-')) . '</div>
                </div>
                <div class="rounded-xl border border-gray-700/50 p-3">
                    <div class="text-xs text-gray-400">Created At</div>
                    <div class="font-medium">' . e(optional($record->created_at)->format('Y-m-d H:i:s') ?? '-') . '</div>
                </div>
            </div>
        ';

        $desc = trim((string) ($record->description ?? ''));
        if ($desc !== '') {
            $desc = preg_replace('/^[A-Za-z0-9_\\\\]+\\s+/', $menu . ' ', $desc) ?: $desc;
        }

        $html .= '
            <div class="rounded-xl border border-gray-700/50 p-3">
                <div class="text-xs text-gray-400 mb-2">Description</div>
                <div class="whitespace-pre-wrap">' . e($desc !== '' ? $desc : '-') . '</div>
            </div>
        ';

        $html .= '
            <div class="rounded-xl border border-gray-700/50 p-3">
                <div class="text-xs text-gray-400 mb-2">Changes (Old → New)</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-gray-400">
                                <th class="text-left py-2 pr-3">Field</th>
                                <th class="text-left py-2 pr-3">Old</th>
                                <th class="text-left py-2">New</th>
                            </tr>
                        </thead>
                        <tbody>';

        if (empty($keys)) {
            $html .= '<tr><td colspan="3" class="py-2 text-gray-400">No changes captured.</td></tr>';
        } else {
            foreach ($keys as $k) {
                $label = self::fieldLabelFor($record, $k);

                $o = Arr::get($old, $k);
                $n = Arr::get($attrs, $k);

                $oText = is_scalar($o) || $o === null ? (string) ($o ?? '') : json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $nText = is_scalar($n) || $n === null ? (string) ($n ?? '') : json_encode($n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $oText = Str::limit((string) $oText, 500);
                $nText = Str::limit((string) $nText, 500);

                $html .= '
                    <tr class="border-t border-gray-800/60">
                        <td class="py-2 pr-3 font-medium">' . e($label) . '</td>
                        <td class="py-2 pr-3 text-gray-200"><pre class="whitespace-pre-wrap">' . e($oText) . '</pre></td>
                        <td class="py-2 text-gray-200"><pre class="whitespace-pre-wrap">' . e($nText) . '</pre></td>
                    </tr>
                ';
            }
        }

        $html .= '</tbody></table></div></div>';

        $html .= '
            <div class="rounded-xl border border-gray-700/50 p-3">
                <div class="text-xs text-gray-400 mb-2">Meta</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <div class="text-xs text-gray-400">IP</div>
                        <div class="font-medium break-all">' . e($ip) . '</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">URL</div>
                        <div class="font-medium break-all">' . e($url) . '</div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="text-xs text-gray-400">User Agent</div>
                        <div class="font-medium break-words">' . e(Str::limit($ua, 140)) . '</div>
                    </div>
                </div>
            </div>
        ';

        $raw = json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $html .= '
            <details class="rounded-xl border border-gray-700/50 p-3">
                <summary class="cursor-pointer text-sm text-gray-300">Raw properties (JSON)</summary>
                <pre class="mt-3 text-xs whitespace-pre-wrap">' . e((string) $raw) . '</pre>
            </details>
        ';

        $html .= '</div>';

        return new HtmlString($html);
    }

    private static function prettyChangesSummary(Activity $record): string
    {
        $props = self::propertiesToArray($record->properties);
        $attrs = (array) ($props['attributes'] ?? []);
        $old   = (array) ($props['old'] ?? []);

        $keys = array_values(array_unique(array_merge(array_keys($attrs), array_keys($old))));
        $keys = array_values(array_filter($keys, fn ($k) => is_string($k) && trim($k) !== ''));

        if (empty($keys)) {
            return '-';
        }

        $labels = [];
        foreach ($keys as $k) {
            $labels[] = self::fieldLabelFor($record, $k);
        }

        $max = 4;
        $shown = array_slice($labels, 0, $max);
        $more = count($labels) - count($shown);

        $text = implode(', ', $shown);
        if ($more > 0) {
            $text .= ' (+' . $more . ')';
        }

        return $text;
    }

    private static function fieldLabelFor(Activity $record, string $key): string
    {
        $subjectType = (string) ($record->subject_type ?? '');
        $base = class_basename($subjectType);

        $map = self::fieldLabelMap($base);

        if (isset($map[$key])) {
            return $map[$key];
        }

        // fallback: c_control_effectiveness -> Control Effectiveness
        $k = $key;
        $k = preg_replace('/^(c_|i_|v_|e_|d_|f_)/', '', $k) ?: $k;
        $k = str_replace('_', ' ', $k);

        return Str::title(trim($k));
    }

    private static function fieldLabelMap(string $subjectBase): array
    {
        if (isset(self::$fieldLabelMapCache[$subjectBase])) {
            return self::$fieldLabelMapCache[$subjectBase];
        }

        $maps = [
            'Tmrisk' => [
                'i_id_taxonomy' => 'Taxonomy',
                'c_risk_year' => 'Tahun Risiko',
                'i_risk' => 'Nomor Risiko',
                'c_risk_status' => 'Status',
                'f_risk_primary' => 'Primary Risk',
                'e_risk_event' => 'Risk Event',
                'e_risk_cause' => 'Risk Cause',
                'e_risk_impact' => 'Risk Impact (Uraian)',
                'v_risk_impact' => 'Risk Impact Value',
                'c_risk_impactunit' => 'Impact Unit',
                'e_kri' => 'KRI',
                'c_kri_unit' => 'KRI Unit',
                'c_kri_operator' => 'KRI Operator',
                'v_threshold_safe' => 'Threshold Safe',
                'v_threshold_caution' => 'Threshold Caution',
                'v_threshold_danger' => 'Threshold Danger',
                'c_org_owner' => 'Owner Organization',
                'c_org_impact' => 'Impacted Organization',
                'd_exposure_period' => 'Exposure Period',
                'c_control_effectiveness' => 'Control Effectiveness',
                'e_exist_ctrl' => 'Eksisting Kontrol',
                'i_entry' => 'Created By',
                'd_entry' => 'Created At',
                'i_update' => 'Updated By',
                'd_update' => 'Updated At',
            ],
            'Tmriskapprove' => [
                'i_id_risk' => 'Risk',
                'i_id_role' => 'Role (Approver)',
                'i_emp' => 'Approved By (NIK)',
                'n_emp' => 'Approved By (Name)',
                'i_entry' => 'Created By',
                'd_entry' => 'Created At',
                'i_update' => 'Updated By',
                'd_update' => 'Updated At',
            ],
            'Trrole' => [
                'c_role' => 'Role Code',
                'n_role' => 'Role Name',
                'f_active' => 'Active',
                'i_entry' => 'Created By',
                'd_entry' => 'Created At',
                'i_update' => 'Updated By',
                'd_update' => 'Updated At',
            ],
            'Trmenu' => [
                'c_menu' => 'Menu Code',
                'n_menu' => 'Menu Name',
                'f_active' => 'Active',
            ],
            'Trrolemenu' => [
                'i_id_role' => 'Role',
                'i_id_menu' => 'Menu',
                'c_action' => 'Action (Bitmask)',
                'f_active' => 'Active',
            ],
            'Truserrole' => [
                'i_id_user' => 'User',
                'i_id_role' => 'Role',
                'f_active' => 'Active',
            ],
        ];

        return self::$fieldLabelMapCache[$subjectBase] = ($maps[$subjectBase] ?? []);
    }

    private static function propertiesToArray(mixed $props): array
    {
        if ($props instanceof Collection) {
            return $props->toArray();
        }
        if (is_array($props)) {
            return $props;
        }
        if (is_string($props) && $props !== '') {
            $decoded = json_decode($props, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_object($props) && method_exists($props, 'toArray')) {
            $arr = $props->toArray();
            return is_array($arr) ? $arr : [];
        }
        return [];
    }

    private static function menuLabelForRecord(Activity $record): string
    {
        // 1) hard map by log_name and subject base
        $log = trim((string) ($record->log_name ?? ''));
        $base = class_basename((string) ($record->subject_type ?? ''));

        foreach ([$log, strtolower($log), $base, strtolower($base)] as $k) {
            $kNorm = self::norm(self::stripPrefix($k));
            if ($kNorm !== '' && isset(self::$hardMenuLabelMap[$kNorm])) {
                return self::$hardMenuLabelMap[$kNorm];
            }
            if ($k !== '' && isset(self::$hardMenuLabelMap[$k])) {
                return self::$hardMenuLabelMap[$k];
            }
        }

        // 2) try lookup from trmenu (c_menu/n_menu)
        $lookup = self::menuLookup();

        foreach (self::menuCandidates($record) as $cand) {
            $k = self::norm($cand);
            if ($k !== '' && isset($lookup[$k])) {
                return $lookup[$k];
            }
        }

        // 3) fallback: buat cantik dari string
        $fallback = $log !== '' ? $log : ($base !== '' ? $base : 'Log');
        $fallback = self::stripPrefix($fallback);
        $fallback = str_replace('_', ' ', $fallback);

        return Str::title($fallback);
    }

    /**
     * @return array<string,string> key(normalized) => label
     */
    private static function menuLookup(): array
    {
        if (self::$menuLookup !== null) {
            return self::$menuLookup;
        }

        $map = [];

        try {
            $menus = Trmenu::query()
                ->select(['c_menu', 'n_menu', 'f_active'])
                ->get();

            foreach ($menus as $m) {
                $label = trim((string) ($m->n_menu ?? ''));
                if ($label === '') {
                    continue;
                }

                $c = trim((string) ($m->c_menu ?? ''));
                $n = trim((string) ($m->n_menu ?? ''));

                foreach ([
                    $c,
                    Str::slug($c),
                    str_replace('_', '', $c),
                    $n,
                    Str::slug($n),
                    str_replace('_', '', Str::slug($n)),
                ] as $k) {
                    $k = self::norm($k);
                    if ($k === '' || isset($map[$k])) {
                        continue;
                    }
                    $map[$k] = $label;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return self::$menuLookup = $map;
    }

    /**
     * @return array<int,string>
     */
    private static function menuCandidates(Activity $record): array
    {
        $out = [];

        $log = (string) ($record->log_name ?? '');
        $base = class_basename((string) ($record->subject_type ?? ''));

        foreach ([
            $log,
            self::stripPrefix($log),
            $base,
            self::stripPrefix($base),
            Str::slug($log),
            Str::slug(self::stripPrefix($log)),
            Str::slug($base),
            Str::slug(self::stripPrefix($base)),
        ] as $v) {
            $v = trim((string) $v);
            if ($v !== '') {
                $out[] = $v;
                $out[] = strtolower($v);
                $out[] = str_replace('_', '', strtolower($v));
            }
        }

        return array_values(array_unique(array_filter($out, fn ($v) => trim((string) $v) !== '')));
    }

    private static function stripPrefix(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';

        // hilangkan prefix umum tm/tr (db model)
        $lower = strtolower($v);
        if (str_starts_with($lower, 'tm')) {
            return substr($v, 2);
        }
        if (str_starts_with($lower, 'tr')) {
            return substr($v, 2);
        }

        return $v;
    }

    private static function norm(string $v): string
    {
        return strtolower(trim($v));
    }
}
