import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    defaultIncidentTypeFormValues,
    IncidentTypeForm,
} from '@/components/config/incident-types/IncidentTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { incidentTypesService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';

type CreateIncidentTypeProps = {
    incidentPriorityOptions: UserOption[];
};

export default function CreateIncidentType({ incidentPriorityOptions }: CreateIncidentTypeProps) {
    const { t } = useTranslation();
    const form = useForm(defaultIncidentTypeFormValues());

    function submit(event: FormEvent) {
        event.preventDefault();
        incidentTypesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('incidentTypes.resource') })}>
            <Head title={t('common.newItem', { resource: t('incidentTypes.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('incidentTypes.title')}
                    title={t('incidentTypes.createTitle')}
                    description={t('incidentTypes.createDescription')}
                    backHref={incidentTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('incidentTypes.resourcePlural') })}
                />

                <IncidentTypeForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    incidentPriorityOptions={incidentPriorityOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('incidentTypes.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
