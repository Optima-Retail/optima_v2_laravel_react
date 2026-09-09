import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { defaultIncidentFormValues, IncidentForm } from '@/components/incidents/IncidentForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { incidentsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type {
    IncidentFormData,
    IncidentSubtypeOption,
    IncidentTypeOption,
    IncidentTypeWorkflowMap,
} from '@/support/types/domain/incident';

type EditIncidentProps = {
    incident: IncidentFormData;
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
    can: {
        delete: boolean;
    };
};

export default function EditIncident({
    incident,
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
    can,
}: EditIncidentProps) {
    const { t } = useTranslation();
    const form = useForm(
        defaultIncidentFormValues({
            subject: incident.subject ?? '',
            comment: incident.comment ?? '',
            incident_status_id: incident.incident_status_id ? String(incident.incident_status_id) : '',
            incident_priority_id: incident.incident_priority_id
                ? String(incident.incident_priority_id)
                : '',
            incident_type_id: incident.incident_type_id ? String(incident.incident_type_id) : '',
            incident_subtype_id: incident.incident_subtype_id
                ? String(incident.incident_subtype_id)
                : '',
            origin_type: incident.origin_type ?? '',
            origin_id: incident.origin_id ? String(incident.origin_id) : '',
            related_type: incident.related_type ?? '',
            related_id: incident.related_id ? String(incident.related_id) : '',
            requester_user_id: incident.requester_user_id ? String(incident.requester_user_id) : '',
            responsible_user_id: incident.responsible_user_id
                ? String(incident.responsible_user_id)
                : '',
            qc_responsible_user_id: incident.qc_responsible_user_id
                ? String(incident.qc_responsible_user_id)
                : '',
            control_at: incident.control_at ?? '',
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
        incidentsService.update(incident.id, form);
    }

    async function destroyIncident() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('incidents.resource') }),
            message: t('common.deleteMessage', {
                name: incident.subject || incident.id,
            }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        incidentsService.destroy(incident.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('incidents.resource') })}>
            <Head
                title={t('common.editItem', {
                    name: incident.subject || incident.id,
                })}
            />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('incidents.title')}
                    title={t('common.editResource', { resource: t('incidents.resource') })}
                    description={t('common.updateDetails', {
                        name: incident.subject || incident.id,
                    })}
                    backHref={incidentsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('incidents.resourcePlural') })}
                />

                <IncidentForm
                    mode="edit"
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
                    readonlyFields={{
                        duration_seconds: incident.duration_seconds,
                        qc_duration_seconds: incident.qc_duration_seconds,
                        closed_at: incident.closed_at,
                    }}
                    onChange={(key, value) => form.setData(key, value)}
                    onTypeChange={applyTypeChange}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyIncident}>
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
