<?php

namespace App\Mail;

use App\Models\Deposit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DepositReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Deposit $deposit) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Deposit Berhasil - Eveeze Ads');
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
                <h1>Deposit Diterima</h1>
                <p>Halo, {$this->deposit->user->name}</p>
                <p>Deposit sebesar <strong>Rp " . number_format($this->deposit->amount) . "</strong> berhasil masuk.</p>
                <p>Order ID: {$this->deposit->order_id}</p>
            "
        );
    }
}