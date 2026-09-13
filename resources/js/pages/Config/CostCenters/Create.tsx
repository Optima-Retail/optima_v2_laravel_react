import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CostCenterForm } from '@/components/config/cost-centers/CostCenterForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { costCentersService } from '@/services';

export default function CreateCostCenter() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        costCentersService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('costCenters.resource') })}>
            <Head title={t('common.newItem', { resource: t('costCenters.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('costCenters.title')}
                    title={t('common.createItem', { resource: t('costCenters.resource') })}
                    description={t('costCenters.createDescription')}
                    backHref={costCentersService.indexPath}
                    backLabel={t('common.backTo', { resource: t('costCenters.resourcePlural') })}
                />

                <CostCenterForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('costCenters.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
