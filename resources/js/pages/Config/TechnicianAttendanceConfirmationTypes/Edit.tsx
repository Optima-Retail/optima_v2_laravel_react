import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianAttendanceConfirmationTypeForm } from '@/components/config/technician-attendance-confirmation-types/TechnicianAttendanceConfirmationTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianAttendanceConfirmationTypesService } from '@/services';
import type { TechnicianAttendanceConfirmationTypeFormData } from '@/support/types/domain/technician-attendance-confirmation-type';

type EditProps = {
    confirmationType: TechnicianAttendanceConfirmationTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditTechnicianAttendanceConfirmationType({
    confirmationType,
    can,
}: EditProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: confirmationType.name,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianAttendanceConfirmationTypesService.update(confirmationType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', {
                resource: t('technicianAttendanceConfirmationTypes.resource'),
            }),
            message: t('common.deleteMessage', { name: confirmationType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        technicianAttendanceConfirmationTypesService.destroy(confirmationType.id);
    }

    return (
        <AppLayout
            title={t('common.editResource', {
                resource: t('technicianAttendanceConfirmationTypes.resource'),
            })}
        >
            <Head title={t('common.editItem', { name: confirmationType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('technicianAttendanceConfirmationTypes.title')}
                    title={t('technicianAttendanceConfirmationTypes.editTitle')}
                    description={t('common.updateDetails', { name: confirmationType.name })}
                    backHref={technicianAttendanceConfirmationTypesService.indexPath}
                    backLabel={t('common.backTo', {
                        resource: t('technicianAttendanceConfirmationTypes.resourcePlural'),
                    })}
                />

                <TechnicianAttendanceConfirmationTypeForm
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
