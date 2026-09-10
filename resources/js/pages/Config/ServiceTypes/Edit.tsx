import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ServiceTypeForm } from '@/components/config/service-types/ServiceTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { serviceTypesService } from '@/services';
import type { ServiceTypeFormData } from '@/support/types/domain/service-type';

type EditServiceTypeProps = {
    serviceType: ServiceTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditServiceType({ serviceType, can }: EditServiceTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: serviceType.name,
        code: serviceType.code ?? '',
        color: serviceType.color ?? '#2563eb',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        serviceTypesService.update(serviceType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('serviceTypes.resource') }),
            message: t('common.deleteMessage', { name: serviceType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        serviceTypesService.destroy(serviceType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('serviceTypes.resource') })}>
            <Head title={t('common.editItem', { name: serviceType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('serviceTypes.title')}
                    title={t('serviceTypes.editTitle')}
                    description={t('common.updateDetails', { name: serviceType.name })}
                    backHref={serviceTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('serviceTypes.resourcePlural') })}
                />

                <ServiceTypeForm
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
