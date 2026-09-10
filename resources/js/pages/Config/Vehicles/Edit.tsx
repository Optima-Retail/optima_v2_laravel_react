import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { VehicleForm } from '@/components/config/vehicles/VehicleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { vehiclesService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { VehicleFormData } from '@/support/types/domain/vehicle';

type EditVehicleProps = {
    vehicle: VehicleFormData;
    technicianOptions: UserOption[];
    can: {
        delete: boolean;
    };
};

function vehicleDisplayName(vehicle: VehicleFormData): string {
    const parts = [vehicle.brand, vehicle.model, vehicle.license_plate].filter(Boolean);

    return parts.length > 0 ? parts.join(' ') : String(vehicle.id);
}

export default function EditVehicle({ vehicle, technicianOptions, can }: EditVehicleProps) {
    const { t } = useTranslation();
    const form = useForm({
        brand: vehicle.brand ?? '',
        model: vehicle.model ?? '',
        license_plate: vehicle.license_plate ?? '',
        company_relationship_id: vehicle.company_relationship_id
            ? String(vehicle.company_relationship_id)
            : '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        vehiclesService.update(vehicle.id, form);
    }

    async function destroyVehicle() {
        const name = vehicleDisplayName(vehicle);
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('vehicles.resource') }),
            message: t('common.deleteMessage', { name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        vehiclesService.destroy(vehicle.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('vehicles.resource') })}>
            <Head title={t('common.editItem', { name: vehicleDisplayName(vehicle) })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('vehicles.title')}
                    title={t('common.editResource', { resource: t('vehicles.resource') })}
                    description={t('common.updateDetails', { name: vehicleDisplayName(vehicle) })}
                    backHref={vehiclesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('vehicles.resourcePlural') })}
                />

                <VehicleForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    technicianOptions={technicianOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyVehicle}>
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
