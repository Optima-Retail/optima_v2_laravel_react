import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page/PageHeader';
import { RelationshipForm } from '@/components/relationships/RelationshipForm';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { suppliersService } from '@/services';
import type { CompanyRelationshipFormData, UserOption } from '@/support/types/domain';

type EditSupplierProps = {
    relationship: CompanyRelationshipFormData;
    companyOptions: UserOption[];
    can: {
        delete: boolean;
    };
};

export default function EditSupplier({ relationship, companyOptions, can }: EditSupplierProps) {
    const { t } = useTranslation();
    const form = useForm({
        related_mode: 'existing' as const,
        related_company_id: String(relationship.related_company_id),
        related_company: {
            name: '',
            tradename: '',
            tax_id: '',
            email: '',
            phone: '',
        },
        kind: relationship.kind,
        status: relationship.status,
        classification: relationship.classification,
        owner_reference: relationship.owner_reference ?? '',
        related_reference: relationship.related_reference ?? '',
        brand_id: relationship.brand_id ? String(relationship.brand_id) : '',
        external_code: relationship.external_code ?? '',
        notes: relationship.notes ?? '',
        starts_at: relationship.starts_at ?? '',
        ends_at: relationship.ends_at ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        suppliersService.update(relationship.id, form);
    }

    async function destroySupplier() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('suppliers.resource') }),
            message: t('common.deleteMessage', { name: String(relationship.id) }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        suppliersService.destroy(relationship.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('suppliers.resource') })}>
            <Head title={t('common.editResource', { resource: t('suppliers.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('suppliers.title')}
                    title={t('common.editResource', { resource: t('suppliers.resource') })}
                    description={t('common.updateDetails', { name: String(relationship.id) })}
                    backHref={suppliersService.indexPath}
                    backLabel={t('common.backTo', { resource: t('suppliers.resourcePlural') })}
                />

                <RelationshipForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    companyOptions={companyOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    allowedKinds={['supplier', 'technician']}
                    kindLabelsNamespace="suppliers"
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroySupplier}>
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
