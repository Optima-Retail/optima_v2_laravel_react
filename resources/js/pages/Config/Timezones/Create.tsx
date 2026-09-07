import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TimezoneForm } from '@/components/config/timezones/TimezoneForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { timezonesService } from '@/services';

export default function CreateTimezone() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        timezone: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        timezonesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('timezones.resource') })}>
            <Head title={t('common.newItem', { resource: t('timezones.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('timezones.title')}
                    title={t('common.createItem', { resource: t('timezones.resource') })}
                    description={t('timezones.createDescription')}
                    backHref={timezonesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('timezones.resourcePlural') })}
                />

                <TimezoneForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('timezones.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
