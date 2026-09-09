import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { IntegrationForm } from '@/components/config/integrations/IntegrationForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { integrationsService } from '@/services';
import type { IntegrationFormData } from '@/support/types/domain/integration';

type EditIntegrationProps = {
    integration: IntegrationFormData;
    can: {
        delete: boolean;
    };
};

export default function EditIntegration({ integration, can }: EditIntegrationProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: integration.name,
        code: integration.code,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        integrationsService.update(integration.id, form);
    }

    async function destroyIntegration() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('integrations.resource') }),
            message: t('common.deleteMessage', { name: integration.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        integrationsService.destroy(integration.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('integrations.resource') })}>
            <Head title={t('common.editItem', { name: integration.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('integrations.title')}
                    title={t('common.editResource', { resource: t('integrations.resource') })}
                    description={t('common.updateDetails', { name: integration.name })}
                    backHref={integrationsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('integrations.resourcePlural') })}
                />

                <IntegrationForm
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
                            <Button type="button" variant="danger" onClick={destroyIntegration}>
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
