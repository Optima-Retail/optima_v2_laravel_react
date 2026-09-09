import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ClientPriorityForm } from '@/components/config/client-priorities/ClientPriorityForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { clientPrioritiesService } from '@/services';

export default function CreateClientPriority() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
        color: '#2563eb',
        level: 3,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        clientPrioritiesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('clientPriorities.resource') })}>
            <Head title={t('common.newItem', { resource: t('clientPriorities.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('clientPriorities.title')}
                    title={t('clientPriorities.createTitle')}
                    description={t('clientPriorities.createDescription')}
                    backHref={clientPrioritiesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('clientPriorities.resourcePlural') })}
                />

                <ClientPriorityForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) =>
                        form.setData((data) => ({
                            ...data,
                            [key]: key === 'level' ? Number(value) || value : value,
                        }))
                    }
                    onSubmit={submit}
                    submitLabel={t('clientPriorities.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
