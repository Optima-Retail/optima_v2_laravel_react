import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TeamForm } from '@/components/config/teams/TeamForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { teamsService } from '@/services';
import type { TeamFormData } from '@/support/types/domain';

type EditTeamProps = {
    team: TeamFormData;
    can: {
        delete: boolean;
    };
};

export default function EditTeam({ team, can }: EditTeamProps) {
    const { t } = useTranslation();
    const form = useForm({
        code: team.code,
        name: team.name,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        teamsService.update(team.id, form);
    }

    async function destroyTeam() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('teams.resource') }),
            message: t('common.deleteMessage', { name: team.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        teamsService.destroy(team.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('teams.resource') })}>
            <Head title={t('common.editItem', { name: team.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('teams.title')}
                    title={t('common.editResource', { resource: t('teams.resource') })}
                    description={t('common.updateDetails', { name: team.name })}
                    backHref={teamsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('teams.resourcePlural') })}
                />

                <TeamForm
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
                            <Button type="button" variant="danger" onClick={destroyTeam}>
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
