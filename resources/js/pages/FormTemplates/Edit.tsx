import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    defaultFormTemplateFormValues,
    FormTemplateForm,
    type FormTemplateSectionValues,
} from '@/components/form-templates/FormTemplateForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { formTemplatesService } from '@/services';

type Option = { id: number; label: string };

type TemplateData = {
    id: number;
    name: string;
    form_type_id: number;
    language_id: number | null;
    is_default: boolean;
    work_order_type_id: number | null;
    owner_type: string;
    brand_id: number | null;
    company_relationship_id: number | null;
    establishment_id: number | null;
    form_bible_id: number | null;
    owner_label: string;
    type_name: string | null;
    sections: FormTemplateSectionValues[];
};

type EditProps = {
    template: TemplateData;
    typeOptions: Option[];
    languageOptions: Option[];
    workOrderTypeOptions: Option[];
    brandOptions: Option[];
    customerOptions: Option[];
    establishmentOptions: Option[];
    bibleOptions: Option[];
    can: { delete: boolean };
};

export default function EditFormTemplate({
    template,
    typeOptions,
    languageOptions,
    workOrderTypeOptions,
    brandOptions,
    customerOptions,
    establishmentOptions,
    bibleOptions,
    can,
}: EditProps) {
    const { t } = useTranslation();
    const displayName = [template.type_name, template.name].filter(Boolean).join(' · ');
    const form = useForm(
        defaultFormTemplateFormValues({
            name: template.name,
            form_type_id: String(template.form_type_id),
            language_id: template.language_id ? String(template.language_id) : '',
            is_default: template.is_default,
            work_order_type_id: template.work_order_type_id ? String(template.work_order_type_id) : '',
            owner_type: template.owner_type,
            brand_id: template.brand_id ? String(template.brand_id) : '',
            company_relationship_id: template.company_relationship_id
                ? String(template.company_relationship_id)
                : '',
            establishment_id: template.establishment_id ? String(template.establishment_id) : '',
            form_bible_id: template.form_bible_id ? String(template.form_bible_id) : '',
            sections: template.sections.map((section) => ({
                ...section,
                label: section.label ?? '',
                fields: section.fields.map((field) => ({
                    ...field,
                    label: field.label ?? '',
                    default_value: field.default_value ?? '',
                })),
            })),
        }),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        formTemplatesService.update(template.id, form);
    }

    async function destroyTemplate() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('formTemplates.resource') }),
            message: t('common.deleteMessage', { name: displayName }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        formTemplatesService.destroy(template.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('formTemplates.resource') })}>
            <Head title={t('common.editItem', { name: displayName })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('formTemplates.title')}
                    title={t('common.editResource', { resource: t('formTemplates.resource') })}
                    description={t('common.updateDetails', { name: displayName })}
                    backHref={formTemplatesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('formTemplates.resourcePlural') })}
                />

                <FormTemplateForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    typeOptions={typeOptions}
                    languageOptions={languageOptions}
                    workOrderTypeOptions={workOrderTypeOptions}
                    brandOptions={brandOptions}
                    customerOptions={customerOptions}
                    establishmentOptions={establishmentOptions}
                    bibleOptions={bibleOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyTemplate}>
                                <Trash2 className="size-4" aria-hidden />
                                {t('common.delete')}
                            </Button>
                        ) : null
                    }
                />
            </div>
        </AppLayout>
    );
}
