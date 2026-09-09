import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ContractStatusForm } from '@/components/config/contract-statuses/ContractStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { contractStatusesService } from '@/services';
import type { ContractStatusFormData } from '@/support/types/domain/contract-status';

type EditContractStatusProps = {
    contractStatus: ContractStatusFormData;
    can: {
        delete: boolean;
    };
};

export default function EditContractStatus({ contractStatus, can }: EditContractStatusProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: contractStatus.name,
        color: contractStatus.color ?? '#a9cef0',
        lifecycle: contractStatus.lifecycle ?? '',
        is_open: contractStatus.is_open,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        contractStatusesService.update(contractStatus.id, form);
    }

    async function destroyStatus() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('contractStatuses.resource') }),
            message: t('common.deleteMessage', { name: contractStatus.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        contractStatusesService.destroy(contractStatus.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('contractStatuses.resource') })}>
            <Head title={t('common.editItem', { name: contractStatus.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('contractStatuses.editTitle')}
                    description={t('common.updateDetails', { name: contractStatus.name })}
                    backHref={contractStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('contractStatuses.resourcePlural') })}
                />

                <ContractStatusForm
                    mode="edit"
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
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyStatus}>
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
