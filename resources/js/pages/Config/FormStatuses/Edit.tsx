import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormStatusForm } from '@/components/config/form-statuses/FormStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { formStatusesService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { FormStatusFormData } from '@/support/types/domain/form-status';

type EditFormStatusProps = {
    formStatus: FormStatusFormData;
    statusOptions: UserOption[];
    can: {
        delete: boolean;
    };
};

export default function EditFormStatus({ formStatus, statusOptions, can }: EditFormStatusProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: formStatus.name,
        next_status_id: formStatus.next_status_id,
        is_active: formStatus.is_active,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        formStatusesService.update(formStatus.id, form);
    }

    async function destroyStatus() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('formStatuses.resource') }),
            message: t('common.deleteMessage', { name: formStatus.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        formStatusesService.destroy(formStatus.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('formStatuses.resource') })}>
            <Head title={t('common.editItem', { name: formStatus.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('formStatuses.editTitle')}
                    description={t('common.updateDetails', { name: formStatus.name })}
                    backHref={formStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('formStatuses.resourcePlural') })}
                />

                <FormStatusForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    statusOptions={statusOptions}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]:
                                key === 'next_status_id'
                                    ? value === ''
                                        ? null
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
