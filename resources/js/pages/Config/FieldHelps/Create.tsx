import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    FieldHelpForm,
    type FieldHelpFormValues,
    type FieldHelpSchema,
    type LocaleOption,
} from '@/components/config/fieldHelps/FieldHelpForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { fieldHelpsService } from '@/services';

type CreateFieldHelpProps = {
    schema: FieldHelpSchema;
    locales: LocaleOption[];
};

export default function CreateFieldHelp({ schema, locales }: CreateFieldHelpProps) {
    const { t } = useTranslation();
    const form = useForm<FieldHelpFormValues>({
        table: '',
        column: '',
        is_active: true,
        translations: locales[0]
            ? [{ locale: locales[0].code, title: '', description: '' }]
            : [],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        fieldHelpsService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('fieldHelps.resource') })}>
            <Head title={t('common.newItem', { resource: t('fieldHelps.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('fieldHelps.title')}
                    title={t('common.createItem', { resource: t('fieldHelps.resource') })}
                    description={t('fieldHelps.createDescription')}
                    backHref={fieldHelpsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('fieldHelps.resourcePlural') })}
                />

                <FieldHelpForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    schema={schema}
                    locales={locales}
                    onChange={(patch) => form.setData((data) => ({ ...data, ...patch }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('fieldHelps.resource') })}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
