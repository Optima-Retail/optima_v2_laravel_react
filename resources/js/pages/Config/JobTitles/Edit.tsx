import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { JobTitleForm } from '@/components/config/job-titles/JobTitleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { jobTitlesService } from '@/services';
import type { JobTitleFormData } from '@/support/types/domain/job-title';

type EditJobTitleProps = {
    jobTitle: JobTitleFormData;
    can: {
        delete: boolean;
    };
};

export default function EditJobTitle({ jobTitle, can }: EditJobTitleProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: jobTitle.name,
        code: jobTitle.code,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        jobTitlesService.update(jobTitle.id, form);
    }

    async function destroyJobTitle() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('jobTitles.resource') }),
            message: t('common.deleteMessage', { name: jobTitle.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        jobTitlesService.destroy(jobTitle.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('jobTitles.resource') })}>
            <Head title={t('common.editItem', { name: jobTitle.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('jobTitles.title')}
                    title={t('common.editResource', { resource: t('jobTitles.resource') })}
                    description={t('common.updateDetails', { name: jobTitle.name })}
                    backHref={jobTitlesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('jobTitles.resourcePlural') })}
                />

                <JobTitleForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyJobTitle}>
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
