import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    FieldHelpForm,
    type FieldHelpFormValues,
    type FieldHelpSchema,
    type LocaleOption,
} from '@/components/config/fieldHelps/FieldHelpForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { fieldHelpsService } from '@/services';
import type { FieldHelpFormData } from '@/support/types/domain';

type EditFieldHelpProps = {
    fieldHelp: FieldHelpFormData;
    schema: FieldHelpSchema;
    locales: LocaleOption[];
    can: {
        delete: boolean;
    };
};

export default function EditFieldHelp({ fieldHelp, schema, locales, can }: EditFieldHelpProps) {
    const { t } = useTranslation();
    const form = useForm<FieldHelpFormValues>({
        table: fieldHelp.table ?? '',
        column: fieldHelp.column ?? '',
        is_active: fieldHelp.is_active,
        translations:
            fieldHelp.translations.length > 0
                ? fieldHelp.translations
                : locales[0]
                  ? [{ locale: locales[0].code, title: '', description: '' }]
                  : [],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        fieldHelpsService.update(fieldHelp.id, form);
    }

    async function destroyFieldHelp() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('fieldHelps.resource') }),
            message: t('common.deleteMessage', { name: fieldHelp.key }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        fieldHelpsService.destroy(fieldHelp.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('fieldHelps.resource') })}>
            <Head title={t('common.editItem', { name: fieldHelp.key })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('fieldHelps.title')}
                    title={t('common.editResource', { resource: t('fieldHelps.resource') })}
                    description={t('common.updateDetails', { name: fieldHelp.key })}
                    backHref={fieldHelpsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('fieldHelps.resourcePlural') })}
                />

                <FieldHelpForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    schema={schema}
                    locales={locales}
                    onChange={(patch) => form.setData((data) => ({ ...data, ...patch }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyFieldHelp}>
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
