import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BankForm } from '@/components/config/banks/BankForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { banksService } from '@/services';
import type { CountryOption } from '@/support/types/domain/country';

type CreateBankProps = {
    countryOptions: CountryOption[];
};

export default function CreateBank({ countryOptions }: CreateBankProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        legal_name: '',
        country_id: '1',
        swift_bic: '',
        national_bank_code: '',
        lei: '',
        supervisor_code: '',
        website: '',
        is_active: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        banksService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('banks.resource') })}>
            <Head title={t('common.newItem', { resource: t('banks.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('banks.title')}
                    title={t('common.createItem', { resource: t('banks.resource') })}
                    description={t('banks.createDescription')}
                    backHref={banksService.indexPath}
                    backLabel={t('common.backTo', { resource: t('banks.resourcePlural') })}
                />

                <BankForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    countryOptions={countryOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('banks.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
