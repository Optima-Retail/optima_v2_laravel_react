import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ExpenseTypeForm } from '@/components/config/expense-types/ExpenseTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { expenseTypesService } from '@/services';

export default function CreateExpenseType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        expenseTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('expenseTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('expenseTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.types')}
                    title={t('common.createItem', { resource: t('expenseTypes.resource') })}
                    description={t('expenseTypes.createDescription')}
                    backHref={expenseTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('expenseTypes.resourcePlural') })}
                />

                <ExpenseTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('expenseTypes.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
