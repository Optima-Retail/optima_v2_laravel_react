import { useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import {
    TechnicianRatingsApiError,
    fetchTechnicianRatings,
    storeTechnicianRating,
    type TechnicianRatingRow,
    type TechnicianRatingSource,
} from '@/services/technicianRatings';
import { useToastStore } from '@/stores/toastStore';

type TechnicianRatingsPanelProps = {
    relationshipId: number;
    canEdit: boolean;
    onAggregatesUpdated?: (aggregates: {
        optima_score: string | null;
        customer_score: string | null;
        average_score: string | null;
        optima_score_count: number;
        customer_score_count: number;
    }) => void;
};

export function TechnicianRatingsPanel({
    relationshipId,
    canEdit,
    onAggregatesUpdated,
}: TechnicianRatingsPanelProps) {
    const { t } = useTranslation();
    const pushToast = useToastStore((state) => state.push);
    const [ratings, setRatings] = useState<TechnicianRatingRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [score, setScore] = useState('8');
    const [notes, setNotes] = useState('');
    const [source, setSource] = useState<TechnicianRatingSource>('optima');

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        void fetchTechnicianRatings(relationshipId)
            .then((rows) => {
                if (!cancelled) {
                    setRatings(rows);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    pushToast(t('technicians.ratings.loadFailed'), 'error');
                    setRatings([]);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [relationshipId, pushToast, t]);

    async function submit() {
        const parsedScore = Number.parseInt(score, 10);
        if (!Number.isFinite(parsedScore) || parsedScore < 0 || parsedScore > 10) {
            pushToast(t('technicians.ratings.invalidScore'), 'error');

            return;
        }

        setSaving(true);

        try {
            const saved = await storeTechnicianRating(relationshipId, {
                score: parsedScore,
                notes: notes.trim() || null,
                source,
            });
            setRatings((current) => [saved, ...current.filter((row) => row.id !== saved.id)].slice(0, 25));
            onAggregatesUpdated?.(saved.aggregates);
            setNotes('');
            pushToast(t('technicians.ratings.saved'), 'success');
        } catch (caught) {
            if (caught instanceof TechnicianRatingsApiError) {
                pushToast(caught.message || t('technicians.ratings.saveFailed'), 'error');
            } else {
                pushToast(
                    caught instanceof Error ? caught.message : t('technicians.ratings.saveFailed'),
                    'error',
                );
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="mt-6 space-y-4 border-t border-line pt-5 sm:col-span-2">
            <div>
                <h3 className="text-sm font-semibold text-ink">{t('technicians.ratings.title')}</h3>
                <p className="mt-1 text-sm text-ink-muted">{t('technicians.ratings.description')}</p>
            </div>

            {canEdit ? (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label={t('technicians.ratings.score')} htmlFor="technician_rating_score">
                        <Input
                            id="technician_rating_score"
                            type="number"
                            min={0}
                            max={10}
                            step={1}
                            value={score}
                            disabled={saving}
                            onChange={(event) => setScore(event.target.value)}
                        />
                    </Field>
                    <Field label={t('technicians.ratings.source')} htmlFor="technician_rating_source">
                        <Select
                            id="technician_rating_source"
                            value={source}
                            disabled={saving}
                            onChange={(event) => setSource(event.target.value as TechnicianRatingSource)}
                        >
                            <option value="optima">{t('technicians.ratings.sources.optima')}</option>
                            <option value="customer">{t('technicians.ratings.sources.customer')}</option>
                        </Select>
                    </Field>
                    <Field
                        label={t('technicians.ratings.notes')}
                        htmlFor="technician_rating_notes"
                        className="sm:col-span-2 lg:col-span-2"
                    >
                        <Input
                            id="technician_rating_notes"
                            value={notes}
                            disabled={saving}
                            onChange={(event) => setNotes(event.target.value)}
                            placeholder={t('technicians.ratings.notesPlaceholder')}
                        />
                    </Field>
                    <div className="flex items-end sm:col-span-2 lg:col-span-4">
                        <Button type="button" loading={saving} onClick={() => void submit()}>
                            <Plus className="size-4" aria-hidden />
                            {t('technicians.ratings.add')}
                        </Button>
                    </div>
                </div>
            ) : null}

            {loading ? (
                <p className="text-sm text-ink-muted">{t('common.loading')}</p>
            ) : ratings.length === 0 ? (
                <p className="text-sm text-ink-muted">{t('technicians.ratings.empty')}</p>
            ) : (
                <ul className="divide-y divide-line rounded-lg border border-line">
                    {ratings.map((rating) => (
                        <li key={rating.id} className="flex flex-wrap items-start justify-between gap-2 px-3 py-2 text-sm">
                            <div>
                                <p className="font-medium text-ink">
                                    {t('technicians.ratings.scoreValue', { score: rating.score })} ·{' '}
                                    {t(`technicians.ratings.sources.${rating.source}`, {
                                        defaultValue: rating.source,
                                    })}
                                </p>
                                {rating.notes ? (
                                    <p className="mt-0.5 text-ink-muted">{rating.notes}</p>
                                ) : null}
                            </div>
                            {rating.created_at ? (
                                <span className="text-xs text-ink-muted">
                                    {new Date(rating.created_at).toLocaleString()}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
