import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BrandForm } from '@/components/config/brands/BrandForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { brandsService } from '@/services';
import type { UserOption } from '@/support/types/domain';

type CreateBrandProps = {
    userOptions: UserOption[];
};

export default function CreateBrand({ userOptions }: CreateBrandProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        account_manager_id: '',
        commercial_manager_id: '',
        collaborator_ids: [] as string[],
        loyalty_meeting_frequency: '',
        is_quality_control_contactable: true,
        send_debt_reminders: true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        brandsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('brands.resource') })}>
            <Head title={t('common.newItem', { resource: t('brands.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    title={t('common.createItem', { resource: t('brands.resource') })}
                    description={t('brands.createDescription')}
                    backHref={brandsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('brands.resourcePlural') })}
                />

                <BrandForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    userOptions={userOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('brands.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
