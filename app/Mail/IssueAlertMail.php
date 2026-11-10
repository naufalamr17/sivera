<?php

namespace App\Mail;

use App\Models\IssueAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IssueAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public IssueAlert $alert) {}

    public function envelope(): Envelope
    {
        $activity = $this->alert->type === 'status_changed'
            ? 'Status changed to '.$this->alert->status : 'Error event';

        return new Envelope(subject: '[SIVERA] '.$activity.' - '.$this->alert->issue->application->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.issue-alert', text: 'mail.issue-alert-text');
    }
}
