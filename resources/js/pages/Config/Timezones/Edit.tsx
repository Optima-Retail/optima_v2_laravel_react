import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TimezoneForm } from '@/components/config/timezones/TimezoneForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { timezonesService } from '@/services';
import type { TimezoneFormData } from '@/support/types/domain/timezone';

type EditTimezoneProps = {
    timezone: TimezoneFormData;
    can: {
        delete: boolean;
    };
};

export default function EditTimezone({ timezone, can }: EditTimezoneProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: timezone.name,
        timezone: timezone.timezone,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        timezonesService.update(timezone.id, form);
    }

    async function destroyTimezone() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('timezones.resource') }),
            message: t('common.deleteMessage', { name: timezone.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        timezonesService.destroy(timezone.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('timezones.resource') })}>
            <Head title={t('common.editItem', { name: timezone.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('timezones.title')}
                    title={t('common.editResource', { resource: t('timezones.resource') })}
                    description={t('common.updateDetails', { name: timezone.name })}
                    backHref={timezonesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('timezones.resourcePlural') })}
                />

                <TimezoneForm
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
                            <Button type="button" variant="danger" onClick={destroyTimezone}>
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
