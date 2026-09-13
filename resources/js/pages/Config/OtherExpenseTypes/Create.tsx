import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { OtherExpenseTypeForm } from '@/components/config/other-expense-types/OtherExpenseTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { otherExpenseTypesService } from '@/services';

export default function CreateOtherExpenseType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        otherExpenseTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('otherExpenseTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('otherExpenseTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.types')}
                    title={t('common.createItem', { resource: t('otherExpenseTypes.resource') })}
                    description={t('otherExpenseTypes.createDescription')}
                    backHref={otherExpenseTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('otherExpenseTypes.resourcePlural') })}
                />

                <OtherExpenseTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('otherExpenseTypes.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
