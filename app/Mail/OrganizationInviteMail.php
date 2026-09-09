<?php

namespace App\Mail;

use App\Models\OrganizationInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public OrganizationInvite $invite) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->invite->organization->name} on Zerrors",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-invite',
            with: [
                'organizationName' => $this->invite->organization->name,
                'inviterName' => $this->invite->inviter?->name,
                'role' => $this->invite->role,
                'acceptUrl' => route('invites.accept', $this->invite->token),
                'expiresAt' => $this->invite->expires_at,
            ],
        );
    }
}
