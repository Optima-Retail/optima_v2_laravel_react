import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormTypeForm } from '@/components/config/form-types/FormTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { formTypesService } from '@/services';

export default function CreateFormType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        formTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('formTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('formTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('formTypes.title')}
                    title={t('formTypes.createTitle')}
                    description={t('formTypes.createDescription')}
                    backHref={formTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('formTypes.resourcePlural') })}
                />

                <FormTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('formTypes.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
