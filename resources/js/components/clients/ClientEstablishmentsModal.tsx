import { useEffect, useId, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { BaseModal } from '@/components/ui/BaseModal';
import { Input } from '@/components/ui/Input';
import { clientsService, type ClientEstablishmentRow } from '@/services/clients';
import { establishmentsService } from '@/services/establishments';
import { cn } from '@/support/cn';

type ClientEstablishmentsModalProps = {
    open: boolean;
    relationshipId: number;
    clientName: string;
    canEdit: boolean;
    onClose: () => void;
};

export function ClientEstablishmentsModal({
    open,
    relationshipId,
    clientName,
    canEdit,
    onClose,
}: ClientEstablishmentsModalProps) {
    const { t } = useTranslation();
    const searchId = useId();
    const [rows, setRows] = useState<ClientEstablishmentRow[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');

    const visibleRows = useMemo(() => {
        const needle = search.trim().toLocaleLowerCase();
        if (needle === '') {
            return rows;
        }

        return rows.filter((row) => {
            const name = row.name.toLocaleLowerCase();
            const code = (row.code ?? '').toLocaleLowerCase();
            const city = (row.city ?? '').toLocaleLowerCase();

            return name.includes(needle) || code.includes(needle) || city.includes(needle);
        });
    }, [rows, search]);

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;
        setLoading(true);
        setError(null);
        setSearch('');

        void clientsService
            .establishments(relationshipId)
            .then((data) => {
                if (!cancelled) {
                    setRows(data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(t('clients.establishmentsLoadFailed'));
                    setRows([]);
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
    }, [relationshipId, open, t]);

    return (
        <BaseModal
            open={open}
            onClose={onClose}
            size="xl"
            title={t('clients.establishmentsModalTitle', { client: clientName })}
            description={t('clients.establishmentsModalDescription')}
            footer={
                <Button type="button" variant="secondary" onClick={onClose}>
                    {t('common.close')}
                </Button>
            }
        >
            {loading ? (
                <p className="text-sm text-ink-muted">{t('common.loading')}</p>
            ) : (
                <div className="space-y-3">
                    <div className="mb-4 space-y-1.5">
                        <label htmlFor={searchId} className="text-sm font-semibold text-ink">
                            {t('common.search')}
                        </label>
                        <Input
                            id={searchId}
                            value={search}
                            placeholder={t('clients.establishmentsSearchPlaceholder')}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {rows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('clients.establishmentsEmpty')}
                        </p>
                    ) : visibleRows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('clients.establishmentsNoSearchResults')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-line">
                            <table className="min-w-full divide-y divide-line text-sm">
                                <thead className="bg-canvas">
                                    <tr>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {t('common.id')}
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {t('common.name')}
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {t('common.code')}
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {t('companies.city')}
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {t('common.status')}
                                        </th>
                                        <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {t('common.actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line bg-surface">
                                    {visibleRows.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-4 py-2.5 text-ink-muted">{row.id}</td>
                                            <td className="px-4 py-2.5 font-medium text-ink">{row.name}</td>
                                            <td className="px-4 py-2.5 text-ink-muted">
                                                {row.code || t('common.emDash')}
                                            </td>
                                            <td className="px-4 py-2.5 text-ink-muted">
                                                {row.city || t('common.emDash')}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-semibold',
                                                        row.is_active
                                                            ? 'bg-success/10 text-success'
                                                            : 'border border-line bg-canvas text-ink-muted',
                                                    )}
                                                >
                                                    {row.is_active ? t('common.active') : t('common.inactive')}
                                                </span>
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <div className="flex items-center justify-end">
                                                    {canEdit ? (
                                                        <Link
                                                            href={establishmentsService.editPath(row.id)}
                                                            className="inline-flex size-8 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-brand/40 hover:text-brand"
                                                            aria-label={t('common.editItem', { name: row.name })}
                                                        >
                                                            <Pencil className="size-3.5" aria-hidden />
                                                        </Link>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {error ? <p className="mt-3 text-sm text-danger">{error}</p> : null}
        </BaseModal>
    );
}
