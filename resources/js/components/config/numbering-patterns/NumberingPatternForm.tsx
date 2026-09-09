import type { FormEvent, ReactNode } from 'react';
import { useMemo } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type NumberingSegmentFormValue = {
    type: string;
    value?: string;
    digit_length?: number | string;
};

export type NumberingPatternFormValues = {
    resource: string;
    segments: NumberingSegmentFormValue[];
    reset_yearly: boolean;
    is_active: boolean;
};

type NumberingPatternFormProps = {
    values: NumberingPatternFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    resourceOptions?: Array<{ id: string; label: string }>;
    segmentTypeOptions?: Array<{ id: string; label: string }>;
    resourceLocked?: boolean;
    onChange: (key: keyof NumberingPatternFormValues, value: string | boolean | NumberingSegmentFormValue[]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

function emptySegment(type = 'letters'): NumberingSegmentFormValue {
    if (type === 'sequence') {
        return { type, digit_length: 5 };
    }

    if (type === 'year') {
        return { type };
    }

    return { type, value: '' };
}

function buildPreview(segments: NumberingSegmentFormValue[]): string {
    const year = String(new Date().getFullYear());

    return segments
        .map((segment) => {
            if (segment.type === 'year') {
                return year;
            }

            if (segment.type === 'sequence') {
                const length = Math.min(10, Math.max(1, Number(segment.digit_length) || 5));

                return '1'.padStart(length, '0');
            }

            return segment.value ?? '';
        })
        .join('');
}

export function NumberingPatternForm({
    values,
    errors,
    processing,
    resourceOptions = [],
    segmentTypeOptions = [],
    resourceLocked = false,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: NumberingPatternFormProps) {
    const { t } = useTranslation();
    const preview = useMemo(() => buildPreview(values.segments), [values.segments]);

    const digitOptions = Array.from({ length: 10 }, (_, index) => {
        const value = String(index + 1);

        return { value, label: value };
    });

    const typeOptions = (segmentTypeOptions.length > 0
        ? segmentTypeOptions
        : [
              { id: 'letters', label: 'letters' },
              { id: 'symbols', label: 'symbols' },
              { id: 'year', label: 'year' },
              { id: 'sequence', label: 'sequence' },
          ]
    ).map((option) => ({
        value: option.id,
        label: t(`numberingPatterns.segmentTypes.${option.id}`, { defaultValue: option.label }),
    }));

    function updateSegment(index: number, patch: Partial<NumberingSegmentFormValue>) {
        const next = values.segments.map((segment, i) => {
            if (i !== index) {
                return segment;
            }

            if (patch.type) {
                return emptySegment(patch.type);
            }

            return { ...segment, ...patch };
        });

        onChange('segments', next);
    }

    function removeSegment(index: number) {
        onChange(
            'segments',
            values.segments.filter((_, i) => i !== index),
        );
    }

    function addSegment() {
        onChange('segments', [...values.segments, emptySegment('letters')]);
    }

    return (
        <FieldHelpScope table="numbering_patterns">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('numberingPatterns.resource')} htmlFor="resource" error={errors.resource} required>
                    {resourceLocked ? (
                        <Input id="resource" value={values.resource} disabled readOnly />
                    ) : (
                        <SearchableSelect
                            id="resource"
                            value={values.resource}
                            invalid={Boolean(errors.resource)}
                            onChange={(value) => onChange('resource', value)}
                            emptyLabel={t('common.select')}
                            options={resourceOptions.map((option) => ({
                                value: option.id,
                                label: t(`numberingPatterns.resources.${option.id}`, {
                                    defaultValue: option.label,
                                }),
                            }))}
                        />
                    )}
                </Field>

                <div className="space-y-3">
                    <div>
                        <p className="text-sm font-semibold text-ink">{t('numberingPatterns.segments')}</p>
                        <p className="text-xs text-ink-muted">{t('numberingPatterns.segmentsHint')}</p>
                    </div>

                    {errors.segments ? <p className="text-sm text-danger">{errors.segments}</p> : null}

                    <div className="space-y-2">
                        {values.segments.map((segment, index) => (
                            <div
                                key={`segment-${index}`}
                                className="flex flex-col gap-3 rounded-xl border border-line bg-canvas/40 p-3 sm:flex-row sm:items-end"
                            >
                                <Field
                                    label={t('numberingPatterns.segmentType')}
                                    htmlFor={`segment-type-${index}`}
                                    helpField="numbering_patterns.segment_type"
                                    error={errors[`segments.${index}.type`]}
                                    className="min-w-0 flex-1"
                                >
                                    <SearchableSelect
                                        id={`segment-type-${index}`}
                                        value={segment.type}
                                        onChange={(value) => updateSegment(index, { type: value })}
                                        emptyLabel={t('common.select')}
                                        options={typeOptions}
                                    />
                                </Field>

                                {segment.type === 'letters' || segment.type === 'symbols' ? (
                                    <Field
                                        label={
                                            segment.type === 'symbols'
                                                ? t('numberingPatterns.symbolValue')
                                                : t('numberingPatterns.lettersValue')
                                        }
                                        htmlFor={`segment-value-${index}`}
                                        helpField="numbering_patterns.segment_value"
                                        error={errors[`segments.${index}.value`]}
                                        required
                                        className="min-w-0 flex-1"
                                    >
                                        <Input
                                            id={`segment-value-${index}`}
                                            value={segment.value ?? ''}
                                            placeholder={
                                                segment.type === 'symbols'
                                                    ? t('numberingPatterns.symbolPlaceholder')
                                                    : t('numberingPatterns.lettersPlaceholder')
                                            }
                                            onChange={(event) => updateSegment(index, { value: event.target.value })}
                                        />
                                    </Field>
                                ) : null}

                                {segment.type === 'sequence' ? (
                                    <Field
                                        label={t('numberingPatterns.digitLength')}
                                        htmlFor={`segment-digits-${index}`}
                                        helpField="numbering_patterns.digit_length"
                                        error={errors[`segments.${index}.digit_length`]}
                                        required
                                        className="min-w-0 flex-1"
                                    >
                                        <SearchableSelect
                                            id={`segment-digits-${index}`}
                                            value={String(segment.digit_length ?? 5)}
                                            onChange={(value) =>
                                                updateSegment(index, { digit_length: Number(value) || 5 })
                                            }
                                            emptyLabel={t('common.select')}
                                            options={digitOptions}
                                        />
                                    </Field>
                                ) : null}

                                {segment.type === 'year' ? (
                                    <div className="min-w-0 flex-1 pb-2">
                                        <p className="text-sm text-ink-muted">{t('numberingPatterns.yearHint')}</p>
                                    </div>
                                ) : null}

                                <Button
                                    type="button"
                                    variant="secondary"
                                    className="shrink-0 border-danger/40 text-danger hover:border-danger hover:text-danger"
                                    onClick={() => removeSegment(index)}
                                    disabled={values.segments.length <= 1}
                                >
                                    <Trash2 className="size-3.5" aria-hidden />
                                    {t('common.delete')}
                                </Button>
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-end">
                        <Button type="button" variant="secondary" onClick={addSegment}>
                            <Plus className="size-3.5" aria-hidden />
                            {t('numberingPatterns.addSegment')}
                        </Button>
                    </div>
                </div>

                <div className="space-y-1">
                    <p className="text-sm font-semibold text-ink">{t('numberingPatterns.resetYearly')}</p>
                    <p className="text-xs text-ink-muted">{t('numberingPatterns.resetYearlyHint')}</p>
                    <Toggle
                        name="reset_yearly"
                        checked={values.reset_yearly}
                        onCheckedChange={(checked) => onChange('reset_yearly', checked)}
                        checkedLabel={t('numberingPatterns.resetYearlyOn')}
                        uncheckedLabel={t('numberingPatterns.resetYearlyOff')}
                    />
                </div>

                <div className="space-y-1">
                    <p className="text-sm font-semibold text-ink">{t('common.active')}</p>
                    <Toggle
                        name="is_active"
                        checked={values.is_active}
                        onCheckedChange={(checked) => onChange('is_active', checked)}
                        checkedLabel={t('common.active')}
                        uncheckedLabel={t('common.inactive')}
                    />
                </div>

                <div className="rounded-xl border border-dashed border-line bg-canvas/60 p-4">
                    <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        {t('numberingPatterns.preview')}
                    </p>
                    <p className="mt-1 font-mono text-lg font-semibold text-ink">{preview || t('common.emDash')}</p>
                    <p className="mt-1 text-xs text-ink-muted">{t('numberingPatterns.previewHint')}</p>
                </div>

                <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                    {actions}
                    <Button type="submit" loading={processing}>
                        {submitIcon}
                        {submitLabel}
                    </Button>
                </div>
            </form>
        </FieldHelpScope>
    );
}
