import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormStatusForm } from '@/components/config/form-statuses/FormStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { formStatusesService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';

type CreateFormStatusProps = {
    statusOptions: UserOption[];
};

export default function CreateFormStatus({ statusOptions }: CreateFormStatusProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        next_status_id: '' as string | number | null,
        is_active: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        formStatusesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('formStatuses.resource') })}>
            <Head title={t('common.newItem', { resource: t('formStatuses.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('formStatuses.createTitle')}
                    description={t('formStatuses.createDescription')}
                    backHref={formStatusesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('formStatuses.resourcePlural') })}
                />

                <FormStatusForm
                    mode="create"
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
                    submitLabel={t('formStatuses.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
