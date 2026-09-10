import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import { cn } from '@/support/cn';
import type { TaskDocumentTypeOption } from '@/support/types/domain/task-to-perform';

export type TaskToPerformFormValues = {
    title: string;
    description: string;
    is_completed: boolean;
    document_type: string;
    document_id: string;
};

type TaskToPerformFormProps = {
    mode: 'create' | 'edit';
    values: TaskToPerformFormValues;
    errors: Partial<Record<keyof TaskToPerformFormValues, string>>;
    processing: boolean;
    documentTypeOptions: TaskDocumentTypeOption[];
    onChange: (key: keyof TaskToPerformFormValues, value: string | boolean) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function TaskToPerformForm({
    values,
    errors,
    processing,
    documentTypeOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: TaskToPerformFormProps) {
    const { t } = useTranslation();

    return (
        <FieldHelpScope table="tasks_to_perform">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <Field label={t('common.title')} htmlFor="title" error={errors.title}>
                    <Input
                        id="title"
                        value={values.title}
                        invalid={Boolean(errors.title)}
                        onChange={(event) => onChange('title', event.target.value)}
                    />
                </Field>

                <Field label={t('common.description')} htmlFor="description" error={errors.description}>
                    <textarea
                        id="description"
                        rows={4}
                        value={values.description}
                        onChange={(event) => onChange('description', event.target.value)}
                        className={cn(
                            'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition',
                            'placeholder:text-ink-muted/70',
                            'focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20',
                            errors.description
                                ? 'border-danger focus:border-danger focus:ring-danger/20'
                                : 'border-line',
                        )}
                    />
                </Field>

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field
                        label={t('tasksToPerform.documentType')}
                        htmlFor="document_type"
                        error={errors.document_type}
                        required
                    >
                        <Select
                            id="document_type"
                            value={values.document_type}
                            invalid={Boolean(errors.document_type)}
                            onChange={(event) => onChange('document_type', event.target.value)}
                        >
                            <option value="">{t('common.select')}</option>
                            {documentTypeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {t(`tasksToPerform.documentTypes.${option.value}`, {
                                        defaultValue: option.label,
                                    })}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field label={t('tasksToPerform.documentId')} htmlFor="document_id" error={errors.document_id}>
                        <Input
                            id="document_id"
                            type="number"
                            min={1}
                            value={values.document_id}
                            invalid={Boolean(errors.document_id)}
                            placeholder={t('tasksToPerform.documentIdPlaceholder')}
                            onChange={(event) => onChange('document_id', event.target.value)}
                        />
                    </Field>
                </div>

                <Field label={t('tasksToPerform.isCompleted')} htmlFor="is_completed" error={errors.is_completed}>
                    <Toggle
                        id="is_completed"
                        name="is_completed"
                        checked={values.is_completed}
                        onCheckedChange={(checked) => onChange('is_completed', checked)}
                        checkedLabel={t('tasksToPerform.completed')}
                        uncheckedLabel={t('tasksToPerform.pending')}
                    />
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
