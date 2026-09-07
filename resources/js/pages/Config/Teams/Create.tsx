import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TeamForm } from '@/components/config/teams/TeamForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { teamsService } from '@/services';

export default function CreateTeam() {
    const { t } = useTranslation();
    const form = useForm({
        code: '',
        name: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        teamsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('teams.resource') })}>
            <Head title={t('common.newItem', { resource: t('teams.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('teams.title')}
                    title={t('common.createItem', { resource: t('teams.resource') })}
                    description={t('teams.createDescription')}
                    backHref={teamsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('teams.resourcePlural') })}
                />

                <TeamForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('teams.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
