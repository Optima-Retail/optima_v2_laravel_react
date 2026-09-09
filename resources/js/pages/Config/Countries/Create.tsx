import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CountryForm } from '@/components/config/countries/CountryForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { countriesService } from '@/services';
import type { TimezoneOption } from '@/support/types/domain/timezone';

type CreateCountryProps = {
    timezoneOptions: TimezoneOption[];
};

export default function CreateCountry({ timezoneOptions }: CreateCountryProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        iso_code: '',
        timezone_id: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        countriesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('countries.resource') })}>
            <Head title={t('common.newItem', { resource: t('countries.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('countries.title')}
                    title={t('common.createItem', { resource: t('countries.resource') })}
                    description={t('countries.createDescription')}
                    backHref={countriesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('countries.resourcePlural') })}
                />

                <CountryForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    timezoneOptions={timezoneOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('countries.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
