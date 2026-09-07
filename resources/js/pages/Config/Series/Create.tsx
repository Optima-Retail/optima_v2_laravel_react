import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { SeriesForm } from '@/components/config/series/SeriesForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { seriesService } from '@/services';
import type { SeriesOption } from '@/support/types/domain';

type CreateSeriesProps = {
    seriesOptions: SeriesOption[];
};

export default function CreateSeries({ seriesOptions }: CreateSeriesProps) {
    const { t } = useTranslation();
    const form = useForm({
        key: '',
        color: '#ffffff',
        is_selectable: true,
        credit_note_series_id: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        seriesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('series.resource') })}>
            <Head title={t('common.newItem', { resource: t('series.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('series.title')}
                    title={t('common.createItem', { resource: t('series.resource') })}
                    description={t('series.createDescription')}
                    backHref={seriesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('series.resourcePlural') })}
                />

                <SeriesForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    seriesOptions={seriesOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('series.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
