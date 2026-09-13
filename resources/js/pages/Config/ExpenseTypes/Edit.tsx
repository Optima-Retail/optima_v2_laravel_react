import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ExpenseTypeForm } from '@/components/config/expense-types/ExpenseTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { expenseTypesService } from '@/services';
import type { ExpenseTypeFormData } from '@/support/types/domain/expense-type';

type EditExpenseTypeProps = {
    expenseType: ExpenseTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditExpenseType({ expenseType, can }: EditExpenseTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: expenseType.name,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        expenseTypesService.update(expenseType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('expenseTypes.resource') }),
            message: t('common.deleteMessage', { name: expenseType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        expenseTypesService.destroy(expenseType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('expenseTypes.resource') })}>
            <Head title={t('common.editItem', { name: expenseType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.types')}
                    title={t('common.editResource', { resource: t('expenseTypes.resource') })}
                    description={t('common.updateDetails', { name: expenseType.name })}
                    backHref={expenseTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('expenseTypes.resourcePlural') })}
                />

                <ExpenseTypeForm
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
