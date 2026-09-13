import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TechnicianIncidentTypeForm } from '@/components/config/technician-incident-types/TechnicianIncidentTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianIncidentTypesService } from '@/services';

export default function CreateTechnicianIncidentType() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        due_days: 1,
        send_mail_to_technician: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianIncidentTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('technicianIncidentTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('technicianIncidentTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('nav.types')}
                    title={t('common.createItem', { resource: t('technicianIncidentTypes.resource') })}
                    description={t('technicianIncidentTypes.createDescription')}
                    backHref={technicianIncidentTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('technicianIncidentTypes.resourcePlural') })}
                />

                <TechnicianIncidentTypeForm
                    mode="create"
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
                    submitLabel={t('common.createItem', { resource: t('technicianIncidentTypes.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
