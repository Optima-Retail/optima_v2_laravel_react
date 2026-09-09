import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page/PageHeader';
import { RelationshipForm } from '@/components/relationships/RelationshipForm';
import { AppLayout } from '@/layouts/AppLayout';
import { clientsService } from '@/services';
import { defaultRelationshipProfileValues, emptyRelatedCompany } from '@/support/relationshipForm';
import type { UserOption } from '@/support/types/domain/common';
import type { RelationshipFormOptions } from '@/support/types/domain/company-relationship';

type CreateClientProps = {
    companyOptions: UserOption[];
    formOptions: RelationshipFormOptions;
};

export default function CreateClient({ companyOptions, formOptions }: CreateClientProps) {
    const { t } = useTranslation();
    const form = useForm({
        related_mode: 'new' as const,
        related_company_id: '',
        related_company: emptyRelatedCompany,
        kind: 'customer',
        status: 'active',
        classification: 'commercial',
        owner_reference: '',
        related_reference: '',
        brand_id: '',
        external_code: '',
        notes: '',
        starts_at: '',
        ends_at: '',
        ...defaultRelationshipProfileValues(),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        clientsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('clients.resource') })}>
            <Head title={t('common.newItem', { resource: t('clients.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('clients.title')}
                    title={t('common.createItem', { resource: t('clients.resource') })}
                    description={t('clients.createDescription')}
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
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onRelatedCompanyChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            related_company: { ...data.related_company, [key]: value },
                        }))
                    }
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('clients.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                    allowedKinds={['customer']}
                    kindLocked
                    allowCreateRelated
                />
            </div>
        </AppLayout>
    );
}
