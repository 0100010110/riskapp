<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Filament\Resources\BaseResource;
use App\Services\RolePermissionService;
use BackedEnum;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityLogResource extends BaseResource
{
    protected static ?string $model = Activity::class;

    protected static ?string $menuCode = 'log';

    protected static ?string $slug = 'log';

    protected static ?string $navigationLabel = 'Log';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 999;

    /**
     * Superadmin user 2542.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $uid = (int) ($user?->getAuthIdentifier() ?? 0);

        if ($uid === 2542) {
            return true;
        }

        return app(RolePermissionService::class)->isSuperuser($user);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
