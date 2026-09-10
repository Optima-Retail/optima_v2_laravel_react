import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlobalServiceTypeForm } from '@/components/config/global-service-types/GlobalServiceTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { globalServiceTypesService } from '@/services';
import type { GlobalServiceTypeFormData } from '@/support/types/domain/global-service-type';

type EditGlobalServiceTypeProps = {
    globalServiceType: GlobalServiceTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditGlobalServiceType({ globalServiceType, can }: EditGlobalServiceTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: globalServiceType.name,
        code: globalServiceType.code ?? '',
        color: globalServiceType.color ?? '#fcba03',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        globalServiceTypesService.update(globalServiceType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('globalServiceTypes.resource') }),
            message: t('common.deleteMessage', { name: globalServiceType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        globalServiceTypesService.destroy(globalServiceType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('globalServiceTypes.resource') })}>
            <Head title={t('common.editItem', { name: globalServiceType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('globalServiceTypes.title')}
                    title={t('globalServiceTypes.editTitle')}
                    description={t('globalServiceTypes.editDescription')}
                    backHref={globalServiceTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('globalServiceTypes.resourcePlural') })}
                />

                <GlobalServiceTypeForm
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
