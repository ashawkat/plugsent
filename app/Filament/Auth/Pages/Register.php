<?php

namespace App\Filament\Auth\Pages;

use App\Actions\CreateWorkspaceForUser;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getWorkspaceNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getWorkspaceNameFormComponent(): Component
    {
        return TextInput::make('workspaceName')
            ->label('Workspace name')
            ->hint('Your company, agency, or team')
            ->required()
            ->maxLength(255);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[\SensitiveParameter] array $data): Model
    {
        $user = $this->getUserModel()::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        app(CreateWorkspaceForUser::class)($user, $data['workspaceName']);

        return $user;
    }
}
