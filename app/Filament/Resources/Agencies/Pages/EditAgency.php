<?php

namespace App\Filament\Resources\Agencies\Pages;

use App\Filament\Resources\Agencies\AgencyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
