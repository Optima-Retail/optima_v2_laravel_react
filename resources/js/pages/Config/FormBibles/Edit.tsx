import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormBibleForm } from '@/components/config/form-bibles/FormBibleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { formBiblesService } from '@/services';

type EditProps = {
    formBible: { id: number; name: string };
    can: { delete: boolean };
};

export default function EditFormBible({ formBible, can }: EditProps) {
    const { t } = useTranslation();
    const form = useForm({ name: formBible.name });

    function submit(event: FormEvent) {
        event.preventDefault();
        formBiblesService.update(formBible.id, form);
    }

    async function destroyBible() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('formBibles.resource') }),
            message: t('common.deleteMessage', { name: formBible.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        formBiblesService.destroy(formBible.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('formBibles.resource') })}>
            <Head title={t('common.editItem', { name: formBible.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('formBibles.title')}
                    title={t('common.editResource', { resource: t('formBibles.resource') })}
                    description={t('common.updateDetails', { name: formBible.name })}
                    backHref={formBiblesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('formBibles.resourcePlural') })}
                />
                <FormBibleForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyBible}>
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
