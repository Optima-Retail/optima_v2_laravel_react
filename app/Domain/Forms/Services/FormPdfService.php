<?php

declare(strict_types=1);

namespace App\Domain\Forms\Services;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSection;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a filled-in Form to a PDF report. Mirrors the block-by-section
 * layout legacy's FormularioPdfBlockBuilder/DocumentComposer produced, kept
 * as a single Blade pass here since the field-type set differs enough from
 * legacy's that a literal port of that split wasn't worth carrying over.
 *
 * Images/signatures are embedded as base64 data URIs rather than fetched
 * over HTTP — they live on a private disk that DomPDF's remote fetcher has
 * no session to authenticate through, so reading them server-side and
 * inlining the bytes is both simpler and more reliable.
 */
final class FormPdfService
{
    public function stream(Form $form): Response
    {
        $pdf = $this->render($form);

        return $pdf->stream($this->filename($form));
    }

    public function bytes(Form $form): string
    {
        return $this->render($form)->output();
    }

    private function render(Form $form): PdfDocument
    {
        $form->loadMissing([
            'sections.fields',
            'type',
            'status',
            'workOrder.establishment',
            'companyRelationship.relatedCompany',
            'user',
        ]);

        return Pdf::loadView('pdf.form', [
            'form' => $form,
            'sections' => $form->sections->map(fn (FormSection $section): array => [
                'label' => $section->label,
                'fields' => $section->fields
                    ->filter(fn (FormField $field): bool => $field->is_visible)
                    ->map(fn (FormField $field): ?array => $this->fieldRow($field))
                    ->filter()
                    ->values()
                    ->all(),
            ])->all(),
            'subjectLabel' => $form->subjectLabel(),
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans');
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldRow(FormField $field): array
    {
        return match ($field->type) {
            'imagen', 'firma' => [
                'kind' => 'image',
                'label' => $field->label,
                'image' => $this->dataUri($field->value),
            ],
            'materiales' => [
                'kind' => 'table',
                'label' => $field->label,
                'columns' => ['Material', 'Quantity'],
                'rows' => collect((array) ($field->payload['items'] ?? []))
                    ->map(fn ($item) => [$item['name'] ?? '', $item['quantity'] ?? ''])
                    ->all(),
            ],
            'tecnicos' => [
                'kind' => 'table',
                'label' => $field->label,
                'columns' => ['Technician', 'Hours'],
                'rows' => collect((array) ($field->payload['entries'] ?? []))
                    ->map(fn ($entry) => [$entry['name'] ?? '', $entry['hours'] ?? ''])
                    ->all(),
            ],
            'trabajo' => [
                'kind' => 'work',
                'label' => $field->label,
                'note' => $field->payload['note'] ?? '',
                'before' => $this->dataUri($field->payload['before'] ?? null),
                'after' => $this->dataUri($field->payload['after'] ?? null),
            ],
            'cabecera' => ['kind' => 'heading', 'label' => $field->label],
            'condicional', 'subformulario', 'nueva-incidencia' => null,
            default => ['kind' => 'text', 'label' => $field->label, 'value' => $field->value],
        };
    }

    private function dataUri(?string $path): ?string
    {
        if ($path === null || $path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/png';
        $contents = Storage::disk('local')->get($path);

        return 'data:'.$mime.';base64,'.base64_encode((string) $contents);
    }

    private function filename(Form $form): string
    {
        $code = $form->name ?: $form->public_id ?: ('form-'.$form->id);
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $code) ?: 'form';

        return $safe.'.pdf';
    }
}
