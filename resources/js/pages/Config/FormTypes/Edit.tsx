import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FormTypeForm } from '@/components/config/form-types/FormTypeForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { formTypesService } from '@/services';
import type { FormTypeFormData } from '@/support/types/domain/form-type';

type EditFormTypeProps = {
    formType: FormTypeFormData;
    can: {
        delete: boolean;
    };
};

export default function EditFormType({ formType, can }: EditFormTypeProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: formType.name,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        formTypesService.update(formType.id, form);
    }

    async function destroyType() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('formTypes.resource') }),
            message: t('common.deleteMessage', { name: formType.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        formTypesService.destroy(formType.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('formTypes.resource') })}>
            <Head title={t('common.editItem', { name: formType.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('formTypes.title')}
                    title={t('formTypes.editTitle')}
                    description={t('common.updateDetails', { name: formType.name })}
                    backHref={formTypesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('formTypes.resourcePlural') })}
                />

                <FormTypeForm
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
                            <Button type="button" variant="danger" onClick={destroyType}>
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
