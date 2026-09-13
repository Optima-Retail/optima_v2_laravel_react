import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    defaultFormTemplateFormValues,
    FormTemplateForm,
} from '@/components/form-templates/FormTemplateForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { formTemplatesService } from '@/services';

type Option = { id: number; label: string };

type CreateProps = {
    typeOptions: Option[];
    languageOptions: Option[];
    workOrderTypeOptions: Option[];
    brandOptions: Option[];
    customerOptions: Option[];
    establishmentOptions: Option[];
    bibleOptions: Option[];
};

export default function CreateFormTemplate({
    typeOptions,
    languageOptions,
    workOrderTypeOptions,
    brandOptions,
    customerOptions,
    establishmentOptions,
    bibleOptions,
}: CreateProps) {
    const { t } = useTranslation();
    const form = useForm(defaultFormTemplateFormValues());

    function submit(event: FormEvent) {
        event.preventDefault();
        formTemplatesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('formTemplates.resource') })}>
            <Head title={t('common.newItem', { resource: t('formTemplates.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('formTemplates.title')}
                    title={t('common.createItem', { resource: t('formTemplates.resource') })}
                    description={t('formTemplates.createDescription')}
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
                    submitLabel={t('common.createItem', { resource: t('formTemplates.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
