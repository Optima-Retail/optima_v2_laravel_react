<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Forms\Enums\FormSubjectType;
use App\Domain\Forms\Events\FormCompleted;
use App\Domain\Forms\Services\FormPdfService;
use App\Mail\FormCompletedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Notifies the work order's responsible user when a linked Form completes.
 *
 * There is no equivalent notification for technician-subject forms yet — a
 * technician's "responsable" contact from legacy has no confirmed analog on
 * CompanyRelationship in this app, so guessing at a recipient there would be
 * inventing a rule rather than porting one. Left for a follow-up once that's
 * defined.
 */
final class SendFormCompletedNotification implements ShouldQueue
{
    public function __construct(
        private readonly FormPdfService $pdf,
    ) {}

    public function handle(FormCompleted $event): void
    {
        $form = $event->form;

        if ($form->subject_type !== FormSubjectType::WorkOrder) {
            return;
        }

        $form->loadMissing('workOrder.responsibleUser');
        $recipient = $form->workOrder?->responsibleUser;

        if ($recipient === null || blank($recipient->email)) {
            return;
        }

        Mail::to($recipient->email)->send(
            new FormCompletedMail($form, $this->pdf->bytes($form)),
        );
    }
}
