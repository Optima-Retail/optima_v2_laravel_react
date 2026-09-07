import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { RatingTypeForm } from '@/components/config/ratingTypes/RatingTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { ratingTypesService } from '@/services';

export default function CreateRatingType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
        max_score: '5',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        ratingTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('ratingTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('ratingTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('ratingTypes.title')}
                    title={t('common.createItem', { resource: t('ratingTypes.resource') })}
                    description={t('ratingTypes.createDescription')}
                    backHref={ratingTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('ratingTypes.resourcePlural') })}
                />

                <RatingTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('ratingTypes.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
