import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CostCenterForm } from '@/components/config/cost-centers/CostCenterForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { costCentersService } from '@/services';
import type { CostCenterFormData } from '@/support/types/domain/cost-center';

type EditCostCenterProps = {
    costCenter: CostCenterFormData;
    can: {
        delete: boolean;
    };
};

export default function EditCostCenter({ costCenter, can }: EditCostCenterProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: costCenter.name,
        code: costCenter.code,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        costCentersService.update(costCenter.id, form);
    }

    async function destroyCostCenter() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('costCenters.resource') }),
            message: t('common.deleteMessage', { name: costCenter.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        costCentersService.destroy(costCenter.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('costCenters.resource') })}>
            <Head title={t('common.editItem', { name: costCenter.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('costCenters.title')}
                    title={t('common.editResource', { resource: t('costCenters.resource') })}
                    description={t('common.updateDetails', { name: costCenter.name })}
                    backHref={costCentersService.indexPath}
                    backLabel={t('common.backTo', { resource: t('costCenters.resourcePlural') })}
                />

                <CostCenterForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyCostCenter}>
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
