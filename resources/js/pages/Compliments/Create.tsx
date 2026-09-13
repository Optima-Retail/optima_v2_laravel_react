import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ComplimentForm, defaultComplimentFormValues } from '@/components/compliments/ComplimentForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { complimentsService } from '@/services';

type Option = { id: number; label: string };

type CreateComplimentProps = {
    typeOptions: Option[];
    brandOptions: Option[];
    customerOptions: Option[];
    establishmentOptions: Option[];
    userOptions: Option[];
    can: {
        upload_attachments: boolean;
    };
};

export default function CreateCompliment({
    typeOptions,
    brandOptions,
    customerOptions,
    establishmentOptions,
    userOptions,
    can,
}: CreateComplimentProps) {
    const { t } = useTranslation();
    const form = useForm(defaultComplimentFormValues());

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(complimentsService.indexPath, {
            forceFormData: Boolean(form.data.file),
        });
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('compliments.resource') })}>
            <Head title={t('common.newItem', { resource: t('compliments.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('compliments.title')}
                    title={t('common.createItem', { resource: t('compliments.resource') })}
                    description={t('compliments.createDescription')}
                    backHref={complimentsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('compliments.resourcePlural') })}
                />

                <ComplimentForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    typeOptions={typeOptions}
                    brandOptions={brandOptions}
                    customerOptions={customerOptions}
                    establishmentOptions={establishmentOptions}
                    userOptions={userOptions}
                    showAttachmentField={can.upload_attachments}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('compliments.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
