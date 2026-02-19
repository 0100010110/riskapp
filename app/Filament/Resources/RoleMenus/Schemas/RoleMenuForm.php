<?php

namespace App\Filament\Resources\RoleMenus\Schemas;

use App\Models\Trmenu;
use App\Models\Trrole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RoleMenuForm
{
    protected const ACTIONS = [
        '1'  => 'C',
        '2'  => 'R',
        '4'  => 'U',
        '8'  => 'D',
        '16' => 'A',
    ];

    protected static function riskApprovalMenuId(): ?int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached ?: null;
        }

        $id = Trmenu::query()
            ->whereIn('c_menu', ['risk_approvals', 'risk_approval'])
            ->orderByRaw("CASE WHEN c_menu='risk_approvals' THEN 0 ELSE 1 END")
            ->value('i_id_menu');

        if (! $id) {
            // fallback by label
            $id = Trmenu::query()
                ->whereRaw("LOWER(COALESCE(n_menu,'')) LIKE ?", ['%risk approval%'])
                ->value('i_id_menu');
        }

        $cached = $id ? (int) $id : 0;
        return $cached > 0 ? $cached : null;
    }

    protected static function lossEventApprovalMenuId(): ?int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached ?: null;
        }

        $id = Trmenu::query()
            ->whereIn('c_menu', ['loss_event_approvals', 'loss_event_approval'])
            ->orderByRaw("CASE WHEN c_menu='loss_event_approvals' THEN 0 ELSE 1 END")
            ->value('i_id_menu');

        if (! $id) {
            // fallback by label
            $id = Trmenu::query()
                ->whereRaw("LOWER(COALESCE(n_menu,'')) LIKE ?", ['%loss event approval%'])
                ->value('i_id_menu');
        }

        $cached = $id ? (int) $id : 0;
        return $cached > 0 ? $cached : null;
    }

    /**
     * @param mixed $state array checkbox keys OR int mask
     */
    protected static function stateHasApprove($state): bool
    {
        if (is_array($state)) {
            return in_array('16', $state, true) || in_array(16, $state, true);
        }

        if (is_numeric($state)) {
            return (((int) $state) & 16) === 16;
        }

        return false;
    }

    protected static function isRiskApprovalMenu($menuId): bool
    {
        $menuId = (int) ($menuId ?? 0);
        $target = (int) (self::riskApprovalMenuId() ?? 0);
        return $menuId > 0 && $target > 0 && $menuId === $target;
    }

    protected static function isLossEventApprovalMenu($menuId): bool
    {
        $menuId = (int) ($menuId ?? 0);
        $target = (int) (self::lossEventApprovalMenuId() ?? 0);
        return $menuId > 0 && $target > 0 && $menuId === $target;
    }

    protected static function isSpecialApprovalMenu($menuId): bool
    {
        return self::isRiskApprovalMenu($menuId) || self::isLossEventApprovalMenu($menuId);
    }

    /**
     * Pastikan action A(16) ada pada state c_action (array versi CheckboxList).
     */
    protected static function ensureApproveAction(Set $set, Get $get): void
    {
        $state = $get('c_action');

        $arr = [];
        if (is_array($state)) {
            $arr = array_map('strval', $state);
        } elseif (is_numeric($state)) {
            $arr = self::maskToArray((int) $state);
        }

        if (! in_array('16', $arr, true)) {
            $arr[] = '16';
        }

        $arr = array_values(array_unique($arr));
        $set('c_action', $arr);
    }

    protected static function forceMenuToRiskApprovalIfNeeded(Set $set, Get $get): void
    {
        $menuId = (int) ($get('i_id_menu') ?? 0);

        if (! self::stateHasApprove($get('c_action'))) {
            return;
        }

        if (self::isSpecialApprovalMenu($menuId)) {
            return;
        }

        $forcedId = self::riskApprovalMenuId();
        if ($forcedId) {
            $set('i_id_menu', $forcedId);
        }
    }

    /**
     * @return array<int, string>
     */
    protected static function maskToArray(int $mask): array
    {
        $out = [];
        foreach (array_keys(self::ACTIONS) as $bit) {
            $b = (int) $bit;
            if (($mask & $b) === $b) {
                $out[] = (string) $bit;
            }
        }
        return $out;
    }

    /**
     * @param mixed $state
     */
    protected static function arrayToMask($state): int
    {
        if (is_numeric($state)) {
            return (int) $state;
        }

        if (! is_array($state)) {
            return 0;
        }

        $mask = 0;
        foreach ($state as $v) {
            if (is_numeric($v)) {
                $mask |= (int) $v;
            }
        }

        return $mask;
    }

    public static function configure(Schema $schema): Schema
    {
        $roleOptions = fn () => Trrole::query()
            ->orderBy('n_role')
            ->pluck('n_role', 'i_id_role')
            ->toArray();

        $menuOptions = fn () => Trmenu::query()
            ->orderBy('n_menu')
            ->pluck('n_menu', 'i_id_menu')
            ->toArray();

        return $schema
            ->columns(1)
            ->schema([
                Section::make('Role')
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        Select::make('i_id_role')
                            ->label('Role')
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->options($roleOptions)
                            ->disabled(fn ($record) => filled($record)),
                    ]),

                Section::make('Menu Permissions')
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        Repeater::make('items')
                            ->label('Items')
                            ->columnSpanFull()
                            ->addActionLabel('Add Menu')
                            ->reorderable(false)
                            ->cloneable(false)
                            ->collapsible(true)
                            ->compact()
                            ->minItems(1)
                            ->schema([
                                Hidden::make('i_id_rolemenu')->dehydrated(true),

                                Select::make('i_id_menu')
                                    ->label('Menu')
                                    ->required()
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->options($menuOptions)
                                    ->columnSpan(5)
                                    ->live()

                                    ->dehydrated(true)

                                    ->disabled(fn (Get $get): bool =>
                                        self::stateHasApprove($get('c_action'))
                                        && ! self::isSpecialApprovalMenu($get('i_id_menu'))
                                        && (bool) self::riskApprovalMenuId()
                                    )

                                    ->afterStateHydrated(function (Get $get, Set $set): void {
                                        $menuId = (int) ($get('i_id_menu') ?? 0);

                                        if (self::isSpecialApprovalMenu($menuId)) {
                                            self::ensureApproveAction($set, $get);
                                            return;
                                        }

                                        self::forceMenuToRiskApprovalIfNeeded($set, $get);
                                    })

                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        $menuId = (int) ($state ?? 0);

                                        if (self::isSpecialApprovalMenu($menuId)) {
                                            self::ensureApproveAction($set, $get);
                                            return;
                                        }

                                        self::forceMenuToRiskApprovalIfNeeded($set, $get);
                                    })

                                    ->dehydrateStateUsing(function ($state, Get $get) {
                                        $menuId = (int) ($state ?? 0);

                                        if (self::isSpecialApprovalMenu($menuId)) {
                                            return $state;
                                        }

                                        if (self::stateHasApprove($get('c_action'))) {
                                            return self::riskApprovalMenuId() ?? $state;
                                        }

                                        return $state;
                                    })

                                    ->helperText(function (Get $get): ?string {
                                        $menuId = (int) ($get('i_id_menu') ?? 0);

                                        if (self::isLossEventApprovalMenu($menuId)) {
                                            return "Menu 'Loss Event Approval' otomatis membutuhkan Approve (A/16).";
                                        }

                                        if (self::isRiskApprovalMenu($menuId)) {
                                            return "Menu 'Risk Approval' otomatis membutuhkan Approve (A/16).";
                                        }

                                        if (self::stateHasApprove($get('c_action'))) {
                                            return "Approve (A) memaksa menu ke 'Risk Approval'.";
                                        }

                                        return null;
                                    }),

                                CheckboxList::make('c_action')
                                    ->label('Action')
                                    ->options(self::ACTIONS)
                                    ->columns(5)
                                    ->gridDirection('row')
                                    ->live()
                                    ->columnSpan(5)
                                    ->helperText('C=Create, R=Read, U=Update, D=Delete, A=Approve')

                                    ->afterStateHydrated(function (CheckboxList $component, $state): void {
                                        if (is_array($state)) {
                                            return;
                                        }

                                        $mask = is_numeric($state) ? (int) $state : 0;
                                        $component->state(self::maskToArray($mask));
                                    })

                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        $menuId = (int) ($get('i_id_menu') ?? 0);

                                        if (self::isSpecialApprovalMenu($menuId)) {
                                            if (! self::stateHasApprove($state)) {
                                                self::ensureApproveAction($set, $get);
                                            }
                                            return;
                                        }

                                        if (self::stateHasApprove($state)) {
                                            $forcedId = self::riskApprovalMenuId();
                                            if ($forcedId) {
                                                $set('i_id_menu', $forcedId);
                                            }
                                        }
                                    })

                                    ->dehydrateStateUsing(fn ($state): int => self::arrayToMask($state)),

                                Toggle::make('f_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->columnSpan(2),
                            ])
                            ->columns(12),
                    ]),
            ]);
    }
}
