import { FormEvent, useMemo } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    defaultTechnicianRequestFormValues,
    TechnicianRequestForm,
} from '@/components/technician-requests/TechnicianRequestForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { technicianRequestsService } from '@/services';

type Option = { id: number; label: string; color?: string | null; kind?: string };

type CreateTechnicianRequestProps = {
    defaultIsScreening: boolean;
    defaultStatusId: number | null;
    statusOptions: Option[];
    priorityOptions: Option[];
    userOptions: Option[];
    languageOptions: Option[];
    countryOptions: Option[];
    serviceTypeOptions: Option[];
    workOrderOptions: Option[];
};

export default function CreateTechnicianRequest({
    defaultIsScreening,
    defaultStatusId,
    statusOptions,
    priorityOptions,
    userOptions,
    languageOptions,
    countryOptions,
    serviceTypeOptions,
    workOrderOptions,
}: CreateTechnicianRequestProps) {
    const { t } = useTranslation();
    const form = useForm(
        defaultTechnicianRequestFormValues({
            is_screening: defaultIsScreening,
            technician_request_status_id: defaultStatusId ? String(defaultStatusId) : '',
        }),
    );

    const filteredStatuses = useMemo(() => {
        const kind = form.data.is_screening ? 'screening' : 'request';

        return statusOptions.filter((option) => !option.kind || option.kind === kind);
    }, [form.data.is_screening, statusOptions]);

    function submit(event: FormEvent) {
        event.preventDefault();
        technicianRequestsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('technicianRequests.resource') })}>
            <Head title={t('common.newItem', { resource: t('technicianRequests.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('technicianRequests.title')}
                    title={t('common.createItem', { resource: t('technicianRequests.resource') })}
                    description={t('technicianRequests.createDescription')}
                    backHref={technicianRequestsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('technicianRequests.resourcePlural') })}
                />

                <TechnicianRequestForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    statusOptions={filteredStatuses}
                    priorityOptions={priorityOptions}
                    userOptions={userOptions}
                    languageOptions={languageOptions}
                    countryOptions={countryOptions}
                    serviceTypeOptions={serviceTypeOptions}
                    workOrderOptions={workOrderOptions}
                    onChange={(key, value) => {
                        form.setData((data) => {
                            const next = {
                                ...data,
                                [key]: value,
                            };

                            if (key === 'is_screening') {
                                const screening = Boolean(value);
                                const kind = screening ? 'screening' : 'request';
                                const defaultId = screening ? '59' : '38';
                                const stillValid = statusOptions.some(
                                    (option) =>
                                        String(option.id) === data.technician_request_status_id &&
                                        (!option.kind || option.kind === kind),
                                );

                                next.technician_request_status_id = stillValid
                                    ? data.technician_request_status_id
                                    : defaultId;
                            }

                            return next;
                        });
                    }}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('technicianRequests.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
