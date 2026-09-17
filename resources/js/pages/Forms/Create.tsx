import { FormEvent, useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Select } from '@/components/ui/Select';
import { toCompanySelectOptions } from '@/support/companySelect';
import type { CompanyOption } from '@/support/types/domain/common';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { formsService } from '@/services';
import { APP_PLATFORM_OPTIONS } from '@/support/types/domain/app-platform';

type Option = { id: number; label: string };

type CreateProps = {
    typeOptions: Option[];
    statusOptions: Option[];
    templateOptions: Option[];
    workOrderOptions: Option[];
    technicianOptions: CompanyOption[];
    languageOptions: Option[];
};

export default function CreateForm({
    typeOptions,
    statusOptions,
    templateOptions,
    workOrderOptions,
    technicianOptions,
    languageOptions,
}: CreateProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        form_template_id: '',
        form_type_id: '',
        form_status_id: '',
        language_id: '',
        subject_type: 'work_order',
        work_order_id: '',
        company_relationship_id: '',
        occurred_on: new Date().toISOString().slice(0, 10),
        app_platform_id: '1',
        technician_code: '',
    });
    const [suggestedTemplateIds, setSuggestedTemplateIds] = useState<number[]>([]);

    // Re-rank the template list around the chosen work order (establishment,
    // work order type, language) instead of leaving it a flat, unscoped
    // alphabetical dropdown — see formTemplatesService.resolveForWorkOrder.
    useEffect(() => {
        if (form.data.subject_type !== 'work_order' || !form.data.work_order_id) {
            setSuggestedTemplateIds([]);

            return;
        }

        let cancelled = false;

        formsService
            .templateSuggestions({
                workOrderId: Number(form.data.work_order_id),
                formTypeId: form.data.form_type_id ? Number(form.data.form_type_id) : null,
            })
            .then((suggestions) => {
                if (cancelled) {
                    return;
                }

                setSuggestedTemplateIds(suggestions.map((suggestion) => suggestion.id));

                if (!form.data.form_template_id && suggestions.length > 0) {
                    form.setData('form_template_id', String(suggestions[0].id));
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setSuggestedTemplateIds([]);
                }
            });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.subject_type, form.data.work_order_id, form.data.form_type_id]);

    const orderedTemplateOptions =
        suggestedTemplateIds.length === 0
            ? templateOptions
            : [
                  ...suggestedTemplateIds
                      .map((id) => templateOptions.find((option) => option.id === id))
                      .filter((option): option is Option => option !== undefined),
                  ...templateOptions.filter((option) => !suggestedTemplateIds.includes(option.id)),
              ];

    function submit(event: FormEvent) {
        event.preventDefault();
        formsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('forms.resource') })}>
            <Head title={t('common.newItem', { resource: t('forms.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('forms.title')}
                    title={t('common.createItem', { resource: t('forms.resource') })}
                    description={t('forms.createDescription')}
                    backHref={formsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('forms.resourcePlural') })}
                />

                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8"
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label={t('common.name')} htmlFor="name" error={form.errors.name} className="sm:col-span-2">
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                            />
                        </Field>

                        <Field label={t('forms.template')} htmlFor="form_template_id" error={form.errors.form_template_id}>
                            <Select
                                id="form_template_id"
                                value={form.data.form_template_id}
                                onChange={(event) => form.setData('form_template_id', event.target.value)}
                            >
                                <option value="">{t('common.select')}</option>
                                {orderedTemplateOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {suggestedTemplateIds.includes(option.id)
                                            ? t('forms.suggestedTemplateOption', { name: option.label })
                                            : option.label}
                                    </option>
                                ))}
                            </Select>
                            {suggestedTemplateIds.length > 0 ? (
                                <p className="text-xs text-ink-muted">{t('forms.suggestedTemplatesHint')}</p>
                            ) : null}
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
                                <SearchableSelect
                                    id="company_relationship_id"
                                    value={form.data.company_relationship_id}
                                    emptyLabel={t('common.select')}
                                    onChange={(value) => form.setData('company_relationship_id', value)}
                                    options={toCompanySelectOptions(technicianOptions)}
                                />
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

                    <div className="flex justify-end border-t border-line pt-4">
                        <Button type="submit" loading={form.processing}>
                            <Plus className="size-4" aria-hidden />
                            {t('common.createItem', { resource: t('forms.resource') })}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
