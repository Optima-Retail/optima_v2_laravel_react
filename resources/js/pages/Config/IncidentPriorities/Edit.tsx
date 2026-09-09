import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { IncidentPriorityForm } from '@/components/config/incident-priorities/IncidentPriorityForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { incidentPrioritiesService } from '@/services';
import type { IncidentPriorityFormData } from '@/support/types/domain/incident-priority';

type EditIncidentPriorityProps = {
    incidentPriority: IncidentPriorityFormData;
    can: {
        delete: boolean;
    };
};

export default function EditIncidentPriority({ incidentPriority, can }: EditIncidentPriorityProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: incidentPriority.name,
        color: incidentPriority.color ?? '#FF9999',
        resolution_time_hours: incidentPriority.resolution_time_hours,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        incidentPrioritiesService.update(incidentPriority.id, form);
    }

    async function destroyPriority() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('incidentPriorities.resource') }),
            message: t('common.deleteMessage', { name: incidentPriority.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        incidentPrioritiesService.destroy(incidentPriority.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('incidentPriorities.resource') })}>
            <Head title={t('common.editItem', { name: incidentPriority.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('incidentPriorities.title')}
                    title={t('incidentPriorities.editTitle')}
                    description={t('common.updateDetails', { name: incidentPriority.name })}
                    backHref={incidentPrioritiesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('incidentPriorities.resourcePlural') })}
                />

                <IncidentPriorityForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]: key === 'resolution_time_hours' ? Number(value) || value : value,
                        }))
                    }
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyPriority}>
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
