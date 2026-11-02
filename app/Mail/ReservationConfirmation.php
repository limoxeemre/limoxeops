<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Transfer Rezervasyonunuz · {$this->reservation->code}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.reservation-confirmation', with: [
            'reservation' => $this->reservation->loadMissing('transfers', 'vehicleType', 'extras.extra'),
        ]);
    }
}
