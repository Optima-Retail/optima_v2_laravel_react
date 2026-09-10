import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PaymentMethodForm } from '@/components/config/payment-methods/PaymentMethodForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { paymentMethodsService } from '@/services';
import type { PaymentMethodFormData } from '@/support/types/domain/payment-method';

type EditPaymentMethodProps = {
    paymentMethod: PaymentMethodFormData;
    can: {
        delete: boolean;
    };
};

export default function EditPaymentMethod({ paymentMethod, can }: EditPaymentMethodProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: paymentMethod.name,
        due_count: paymentMethod.due_count != null ? String(paymentMethod.due_count) : '',
        days: paymentMethod.days != null ? String(paymentMethod.days) : '',
        code: paymentMethod.code ?? '',
        is_active: paymentMethod.is_active,
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
        paymentMethodsService.update(paymentMethod.id, form);
    }

    async function destroyMethod() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('paymentMethods.resource') }),
            message: t('common.deleteMessage', { name: paymentMethod.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        paymentMethodsService.destroy(paymentMethod.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('paymentMethods.resource') })}>
            <Head title={t('common.editItem', { name: paymentMethod.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('paymentMethods.title')}
                    title={t('paymentMethods.editTitle')}
                    description={t('common.updateDetails', { name: paymentMethod.name })}
                    backHref={paymentMethodsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('paymentMethods.resourcePlural') })}
                />

                <PaymentMethodForm
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
                            <Button type="button" variant="danger" onClick={destroyMethod}>
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
