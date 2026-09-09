import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BrandForm } from '@/components/config/brands/BrandForm';
import { BrandMessagesPanel } from '@/components/config/brands/BrandMessagesPanel';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { brandsService } from '@/services';
import type { BrandFormData, BrandMessageItem } from '@/support/types/domain/brand';
import type { UserOption } from '@/support/types/domain/common';

type EditBrandProps = {
    brand: BrandFormData;
    userOptions: UserOption[];
    messages: BrandMessageItem[];
    can: {
        delete: boolean;
        view_messages: boolean;
        send_messages: boolean;
        view_message_files: boolean;
        download_message_files: boolean;
        send_message_files: boolean;
    };
};

export default function EditBrand({ brand, userOptions, messages, can }: EditBrandProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: brand.name,
        account_manager_id: brand.account_manager_id !== null ? String(brand.account_manager_id) : '',
        commercial_manager_id: brand.commercial_manager_id !== null ? String(brand.commercial_manager_id) : '',
        collaborator_ids: brand.collaborator_ids.map(String),
        loyalty_meeting_frequency: brand.loyalty_meeting_frequency ?? '',
        is_quality_control_contactable: brand.is_quality_control_contactable,
        send_debt_reminders: brand.send_debt_reminders,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        brandsService.update(brand.id, form);
    }

    async function destroyBrand() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('brands.resource') }),
            message: t('common.deleteMessage', { name: brand.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (confirmed) {
            brandsService.destroy(brand.id);
        }
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('brands.resource') })}>
            <Head title={t('common.editItem', { name: brand.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    title={t('common.editResource', { resource: t('brands.resource') })}
                    description={t('common.updateDetails', { name: brand.name })}
                    backHref={brandsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('brands.resourcePlural') })}
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.9fr)]">
                    <BrandForm
                        values={form.data}
                        errors={form.errors}
                        processing={form.processing}
                        userOptions={userOptions}
                        onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                        onSubmit={submit}
                        submitLabel={t('common.save')}
                        submitIcon={<Save className="size-4" aria-hidden />}
                        actions={
                            can.delete ? (
                                <Button type="button" variant="danger" onClick={destroyBrand}>
                                    <Trash2 className="size-4" aria-hidden />
                                    {t('common.delete')}
                                </Button>
                            ) : null
                        }
                    />

                    {can.view_messages ? (
                        <BrandMessagesPanel
                            brandId={brand.id}
                            messages={messages}
                            can={{
                                send_messages: can.send_messages,
                                view_message_files: can.view_message_files,
                                download_message_files: can.download_message_files,
                                send_message_files: can.send_message_files,
                            }}
                        />
                    ) : null}
                </div>
            </div>
        </AppLayout>
    );
}
