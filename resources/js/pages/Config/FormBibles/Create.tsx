import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormBibleForm } from '@/components/config/form-bibles/FormBibleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { formBiblesService } from '@/services';

export default function CreateFormBible() {
    const { t } = useTranslation();
    const form = useForm({ name: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        formBiblesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('formBibles.resource') })}>
            <Head title={t('common.newItem', { resource: t('formBibles.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('formBibles.title')}
                    title={t('formBibles.createTitle')}
                    description={t('formBibles.createDescription')}
                    backHref={formBiblesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('formBibles.resourcePlural') })}
                />
                <FormBibleForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('formBibles.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
