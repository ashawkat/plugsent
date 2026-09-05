<?php

namespace App\Filament\Resources\Sites;

use App\Filament\Resources\Sites\Pages\AdminLogin;
use App\Filament\Resources\Sites\Pages\CreateSite;
use App\Filament\Resources\Sites\Pages\EditSite;
use App\Filament\Resources\Sites\Pages\ListSites;
use App\Filament\Resources\Sites\Pages\ViewSite;
use App\Filament\Resources\Sites\Schemas\SiteForm;
use App\Filament\Resources\Sites\Tables\SitesTable;
use App\Models\Site;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $tenantOwnershipRelationshipName = 'workspace';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        $tenant = Filament::getTenant();

        // Workspace admins/owners see everything. Regular members only see
        // sites in projects that are open or explicitly assigned to them.
        if ($user && $tenant && ! $user->isWorkspaceAdmin($tenant)) {
            $query->whereHas('project', fn (Builder $p) => $p->where(
                fn (Builder $q) => $q->whereDoesntHave('members')
                    ->orWhereHas('members', fn (Builder $m) => $m->whereKey($user->getKey())),
            ));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return SiteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SitesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSites::route('/'),
            'create' => CreateSite::route('/create'),
            'view' => ViewSite::route('/{record}'),
            'admin-login' => AdminLogin::route('/{record}/admin-login'),
            'edit' => EditSite::route('/{record}/edit'),
        ];
    }
}
