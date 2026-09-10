import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { cn } from '@/support/cn';

export type CompanyScheduleValues = {
    monday_opens: string;
    monday_closes: string;
    tuesday_opens: string;
    tuesday_closes: string;
    wednesday_opens: string;
    wednesday_closes: string;
    thursday_opens: string;
    thursday_closes: string;
    friday_opens: string;
    friday_closes: string;
    saturday_opens: string;
    saturday_closes: string;
    sunday_opens: string;
    sunday_closes: string;
};

type CompanySchedulePanelProps = {
    relationshipId: number;
    schedule: CompanyScheduleValues;
    canEdit?: boolean;
    embedded?: boolean;
};

const DAYS = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
] as const;

export function CompanySchedulePanel({
    relationshipId,
    schedule,
    canEdit = true,
    embedded = false,
}: CompanySchedulePanelProps) {
    const { t } = useTranslation();
    const form = useForm({
        monday_opens: schedule.monday_opens ?? '',
        monday_closes: schedule.monday_closes ?? '',
        tuesday_opens: schedule.tuesday_opens ?? '',
        tuesday_closes: schedule.tuesday_closes ?? '',
        wednesday_opens: schedule.wednesday_opens ?? '',
        wednesday_closes: schedule.wednesday_closes ?? '',
        thursday_opens: schedule.thursday_opens ?? '',
        thursday_closes: schedule.thursday_closes ?? '',
        friday_opens: schedule.friday_opens ?? '',
        friday_closes: schedule.friday_closes ?? '',
        saturday_opens: schedule.saturday_opens ?? '',
        saturday_closes: schedule.saturday_closes ?? '',
        sunday_opens: schedule.sunday_opens ?? '',
        sunday_closes: schedule.sunday_closes ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (!canEdit) {
            return;
        }
        form.put(`/clients/${relationshipId}/schedule`, {
            preserveScroll: true,
        });
    }

    return (
        <form
            onSubmit={submit}
            className={cn(
                'space-y-5',
                !embedded && 'rounded-2xl border border-line bg-surface p-6 sm:p-8',
            )}
        >
            {!embedded ? (
                <div>
                    <h2 className="text-base font-semibold text-ink">{t('companySchedules.title')}</h2>
                    <p className="mt-1 text-sm text-ink-muted">{t('companySchedules.description')}</p>
                </div>
            ) : (
                <p className="text-sm text-ink-muted">{t('companySchedules.description')}</p>
            )}

            <div className="overflow-x-auto">
                <div className="min-w-[48rem] space-y-3">
                    <div className="grid grid-cols-[6.5rem_repeat(7,minmax(0,1fr))] gap-2">
                        <span className="text-xs font-semibold uppercase tracking-wide text-ink-muted" />
                        {DAYS.map((day) => (
                            <p
                                key={day}
                                className="text-center text-xs font-semibold uppercase tracking-wide text-ink-muted"
                            >
                                {t(`companySchedules.${day}`)}
                            </p>
                        ))}
                    </div>

                    <div className="grid grid-cols-[6.5rem_repeat(7,minmax(0,1fr))] items-start gap-2">
                        <p className="pt-2 text-sm font-medium text-ink">{t('companySchedules.opens')}</p>
                        {DAYS.map((day) => {
                            const opensKey = `${day}_opens` as keyof CompanyScheduleValues;

                            return (
                                <Field
                                    key={opensKey}
                                    label={t('companySchedules.opens')}
                                    htmlFor={opensKey}
                                    error={form.errors[opensKey]}
                                    className="[&>label]:sr-only"
                                >
                                    <Input
                                        id={opensKey}
                                        type="time"
                                        value={form.data[opensKey]}
                                        disabled={!canEdit || form.processing}
                                        onChange={(event) => form.setData(opensKey, event.target.value)}
                                    />
                                </Field>
                            );
                        })}
                    </div>

                    <div className="grid grid-cols-[6.5rem_repeat(7,minmax(0,1fr))] items-start gap-2">
                        <p className="pt-2 text-sm font-medium text-ink">{t('companySchedules.closes')}</p>
                        {DAYS.map((day) => {
                            const closesKey = `${day}_closes` as keyof CompanyScheduleValues;

                            return (
                                <Field
                                    key={closesKey}
                                    label={t('companySchedules.closes')}
                                    htmlFor={closesKey}
                                    error={form.errors[closesKey]}
                                    className="[&>label]:sr-only"
                                >
                                    <Input
                                        id={closesKey}
                                        type="time"
                                        value={form.data[closesKey]}
                                        disabled={!canEdit || form.processing}
                                        onChange={(event) => form.setData(closesKey, event.target.value)}
                                    />
                                </Field>
                            );
                        })}
                    </div>
                </div>
            </div>

            {canEdit ? (
                <div className="flex justify-end border-t border-line pt-4">
                    <Button type="submit" loading={form.processing}>
                        <Save className="size-4" aria-hidden />
                        {t('companySchedules.save')}
                    </Button>
                </div>
            ) : null}
        </form>
    );
}
