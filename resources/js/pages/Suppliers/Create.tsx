import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page/PageHeader';
import { RelationshipForm } from '@/components/relationships/RelationshipForm';
import { AppLayout } from '@/layouts/AppLayout';
import { suppliersService } from '@/services';
import { defaultRelationshipProfileValues, emptyRelatedCompany } from '@/support/relationshipForm';
import type { RelationshipFormOptions, UserOption } from '@/support/types/domain';

type CreateSupplierProps = {
    companyOptions: UserOption[];
    formOptions: RelationshipFormOptions;
};

export default function CreateSupplier({ companyOptions, formOptions }: CreateSupplierProps) {
    const { t } = useTranslation();
    const form = useForm({
        related_mode: 'new' as const,
        related_company_id: '',
        related_company: emptyRelatedCompany,
        kind: 'supplier',
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
        suppliersService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('suppliers.resource') })}>
            <Head title={t('common.newItem', { resource: t('suppliers.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('suppliers.title')}
                    title={t('common.createItem', { resource: t('suppliers.resource') })}
                    description={t('suppliers.createDescription')}
                    backHref={suppliersService.indexPath}
                    backLabel={t('common.backTo', { resource: t('suppliers.resourcePlural') })}
                />

                <RelationshipForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    companyOptions={companyOptions}
                    formOptions={formOptions}
                    profileMode="supplier"
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onRelatedCompanyChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            related_company: { ...data.related_company, [key]: value },
                        }))
                    }
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('suppliers.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                    allowedKinds={['supplier', 'technician']}
                    kindLabelsNamespace="suppliers"
                    allowCreateRelated
                />
            </div>
        </AppLayout>
    );
}
