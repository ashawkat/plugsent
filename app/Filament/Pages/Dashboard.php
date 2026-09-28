<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getColumns(): array|int
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }
}
