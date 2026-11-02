<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Acente / sürücü için Sanctum API token'ı üretir. Token yalnızca bir kez gösterilir.
 */
class GenerateApiTokenAction
{
    public static function make(): Action
    {
        return Action::make('generateToken')
            ->label('API Token')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->modalHeading('Yeni API token oluştur')
            ->modalDescription('Token yalnızca bir kez gösterilir; güvenli bir yerde saklayın.')
            ->modalSubmitActionLabel('Oluştur')
            ->schema([
                TextInput::make('name')->label('Token adı')->default('default')->required()->maxLength(50),
                Toggle::make('revoke_existing')->label('Mevcut token\'ları iptal et')->default(false),
            ])
            ->action(function (Model $record, array $data) {
                if ($data['revoke_existing']) {
                    $record->tokens()->delete();
                }

                $token = $record->createToken($data['name'])->plainTextToken;

                Notification::make()
                    ->title('API token oluşturuldu')
                    ->body("Bearer token:\n{$token}")
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
