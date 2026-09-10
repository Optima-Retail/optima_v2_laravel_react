import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { type CompanyScheduleValues } from '@/components/clients/CompanySchedulePanel';
import { PageHeader } from '@/components/page/PageHeader';
import { RelationshipForm } from '@/components/relationships/RelationshipForm';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { clientsService } from '@/services';
import { relationshipFormValuesFromData } from '@/support/relationshipForm';
import type { UserOption } from '@/support/types/domain/common';
import type { CompanyRelationshipFormData, RelationshipFormOptions } from '@/support/types/domain/company-relationship';

type EditClientProps = {
    relationship: CompanyRelationshipFormData;
    schedule: CompanyScheduleValues;
    companyOptions: UserOption[];
    formOptions: RelationshipFormOptions;
    can: {
        delete: boolean;
    };
};

export default function EditClient({
    relationship,
    schedule,
    companyOptions,
    formOptions,
    can,
}: EditClientProps) {
    const { t } = useTranslation();
    const form = useForm(relationshipFormValuesFromData(relationship));
    const displayName = relationship.related_company_name ?? String(relationship.id);

    function submit(event: FormEvent) {
        event.preventDefault();
        clientsService.update(relationship.id, form);
    }

    async function destroyClient() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('clients.resource') }),
            message: t('common.deleteMessage', { name: displayName }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        clientsService.destroy(relationship.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('clients.resource') })}>
            <Head title={t('common.editItem', { name: displayName })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('clients.title')}
                    title={t('common.editResource', { resource: t('clients.resource') })}
                    description={t('common.updateDetails', { name: displayName })}
                    backHref={clientsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('clients.resourcePlural') })}
                />

                <RelationshipForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    companyOptions={companyOptions}
                    formOptions={formOptions}
                    profileMode="customer"
                    relationshipId={relationship.id}
                    schedule={schedule}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    allowedKinds={['customer']}
                    kindLocked
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={() => void destroyClient()}>
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
