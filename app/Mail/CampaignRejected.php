<?php

namespace App\Mail;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Campaign $campaign, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kampanye Ditolak - Eveeze Ads');
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
                <h1>Kampanye Ditolak</h1>
                <p>Halo, {$this->campaign->user->name}</p>
                <p>Kampanye <strong>{$this->campaign->name}</strong> ditolak.</p>
                <p>Alasan: {$this->reason}</p>
                <p>Dana telah dikembalikan (Refund).</p>
            "
        );
    }
}