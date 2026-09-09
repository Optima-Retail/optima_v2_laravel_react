import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { LanguageForm } from '@/components/config/languages/LanguageForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { languagesService } from '@/services';
import type { LanguageFormData } from '@/support/types/domain/language';

type EditLanguageProps = {
    language: LanguageFormData;
    can: {
        delete: boolean;
    };
};

export default function EditLanguage({ language, can }: EditLanguageProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: language.name,
        code: language.code,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        languagesService.update(language.id, form);
    }

    async function destroyLanguage() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('languages.resource') }),
            message: t('common.deleteMessage', { name: language.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        languagesService.destroy(language.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('languages.resource') })}>
            <Head title={t('common.editItem', { name: language.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('languages.title')}
                    title={t('common.editResource', { resource: t('languages.resource') })}
                    description={t('common.updateDetails', { name: language.name })}
                    backHref={languagesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('languages.resourcePlural') })}
                />

                <LanguageForm
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
                            <Button type="button" variant="danger" onClick={destroyLanguage}>
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
