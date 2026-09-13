import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { OtherExpenseTypeForm } from '@/components/config/other-expense-types/OtherExpenseTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { otherExpenseTypesService } from '@/services';
import type { OtherExpenseTypeFormData } from '@/support/types/domain/other-expense-type';

type EditOtherExpenseTypeProps = {
    otherExpenseType: OtherExpenseTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditOtherExpenseType({ otherExpenseType, can }: EditOtherExpenseTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: otherExpenseType.name,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        otherExpenseTypesService.update(otherExpenseType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('otherExpenseTypes.resource') }),
            message: t('common.deleteMessage', { name: otherExpenseType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        otherExpenseTypesService.destroy(otherExpenseType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('otherExpenseTypes.resource') })}>
            <Head title={t('common.editItem', { name: otherExpenseType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.types')}
                    title={t('common.editResource', { resource: t('otherExpenseTypes.resource') })}
                    description={t('common.updateDetails', { name: otherExpenseType.name })}
                    backHref={otherExpenseTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('otherExpenseTypes.resourcePlural') })}
                />

                <OtherExpenseTypeForm
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
