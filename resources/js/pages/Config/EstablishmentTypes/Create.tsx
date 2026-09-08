import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EstablishmentTypeForm } from '@/components/config/establishmentTypes/EstablishmentTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { establishmentTypesService } from '@/services';

export default function CreateEstablishmentType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
        health_and_safety_delay_days: '1',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        establishmentTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('establishmentTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('establishmentTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('establishmentTypes.title')}
                    title={t('common.createItem', { resource: t('establishmentTypes.resource') })}
                    description={t('establishmentTypes.createDescription')}
                    backHref={establishmentTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('establishmentTypes.resourcePlural') })}
                />

                <EstablishmentTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('establishmentTypes.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
