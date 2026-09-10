import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PaymentMethodForm } from '@/components/config/payment-methods/PaymentMethodForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { paymentMethodsService } from '@/services';

export default function CreatePaymentMethod() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        due_count: '1',
        days: '',
        code: '',
        is_active: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            name: data.name,
            due_count: data.due_count === '' ? null : Number(data.due_count),
            days: data.days === '' ? null : Number(data.days),
            code: data.code || null,
            is_active: data.is_active,
        }));
        paymentMethodsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('paymentMethods.resource') })}>
            <Head title={t('common.newItem', { resource: t('paymentMethods.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('paymentMethods.title')}
                    title={t('paymentMethods.createTitle')}
                    description={t('paymentMethods.createDescription')}
                    backHref={paymentMethodsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('paymentMethods.resourcePlural') })}
                />

                <PaymentMethodForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('paymentMethods.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
