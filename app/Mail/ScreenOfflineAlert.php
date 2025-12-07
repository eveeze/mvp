<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ScreenOfflineAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $screens) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ALERT: Layar Offline Deteksi!');
    }

    public function content(): Content
    {
        $list = $this->screens->map(fn($s) => "<li>{$s->name} ({$s->code})</li>")->join('');
        return new Content(
            htmlString: "
                <h1>Alert System</h1>
                <p>Layar berikut mati > 1 jam:</p>
                <ul>{$list}</ul>
            "
        );
    }
}