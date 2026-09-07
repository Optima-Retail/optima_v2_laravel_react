import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { SeriesForm } from '@/components/config/series/SeriesForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { seriesService } from '@/services';
import type { SeriesFormData, SeriesOption } from '@/support/types/domain';

type EditSeriesProps = {
    seriesItem: SeriesFormData;
    seriesOptions: SeriesOption[];
    can: {
        delete: boolean;
    };
};

export default function EditSeries({ seriesItem, seriesOptions, can }: EditSeriesProps) {
    const { t } = useTranslation();
    const form = useForm({
        key: seriesItem.key,
        color: seriesItem.color,
        is_selectable: seriesItem.is_selectable,
        credit_note_series_id:
            seriesItem.credit_note_series_id !== null ? String(seriesItem.credit_note_series_id) : '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        seriesService.update(seriesItem.id, form);
    }

    async function destroySeries() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('series.resource') }),
            message: t('common.deleteMessage', { name: seriesItem.key }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        seriesService.destroy(seriesItem.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('series.resource') })}>
            <Head title={t('common.editItem', { name: seriesItem.key })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('series.title')}
                    title={t('common.editResource', { resource: t('series.resource') })}
                    description={t('common.updateDetails', { name: seriesItem.key })}
                    backHref={seriesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('series.resourcePlural') })}
                />

                <SeriesForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    seriesOptions={seriesOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroySeries}>
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
