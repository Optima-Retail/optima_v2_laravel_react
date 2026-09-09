import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ClientPriorityForm } from '@/components/config/client-priorities/ClientPriorityForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { clientPrioritiesService } from '@/services';
import type { ClientPriorityFormData } from '@/support/types/domain/client-priority';

type EditClientPriorityProps = {
    clientPriority: ClientPriorityFormData;
    can: {
        delete: boolean;
    };
};

export default function EditClientPriority({ clientPriority, can }: EditClientPriorityProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: clientPriority.name,
        code: clientPriority.code ?? '',
        color: clientPriority.color ?? '#2563eb',
        level: clientPriority.level,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        clientPrioritiesService.update(clientPriority.id, form);
    }

    async function destroyPriority() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('clientPriorities.resource') }),
            message: t('common.deleteMessage', { name: clientPriority.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        clientPrioritiesService.destroy(clientPriority.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('clientPriorities.resource') })}>
            <Head title={t('common.editItem', { name: clientPriority.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('clientPriorities.title')}
                    title={t('clientPriorities.editTitle')}
                    description={t('common.updateDetails', { name: clientPriority.name })}
                    backHref={clientPrioritiesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('clientPriorities.resourcePlural') })}
                />

                <ClientPriorityForm
                    mode="edit"
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
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyPriority}>
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
