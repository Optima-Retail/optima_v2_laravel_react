import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NumberingPatternForm } from '@/components/config/numbering-patterns/NumberingPatternForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { numberingPatternsService } from '@/services';
import type { NumberingPatternFormData } from '@/support/types/domain/numbering-pattern';

type EditNumberingPatternProps = {
    pattern: NumberingPatternFormData;
    segmentTypeOptions: Array<{ id: string; label: string }>;
    can: {
        delete: boolean;
    };
};

export default function EditNumberingPattern({
    pattern,
    segmentTypeOptions,
    can,
}: EditNumberingPatternProps) {
    const { t } = useTranslation();
    const form = useForm({
        resource: pattern.resource,
        segments: pattern.segments,
        reset_yearly: pattern.reset_yearly,
        is_active: pattern.is_active,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        numberingPatternsService.update(pattern.id!, form);
    }

    async function destroyPattern() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('numberingPatterns.resourceSingular') }),
            message: t('common.deleteMessage', { name: pattern.resource }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed || !pattern.id) {
            return;
        }

        numberingPatternsService.destroy(pattern.id);
    }

    return (
        <AppLayout title={t('numberingPatterns.editTitle')}>
            <Head title={t('numberingPatterns.editTitle')} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('numberingPatterns.title')}
                    title={t('numberingPatterns.editTitle')}
                    description={t('common.updateDetails', { name: pattern.resource })}
                    backHref={numberingPatternsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('numberingPatterns.resourcePlural') })}
                />

                <NumberingPatternForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    segmentTypeOptions={segmentTypeOptions}
                    resourceLocked
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyPattern}>
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
