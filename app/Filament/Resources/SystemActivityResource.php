<?php

namespace App\Filament\Resources;

use App\Support\SystemAccess;
use Illuminate\Database\Eloquent\Model;

class SystemActivityResource extends \MrAdder\FilamentLogger\Resources\ActivityResource
{
    protected static ?string $slug = 'system/activities';
    public static function getNavigationLabel(): string { return 'Audit Aktivitas'; }
    public static function getNavigationGroup(): ?string { return 'Sistem'; }
    public static function canViewAny(): bool { return SystemAccess::allowed(); }
    public static function canView(Model $record): bool { return SystemAccess::allowed(); }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
    public static function getPages(): array
    {
        return ['index' => SystemActivityResource\Pages\ListActivities::route('/'),
            'view' => SystemActivityResource\Pages\ViewActivity::route('/{record}')];
    }
}
