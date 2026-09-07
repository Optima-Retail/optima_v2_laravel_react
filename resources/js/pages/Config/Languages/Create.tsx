import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { LanguageForm } from '@/components/config/languages/LanguageForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { languagesService } from '@/services';

export default function CreateLanguage() {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        code: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        languagesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('languages.resource') })}>
            <Head title={t('common.newItem', { resource: t('languages.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('languages.title')}
                    title={t('common.createItem', { resource: t('languages.resource') })}
                    description={t('languages.createDescription')}
                    backHref={languagesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('languages.resourcePlural') })}
                />

                <LanguageForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('languages.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
