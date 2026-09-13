import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page/PageHeader';
import { RelationshipForm } from '@/components/relationships/RelationshipForm';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { techniciansService } from '@/services';
import { relationshipFormValuesFromData } from '@/support/relationshipForm';
import type { UserOption } from '@/support/types/domain/common';
import type { CompanyRelationshipFormData, RelationshipFormOptions } from '@/support/types/domain/company-relationship';

type EditTechnicianProps = {
    relationship: CompanyRelationshipFormData;
    companyOptions: UserOption[];
    formOptions: RelationshipFormOptions;
    initialTab?: string;
    selectedIncidentId?: number | null;
    can: {
        delete: boolean;
        viewIncidents?: boolean;
    };
};

export default function EditTechnician({
    relationship,
    companyOptions,
    formOptions,
    initialTab = 'general',
    selectedIncidentId = null,
    can,
}: EditTechnicianProps) {
    const { t } = useTranslation();
    const form = useForm(relationshipFormValuesFromData(relationship));
    const displayName = relationship.related_company_name ?? String(relationship.id);

    function submit(event: FormEvent) {
        event.preventDefault();
        techniciansService.update(relationship.id, form);
    }

    async function destroyTechnician() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('technicians.resource') }),
            message: t('common.deleteMessage', { name: displayName }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        techniciansService.destroy(relationship.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('technicians.resource') })}>
            <Head title={t('common.editItem', { name: displayName })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('technicians.title')}
                    title={t('common.editResource', { resource: t('technicians.resource') })}
                    description={t('common.updateDetails', { name: displayName })}
                    backHref={techniciansService.indexPath}
                    backLabel={t('common.backTo', { resource: t('technicians.resourcePlural') })}
                />

                <RelationshipForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    companyOptions={companyOptions}
                    formOptions={formOptions}
                    profileMode="technician"
                    relationshipId={relationship.id}
                    initialTab={initialTab}
                    selectedIncidentId={selectedIncidentId}
                    showIncidentsTab={can.viewIncidents ?? false}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    allowedKinds={['technician']}
                    kindLocked
                    kindLabelsNamespace="technicians"
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyTechnician}>
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
