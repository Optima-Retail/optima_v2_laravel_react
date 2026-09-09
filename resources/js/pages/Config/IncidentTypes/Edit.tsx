import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    defaultIncidentTypeFormValues,
    IncidentTypeForm,
} from '@/components/config/incident-types/IncidentTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { incidentTypesService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { IncidentTypeFormData } from '@/support/types/domain/incident-type';

type EditIncidentTypeProps = {
    incidentType: IncidentTypeFormData;
    incidentPriorityOptions: UserOption[];
    can: {
        delete: boolean;
    };
};

export default function EditIncidentType({
    incidentType,
    incidentPriorityOptions,
    can,
}: EditIncidentTypeProps) {
    const { t } = useTranslation();
    const form = useForm(
        defaultIncidentTypeFormValues({
            name: incidentType.name,
            color: incidentType.color ?? '#FFFFFF',
            default_priority_id:
                incidentType.default_priority_id !== null
                    ? String(incidentType.default_priority_id)
                    : '',
            origin_selectable: incidentType.origin_selectable,
            origin_options: incidentType.origin_options,
            default_origin_type: incidentType.default_origin_type ?? '',
            origin_required: incidentType.origin_required,
            related_type: incidentType.related_type ?? '',
            show_related: incidentType.show_related,
        }),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        incidentTypesService.update(incidentType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('incidentTypes.resource') }),
            message: t('common.deleteMessage', { name: incidentType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        incidentTypesService.destroy(incidentType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('incidentTypes.resource') })}>
            <Head title={t('common.editItem', { name: incidentType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('incidentTypes.title')}
                    title={t('incidentTypes.editTitle')}
                    description={t('common.updateDetails', { name: incidentType.name })}
                    backHref={incidentTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('incidentTypes.resourcePlural') })}
                />

                <IncidentTypeForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    incidentPriorityOptions={incidentPriorityOptions}
                    onChange={(key, value) => form.setData(key, value)}
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
