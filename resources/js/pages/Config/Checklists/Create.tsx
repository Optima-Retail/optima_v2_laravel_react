import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ChecklistForm } from '@/components/config/checklists/ChecklistForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { checklistsService } from '@/services';
import type {
    ChecklistDocumentTypeOption,
    ChecklistStatusOption,
} from '@/support/types/domain/checklist';

type CreateChecklistProps = {
    documentTypeOptions: ChecklistDocumentTypeOption[];
    workOrderStatusOptions: ChecklistStatusOption[];
    estimateStatusOptions: ChecklistStatusOption[];
};

export default function CreateChecklist({
    documentTypeOptions,
    workOrderStatusOptions,
    estimateStatusOptions,
}: CreateChecklistProps) {
    const { t } = useTranslation();
    const form = useForm({
        label: '',
        requires_validation: true,
        document_type: 'work_order',
        work_order_status_id: '',
        sort_order: 0,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        checklistsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('checklists.resource') })}>
            <Head title={t('common.newItem', { resource: t('checklists.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('checklists.title')}
                    title={t('checklists.createTitle')}
                    description={t('checklists.createDescription')}
                    backHref={checklistsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('checklists.resourcePlural') })}
                />

                <ChecklistForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    documentTypeOptions={documentTypeOptions}
                    workOrderStatusOptions={workOrderStatusOptions}
                    estimateStatusOptions={estimateStatusOptions}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]:
                                key === 'sort_order'
                                    ? value === ''
                                        ? ''
                                        : Number(value) || value
                                    : value,
                            ...(key === 'document_type'
                                ? {
                                      work_order_status_id: '',
                                  }
                                : {}),
                        }))
                    }
                    onSubmit={submit}
                    submitLabel={t('checklists.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
