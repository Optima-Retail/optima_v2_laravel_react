import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ToastHost } from '@/components/feedback/ToastHost';
import { LocaleSync } from '@/components/i18n/LocaleSync';
import { LocaleSwitcher } from '@/components/navigation/LocaleSwitcher';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';

type PublicField = {
    id: number;
    type: string;
    label: string | null;
    value: string | null;
    is_required: boolean;
};

type PublicSection = {
    id: number;
    label: string | null;
    fields: PublicField[];
};

type PublicForm = {
    public_id: string;
    name: string | null;
    type_name: string | null;
    status_name: string | null;
    occurred_on: string | null;
    sections: PublicSection[];
};

type PublicProps = {
    form: PublicForm;
};

export default function PublicFormPage({ form }: PublicProps) {
    const { t } = useTranslation();
    const title = form.name || form.type_name || t('forms.resource');

    return (
        <div className="relative min-h-screen bg-canvas px-4 py-10">
            <div className="absolute right-4 top-4">
                <LocaleSwitcher />
            </div>
            <div className="mx-auto w-full max-w-3xl space-y-6">
                <Head title={title} />
                <div className="flex items-center gap-2">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-brand font-display text-lg font-bold text-white">
                        O
                    </span>
                    <span className="font-display text-xl font-semibold tracking-tight text-ink">Optima</span>
                </div>

                <div className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                    <div className="space-y-1">
                        <h1 className="font-display text-2xl font-semibold text-ink">{title}</h1>
                        <p className="text-sm text-ink-muted">
                            {[form.type_name, form.status_name, form.occurred_on].filter(Boolean).join(' · ')}
                        </p>
                    </div>

                    {form.sections.length === 0 ? (
                        <p className="text-sm text-ink-muted">{t('forms.fieldsEmpty')}</p>
                    ) : (
                        form.sections.map((section) => (
                            <div key={section.id} className="space-y-3 rounded-xl border border-line p-4">
                                <h2 className="text-sm font-semibold text-ink">
                                    {section.label || t('forms.untitledSection')}
                                </h2>
                                {section.fields.map((field) => (
                                    <Field
                                        key={field.id}
                                        label={`${field.label || field.type}${field.is_required ? ' *' : ''}`}
                                        htmlFor={`public-field-${field.id}`}
                                    >
                                        <Input
                                            id={`public-field-${field.id}`}
                                            value={field.value ?? ''}
                                            readOnly
                                            disabled
                                        />
                                    </Field>
                                ))}
                            </div>
                        ))
                    )}
                </div>
            </div>
            <LocaleSync />
            <ToastHost />
        </div>
    );
}
