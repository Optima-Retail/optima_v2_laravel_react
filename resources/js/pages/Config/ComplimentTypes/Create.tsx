import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ComplimentTypeForm } from '@/components/config/complimentTypes/ComplimentTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { complimentTypesService } from '@/services';

export default function CreateComplimentType() {
    const { t } = useTranslation();
    const form = useForm({ name: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        complimentTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('complimentTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('complimentTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('complimentTypes.title')}
                    title={t('common.createItem', { resource: t('complimentTypes.resource') })}
                    description={t('complimentTypes.createDescription')}
                    backHref={complimentTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('complimentTypes.resourcePlural') })}
                />

                <ComplimentTypeForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('complimentTypes.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
