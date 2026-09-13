import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianRequestPriorityForm } from '@/components/config/technician-request-priorities/TechnicianRequestPriorityForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianRequestPrioritiesService } from '@/services';

type EditTechnicianRequestPriorityProps = {
    technicianRequestPriority: {
        id: number;
        name: string;
        key: string;
        color: string | null;
    };
    can: {
        delete: boolean;
    };
};

export default function EditTechnicianRequestPriority({
    technicianRequestPriority,
    can,
}: EditTechnicianRequestPriorityProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: technicianRequestPriority.name,
        key: technicianRequestPriority.key,
        color: technicianRequestPriority.color ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianRequestPrioritiesService.update(technicianRequestPriority.id, form);
    }

    async function handleDelete() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('technicianRequestPriorities.resource') }),
            message: t('common.deleteMessage', { name: technicianRequestPriority.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        technicianRequestPrioritiesService.destroy(technicianRequestPriority.id);
    }

    return (
        <AppLayout title={t('common.editItem', { name: technicianRequestPriority.name })}>
            <Head title={t('common.editItem', { name: technicianRequestPriority.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('technicianRequestPriorities.title')}
                    title={t('technicianRequestPriorities.editTitle')}
                    backHref={technicianRequestPrioritiesService.indexPath}
                    backLabel={t('common.backTo', {
                        resource: t('technicianRequestPriorities.resourcePlural'),
                    })}
                />

                <TechnicianRequestPriorityForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={handleDelete}>
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
