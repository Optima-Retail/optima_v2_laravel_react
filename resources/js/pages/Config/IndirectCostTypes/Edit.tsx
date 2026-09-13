import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { IndirectCostTypeForm } from '@/components/config/indirect-cost-types/IndirectCostTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { indirectCostTypesService } from '@/services';
import type { IndirectCostTypeFormData } from '@/support/types/domain/indirect-cost-type';

type EditIndirectCostTypeProps = {
    indirectCostType: IndirectCostTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditIndirectCostType({ indirectCostType, can }: EditIndirectCostTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: indirectCostType.name,
        code: indirectCostType.code,
        color: indirectCostType.color ?? '#33FF57',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        indirectCostTypesService.update(indirectCostType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('indirectCostTypes.resource') }),
            message: t('common.deleteMessage', { name: indirectCostType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        indirectCostTypesService.destroy(indirectCostType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('indirectCostTypes.resource') })}>
            <Head title={t('common.editItem', { name: indirectCostType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.types')}
                    title={t('common.editResource', { resource: t('indirectCostTypes.resource') })}
                    description={t('common.updateDetails', { name: indirectCostType.name })}
                    backHref={indirectCostTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('indirectCostTypes.resourcePlural') })}
                />

                <IndirectCostTypeForm
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
                            <Button type="button" variant="danger" onClick={destroyType}>
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
