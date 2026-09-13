import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ComplimentTypeForm } from '@/components/config/complimentTypes/ComplimentTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { complimentTypesService } from '@/services';

type EditComplimentTypeProps = {
    complimentType: { id: number; name: string };
    can: { delete: boolean };
};

export default function EditComplimentType({ complimentType, can }: EditComplimentTypeProps) {
    const { t } = useTranslation();
    const form = useForm({ name: complimentType.name });

    function submit(event: FormEvent) {
        event.preventDefault();
        complimentTypesService.update(complimentType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('complimentTypes.resource') }),
            message: t('common.deleteMessage', { name: complimentType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        complimentTypesService.destroy(complimentType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('complimentTypes.resource') })}>
            <Head title={t('common.editItem', { name: complimentType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('complimentTypes.title')}
                    title={t('common.editResource', { resource: t('complimentTypes.resource') })}
                    description={t('common.updateDetails', { name: complimentType.name })}
                    backHref={complimentTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('complimentTypes.resourcePlural') })}
                />

                <ComplimentTypeForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyType}>
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
