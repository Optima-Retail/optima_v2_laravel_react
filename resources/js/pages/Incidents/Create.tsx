import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { defaultIncidentFormValues, IncidentForm } from '@/components/incidents/IncidentForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { incidentsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type {
    IncidentSubtypeOption,
    IncidentTypeOption,
    IncidentTypeWorkflowMap,
} from '@/support/types/domain/incident';

type CreateIncidentProps = {
    defaultIncidentStatusId: number | null;
    defaultRequesterUserId: number | null;
    typeWorkflow: IncidentTypeWorkflowMap;
    incidentStatusOptions: UserOption[];
    incidentPriorityOptions: UserOption[];
    incidentTypeOptions: IncidentTypeOption[];
    incidentSubtypeOptions: IncidentSubtypeOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    clientOptions: UserOption[];
    brandOptions: UserOption[];
    evaluationOptions: UserOption[];
};

export default function CreateIncident({
    defaultIncidentStatusId,
    defaultRequesterUserId,
    typeWorkflow,
    incidentStatusOptions,
    incidentPriorityOptions,
    incidentTypeOptions,
    incidentSubtypeOptions,
    userOptions,
    establishmentOptions,
    clientOptions,
    brandOptions,
    evaluationOptions,
}: CreateIncidentProps) {
    const { t } = useTranslation();
    const form = useForm(
        defaultIncidentFormValues({
            incident_status_id: defaultIncidentStatusId ? String(defaultIncidentStatusId) : '',
            requester_user_id: defaultRequesterUserId ? String(defaultRequesterUserId) : '',
        }),
    );

    function applyTypeChange(typeId: string) {
        const workflow = typeId ? typeWorkflow[typeId] : null;
        const typeOption = incidentTypeOptions.find((option) => String(option.id) === typeId);
        const defaultOrigin = workflow?.default_origin_type ?? '';

        form.setData({
            ...form.data,
            incident_type_id: typeId,
            incident_subtype_id: '',
            incident_priority_id: typeOption?.default_priority_id
                ? String(typeOption.default_priority_id)
                : '',
            origin_type: defaultOrigin,
            origin_id: '',
            related_type: workflow?.related_type ?? '',
            related_id: '',
            responsible_user_id: '',
        });
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        incidentsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('incidents.resource') })}>
            <Head title={t('common.newItem', { resource: t('incidents.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('incidents.title')}
                    title={t('common.createItem', { resource: t('incidents.resource') })}
                    description={t('incidents.createDescription')}
                    backHref={incidentsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('incidents.resourcePlural') })}
                />

                <IncidentForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    typeWorkflow={typeWorkflow}
                    incidentStatusOptions={incidentStatusOptions}
                    incidentPriorityOptions={incidentPriorityOptions}
                    incidentTypeOptions={incidentTypeOptions}
                    incidentSubtypeOptions={incidentSubtypeOptions}
                    userOptions={userOptions}
                    establishmentOptions={establishmentOptions}
                    clientOptions={clientOptions}
                    brandOptions={brandOptions}
                    evaluationOptions={evaluationOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onTypeChange={applyTypeChange}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('incidents.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
