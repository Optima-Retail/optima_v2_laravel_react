import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianRequestStatusForm } from '@/components/config/technician-request-statuses/TechnicianRequestStatusForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianRequestStatusesService } from '@/services';

type EditTechnicianRequestStatusProps = {
    technicianRequestStatus: {
        id: number;
        kind: string;
        name: string;
        color: string | null;
        lifecycle: number | null;
        is_open: boolean;
    };
    can: {
        delete: boolean;
    };
};

export default function EditTechnicianRequestStatus({
    technicianRequestStatus,
    can,
}: EditTechnicianRequestStatusProps) {
    const { t } = useTranslation();
    const form = useForm({
        kind: technicianRequestStatus.kind,
        name: technicianRequestStatus.name,
        color: technicianRequestStatus.color ?? '',
        lifecycle: technicianRequestStatus.lifecycle ?? '',
        is_open: technicianRequestStatus.is_open,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianRequestStatusesService.update(technicianRequestStatus.id, form);
    }

    async function handleDelete() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('technicianRequestStatuses.resource') }),
            message: t('common.deleteMessage', { name: technicianRequestStatus.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        technicianRequestStatusesService.destroy(technicianRequestStatus.id);
    }

    return (
        <AppLayout title={t('common.editItem', { name: technicianRequestStatus.name })}>
            <Head title={t('common.editItem', { name: technicianRequestStatus.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.statuses')}
                    title={t('technicianRequestStatuses.editTitle')}
                    backHref={technicianRequestStatusesService.indexPath}
                    backLabel={t('common.backTo', {
                        resource: t('technicianRequestStatuses.resourcePlural'),
                    })}
                />

                <TechnicianRequestStatusForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]:
                                key === 'lifecycle'
                                    ? value === ''
                                        ? ''
                                        : Number(value) || value
                                    : value,
                        }))
                    }
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
