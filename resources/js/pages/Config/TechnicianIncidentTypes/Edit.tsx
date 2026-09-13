import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianIncidentTypeForm } from '@/components/config/technician-incident-types/TechnicianIncidentTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianIncidentTypesService } from '@/services';
import type { TechnicianIncidentTypeFormData } from '@/support/types/domain/technician-incident-type';

type EditTechnicianIncidentTypeProps = {
    technicianIncidentType: TechnicianIncidentTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditTechnicianIncidentType({
    technicianIncidentType,
    can,
}: EditTechnicianIncidentTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: technicianIncidentType.name,
        due_days: technicianIncidentType.due_days,
        send_mail_to_technician: technicianIncidentType.send_mail_to_technician,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianIncidentTypesService.update(technicianIncidentType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('technicianIncidentTypes.resource') }),
            message: t('common.deleteMessage', { name: technicianIncidentType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        technicianIncidentTypesService.destroy(technicianIncidentType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('technicianIncidentTypes.resource') })}>
            <Head title={t('common.editItem', { name: technicianIncidentType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.types')}
                    title={t('common.editResource', { resource: t('technicianIncidentTypes.resource') })}
                    description={t('common.updateDetails', { name: technicianIncidentType.name })}
                    backHref={technicianIncidentTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('technicianIncidentTypes.resourcePlural') })}
                />

                <TechnicianIncidentTypeForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]:
                                key === 'due_days'
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
                            <Button type="button" variant="danger" onClick={destroyType}>
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
