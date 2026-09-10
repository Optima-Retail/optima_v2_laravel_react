import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TaskToPerformForm } from '@/components/config/tasks-to-perform/TaskToPerformForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { tasksToPerformService } from '@/services';
import type {
    TaskDocumentTypeOption,
    TaskToPerformFormData,
} from '@/support/types/domain/task-to-perform';

type EditTaskToPerformProps = {
    taskToPerform: TaskToPerformFormData;
    documentTypeOptions: TaskDocumentTypeOption[];
    can: {
        delete: boolean;
    };
};

export default function EditTaskToPerform({
    taskToPerform,
    documentTypeOptions,
    can,
}: EditTaskToPerformProps) {
    const { t } = useTranslation();
    const form = useForm({
        title: taskToPerform.title ?? '',
        description: taskToPerform.description ?? '',
        is_completed: taskToPerform.is_completed,
        document_type: taskToPerform.document_type,
        document_id: taskToPerform.document_id != null ? String(taskToPerform.document_id) : '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        tasksToPerformService.update(taskToPerform.id, form);
    }

    async function destroyTask() {
        const name = taskToPerform.title || String(taskToPerform.id);
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('tasksToPerform.resource') }),
            message: t('common.deleteMessage', { name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        tasksToPerformService.destroy(taskToPerform.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('tasksToPerform.resource') })}>
            <Head
                title={t('common.editItem', {
                    name: taskToPerform.title || String(taskToPerform.id),
                })}
            />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('tasksToPerform.title')}
                    title={t('common.editResource', { resource: t('tasksToPerform.resource') })}
                    description={t('common.updateDetails', {
                        name: taskToPerform.title || String(taskToPerform.id),
                    })}
                    backHref={tasksToPerformService.indexPath}
                    backLabel={t('common.backTo', { resource: t('tasksToPerform.resourcePlural') })}
                />

                <TaskToPerformForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    documentTypeOptions={documentTypeOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={() => void destroyTask()}>
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
