import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PaymentDocumentForm } from '@/components/config/payment-documents/PaymentDocumentForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { paymentDocumentsService } from '@/services';
import type { PaymentDocumentFormData } from '@/support/types/domain/payment-document';

type EditPaymentDocumentProps = {
    paymentDocument: PaymentDocumentFormData;
    can: {
        delete: boolean;
    };
};

export default function EditPaymentDocument({ paymentDocument, can }: EditPaymentDocumentProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: paymentDocument.name,
        is_active: paymentDocument.is_active,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        paymentDocumentsService.update(paymentDocument.id, form);
    }

    async function destroyDocument() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('paymentDocuments.resource') }),
            message: t('common.deleteMessage', { name: paymentDocument.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        paymentDocumentsService.destroy(paymentDocument.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('paymentDocuments.resource') })}>
            <Head title={t('common.editItem', { name: paymentDocument.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('paymentDocuments.title')}
                    title={t('paymentDocuments.editTitle')}
                    description={t('common.updateDetails', { name: paymentDocument.name })}
                    backHref={paymentDocumentsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('paymentDocuments.resourcePlural') })}
                />

                <PaymentDocumentForm
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
                            <Button type="button" variant="danger" onClick={destroyDocument}>
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
