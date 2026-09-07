import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CurrencyForm } from '@/components/config/currencies/CurrencyForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { currenciesService } from '@/services';

export default function CreateCurrency() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        currenciesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('currencies.resource') })}>
            <Head title={t('common.newItem', { resource: t('currencies.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('currencies.title')}
                    title={t('common.createItem', { resource: t('currencies.resource') })}
                    description={t('currencies.createDescription')}
                    backHref={currenciesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('currencies.resourcePlural') })}
                />

                <CurrencyForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('currencies.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
