import { FormEvent, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { History, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { incidentsService } from '@/services';
import type { UserOption } from '@/support/types/domain/common';
import type { IncidentLineItem } from '@/support/types/domain/incident';

type IncidentLinesPanelProps = {
    incidentId: number;
    lines: IncidentLineItem[];
    incidentStatusOptions: UserOption[];
    canCreate: boolean;
    durationSeconds: number | null;
};

function formatDateTime(value: string | null, locale: string): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString(locale, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDuration(seconds: number | null): string {
    if (seconds === null || seconds === undefined || Number.isNaN(seconds)) {
        return '—';
    }

    const total = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;

    return [hours, minutes, secs].map((part) => String(part).padStart(2, '0')).join(':');
}

export function IncidentLinesPanel({
    incidentId,
    lines,
    incidentStatusOptions,
    canCreate,
    durationSeconds,
}: IncidentLinesPanelProps) {
    const { t, i18n } = useTranslation();
    const [showForm, setShowForm] = useState(false);
    const form = useForm({
        comment: '',
        incident_status_id: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        incidentsService.storeLine(incidentId, form, {
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
        });
    }

    return (
        <div className="space-y-4 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-ink">{t('incidents.actionsTitle')}</h2>
                    <p className="mt-1 text-sm text-ink-muted">{t('incidents.actionsDescription')}</p>
                    <p className="mt-2 text-sm text-ink">
                        <span className="font-medium">{t('incidents.durationSeconds')}:</span>{' '}
                        {formatDuration(durationSeconds)}
                    </p>
                </div>

                {canCreate && !showForm ? (
                    <Button type="button" variant="secondary" onClick={() => setShowForm(true)}>
                        <Plus className="size-4" aria-hidden />
                        {t('incidents.addAction')}
                    </Button>
                ) : null}
            </div>

            {canCreate && showForm ? (
                <form onSubmit={submit} className="space-y-4 rounded-xl border border-line p-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label={t('incidents.status')}
                            htmlFor="line_incident_status_id"
                            error={form.errors.incident_status_id}
                            required
                        >
                            <SearchableSelect
                                id="line_incident_status_id"
                                value={form.data.incident_status_id}
                                invalid={Boolean(form.errors.incident_status_id)}
                                onChange={(value) => form.setData('incident_status_id', value)}
                                emptyLabel={t('common.select')}
                                options={incidentStatusOptions.map((option) => ({
                                    value: String(option.id),
                                    label: option.label,
                                    color: option.color ?? null,
                                }))}
                            />
                        </Field>

                        <Field
                            label={t('incidents.comment')}
                            htmlFor="line_comment"
                            error={form.errors.comment}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="line_comment"
                                value={form.data.comment}
                                invalid={Boolean(form.errors.comment)}
                                onChange={(event) => form.setData('comment', event.target.value)}
                            />
                        </Field>
                    </div>

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => {
                                form.reset();
                                setShowForm(false);
                            }}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            <Plus className="size-4" aria-hidden />
                            {t('incidents.addAction')}
                        </Button>
                    </div>
                </form>
            ) : null}

            {lines.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-line px-4 py-10 text-center">
                    <History className="size-5 text-ink-muted" aria-hidden />
                    <p className="text-sm text-ink-muted">{t('incidents.actionsEmpty')}</p>
                </div>
            ) : (
                <ol className="relative space-y-0 border-l border-line pl-5">
                    {lines.map((line) => (
                        <li key={line.id} className="relative pb-6 last:pb-0">
                            <span
                                className="absolute -left-[1.4rem] top-1.5 size-2.5 rounded-full border border-line"
                                style={{
                                    backgroundColor: line.status_color || '#94a3b8',
                                }}
                                aria-hidden
                            />
                            <div className="rounded-xl border border-line px-4 py-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-sm font-semibold text-ink">
                                        {line.user_name || t('common.emDash')}
                                    </p>
                                    <p className="text-xs text-ink-muted">
                                        {formatDateTime(line.ended_at, i18n.language)}
                                        {' · '}
                                        {line.duration_minutes} {t('incidents.minutesShort')}
                                    </p>
                                </div>
                                <p className="mt-1 text-sm text-ink">{line.comment}</p>
                                <p className="mt-2">
                                    <span
                                        className="inline-flex items-center rounded-md border border-line px-1.5 py-0.5 text-xs font-semibold text-ink"
                                        style={{
                                            backgroundColor: line.status_color
                                                ? `color-mix(in srgb, ${line.status_color} 18%, white)`
                                                : undefined,
                                        }}
                                    >
                                        {line.status_name || t('common.emDash')}
                                    </span>
                                </p>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}
