import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page/PageHeader';
import { TechnicianIncidentForm } from '@/components/technicians/TechnicianIncidentForm';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianIncidentsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';

type CreateTechnicianIncidentProps = {
    defaultTechnicianId: number | null;
    defaultRespondedById: number | null;
    typeOptions: UserOption[];
    userOptions: UserOption[];
    technicianOptions: UserOption[];
};

export default function CreateTechnicianIncident({
    defaultTechnicianId,
    defaultRespondedById,
    typeOptions,
    userOptions,
    technicianOptions,
}: CreateTechnicianIncidentProps) {
    const { t } = useTranslation();
    const form = useForm({
        technician_id: defaultTechnicianId ? String(defaultTechnicianId) : '',
        technician_incident_type_id: '',
        responded_by_id: defaultRespondedById ? String(defaultRespondedById) : '',
        incident_text: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianIncidentsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('technicianIncidents.resource') })}>
            <Head title={t('common.newItem', { resource: t('technicianIncidents.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    title={t('common.createItem', { resource: t('technicianIncidents.resource') })}
                    description={t('technicianIncidents.createDescription')}
                    backHref={
                        defaultTechnicianId
                            ? `/technicians/${defaultTechnicianId}/edit?tab=incidents`
                            : technicianIncidentsService.indexPath
                    }
                    backLabel={t('common.backTo', {
                        resource: defaultTechnicianId
                            ? t('technicians.resource')
                            : t('technicianIncidents.resourcePlural'),
                    })}
                />

                <TechnicianIncidentForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    typeOptions={typeOptions}
                    userOptions={userOptions}
                    technicianOptions={technicianOptions}
                    technicianLocked={Boolean(defaultTechnicianId)}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.create')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
