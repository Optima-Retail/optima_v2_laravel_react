import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ContractStatusForm } from '@/components/config/contract-statuses/ContractStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { contractStatusesService } from '@/services';

export default function CreateContractStatus() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        color: '#a9cef0',
        lifecycle: 1,
        is_open: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        contractStatusesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('contractStatuses.resource') })}>
            <Head title={t('common.newItem', { resource: t('contractStatuses.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('contractStatuses.createTitle')}
                    description={t('contractStatuses.createDescription')}
                    backHref={contractStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('contractStatuses.resourcePlural') })}
                />

                <ContractStatusForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]:
                                key === 'lifecycle'
                                    ? value === ''
                                        ? ''
                                        : Number(value) || value
                                    : value,
                        }))
                    }
                    onSubmit={submit}
                    submitLabel={t('contractStatuses.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
