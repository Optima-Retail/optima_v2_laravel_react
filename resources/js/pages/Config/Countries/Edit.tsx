import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CountryForm } from '@/components/config/countries/CountryForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { countriesService } from '@/services';
import type { CountryFormData, TimezoneOption } from '@/support/types/domain';

type EditCountryProps = {
    country: CountryFormData;
    timezoneOptions: TimezoneOption[];
    can: {
        delete: boolean;
    };
};

export default function EditCountry({ country, timezoneOptions, can }: EditCountryProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: country.name,
        iso_code: country.iso_code ?? '',
        timezone_id: country.timezone_id !== null ? String(country.timezone_id) : '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        countriesService.update(country.id, form);
    }

    async function destroyCountry() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('countries.resource') }),
            message: t('common.deleteMessage', { name: country.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        countriesService.destroy(country.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('countries.resource') })}>
            <Head title={t('common.editItem', { name: country.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('countries.title')}
                    title={t('common.editResource', { resource: t('countries.resource') })}
                    description={t('common.updateDetails', { name: country.name })}
                    backHref={countriesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('countries.resourcePlural') })}
                />

                <CountryForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    timezoneOptions={timezoneOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyCountry}>
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
