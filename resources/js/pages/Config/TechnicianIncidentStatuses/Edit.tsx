import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianIncidentStatusForm } from '@/components/config/technician-incident-statuses/TechnicianIncidentStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianIncidentStatusesService } from '@/services';
import type { TechnicianIncidentStatusFormData } from '@/support/types/domain/technician-incident-status';

type EditTechnicianIncidentStatusProps = {
    technicianIncidentStatus: TechnicianIncidentStatusFormData;
    can: {
        delete: boolean;
    };
};

export default function EditTechnicianIncidentStatus({
    technicianIncidentStatus,
    can,
}: EditTechnicianIncidentStatusProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: technicianIncidentStatus.name,
        color: technicianIncidentStatus.color ?? '#a9cef0',
        lifecycle: technicianIncidentStatus.lifecycle ?? '',
        is_open: technicianIncidentStatus.is_open,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianIncidentStatusesService.update(technicianIncidentStatus.id, form);
    }

    async function destroyStatus() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('technicianIncidentStatuses.resource') }),
            message: t('common.deleteMessage', { name: technicianIncidentStatus.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        technicianIncidentStatusesService.destroy(technicianIncidentStatus.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('technicianIncidentStatuses.resource') })}>
            <Head title={t('common.editItem', { name: technicianIncidentStatus.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('technicianIncidentStatuses.editTitle')}
                    description={t('common.updateDetails', { name: technicianIncidentStatus.name })}
                    backHref={technicianIncidentStatusesService.indexPath}
                    backLabel={t('common.backTo', {
                        resource: t('technicianIncidentStatuses.resourcePlural'),
                    })}
                />

                <TechnicianIncidentStatusForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]:
                                key === 'lifecycle'
                                    ? value === ''
                                        ? ''
                                        : Number(value) || value
                                    : value,
                        }))
                    }
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyStatus}>
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
