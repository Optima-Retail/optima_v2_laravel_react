import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BankForm } from '@/components/config/banks/BankForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { banksService } from '@/services';
import type { BankFormData } from '@/support/types/domain/bank';
import type { CountryOption } from '@/support/types/domain/country';

type EditBankProps = {
    bank: BankFormData;
    countryOptions: CountryOption[];
    can: {
        delete: boolean;
    };
};

export default function EditBank({ bank, countryOptions, can }: EditBankProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: bank.name,
        legal_name: bank.legal_name ?? '',
        country_id: bank.country_id !== null ? String(bank.country_id) : '1',
        swift_bic: bank.swift_bic ?? '',
        national_bank_code: bank.national_bank_code ?? '',
        lei: bank.lei ?? '',
        supervisor_code: bank.supervisor_code ?? '',
        website: bank.website ?? '',
        is_active: bank.is_active,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        banksService.update(bank.id, form);
    }

    async function destroyBank() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('banks.resource') }),
            message: t('common.deleteMessage', { name: bank.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        banksService.destroy(bank.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('banks.resource') })}>
            <Head title={t('common.editItem', { name: bank.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('banks.title')}
                    title={t('common.editResource', { resource: t('banks.resource') })}
                    description={t('common.updateDetails', { name: bank.name })}
                    backHref={banksService.indexPath}
                    backLabel={t('common.backTo', { resource: t('banks.resourcePlural') })}
                />

                <BankForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    countryOptions={countryOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyBank}>
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
