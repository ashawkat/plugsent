<?php

namespace App\Filament\Pages;

use App\Actions\IssuePairingCode;
use App\Models\Project;
use App\Models\Site;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ConnectSite extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Connect site';

    protected ?string $heading = 'Connect a WordPress site';

    public ?array $data = [];

    public ?string $pairingCode = null;

    public ?string $expiresAt = null;

    protected string $view = 'filament.pages.connect-site';

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Site name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('url')
                    ->label('Site URL')
                    ->required()
                    ->url()
                    ->maxLength(255),
                Select::make('project_id')
                    ->label('Project')
                    ->options(
                        fn (): array => Project::query()
                            ->where('workspace_id', Filament::getTenant()?->getKey())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all(),
                    )
                    ->required()
                    ->native(false),
            ])
            ->columns(2);
    }

    public function connect(): void
    {
        $data = $this->form->getState();

        $site = Site::query()->create([
            'workspace_id' => Filament::getTenant()->getKey(),
            'project_id' => $data['project_id'],
            'name' => $data['name'],
            'url' => $data['url'],
            'status' => 'pending',
        ]);

        $pairing = app(IssuePairingCode::class)($site);

        $this->pairingCode = $pairing['code'];
        $this->expiresAt = $pairing['expires_at']->format('H:i');

        Notification::make()
            ->title('Site registered — pairing code ready')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        $components = [$this->getFormContentComponent()];

        if (filled($this->pairingCode)) {
            $components[] = Section::make('Finish pairing on the WordPress site')
                ->description('Valid for 15 minutes, single use. The site flips to Connected automatically on its first check-in.')
                ->components([
                    View::make('filament.pages.connect-site-instructions'),
                ]);
        }

        return $schema->components($components);
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('connect')
            ->footer([
                Actions::make($this->getFormActions())
                    ->fullWidth(),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('connect')
                ->label($this->pairingCode ? 'Generate a new pairing code' : 'Generate pairing code')
                ->submit('connect'),
        ];
    }
}
