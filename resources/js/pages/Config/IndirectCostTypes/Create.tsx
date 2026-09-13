import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { IndirectCostTypeForm } from '@/components/config/indirect-cost-types/IndirectCostTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { indirectCostTypesService } from '@/services';

export default function CreateIndirectCostType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
        color: '#33FF57',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        indirectCostTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('indirectCostTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('indirectCostTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.types')}
                    title={t('common.createItem', { resource: t('indirectCostTypes.resource') })}
                    description={t('indirectCostTypes.createDescription')}
                    backHref={indirectCostTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('indirectCostTypes.resourcePlural') })}
                />

                <IndirectCostTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('indirectCostTypes.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
