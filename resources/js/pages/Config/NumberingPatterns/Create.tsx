import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NumberingPatternForm } from '@/components/config/numbering-patterns/NumberingPatternForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { numberingPatternsService } from '@/services';
import type { NumberingSegmentFormData } from '@/support/types/domain/numbering-pattern';

type CreateNumberingPatternProps = {
    resourceOptions: Array<{ id: string; label: string }>;
    segmentTypeOptions: Array<{ id: string; label: string }>;
    defaultSegments: NumberingSegmentFormData[];
};

export default function CreateNumberingPattern({
    resourceOptions,
    segmentTypeOptions,
    defaultSegments,
}: CreateNumberingPatternProps) {
    const { t } = useTranslation();
    const form = useForm({
        resource: resourceOptions[0]?.id ?? 'contracts',
        segments: defaultSegments,
        reset_yearly: false,
        is_active: true,
    });

    function defaultSegmentsFor(resource: string): NumberingSegmentFormData[] {
        const letter = (resource.trim().charAt(0) || 'X').toUpperCase();

        return [
            { type: 'letters', value: letter },
            { type: 'sequence', digit_length: 5 },
        ];
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        numberingPatternsService.store(form);
    }

    return (
        <AppLayout title={t('numberingPatterns.createTitle')}>
            <Head title={t('numberingPatterns.createTitle')} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('numberingPatterns.title')}
                    title={t('numberingPatterns.createTitle')}
                    description={t('numberingPatterns.createDescription')}
                    backHref={numberingPatternsService.indexPath}
                    backLabel={t('common.backTo', { resource: t('numberingPatterns.resourcePlural') })}
                />

                <NumberingPatternForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    resourceOptions={resourceOptions}
                    segmentTypeOptions={segmentTypeOptions}
                    onChange={(key, value) => {
                        form.setData(key, value);

                        if (key === 'resource' && typeof value === 'string') {
                            form.setData('segments', defaultSegmentsFor(value));
                        }
                    }}
                    onSubmit={submit}
                    submitLabel={t('numberingPatterns.createTitle')}
                    submitIcon={<Plus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
