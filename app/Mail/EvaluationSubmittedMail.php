<?php

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To eMotion staff: a complete evaluation has just arrived through the live webhook. */
class EvaluationSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Submission $submission)
    {
        $this->submission->loadMissing(['hospital', 'form', 'branch']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New evaluation: '.$this->submission->hospital->name.' · '.$this->submission->form->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.evaluation-submitted');
    }
}
