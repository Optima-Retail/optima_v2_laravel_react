import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { JobTitleForm } from '@/components/config/job-titles/JobTitleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { jobTitlesService } from '@/services';

export default function CreateJobTitle() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        jobTitlesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('jobTitles.resource') })}>
            <Head title={t('common.newItem', { resource: t('jobTitles.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('jobTitles.title')}
                    title={t('common.createItem', { resource: t('jobTitles.resource') })}
                    description={t('jobTitles.createDescription')}
                    backHref={jobTitlesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('jobTitles.resourcePlural') })}
                />

                <JobTitleForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('jobTitles.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
