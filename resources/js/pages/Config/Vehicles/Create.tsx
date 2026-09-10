import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { VehicleForm } from '@/components/config/vehicles/VehicleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { vehiclesService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';

type CreateVehicleProps = {
    technicianOptions: UserOption[];
};

export default function CreateVehicle({ technicianOptions }: CreateVehicleProps) {
    const { t } = useTranslation();
    const form = useForm({
        brand: '',
        model: '',
        license_plate: '',
        company_relationship_id: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        vehiclesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('vehicles.resource') })}>
            <Head title={t('common.newItem', { resource: t('vehicles.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('vehicles.title')}
                    title={t('common.createItem', { resource: t('vehicles.resource') })}
                    description={t('vehicles.createDescription')}
                    backHref={vehiclesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('vehicles.resourcePlural') })}
                />

                <VehicleForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    technicianOptions={technicianOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('vehicles.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
