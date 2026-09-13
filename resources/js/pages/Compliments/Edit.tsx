import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ComplimentAttachmentsPanel } from '@/components/compliments/ComplimentAttachmentsPanel';
import { ComplimentForm, defaultComplimentFormValues } from '@/components/compliments/ComplimentForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { complimentsService } from '@/services';
import type { ComplimentAttachmentItem } from '@/support/types/domain/compliment';

type Option = { id: number; label: string };

type ComplimentFormData = {
    id: number;
    subject_type: string;
    brand_id: number | null;
    company_relationship_id: number | null;
    establishment_id: number | null;
    compliment_type_id: number;
    comment: string | null;
    user_ids: number[];
    score: number;
    subject_label: string;
    type_name: string | null;
};

type EditComplimentProps = {
    compliment: ComplimentFormData;
    attachments: ComplimentAttachmentItem[];
    typeOptions: Option[];
    brandOptions: Option[];
    customerOptions: Option[];
    establishmentOptions: Option[];
    userOptions: Option[];
    can: {
        delete: boolean;
        view_attachments: boolean;
        upload_attachments: boolean;
        download_attachments: boolean;
        delete_attachments: boolean;
    };
};

export default function EditCompliment({
    compliment,
    attachments,
    typeOptions,
    brandOptions,
    customerOptions,
    establishmentOptions,
    userOptions,
    can,
}: EditComplimentProps) {
    const { t } = useTranslation();
    const displayName = [compliment.type_name, compliment.subject_label].filter(Boolean).join(' · ')
        || `#${compliment.id}`;
    const form = useForm(
        defaultComplimentFormValues({
            subject_type: compliment.subject_type,
            brand_id: compliment.brand_id ? String(compliment.brand_id) : '',
            company_relationship_id: compliment.company_relationship_id
                ? String(compliment.company_relationship_id)
                : '',
            establishment_id: compliment.establishment_id ? String(compliment.establishment_id) : '',
            compliment_type_id: String(compliment.compliment_type_id),
            comment: compliment.comment ?? '',
            user_ids: compliment.user_ids.map(String),
            score: String(compliment.score || 1),
        }),
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        complimentsService.update(compliment.id, form);
    }

    async function destroyCompliment() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('compliments.resource') }),
            message: t('common.deleteMessage', { name: displayName }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        complimentsService.destroy(compliment.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('compliments.resource') })}>
            <Head title={t('common.editItem', { name: displayName })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('compliments.title')}
                    title={t('common.editResource', { resource: t('compliments.resource') })}
                    description={t('common.updateDetails', { name: displayName })}
                    backHref={complimentsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('compliments.resourcePlural') })}
                />

                <div
                    className={
                        can.view_attachments
                            ? 'grid gap-6 lg:grid-cols-2 lg:items-start'
                            : undefined
                    }
                >
                    <ComplimentForm
                        values={form.data}
                        errors={form.errors}
                        processing={form.processing}
                        typeOptions={typeOptions}
                        brandOptions={brandOptions}
                        customerOptions={customerOptions}
                        establishmentOptions={establishmentOptions}
                        userOptions={userOptions}
                        onChange={(key, value) => form.setData(key, value)}
                        onSubmit={submit}
                        submitLabel={t('common.save')}
                        submitIcon={<Save className="size-4" aria-hidden />}
                        actions={
                            can.delete ? (
                                <Button type="button" variant="danger" onClick={destroyCompliment}>
                                    <Trash2 className="size-4" aria-hidden />
                                    {t('common.delete')}
                                </Button>
                            ) : null
                        }
                    />

                    {can.view_attachments ? (
                        <div className="lg:sticky lg:top-4">
                            <ComplimentAttachmentsPanel
                                complimentId={compliment.id}
                                attachments={attachments}
                                can={{
                                    upload_attachments: can.upload_attachments,
                                    download_attachments: can.download_attachments,
                                    delete_attachments: can.delete_attachments,
                                }}
                            />
                        </div>
                    ) : null}
                </div>
            </div>
        </AppLayout>
    );
}
