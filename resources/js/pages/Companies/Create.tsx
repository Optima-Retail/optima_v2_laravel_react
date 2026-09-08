import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CompanyForm } from '@/components/companies/CompanyForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { companiesService } from '@/services';
import type { ProvinceOption, UserOption } from '@/support/types/domain';

type CreateCompanyProps = {
    countryOptions: UserOption[];
    provinceOptions: ProvinceOption[];
    brandOptions: UserOption[];
    languageOptions: UserOption[];
};

export default function CreateCompany({ countryOptions, provinceOptions, brandOptions, languageOptions }: CreateCompanyProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        tradename: '',
        tax_id: '',
        kind: 'party',
        country_id: '',
        residence_country_id: '',
        person_type: '',
        email: '',
        phone: '',
        website: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        province_id: '',
        postal_code: '',
        employee_count: '',
        is_active: true,
        brand_id: '',
        language_id: '',
        latitude: '',
        longitude: '',
        legacy_erp_id: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        companiesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('companies.resource') })}>
            <Head title={t('common.newItem', { resource: t('companies.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('companies.title')}
                    title={t('common.createItem', { resource: t('companies.resource') })}
                    description={t('companies.createDescription')}
                    backHref={companiesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('companies.resourcePlural') })}
                />

                <CompanyForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    countryOptions={countryOptions}
                    provinceOptions={provinceOptions}
                    brandOptions={brandOptions}
                    languageOptions={languageOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('companies.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
