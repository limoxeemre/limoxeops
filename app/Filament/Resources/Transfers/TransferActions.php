<?php

namespace App\Filament\Resources\Transfers;

use App\Enums\TransferStatus;
use App\Models\Driver;
use App\Models\Transfer;
use App\Models\Vehicle;
use App\Services\Reservations\TransferService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Transfer bacakları için operasyon aksiyonları. Operasyon panosu, rezervasyon detayı
 * ve dashboard widget'ı aynı aksiyonları kullanır.
 */
class TransferActions
{
    /** Satır aksiyonları: hızlı "Ata" butonu + durum menüsü */
    public static function row(): array
    {
        return [
            self::assign(),
            ActionGroup::make([
                self::status(TransferStatus::EnRoute, 'Yola çıktı', Heroicon::OutlinedTruck),
                self::status(TransferStatus::OnBoard, 'Yolcu alındı', Heroicon::OutlinedUsers),
                self::status(TransferStatus::Completed, 'Tamamlandı', Heroicon::OutlinedCheckCircle),
                self::status(TransferStatus::NoShow, 'Yolcu gelmedi', Heroicon::OutlinedUserMinus, requiresNote: true),
                self::editDetails(),
                self::unassign(),
                Action::make('navigate')->label('Yol tarifi')->icon(Heroicon::OutlinedMap)
                    ->url(fn (Transfer $record) => $record->google_maps_url, shouldOpenInNewTab: true),
                Action::make('whatsapp')->label('Yolcuya WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->url(fn (Transfer $record) => $record->reservation->whatsapp_url, shouldOpenInNewTab: true),
            ])->tooltip('İşlemler'),
        ];
    }

    public static function assign(): Action
    {
        return Action::make('assign')
            ->label(fn (Transfer $record) => $record->driver_id ? 'Değiştir' : 'Ata')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color(fn (Transfer $record) => $record->driver_id ? 'gray' : 'warning')
            ->button()
            ->size('sm')
            ->visible(fn (Transfer $record) => $record->status->isActive())
            ->modalHeading(fn (Transfer $record) => 'Sürücü ata · '.$record->pickup_at->format('d.m H:i').' · '.$record->reservation->code)
            ->modalDescription(fn (Transfer $record) => "{$record->pickup_address} → {$record->dropoff_address} · {$record->reservation->total_passengers} yolcu, {$record->reservation->luggage} bagaj · istenen: {$record->reservation->vehicleType->name}")
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Ata')
            ->fillForm(fn (Transfer $record) => ['driver_id' => $record->driver_id, 'vehicle_id' => $record->vehicle_id, 'driver_notes' => $record->driver_notes])
            ->schema(fn (Transfer $record) => [
                Select::make('driver_id')->label('Sürücü')->required()->searchable()->live()
                    ->options(fn () => self::driverOptions($record))
                    ->allowHtml()
                    ->afterStateUpdated(fn ($state, Set $set) => $set('vehicle_id', Vehicle::where('driver_id', $state)->value('id'))),
                Select::make('vehicle_id')->label('Araç')->searchable()
                    ->options(fn () => Vehicle::active()->with('vehicleType')
                        ->whereHas('vehicleType', fn ($query) => $query->where('max_passengers', '>=', $record->reservation->total_passengers))
                        ->get()
                        ->mapWithKeys(fn (Vehicle $v) => [$v->id => "{$v->plate} · {$v->brand_model} ({$v->vehicleType->name}, {$v->vehicleType->max_passengers} kişi)"]))
                    ->helperText('Yalnızca yolcu sayısına yeterli kapasitedeki araçlar listelenir.'),
                Text::make(fn (Get $get) => self::conflictWarning($record, $get('driver_id'))),
                Textarea::make('driver_notes')->label('Sürücüye not')->rows(2),
            ])
            ->action(function (Transfer $record, array $data) {
                $driver = Driver::findOrFail($data['driver_id']);
                $conflicts = app(TransferService::class)->conflictsFor($record, $driver);

                try {
                    app(TransferService::class)->assign($record, $driver, Vehicle::find($data['vehicle_id'] ?? null), auth()->user());
                    $record->update(['driver_notes' => $data['driver_notes'] ?? null]);
                } catch (ValidationException $e) {
                    Notification::make()->title('Atama yapılamadı')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();

                    return;
                }

                $notification = Notification::make()->title("{$driver->name} atandı");
                $conflicts->isNotEmpty()
                    ? $notification->warning()->body('Dikkat: sürücünün bu saatlerde başka işi var ('.$conflicts->map(fn ($t) => $t->pickup_at->format('H:i'))->implode(', ').').')
                    : $notification->success();
                $notification->send();
            });
    }

    /** Toplu atama: aynı sürücüye birden fazla transfer (ör. shuttle / aynı uçak) */
    public static function bulkAssign(): BulkAction
    {
        return BulkAction::make('bulkAssign')
            ->label('Sürücü ata')
            ->icon(Heroicon::OutlinedUserPlus)
            ->schema([
                Select::make('driver_id')->label('Sürücü')->options(Driver::active()->pluck('name', 'id'))->required()->searchable(),
            ])
            ->action(function (Collection $records, array $data) {
                $driver = Driver::with('vehicle.vehicleType')->findOrFail($data['driver_id']);
                $ok = 0;
                $failed = [];
                foreach ($records->load('reservation') as $transfer) {
                    try {
                        app(TransferService::class)->assign($transfer, $driver, null, auth()->user());
                        $ok++;
                    } catch (ValidationException $e) {
                        $failed[] = $transfer->reservation->code;
                    }
                }

                Notification::make()
                    ->title("{$ok} transfer {$driver->name} sürücüsüne atandı")
                    ->body($failed ? 'Atanamayanlar: '.implode(', ', $failed) : null)
                    ->{$failed ? 'warning' : 'success'}()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function status(TransferStatus $to, string $label, Heroicon $icon, bool $requiresNote = false): Action
    {
        $action = Action::make('status_'.$to->value)
            ->label($label)
            ->icon($icon)
            ->color($to->getColor())
            ->visible(fn (Transfer $record) => $record->status->canTransitionTo($to))
            ->action(function (Transfer $record, array $data) use ($to) {
                try {
                    app(TransferService::class)->changeStatus($record, $to, auth()->user(), $data['note'] ?? null);
                    Notification::make()->title($to->getLabel())->success()->send();
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                }
            });

        return $requiresNote
            ? $action->schema([Textarea::make('note')->label('Açıklama')->required()->placeholder('45 dk beklendi, yolcuya ulaşılamadı…')])
            : $action->requiresConfirmation(false);
    }

    public static function cancel(): Action
    {
        return self::status(TransferStatus::Cancelled, 'Bu bacağı iptal et', Heroicon::OutlinedXCircle, requiresNote: true);
    }

    public static function unassign(): Action
    {
        return Action::make('unassign')
            ->label('Atamayı kaldır')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Transfer $record) => $record->driver_id && $record->status === TransferStatus::Assigned)
            ->action(fn (Transfer $record) => app(TransferService::class)->unassign($record, auth()->user()));
    }

    public static function editDetails(): Action
    {
        return Action::make('editDetails')
            ->label('Saat / uçuş düzenle')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Transfer $record) => $record->status->isActive())
            ->fillForm(fn (Transfer $record) => $record->only('pickup_at', 'flight_number', 'flight_time', 'pickup_address', 'dropoff_address', 'driver_notes'))
            ->schema([
                DateTimePicker::make('pickup_at')->label('Alış saati')->required()->seconds(false)->native(false)->displayFormat('d.m.Y H:i'),
                TextInput::make('flight_number')->label('Uçuş no'),
                DateTimePicker::make('flight_time')->label('Uçuş saati')->seconds(false)->native(false)->displayFormat('d.m.Y H:i'),
                TextInput::make('pickup_address')->label('Alış adresi')->required(),
                TextInput::make('dropoff_address')->label('Bırakış adresi')->required(),
                Textarea::make('driver_notes')->label('Sürücüye not')->rows(2),
            ])
            ->action(function (Transfer $record, array $data) {
                $record->update($data);
                Notification::make()->title('Transfer güncellendi')->success()->send();
            });
    }

    private static function driverOptions(Transfer $transfer): array
    {
        $service = app(TransferService::class);

        return Driver::active()->with('vehicle.vehicleType')->orderBy('name')->get()
            ->mapWithKeys(function (Driver $driver) use ($transfer, $service) {
                $conflicts = $service->conflictsFor($transfer, $driver);
                $vehicle = $driver->vehicle ? " · {$driver->vehicle->plate} ({$driver->vehicle->vehicleType->name})" : ' · araç yok';
                $state = $conflicts->isEmpty()
                    ? '<span style="color:var(--success-600)">● müsait</span>'
                    : '<span style="color:var(--danger-600)">● '.$conflicts->map(fn ($t) => $t->pickup_at->format('H:i'))->implode(', ').' meşgul</span>';

                return [$driver->id => e($driver->name).e($vehicle).' &nbsp; '.$state];
            })
            ->all();
    }

    private static function conflictWarning(Transfer $transfer, $driverId): ?HtmlString
    {
        if (! $driverId || ! ($driver = Driver::find($driverId))) {
            return null;
        }

        $conflicts = app(TransferService::class)->conflictsFor($transfer, $driver);
        if ($conflicts->isEmpty()) {
            return new HtmlString('<span class="tr-badge success">✓ Sürücü bu saat aralığında müsait</span>');
        }

        $list = $conflicts->load('reservation')->map(fn (Transfer $t) => e("{$t->pickup_at->format('H:i')} {$t->reservation->code}: {$t->pickup_address} → {$t->dropoff_address}"))->implode('<br>');

        return new HtmlString('<span class="tr-badge danger">⚠ Çakışan iş var</span><div style="font-size:.8125rem;margin-top:.35rem;color:var(--gray-500)">'.$list.'</div>');
    }
}
