import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { establishmentFormValuesFromData, EstablishmentForm } from '@/components/establishments/EstablishmentForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { establishmentsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentFormData } from '@/support/types/domain/establishment';
import type { ProvinceOption } from '@/support/types/domain/province';

type EditEstablishmentProps = {
    establishment: EstablishmentFormData;
    companyOptions: UserOption[];
    countryOptions: UserOption[];
    provinceOptions: ProvinceOption[];
    timezoneOptions: UserOption[];
    delegationOptions: UserOption[];
    languageOptions: UserOption[];
    establishmentTypeOptions: UserOption[];
    seriesOptions: UserOption[];
    userOptions: UserOption[];
    can: {
        delete: boolean;
    };
};

export default function EditEstablishment({
    establishment,
    companyOptions,
    countryOptions,
    provinceOptions,
    timezoneOptions,
    delegationOptions,
    languageOptions,
    establishmentTypeOptions,
    seriesOptions,
    userOptions,
    can,
}: EditEstablishmentProps) {
    const { t } = useTranslation();
    const form = useForm(establishmentFormValuesFromData(establishment));

    function submit(event: FormEvent) {
        event.preventDefault();
        establishmentsService.update(establishment.id, form);
    }

    async function destroyEstablishment() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('establishments.resource') }),
            message: t('common.deleteMessage', { name: establishment.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        establishmentsService.destroy(establishment.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('establishments.resource') })}>
            <Head title={t('common.editItem', { name: establishment.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('establishments.title')}
                    title={t('common.editResource', { resource: t('establishments.resource') })}
                    description={t('common.updateDetails', { name: establishment.name })}
                    backHref={establishmentsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('establishments.resourcePlural') })}
                />

                <EstablishmentForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    companyOptions={companyOptions}
                    countryOptions={countryOptions}
                    provinceOptions={provinceOptions}
                    timezoneOptions={timezoneOptions}
                    delegationOptions={delegationOptions}
                    languageOptions={languageOptions}
                    establishmentTypeOptions={establishmentTypeOptions}
                    seriesOptions={seriesOptions}
                    userOptions={userOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyEstablishment}>
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
