<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Mail\ReservationConfirmation;
use App\Services\Reservations\ReservationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->code;
    }

    public function getSubheading(): ?string
    {
        $r = $this->getRecord();

        return $r->passenger_name.' · '.$r->trip_type->getLabel().' · '.$r->vehicleType->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')->label('Onayla')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                ->visible(fn () => $this->getRecord()->status === ReservationStatus::Pending)
                ->requiresConfirmation()
                ->action(function () {
                    app(ReservationService::class)->confirm($this->getRecord());
                    Notification::make()->title('Rezervasyon onaylandı')->success()->send();
                }),
            EditAction::make(),
            ActionGroup::make([
                Action::make('call')->label('Yolcuyu ara')->icon(Heroicon::OutlinedPhone)
                    ->url(fn () => 'tel:'.preg_replace('/[^0-9+]/', '', $this->getRecord()->passenger_phone)),
                Action::make('whatsapp')->label('WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->url(fn () => $this->getRecord()->whatsapp_url, shouldOpenInNewTab: true),
                Action::make('resendMail')->label('Onay e-postasını tekrar gönder')->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()
                    ->action(function () {
                        Mail::to($this->getRecord()->passenger_email)->queue(new ReservationConfirmation($this->getRecord()));
                        Notification::make()->title('E-posta kuyruğa alındı')->success()->send();
                    }),
                Action::make('cancel')->label('Rezervasyonu iptal et')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn () => $this->getRecord()->status->isOpen())
                    ->requiresConfirmation()
                    ->modalDescription('Tüm transfer bacakları iptal edilir ve atanmış sürücüler serbest kalır.')
                    ->schema([Textarea::make('reason')->label('İptal nedeni')->required()])
                    ->action(function (array $data) {
                        app(ReservationService::class)->cancel($this->getRecord(), $data['reason'], auth()->user());
                        Notification::make()->title('Rezervasyon iptal edildi')->success()->send();
                        $this->refreshFormData(['status']);
                    }),
            ])->label('Diğer')->button()->color('gray'),
        ];
    }
}
