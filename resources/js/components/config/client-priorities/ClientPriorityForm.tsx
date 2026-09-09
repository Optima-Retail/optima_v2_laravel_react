import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type ClientPriorityFormValues = {
    name: string;
    code: string;
    color: string;
    level: number | string;
};

type ClientPriorityFormProps = {
    mode: 'create' | 'edit';
    values: ClientPriorityFormValues;
    errors: Partial<Record<keyof ClientPriorityFormValues, string>>;
    processing: boolean;
    onChange: (key: keyof ClientPriorityFormValues, value: string) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function ClientPriorityForm({
    values,
    errors,
    processing,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: ClientPriorityFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="client_priorities">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value)}
                    />
                </Field>

                <Field label={t('common.code')} htmlFor="code" error={errors.code}>
                    <Input
                        id="code"
                        value={values.code}
                        placeholder={t('clientPriorities.codePlaceholder')}
                        invalid={Boolean(errors.code)}
                        onChange={(event) => onChange('code', event.target.value)}
                    />
                </Field>

                <Field label={t('common.color')} htmlFor="color" error={errors.color}>
                    <div className="flex items-center gap-3">
                        <input
                            id="color"
                            type="color"
                            value={values.color || '#2563eb'}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="size-8 cursor-pointer rounded-lg border border-line bg-surface p-0.5"
                        />
                        <Input
                            value={values.color}
                            placeholder={t('clientPriorities.colorPlaceholder')}
                            invalid={Boolean(errors.color)}
                            onChange={(event) => onChange('color', event.target.value)}
                            className="flex-1"
                        />
                    </div>
                </Field>

                <Field label={t('common.level')} htmlFor="level" error={errors.level} required>
                    <Input
                        id="level"
                        type="number"
                        min={1}
                        max={10}
                        value={String(values.level)}
                        invalid={Boolean(errors.level)}
                        onChange={(event) => onChange('level', event.target.value)}
                    />
                    <p className="text-xs text-ink-muted">{t('clientPriorities.levelHint')}</p>
                </Field>

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
