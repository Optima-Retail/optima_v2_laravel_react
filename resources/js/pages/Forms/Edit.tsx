import { FormEvent, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Copy, Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { confirmAction } from '@/helpers/confirm';
import { copyText } from '@/helpers/clipboard';
import { AppLayout } from '@/layouts/AppLayout';
import { formsService } from '@/services';
import { APP_PLATFORM_OPTIONS } from '@/support/types/domain/app-platform';
import { useToastStore } from '@/stores/toastStore';

type Option = { id: number; label: string };

type FormFieldRow = {
    id: number;
    sort_order: number;
    type: string;
    label: string | null;
    value: string | null;
    placeholder: string | null;
    is_required: boolean;
    is_visible: boolean;
    is_locked: boolean;
};

type FormSectionRow = {
    id: number;
    sort_order: number;
    label: string | null;
    fields: FormFieldRow[];
};

type FormData = {
    id: number;
    public_id: string;
    public_url?: string;
    name: string | null;
    form_type_id: number | null;
    form_status_id: number | null;
    language_id: number | null;
    form_template_id: number | null;
    subject_type: string;
    work_order_id: number | null;
    company_relationship_id: number | null;
    occurred_on: string | null;
    app_platform_id: number;
    technician_code: string | null;
    subject_label: string;
    type_name: string | null;
    status_name: string | null;
    next_status_id: number | null;
    sections: FormSectionRow[];
};

type EditProps = {
    form: FormData;
    typeOptions: Option[];
    statusOptions: Option[];
    templateOptions: Option[];
    workOrderOptions: Option[];
    technicianOptions: Option[];
    languageOptions: Option[];
    can: { delete: boolean };
};

export default function EditForm({
    form: formRecord,
    typeOptions,
    statusOptions,
    workOrderOptions,
    technicianOptions,
    languageOptions,
    can,
}: EditProps) {
    const { t } = useTranslation();
    const pushToast = useToastStore((state) => state.push);
    const [copyingLink, setCopyingLink] = useState(false);
    const displayName = [formRecord.type_name, formRecord.name || formRecord.subject_label]
        .filter(Boolean)
        .join(' · ') || `#${formRecord.id}`;

    const publicUrl = formRecord.public_url || formsService.publicUrl(formRecord.public_id);

    async function copyPublicLink() {
        setCopyingLink(true);
        const ok = await copyText(publicUrl);
        pushToast(
            ok ? t('forms.publicLinkCopied') : t('forms.publicLinkCopyFailed'),
            ok ? 'success' : 'error',
        );
        setCopyingLink(false);
    }

    const form = useForm({
        name: formRecord.name ?? '',
        form_type_id: formRecord.form_type_id ? String(formRecord.form_type_id) : '',
        form_status_id: formRecord.form_status_id ? String(formRecord.form_status_id) : '',
        language_id: formRecord.language_id ? String(formRecord.language_id) : '',
        subject_type: formRecord.subject_type,
        work_order_id: formRecord.work_order_id ? String(formRecord.work_order_id) : '',
        company_relationship_id: formRecord.company_relationship_id
            ? String(formRecord.company_relationship_id)
            : '',
        occurred_on: formRecord.occurred_on ?? '',
        app_platform_id: String(formRecord.app_platform_id || 1),
        technician_code: formRecord.technician_code ?? '',
        sections: formRecord.sections.map((section) => ({
            id: section.id,
            sort_order: section.sort_order,
            label: section.label ?? '',
            fields: section.fields.map((field) => ({
                id: field.id,
                sort_order: field.sort_order,
                type: field.type,
                label: field.label ?? '',
                value: field.value ?? '',
                placeholder: field.placeholder ?? '',
                is_required: field.is_required,
                is_visible: field.is_visible,
                is_locked: field.is_locked,
            })),
        })),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        formsService.update(formRecord.id, form);
    }

    async function destroyForm() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('forms.resource') }),
            message: t('common.deleteMessage', { name: displayName }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        formsService.destroy(formRecord.id);
    }

    function updateFieldValue(sectionIndex: number, fieldIndex: number, value: string) {
        const sections = [...form.data.sections];
        const fields = [...sections[sectionIndex].fields];
        fields[fieldIndex] = { ...fields[fieldIndex], value };
        sections[sectionIndex] = { ...sections[sectionIndex], fields };
        form.setData('sections', sections);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('forms.resource') })}>
            <Head title={t('common.editItem', { name: displayName })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('forms.title')}
                    title={t('common.editResource', { resource: t('forms.resource') })}
                    description={t('common.updateDetails', { name: displayName })}
                    backHref={formsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('forms.resourcePlural') })}
                />

                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label={t('common.name')} htmlFor="name" error={form.errors.name} className="sm:col-span-2">
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                />
                            </Field>

                            <Field label={t('forms.publicId')} htmlFor="public_id" className="sm:col-span-2">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <Input id="public_id" value={formRecord.public_id} readOnly className="flex-1" />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={copyPublicLink}
                                        loading={copyingLink}
                                        className="shrink-0"
                                    >
                                        <Copy className="size-4" aria-hidden />
                                        {t('forms.copyPublicLink')}
                                    </Button>
                                </div>
                                <p className="mt-1.5 truncate text-xs text-ink-muted" title={publicUrl}>
                                    {publicUrl}
                                </p>
                            </Field>

                            <Field label={t('forms.status')} htmlFor="form_status_id" error={form.errors.form_status_id}>
                                <Select
                                    id="form_status_id"
                                    value={form.data.form_status_id}
                                    onChange={(event) => form.setData('form_status_id', event.target.value)}
                                >
                                    <option value="">{t('common.select')}</option>
                                    {statusOptions.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {option.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label={t('forms.type')} htmlFor="form_type_id" error={form.errors.form_type_id}>
                                <Select
                                    id="form_type_id"
                                    value={form.data.form_type_id}
                                    onChange={(event) => form.setData('form_type_id', event.target.value)}
                                >
                                    <option value="">{t('common.select')}</option>
                                    {typeOptions.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {option.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label={t('forms.language')} htmlFor="language_id" error={form.errors.language_id}>
                                <Select
                                    id="language_id"
                                    value={form.data.language_id}
                                    onChange={(event) => form.setData('language_id', event.target.value)}
                                >
                                    <option value="">{t('common.select')}</option>
                                    {languageOptions.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {option.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <Field label={t('forms.subjectType')} htmlFor="subject_type" error={form.errors.subject_type} required>
                                <Select
                                    id="subject_type"
                                    value={form.data.subject_type}
                                    onChange={(event) => form.setData('subject_type', event.target.value)}
                                >
                                    <option value="work_order">{t('forms.subjectTypes.work_order')}</option>
                                    <option value="technician">{t('forms.subjectTypes.technician')}</option>
                                </Select>
                            </Field>

                            {form.data.subject_type === 'work_order' ? (
                                <Field label={t('forms.workOrder')} htmlFor="work_order_id" error={form.errors.work_order_id} required>
                                    <Select
                                        id="work_order_id"
                                        value={form.data.work_order_id}
                                        onChange={(event) => form.setData('work_order_id', event.target.value)}
                                    >
                                        <option value="">{t('common.select')}</option>
                                        {workOrderOptions.map((option) => (
                                            <option key={option.id} value={option.id}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            ) : (
                                <Field
                                    label={t('forms.technician')}
                                    htmlFor="company_relationship_id"
                                    error={form.errors.company_relationship_id}
                                    required
                                >
                                    <Select
                                        id="company_relationship_id"
                                        value={form.data.company_relationship_id}
                                        onChange={(event) => form.setData('company_relationship_id', event.target.value)}
                                    >
                                        <option value="">{t('common.select')}</option>
                                        {technicianOptions.map((option) => (
                                            <option key={option.id} value={option.id}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            )}

                            <Field label={t('forms.occurredOn')} htmlFor="occurred_on" error={form.errors.occurred_on}>
                                <Input
                                    id="occurred_on"
                                    type="date"
                                    value={form.data.occurred_on}
                                    onChange={(event) => form.setData('occurred_on', event.target.value)}
                                />
                            </Field>

                            <Field label={t('forms.platform')} htmlFor="app_platform_id" error={form.errors.app_platform_id}>
                                <Select
                                    id="app_platform_id"
                                    value={form.data.app_platform_id}
                                    onChange={(event) => form.setData('app_platform_id', event.target.value)}
                                >
                                    {APP_PLATFORM_OPTIONS.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {option.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </div>
                    </div>

                    <div className="space-y-4 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                        <h2 className="text-base font-semibold text-ink">{t('forms.fieldsTitle')}</h2>
                        {form.data.sections.length === 0 ? (
                            <p className="text-sm text-ink-muted">{t('forms.fieldsEmpty')}</p>
                        ) : (
                            form.data.sections.map((section, sectionIndex) => (
                                <div key={section.id} className="space-y-3 rounded-xl border border-line p-4">
                                    <h3 className="text-sm font-semibold text-ink">
                                        {section.label || t('forms.untitledSection')}
                                    </h3>
                                    {section.fields.map((field, fieldIndex) => (
                                        <Field
                                            key={field.id}
                                            label={`${field.label || field.type}${field.is_required ? ' *' : ''}`}
                                            htmlFor={`field-${field.id}`}
                                        >
                                            <Input
                                                id={`field-${field.id}`}
                                                value={field.value}
                                                disabled={field.is_locked}
                                                onChange={(event) =>
                                                    updateFieldValue(sectionIndex, fieldIndex, event.target.value)
                                                }
                                            />
                                        </Field>
                                    ))}
                                </div>
                            ))
                        )}
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-2">
                        {can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyForm}>
                                <Trash2 className="size-4" aria-hidden />
                                {t('common.delete')}
                            </Button>
                        ) : null}
                        {formRecord.next_status_id ? (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => formsService.advance(formRecord.id)}
                            >
                                <ArrowRight className="size-4" aria-hidden />
                                {t('forms.advanceStatus')}
                            </Button>
                        ) : null}
                        <Button type="submit" loading={form.processing}>
                            <Save className="size-4" aria-hidden />
                            {t('common.save')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
