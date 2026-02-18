<?php

namespace App\Filament\Resources\RiskMitigations\Schemas;

use App\Models\Tmriskinherent;
use App\Models\Tmriskmitigation;
use App\Support\RiskApprovalWorkflow;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class RiskMitigationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 1,
            ])
            ->schema([
                Section::make('Risk Mitigation')
                    ->columns(2)
                    ->schema([
                        Select::make('i_id_riskinherent')
                            ->label('Risk Inherent')
                            ->relationship(
                                name: 'riskInherent',
                                titleAttribute: 'i_risk_inherent',
                                modifyQueryUsing: function (Builder $query, ?Tmriskmitigation $record): Builder {
                                    $ctx      = RiskApprovalWorkflow::context();
                                    $isSuper  = (bool) ($ctx['is_superadmin'] ?? false);
                                    $roleType = (string) ($ctx['role_type'] ?? '');
                                    $org      = strtoupper(trim((string) ($ctx['org_prefix'] ?? '')));
                                    $uid      = (int) ($ctx['user_id'] ?? 0);

                                    
                                    $canAll = $isSuper || in_array($roleType, [
                                        RiskApprovalWorkflow::ROLE_TYPE_ADMIN_GRC ?? 'admin_grc',
                                        RiskApprovalWorkflow::ROLE_TYPE_APPROVAL_GRC ?? 'approval_grc',
                                        RiskApprovalWorkflow::ROLE_TYPE_GRC ?? 'grc',
                                    ], true);

                                    if (! $canAll) {
                                        if (in_array($roleType, [
                                            RiskApprovalWorkflow::ROLE_TYPE_RISK_OFFICER ?? 'risk_officer',
                                            RiskApprovalWorkflow::ROLE_TYPE_OFFICER ?? 'officer',
                                            RiskApprovalWorkflow::ROLE_TYPE_KADIV ?? 'kadiv',
                                        ], true)) {
                                            if ($org === '') {
                                                return $query->whereRaw('1=0');
                                            }
                                            $query->whereHas('risk', fn (Builder $q) => $q->where('c_org_owner', $org));
                                        } else {
                                            if (($roleType === (RiskApprovalWorkflow::ROLE_TYPE_RSA_ENTRY ?? 'rsa_entry')) && $uid > 0) {
                                                $query->where('i_entry', $uid);
                                            } else {
                                                return $query->whereRaw('1=0');
                                            }
                                        }
                                    }

                                    $query->whereHas('risk', fn (Builder $q) => $q->where('c_risk_status', 4));

                                    $usedInherentIds = Tmriskmitigation::query()->select('i_id_riskinherent');

                                    $currentInherentId = (int) ($record?->i_id_riskinherent ?? 0);

                                    if ($currentInherentId > 0) {
                                        $query->where(function (Builder $w) use ($usedInherentIds, $currentInherentId) {
                                            $w->whereNotIn('i_id_riskinherent', $usedInherentIds)
                                              ->orWhere('i_id_riskinherent', $currentInherentId);
                                        });
                                    } else {
                                        $query->whereNotIn('i_id_riskinherent', $usedInherentIds);
                                    }

                                    return $query;
                                }
                            )
                            ->preload()
                            ->searchable()
                            ->required()
                            ->allowHtml()
                            ->getOptionLabelFromRecordUsing(fn (Tmriskinherent $ri) => self::riskInherentOptionLabel($ri)),

                        Textarea::make('e_risk_mitigation')
                            ->label('Risk Mitigation')
                            ->rows(4)
                            ->required(),

                        TextInput::make('c_org_mitigation')
                            ->label('Organization In Charge')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('v_mitigation_cost')
                            ->label('Mitigation Cost ($)')
                            ->numeric()
                            ->required(),

                        Hidden::make('f_mitigation_month')
                            ->required()
                            ->default('000000000000'),

                        CheckboxList::make('months')
                            ->label('Planned Month')
                            ->options([
                                '1'  => 'Jan',
                                '2'  => 'Feb',
                                '3'  => 'Mar',
                                '4'  => 'Apr',
                                '5'  => 'May',
                                '6'  => 'Jun',
                                '7'  => 'Jul',
                                '8'  => 'Aug',
                                '9'  => 'Sep',
                                '10' => 'Oct',
                                '11' => 'Nov',
                                '12' => 'Dec',
                            ])
                            ->columns(6)
                            ->columnSpanFull()
                            ->required()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateHydrated(function (Set $set, $state, $record) {
                                $bin = (string) ($record?->f_mitigation_month ?? '000000000000');
                                $bin = self::normalizeBinary($bin);

                                $set('months', self::monthsFromBinary($bin));
                                $set('f_mitigation_month', $bin);
                            })
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('f_mitigation_month', self::binaryFromMonths($state));
                            }),
                    ]),
            ]);
    }

    private static function riskInherentOptionLabel(Tmriskinherent $ri): string
    {
        $ri->loadMissing(['risk']);

        $no = (string) ($ri->i_risk_inherent ?? $ri->i_id_riskinherent);

        $year  = trim((string) ($ri->risk?->c_risk_year ?? ''));
        $riskNo = trim((string) ($ri->risk?->i_risk ?? ''));
        $event = trim((string) ($ri->risk?->e_risk_event ?? ''));

        if (mb_strlen($event) > 60) {
            $event = mb_substr($event, 0, 60) . '…';
        }

        $left = trim(($year !== '' ? $year : '-') . ' - ' . ($riskNo !== '' ? $riskNo : '-'));

        return (string) new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:2px;line-height:1.25;">
                <div style="display:flex;gap:10px;align-items:center;">
                    <span style="font-weight:700;">' . e($no) . '</span>
                    <span style="opacity:.6;">|</span>
                    <span style="opacity:.9;">' . e($left) . '</span>
                </div>
                <div style="opacity:.7;font-size:12px;">' . e($event) . '</div>
            </div>'
        );
    }

    private static function normalizeBinary(string $bin): string
    {
        $bin = preg_replace('/[^01]/', '', $bin) ?? '';
        $bin = substr($bin, 0, 12);
        return str_pad($bin, 12, '0', STR_PAD_RIGHT);
    }

    private static function monthsFromBinary(string $bin): array
    {
        $bin = self::normalizeBinary($bin);

        $out = [];
        for ($i = 0; $i < 12; $i++) {
            if (($bin[$i] ?? '0') === '1') {
                $out[] = (string) ($i + 1);
            }
        }

        return $out;
    }

    private static function binaryFromMonths($months): string
    {
        $selected = [];

        if (is_array($months)) {
            foreach ($months as $m) {
                $n = (int) $m;
                if ($n >= 1 && $n <= 12) {
                    $selected[$n] = true;
                }
            }
        }

        $bits = [];
        for ($i = 1; $i <= 12; $i++) {
            $bits[] = isset($selected[$i]) ? '1' : '0';
        }

        return implode('', $bits);
    }
}
