<?php

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To a hospital's admins: an evaluation was approved and is now on their dashboard. */
class EvaluationPublishedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Submission $submission)
    {
        $this->submission->loadMissing(['hospital', 'form', 'branch']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Evaluation published: '.$this->submission->form->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.evaluation-published');
    }
}
