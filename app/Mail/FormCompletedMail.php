<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class FormCompletedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Form $form,
        private readonly string $pdfBytes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Form completed: '.($this->form->name ?: $this->form->subjectLabel()),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mails.forms.completed',
            with: [
                'form' => $this->form,
                'url' => url('/forms/'.$this->form->id.'/edit'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => $this->pdfBytes,
                ($this->form->name ?: 'form-'.$this->form->id).'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
