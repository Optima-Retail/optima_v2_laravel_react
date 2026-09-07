import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CurrencyForm } from '@/components/config/currencies/CurrencyForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { currenciesService } from '@/services';
import type { CurrencyFormData } from '@/support/types/domain';

type EditCurrencyProps = {
    currency: CurrencyFormData;
    can: {
        delete: boolean;
    };
};

export default function EditCurrency({ currency, can }: EditCurrencyProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: currency.name,
        code: currency.code,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        currenciesService.update(currency.id, form);
    }

    async function destroyCurrency() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('currencies.resource') }),
            message: t('common.deleteMessage', { name: currency.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        currenciesService.destroy(currency.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('currencies.resource') })}>
            <Head title={t('common.editItem', { name: currency.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('currencies.title')}
                    title={t('common.editResource', { resource: t('currencies.resource') })}
                    description={t('common.updateDetails', { name: currency.name })}
                    backHref={currenciesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('currencies.resourcePlural') })}
                />

                <CurrencyForm
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
                            <Button type="button" variant="danger" onClick={destroyCurrency}>
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
