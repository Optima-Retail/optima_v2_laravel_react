import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { IncidentStatusForm } from '@/components/config/incident-statuses/IncidentStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { incidentStatusesService } from '@/services';
import type { IncidentStatusFormData } from '@/support/types/domain/incident-status';

type EditIncidentStatusProps = {
    incidentStatus: IncidentStatusFormData;
    can: {
        delete: boolean;
    };
};

export default function EditIncidentStatus({ incidentStatus, can }: EditIncidentStatusProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: incidentStatus.name,
        color: incidentStatus.color ?? '#a9cef0',
        lifecycle: incidentStatus.lifecycle ?? '',
        is_open: incidentStatus.is_open,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        incidentStatusesService.update(incidentStatus.id, form);
    }

    async function destroyStatus() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('incidentStatuses.resource') }),
            message: t('common.deleteMessage', { name: incidentStatus.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        incidentStatusesService.destroy(incidentStatus.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('incidentStatuses.resource') })}>
            <Head title={t('common.editItem', { name: incidentStatus.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('incidentStatuses.editTitle')}
                    description={t('common.updateDetails', { name: incidentStatus.name })}
                    backHref={incidentStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('incidentStatuses.resourcePlural') })}
                />

                <IncidentStatusForm
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
