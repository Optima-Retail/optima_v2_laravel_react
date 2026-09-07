import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { IntegrationForm } from '@/components/config/integrations/IntegrationForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { integrationsService } from '@/services';

export default function CreateIntegration() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        integrationsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('integrations.resource') })}>
            <Head title={t('common.newItem', { resource: t('integrations.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('integrations.title')}
                    title={t('common.createItem', { resource: t('integrations.resource') })}
                    description={t('integrations.createDescription')}
                    backHref={integrationsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('integrations.resourcePlural') })}
                />

                <IntegrationForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('integrations.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
