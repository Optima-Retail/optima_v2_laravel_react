import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EstablishmentTypeForm } from '@/components/config/establishmentTypes/EstablishmentTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { establishmentTypesService } from '@/services';
import type { EstablishmentTypeFormData } from '@/support/types/domain/establishment-type';

type EditEstablishmentTypeProps = {
    establishmentType: EstablishmentTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditEstablishmentType({ establishmentType, can }: EditEstablishmentTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: establishmentType.name,
        code: establishmentType.code,
        health_and_safety_delay_days: String(establishmentType.health_and_safety_delay_days),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        establishmentTypesService.update(establishmentType.id, form);
    }

    async function destroyEstablishmentType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('establishmentTypes.resource') }),
            message: t('common.deleteMessage', { name: establishmentType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        establishmentTypesService.destroy(establishmentType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('establishmentTypes.resource') })}>
            <Head title={t('common.editItem', { name: establishmentType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('establishmentTypes.title')}
                    title={t('common.editResource', { resource: t('establishmentTypes.resource') })}
                    description={t('common.updateDetails', { name: establishmentType.name })}
                    backHref={establishmentTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('establishmentTypes.resourcePlural') })}
                />

                <EstablishmentTypeForm
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
                            <Button type="button" variant="danger" onClick={destroyEstablishmentType}>
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
