import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianAttendanceConfirmationTypeForm } from '@/components/config/technician-attendance-confirmation-types/TechnicianAttendanceConfirmationTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianAttendanceConfirmationTypesService } from '@/services';

export default function CreateTechnicianAttendanceConfirmationType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianAttendanceConfirmationTypesService.store(form);
    }

    return (
        <AppLayout
            title={t('common.newItem', {
                resource: t('technicianAttendanceConfirmationTypes.resource'),
            })}
        >
            <Head
                title={t('common.newItem', {
                    resource: t('technicianAttendanceConfirmationTypes.resource'),
                })}
            />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('technicianAttendanceConfirmationTypes.title')}
                    title={t('technicianAttendanceConfirmationTypes.createTitle')}
                    description={t('technicianAttendanceConfirmationTypes.createDescription')}
                    backHref={technicianAttendanceConfirmationTypesService.indexPath}
                    backLabel={t('common.backTo', {
                        resource: t('technicianAttendanceConfirmationTypes.resourcePlural'),
                    })}
                />

                <TechnicianAttendanceConfirmationTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('technicianAttendanceConfirmationTypes.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
