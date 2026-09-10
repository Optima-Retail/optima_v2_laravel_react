import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TaskToPerformForm } from '@/components/config/tasks-to-perform/TaskToPerformForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { tasksToPerformService } from '@/services';
import type { TaskDocumentTypeOption } from '@/support/types/domain/task-to-perform';

type CreateTaskToPerformProps = {
    documentTypeOptions: TaskDocumentTypeOption[];
};

export default function CreateTaskToPerform({ documentTypeOptions }: CreateTaskToPerformProps) {
    const { t } = useTranslation();
    const form = useForm({
        title: '',
        description: '',
        is_completed: false,
        document_type: documentTypeOptions[0]?.value ?? 'work_order',
        document_id: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        tasksToPerformService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('tasksToPerform.resource') })}>
            <Head title={t('common.newItem', { resource: t('tasksToPerform.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('tasksToPerform.title')}
                    title={t('common.createItem', { resource: t('tasksToPerform.resource') })}
                    description={t('tasksToPerform.createDescription')}
                    backHref={tasksToPerformService.indexPath}
                    backLabel={t('common.backTo', { resource: t('tasksToPerform.resourcePlural') })}
                />

                <TaskToPerformForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    documentTypeOptions={documentTypeOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('tasksToPerform.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
