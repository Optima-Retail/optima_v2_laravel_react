import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ChecklistForm } from '@/components/config/checklists/ChecklistForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { checklistsService } from '@/services';
import type {
    ChecklistDocumentTypeOption,
    ChecklistFormData,
    ChecklistStatusOption,
} from '@/support/types/domain/checklist';

type EditChecklistProps = {
    checklist: ChecklistFormData;
    documentTypeOptions: ChecklistDocumentTypeOption[];
    workOrderStatusOptions: ChecklistStatusOption[];
    estimateStatusOptions: ChecklistStatusOption[];
    can: {
        delete: boolean;
    };
};

export default function EditChecklist({
    checklist,
    documentTypeOptions,
    workOrderStatusOptions,
    estimateStatusOptions,
    can,
}: EditChecklistProps) {
    const { t } = useTranslation();
    const form = useForm({
        label: checklist.label,
        requires_validation: checklist.requires_validation,
        document_type: checklist.document_type,
        work_order_status_id: checklist.work_order_status_id ? String(checklist.work_order_status_id) : '',
        sort_order: checklist.sort_order,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        checklistsService.update(checklist.id, form);
    }

    async function destroyChecklist() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('checklists.resource') }),
            message: t('common.deleteMessage', { name: checklist.label }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        checklistsService.destroy(checklist.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('checklists.resource') })}>
            <Head title={t('common.editItem', { name: checklist.label })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('checklists.title')}
                    title={t('checklists.editTitle')}
                    description={t('common.updateDetails', { name: checklist.label })}
                    backHref={checklistsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('checklists.resourcePlural') })}
                />

                <ChecklistForm
                    mode="edit"
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
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyChecklist}>
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
