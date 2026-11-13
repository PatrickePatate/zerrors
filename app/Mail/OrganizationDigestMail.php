<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class OrganizationDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $projects  Per-project summaries built by zerrors:send-digests.
     */
    public function __construct(
        public Organization $organization,
        public string $frequency,
        public Carbon $periodStart,
        public Carbon $periodEnd,
        public array $projects,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->frequency === 'weekly' ? 'weekly' : 'daily';

        return new Envelope(subject: "Your {$label} Zerrors digest for {$this->organization->name}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-digest',
            with: [
                'organizationName' => $this->organization->name,
                'frequency' => $this->frequency,
                'periodStart' => $this->periodStart,
                'periodEnd' => $this->periodEnd,
                'projects' => $this->projects,
            ],
        );
    }
}
