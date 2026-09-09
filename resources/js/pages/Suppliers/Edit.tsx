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
import { relationshipFormValuesFromData } from '@/support/relationshipForm';
import type { UserOption } from '@/support/types/domain/common';
import type { CompanyRelationshipFormData, RelationshipFormOptions } from '@/support/types/domain/company-relationship';

type EditSupplierProps = {
    relationship: CompanyRelationshipFormData;
    companyOptions: UserOption[];
    formOptions: RelationshipFormOptions;
    can: {
        delete: boolean;
    };
};

export default function EditSupplier({ relationship, companyOptions, formOptions, can }: EditSupplierProps) {
    const { t } = useTranslation();
    const form = useForm(relationshipFormValuesFromData(relationship));
    const displayName = relationship.related_company_name ?? String(relationship.id);

    function submit(event: FormEvent) {
        event.preventDefault();
        suppliersService.update(relationship.id, form);
    }

    async function destroySupplier() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('suppliers.resource') }),
            message: t('common.deleteMessage', { name: displayName }),
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
            <Head title={t('common.editItem', { name: displayName })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('suppliers.title')}
                    title={t('common.editResource', { resource: t('suppliers.resource') })}
                    description={t('common.updateDetails', { name: displayName })}
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
