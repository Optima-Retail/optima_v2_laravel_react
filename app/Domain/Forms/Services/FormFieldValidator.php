<?php

declare(strict_types=1);

namespace App\Domain\Forms\Services;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSection;
use Illuminate\Support\Collection;

/**
 * Per-field-type completeness rules enforced before a Form is allowed to
 * advance to its next status — the filling-side counterpart to the
 * type-conditional authoring rules already enforced on FormTemplate.
 *
 * Structural types (cabecera, condicional, subformulario, nueva-incidencia)
 * carry no answerable value in this system and are never checked. A field
 * hidden by its own `is_visible` flag, or by a conditional_field_id whose
 * target field doesn't currently match `payload.value`, is skipped too —
 * an answer can't be required for a question the technician never sees.
 */
final class FormFieldValidator
{
    private const MAX_TECHNICIANS = 50;

    /**
     * @return array<string, string>
     */
    public function validate(Form $form): array
    {
        $form->loadMissing('sections.fields');

        $fieldValues = $form->sections
            ->flatMap(fn (FormSection $section) => $section->fields)
            ->pluck('value', 'id');

        $errors = [];

        foreach ($form->sections->values() as $sectionIndex => $section) {
            foreach ($section->fields->values() as $fieldIndex => $field) {
                if (! $this->isVisible($field, $fieldValues)) {
                    continue;
                }

                $path = "sections.{$sectionIndex}.fields.{$fieldIndex}";
                $error = $this->validateField($field);

                if ($error !== null) {
                    $errors[$path.'.'.$error['key']] = $error['message'];
                }
            }
        }

        return $errors;
    }

    /**
     * @return array{key: string, message: string}|null
     */
    private function validateField(FormField $field): ?array
    {
        return match ($field->type) {
            'texto', 'nota', 'fecha', 'fecha-hora', 'si-no', 'imagen', 'firma' => $this->validateRequiredValue($field),
            'numero' => $this->validateNumero($field),
            'materiales' => $this->validateMateriales($field),
            'tecnicos' => $this->validateTecnicos($field),
            'trabajo' => $this->validateTrabajo($field),
            default => null,
        };
    }

    /**
     * @return array{key: string, message: string}|null
     */
    private function validateRequiredValue(FormField $field): ?array
    {
        if ($field->is_required && trim((string) $field->value) === '') {
            return ['key' => 'value', 'message' => 'This field is required.'];
        }

        return null;
    }

    /**
     * @return array{key: string, message: string}|null
     */
    private function validateNumero(FormField $field): ?array
    {
        $value = trim((string) $field->value);

        if ($field->is_required && $value === '') {
            return ['key' => 'value', 'message' => 'This field is required.'];
        }

        if ($value !== '' && ! is_numeric($value)) {
            return ['key' => 'value', 'message' => 'This field must be a number.'];
        }

        return null;
    }

    /**
     * @return array{key: string, message: string}|null
     */
    private function validateMateriales(FormField $field): ?array
    {
        $items = (array) ($field->payload['items'] ?? []);

        if (! $field->is_required) {
            return null;
        }

        if ($items === []) {
            return ['key' => 'payload', 'message' => 'Add at least one material.'];
        }

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $quantity = $item['quantity'] ?? null;

            if ($name === '' || $quantity === null || $quantity === '' || ! is_numeric($quantity)) {
                return ['key' => 'payload', 'message' => 'Every material needs a name and a numeric quantity.'];
            }
        }

        return null;
    }

    /**
     * @return array{key: string, message: string}|null
     */
    private function validateTecnicos(FormField $field): ?array
    {
        $entries = (array) ($field->payload['entries'] ?? []);

        if (! $field->is_required) {
            return null;
        }

        if ($entries === []) {
            return ['key' => 'payload', 'message' => 'Add at least one technician.'];
        }

        if (count($entries) > self::MAX_TECHNICIANS) {
            return ['key' => 'payload', 'message' => 'No more than '.self::MAX_TECHNICIANS.' technicians are allowed.'];
        }

        foreach ($entries as $entry) {
            if (trim((string) ($entry['name'] ?? '')) === '') {
                return ['key' => 'payload', 'message' => 'Every technician needs a name.'];
            }
        }

        return null;
    }

    /**
     * A work item is checked whenever any of its three parts has been started,
     * regardless of the field's own is_required flag — mirrors legacy, which
     * treats a partially-filled work item as always invalid.
     *
     * @return array{key: string, message: string}|null
     */
    private function validateTrabajo(FormField $field): ?array
    {
        $payload = $field->payload ?? [];
        $note = trim((string) ($payload['note'] ?? ''));
        $before = $payload['before'] ?? null;
        $after = $payload['after'] ?? null;

        $started = $note !== '' || $before !== null || $after !== null;

        if (! $started && ! $field->is_required) {
            return null;
        }

        if ($note === '' || $before === null || $after === null) {
            return ['key' => 'payload', 'message' => 'A work item needs a note and both before/after photos.'];
        }

        return null;
    }

    /**
     * @param  Collection<int, string|null>  $fieldValues
     */
    private function isVisible(FormField $field, $fieldValues): bool
    {
        if (! $field->is_visible) {
            return false;
        }

        if ($field->conditional_field_id === null) {
            return true;
        }

        $expected = $field->payload['value'] ?? null;

        if ($expected === null || $expected === '') {
            return true;
        }

        $actual = $fieldValues->get($field->conditional_field_id);

        return (string) $actual === (string) $expected;
    }
}
