import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PaymentDocumentForm } from '@/components/config/payment-documents/PaymentDocumentForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { paymentDocumentsService } from '@/services';

export default function CreatePaymentDocument() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        is_active: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        paymentDocumentsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('paymentDocuments.resource') })}>
            <Head title={t('common.newItem', { resource: t('paymentDocuments.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('paymentDocuments.title')}
                    title={t('paymentDocuments.createTitle')}
                    description={t('paymentDocuments.createDescription')}
                    backHref={paymentDocumentsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('paymentDocuments.resourcePlural') })}
                />

                <PaymentDocumentForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('paymentDocuments.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
