import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { RatingTypeForm } from '@/components/config/ratingTypes/RatingTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { ratingTypesService } from '@/services';
import type { RatingTypeFormData } from '@/support/types/domain/rating-type';

type EditRatingTypeProps = {
    ratingType: RatingTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditRatingType({ ratingType, can }: EditRatingTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: ratingType.name,
        code: ratingType.code,
        max_score: String(ratingType.max_score),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        ratingTypesService.update(ratingType.id, form);
    }

    async function destroyRatingType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('ratingTypes.resource') }),
            message: t('common.deleteMessage', { name: ratingType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        ratingTypesService.destroy(ratingType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('ratingTypes.resource') })}>
            <Head title={t('common.editItem', { name: ratingType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('ratingTypes.title')}
                    title={t('common.editResource', { resource: t('ratingTypes.resource') })}
                    description={t('common.updateDetails', { name: ratingType.name })}
                    backHref={ratingTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('ratingTypes.resourcePlural') })}
                />

                <RatingTypeForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyRatingType}>
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
