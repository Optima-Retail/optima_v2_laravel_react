import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NumberingPatternForm } from '@/components/config/numbering-patterns/NumberingPatternForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { numberingPatternsService } from '@/services';
import type { NumberingPatternFormData } from '@/support/types/domain/numbering-pattern';

type ConfigureNumberingPatternProps = {
    pattern: NumberingPatternFormData;
    segmentTypeOptions: Array<{ id: string; label: string }>;
    returnTo: string | null;
};

export default function ConfigureNumberingPattern({
    pattern,
    segmentTypeOptions,
    returnTo,
}: ConfigureNumberingPatternProps) {
    const { t } = useTranslation();
    const form = useForm({
        resource: pattern.resource,
        segments: pattern.segments,
        reset_yearly: pattern.reset_yearly,
        is_active: pattern.is_active,
        return: returnTo ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        numberingPatternsService.upsertByResource(pattern.resource, form);
    }

    const resourceLabel = t(`numberingPatterns.resources.${pattern.resource}`, {
        defaultValue: pattern.resource,
    });

    return (
        <AppLayout title={t('numberingPatterns.configureTitle', { resource: resourceLabel })}>
            <Head title={t('numberingPatterns.configureTitle', { resource: resourceLabel })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('numberingPatterns.title')}
                    title={t('numberingPatterns.configureTitle', { resource: resourceLabel })}
                    description={t('numberingPatterns.configureDescription', { resource: resourceLabel })}
                    backHref={returnTo || numberingPatternsService.indexPath}
                    backLabel={
                        returnTo
                            ? t('common.back')
                            : t('common.backTo', { resource: t('numberingPatterns.resourcePlural') })
                    }
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
                />
            </div>
        </AppLayout>
    );
}
