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
        'tmrisk'        => 'Risk Register',
        'tmriskapprove' => 'Risk Approval',
        'tmriskapproval' => 'Risk Approval',
        'tmtaxonomy'    => 'Risk Taxonomy',
        'tmrealization' => 'Risk Realization',
        'tmriskrealization' => 'Risk Realization',
        'tmlossevent'   => 'Loss Event',
        'tmlosseventapprove' => 'Loss Event Approval',
        'tmlosseventapproval' => 'Loss Event Approval',

        'trmenu'     => 'Menu',
        'trrole'     => 'Role',
        'trrolemenu' => 'Role Menu',
        'truserrole' => 'User Roles',

        'Tmrisk'        => 'Risk Register',
        'Tmriskapprove' => 'Risk Approval',
        'Tmriskapproval' => 'Risk Approval',
        'Tmtaxonomy'    => 'Risk Taxonomy',
        'Tmrealization' => 'Risk Realization',
        'Tmriskrealization' => 'Risk Realization',
        'Tmlossevent'   => 'Loss Event',
        'Tmlosseventapprove' => 'Loss Event Approval',
        'Tmlosseventapproval' => 'Loss Event Approval',
        'Trmenu'        => 'Menu',
        'Trrole'        => 'Role',
        'Trrolemenu'    => 'Role Menu',
        'Truserrole'    => 'User Roles',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('subject'))
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

                        $menu = self::menuLabelForRecord($record);
                        $desc = preg_replace('/^[A-Za-z0-9_\\\\]+\\s+/', $menu . ' ', $desc) ?: $desc;

                        return $desc;
                    })
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->formatStateUsing(fn ($state, Activity $record) => self::subjectLabel($record))
                    ->wrap()
                    ->searchable(query: function ($query, string $search) {
                        $search = trim($search);

                        if ($search === '') {
                            return $query;
                        }

                        return $query->where(function ($q) use ($search) {
                            $q->whereRaw("CAST(properties as text) ILIKE ?", ['%' . $search . '%'])
                                ->orWhereRaw("CAST(subject_id as text) ILIKE ?", ['%' . $search . '%'])
                                ->orWhereRaw("CAST(log_name as text) ILIKE ?", ['%' . $search . '%']);
                        });
                    }),

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

                        return $query->whereRaw("CAST(properties as text) ILIKE ?", ['%' . $search . '%']);
                    }),

                Tables\Columns\TextColumn::make('updated_at_display')
                    ->label('Updated At')
                    ->state(fn (Activity $record) => self::updatedAtLabel($record))
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('created_at', $direction)),
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
        $parts = array_values(array_filter(array_map('trim', explode('|', $raw))));

        $nik  = $parts[0] ?? (string) ((int) $id);
        $name = $parts[2] ?? ($parts[1] ?? 'Unknown');

        return trim($nik . ' | ' . $name);
    }

    private static function viewModalContent(Activity $record): HtmlString
    {
        $props = self::propertiesToArray($record->properties);
        $meta  = (array) Arr::get($props, 'meta', []);
        $changes = self::diffRows($record);

        $menu = self::menuLabelForRecord($record);
        $subject = self::subjectLabel($record);
        $causer  = self::causerLabel($record);

        $event = trim((string) ($record->event ?? ''));
        $desc = trim((string) ($record->description ?? ''));
        if ($desc !== '') {
            $desc = preg_replace('/^[A-Za-z0-9_\\\\]+\\s+/', $menu . ' ', $desc) ?: $desc;
        }

        $updatedAt = self::updatedAtLabel($record);

        $ip  = (string) ($meta['ip'] ?? '-');
        $url = (string) ($meta['url'] ?? '-');
        $ua  = (string) ($meta['user_agent'] ?? '-');

        $raw = json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $html = '
<style>
    .log-modal-wrap {
        color: #f3f4f6;
    }

    .log-modal-stack {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .log-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 18px;
        row-gap: 18px;
    }

    .log-modal-card {
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 16px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.02);
    }

    .log-modal-title {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: rgba(255,255,255,.60);
        margin-bottom: 10px;
    }

    .log-modal-value {
        color: #fff;
        font-size: 15px;
        line-height: 1.7;
        word-break: break-word;
    }

    .log-modal-muted {
        color: rgba(255,255,255,.74);
        font-size: 13px;
        line-height: 1.6;
    }

    .log-modal-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 5px 11px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(255,255,255,.08);
        color: rgba(255,255,255,.92);
    }

    .log-modal-section-title {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 4px;
    }

    .log-modal-section-subtitle {
        font-size: 13px;
        color: rgba(255,255,255,.72);
        margin-bottom: 16px;
    }

    .log-change-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
        margin-top: 8px;
    }

    .log-change-card {
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 16px;
        padding: 16px;
        background: rgba(255, 255, 255, 0.02);
    }

    .log-change-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-top: 14px;
    }

    .log-change-old,
    .log-change-new {
        border-radius: 12px;
        padding: 14px;
    }

    .log-change-old {
        border: 1px solid rgba(239, 68, 68, 0.24);
        background: rgba(239, 68, 68, 0.07);
    }

    .log-change-new {
        border: 1px solid rgba(34, 197, 94, 0.24);
        background: rgba(34, 197, 94, 0.07);
    }

    .log-change-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin-bottom: 8px;
        color: rgba(255,255,255,.68);
    }

    .log-change-value {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 14px;
        line-height: 1.6;
        color: #fff;
        font-family: inherit;
    }

    .log-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-top: 8px;
    }

    .log-meta-box {
        border-radius: 12px;
        padding: 14px;
        background: rgba(255,255,255,.04);
    }

    @media (max-width: 900px) {
        .log-modal-grid,
        .log-change-grid,
        .log-meta-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="log-modal-wrap">
    <div class="log-modal-stack">
';

        $html .= '
        <div class="log-modal-grid">
            <div class="log-modal-card">
                <div class="log-modal-title">Subject</div>
                <div class="log-modal-value">' . e($subject) . '</div>
            </div>

            <div class="log-modal-card">
                <div class="log-modal-title">Causer</div>
                <div class="log-modal-value">' . e($causer) . '</div>
            </div>

            <div class="log-modal-card">
                <div class="log-modal-title">Log / Event</div>
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:4px;">
                    <span class="log-modal-badge">' . e($menu) . '</span>
                    <span class="log-modal-badge">' . e($event !== '' ? Str::title($event) : '-') . '</span>
                </div>
            </div>

            <div class="log-modal-card">
                <div class="log-modal-title">Updated At</div>
                <div class="log-modal-value">' . e($updatedAt) . '</div>
            </div>
        </div>
';

        $html .= '
        <div class="log-modal-card">
            <div class="log-modal-title">Description</div>
            <div class="log-modal-value">' . e($desc !== '' ? $desc : '-') . '</div>
        </div>
';

        $html .= '
        <div class="log-modal-card">
            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                    <div class="log-modal-section-title">Changes</div>
                    <div class="log-modal-section-subtitle">Perubahan field yang benar-benar tercatat pada log ini.</div>
                </div>
                <span class="log-modal-badge">' . e((string) count($changes)) . ' field</span>
            </div>
';

        if (empty($changes)) {
            $html .= '
            <div class="log-modal-card" style="padding:14px; background:rgba(255,255,255,.03);">
                <div class="log-modal-muted">No changes captured.</div>
            </div>
';
        } else {
            $html .= '<div class="log-change-list">';

            foreach ($changes as $row) {
                $html .= '
                <div class="log-change-card">
                    <div class="log-modal-value" style="font-size:15px; font-weight:700;">' . e($row['label']) . '</div>

                    <div class="log-change-grid">
                        <div class="log-change-old">
                            <div class="log-change-label">Old</div>
                            <pre class="log-change-value">' . e($row['old']) . '</pre>
                        </div>

                        <div class="log-change-new">
                            <div class="log-change-label">New</div>
                            <pre class="log-change-value">' . e($row['new']) . '</pre>
                        </div>
                    </div>
                </div>
';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        $html .= '
        <div class="log-modal-card">
            <div class="log-modal-section-title">Meta</div>
            <div class="log-meta-grid">
                <div class="log-meta-box">
                    <div class="log-modal-title">IP</div>
                    <div class="log-modal-value">' . e($ip) . '</div>
                </div>

                <div class="log-meta-box">
                    <div class="log-modal-title">URL</div>
                    <div class="log-modal-value">' . e($url) . '</div>
                </div>

                <div class="log-meta-box" style="grid-column: 1 / -1;">
                    <div class="log-modal-title">User Agent</div>
                    <div class="log-modal-value">' . e($ua) . '</div>
                </div>
            </div>
        </div>
';

        $html .= '
        <details class="log-modal-card">
            <summary style="cursor:pointer; font-size:14px; font-weight:600; color:#e5e7eb;">Raw properties (JSON)</summary>
            <pre style="margin-top:14px; white-space:pre-wrap; word-break:break-word; border-radius:12px; padding:14px; background:rgba(255,255,255,.04); font-size:12px; line-height:1.6; color:#e5e7eb;">'
                . e((string) $raw) .
            '</pre>
        </details>
';

        $html .= '
    </div>
</div>';

        return new HtmlString($html);
    }

    private static function prettyChangesSummary(Activity $record): string
    {
        $changes = self::diffRows($record);

        if (empty($changes)) {
            return '-';
        }

        $labels = array_map(fn (array $row) => $row['label'], $changes);

        $max = 4;
        $shown = array_slice($labels, 0, $max);
        $more = count($labels) - count($shown);

        $text = implode(', ', $shown);
        if ($more > 0) {
            $text .= ' (+' . $more . ')';
        }

        return $text;
    }

    /**
     * @return array<int, array{key:string,label:string,old:string,new:string}>
     */
    private static function diffRows(Activity $record): array
    {
        $props = self::propertiesToArray($record->properties);
        $attrs = (array) Arr::get($props, 'attributes', []);
        $old   = (array) Arr::get($props, 'old', []);

        $keys = array_unique(array_merge(array_keys($attrs), array_keys($old)));
        $keys = array_values(array_filter($keys, fn ($k) => is_string($k) && trim($k) !== ''));

        $ignored = [
            'updated_at',
            'created_at',
            'i_update',
            'd_update',
            'i_entry',
            'd_entry',
        ];

        $rows = [];

        foreach ($keys as $k) {
            if (in_array($k, $ignored, true)) {
                continue;
            }

            $oldVal = Arr::get($old, $k);
            $newVal = Arr::get($attrs, $k);

            if (self::valuesAreEqual($oldVal, $newVal)) {
                continue;
            }

            $rows[] = [
                'key' => $k,
                'label' => self::fieldLabelFor($record, $k),
                'old' => self::formatValueForDisplay($oldVal),
                'new' => self::formatValueForDisplay($newVal),
            ];
        }

        return $rows;
    }

    private static function updatedAtLabel(Activity $record): string
    {
        $props = self::propertiesToArray($record->properties);
        $attrs = (array) Arr::get($props, 'attributes', []);
        $old   = (array) Arr::get($props, 'old', []);

        foreach (['d_update', 'updated_at', 'd_entry', 'created_at'] as $field) {
            $value = $attrs[$field] ?? $old[$field] ?? null;
            $formatted = self::formatDateTimeValue($value);
            if ($formatted !== null) {
                return $formatted;
            }
        }

        return $record->created_at?->format('Y-m-d H:i:s') ?? '-';
    }

    private static function formatDateTimeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return trim((string) $value) !== '' ? (string) $value : null;
        }
    }

    private static function subjectLabel(Activity $record): string
    {
        $menu = self::menuLabelForRecord($record);
        $fallback = $menu . ($record->subject_id ? (' #' . $record->subject_id) : '');

        try {
            $subject = $record->subject;
            if ($subject) {
                $fromModel = self::subjectLabelFromModel($subject);
                if ($fromModel !== '') {
                    return $fromModel;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $props = self::propertiesToArray($record->properties);
        $attrs = (array) Arr::get($props, 'attributes', []);
        $old   = (array) Arr::get($props, 'old', []);

        $subjectType = strtolower(class_basename((string) ($record->subject_type ?? '')));

        $pick = function (array $keys) use ($attrs, $old): ?string {
            foreach ($keys as $key) {
                $value = $attrs[$key] ?? $old[$key] ?? null;

                if (is_array($value) || is_object($value)) {
                    continue;
                }

                $value = trim((string) ($value ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            return null;
        };

        return match ($subjectType) {
            'tmrisk' => $pick(['e_risk_event', 'n_risk_event', 'risk_event', 'event', 'title']) ?: $fallback,
            'tmtaxonomy' => self::joinParts([
                $pick(['c_taxonomy']),
                $pick(['n_taxonomy']),
            ]) ?: ($pick(['n_taxonomy']) ?: $fallback),
            'trrole' => $pick(['n_role', 'role_name', 'name']) ?: self::joinParts([
                $pick(['c_role']),
                $pick(['n_role']),
            ]) ?: $fallback,
            'trmenu' => self::joinParts([
                $pick(['c_menu']),
                $pick(['n_menu']),
            ]) ?: ($pick(['n_menu']) ?: $fallback),
            'tmlossevent' => $pick(['e_loss_event', 'n_loss_event', 'loss_event', 'event', 'title']) ?: $fallback,
            default => $fallback,
        };
    }

    private static function subjectLabelFromModel(mixed $subject): string
    {
        $base = strtolower(class_basename($subject));

        $pick = function (array $keys) use ($subject): ?string {
            foreach ($keys as $key) {
                try {
                    $value = data_get($subject, $key);
                } catch (\Throwable) {
                    $value = null;
                }

                if (is_array($value) || is_object($value)) {
                    continue;
                }

                $value = trim((string) ($value ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            return null;
        };

        return match ($base) {
            'tmrisk' => $pick([
                'e_risk_event',
                'n_risk_event',
                'risk_event',
                'event',
                'title',
            ]) ?: ($pick(['i_risk']) ? ('Risk No: ' . $pick(['i_risk'])) : ''),

            'tmtaxonomy' => self::joinParts([
                $pick(['c_taxonomy']),
                $pick(['n_taxonomy']),
            ]) ?: ($pick(['n_taxonomy']) ?: ''),

            'trrole' => self::joinParts([
                $pick(['c_role']),
                $pick(['n_role', 'role_name', 'name']),
            ]) ?: ($pick(['n_role', 'role_name', 'name']) ?: ''),

            'trmenu' => self::joinParts([
                $pick(['c_menu']),
                $pick(['n_menu']),
            ]) ?: ($pick(['n_menu']) ?: ''),

            'trrolemenu' => self::joinParts([
                $pick(['role.n_role', 'role.role_name', 'role.name']) ?: ($pick(['i_id_role']) ? 'Role #' . $pick(['i_id_role']) : null),
                $pick(['menu.n_menu', 'menu.name']) ?: ($pick(['i_id_menu']) ? 'Menu #' . $pick(['i_id_menu']) : null),
            ], ' | '),

            'truserrole' => self::joinParts([
                $pick(['user.name', 'user.n_user']) ?: ($pick(['i_id_user']) ? 'User #' . $pick(['i_id_user']) : null),
                $pick(['role.n_role', 'role.role_name', 'role.name']) ?: ($pick(['i_id_role']) ? 'Role #' . $pick(['i_id_role']) : null),
            ], ' | '),

            'tmriskapprove', 'tmriskapproval' => self::joinParts([
                $pick(['risk.e_risk_event', 'risk.n_risk_event']) ?: ($pick(['i_id_risk']) ? 'Risk #' . $pick(['i_id_risk']) : null),
                $pick(['n_emp']),
                $pick(['role.n_role', 'role.role_name', 'role.name']) ?: ($pick(['i_id_role']) ? 'Role #' . $pick(['i_id_role']) : null),
            ], ' | '),

            'tmrealization', 'tmriskrealization' => $pick([
                'e_realization',
                'n_realization',
                'e_risk_realization',
                'n_risk_realization',
                'description',
            ]) ?: ($pick(['i_id_risk']) ? 'Risk #' . $pick(['i_id_risk']) : ''),

            'tmlossevent' => $pick([
                'e_loss_event',
                'n_loss_event',
                'loss_event',
                'event',
                'title',
                'description',
            ]) ?: ($pick(['i_loss_event']) ? 'Loss Event #' . $pick(['i_loss_event']) : ''),

            'tmlosseventapprove', 'tmlosseventapproval' => self::joinParts([
                $pick(['lossEvent.e_loss_event', 'lossEvent.n_loss_event', 'loss_event.e_loss_event', 'loss_event.n_loss_event'])
                    ?: ($pick(['i_id_loss_event']) ? 'Loss Event #' . $pick(['i_id_loss_event']) : null),
                $pick(['n_emp']),
                $pick(['role.n_role', 'role.role_name', 'role.name']) ?: ($pick(['i_id_role']) ? 'Role #' . $pick(['i_id_role']) : null),
            ], ' | '),

            default => self::joinParts([
                $pick(['name']),
                $pick(['title']),
                $pick(['description']),
            ]),
        };
    }

    private static function joinParts(array $parts, string $glue = ' - '): string
    {
        $parts = array_values(array_filter(array_map(
            fn ($v) => trim((string) ($v ?? '')),
            $parts
        ), fn ($v) => $v !== ''));

        return implode($glue, $parts);
    }

    private static function valuesAreEqual(mixed $old, mixed $new): bool
    {
        if (is_array($old) || is_array($new) || is_object($old) || is_object($new)) {
            return json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                === json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) ($old ?? '') === (string) ($new ?? '');
    }

    private static function formatValueForDisplay(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $json !== false ? $json : '-';
        }

        $text = trim((string) $value);

        if ($text === '') {
            return '-';
        }

        return Str::limit($text, 2000);
    }

    private static function fieldLabelFor(Activity $record, string $key): string
    {
        $subjectType = (string) ($record->subject_type ?? '');
        $base = class_basename($subjectType);

        $map = self::fieldLabelMap($base);

        if (isset($map[$key])) {
            return $map[$key];
        }

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
            'Tmlossevent' => [
                'e_loss_event' => 'Loss Event',
                'n_loss_event' => 'Loss Event',
            ],
            'Tmlosseventapprove' => [
                'i_id_loss_event' => 'Loss Event',
                'n_emp' => 'Approved By (Name)',
                'i_id_role' => 'Role (Approver)',
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

        $lookup = self::menuLookup();

        foreach (self::menuCandidates($record) as $cand) {
            $k = self::norm($cand);
            if ($k !== '' && isset($lookup[$k])) {
                return $lookup[$k];
            }
        }

        $fallback = $log !== '' ? $log : ($base !== '' ? $base : 'Log');
        $fallback = self::stripPrefix($fallback);
        $fallback = str_replace('_', ' ', $fallback);

        return Str::title($fallback);
    }

    /**
     * @return array<string,string>
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
        } catch (\Throwable) {
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
        if ($v === '') {
            return '';
        }

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