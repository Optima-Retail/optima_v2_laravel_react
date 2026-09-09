import { useEffect, useId, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { badgeVariantForRelationshipStatus } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { BaseModal } from '@/components/ui/BaseModal';
import { Input } from '@/components/ui/Input';
import { brandsService, type BrandClientRow } from '@/services/brands';
import { clientsService } from '@/services/clients';
import { cn } from '@/support/cn';

type BrandClientsModalProps = {
    open: boolean;
    brandId: number;
    brandName: string;
    canEdit: boolean;
    onClose: () => void;
};

const badgeVariantClassName: Record<string, string> = {
    success: 'bg-success/10 text-success',
    neutral: 'border border-line bg-canvas text-ink-muted',
    danger: 'bg-danger/10 text-danger',
    warning: 'bg-amber-100 text-amber-800',
    brand: 'bg-brand-soft text-brand',
};

export function BrandClientsModal({
    open,
    brandId,
    brandName,
    canEdit,
    onClose,
}: BrandClientsModalProps) {
    const { t } = useTranslation();
    const searchId = useId();
    const [rows, setRows] = useState<BrandClientRow[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');

    const visibleRows = useMemo(() => {
        const needle = search.trim().toLocaleLowerCase();
        if (needle === '') {
            return rows;
        }

        return rows.filter((row) => {
            const name = (row.related_company_name ?? '').toLocaleLowerCase();
            const owner = (row.owner_company_name ?? '').toLocaleLowerCase();
            const status = row.status.toLocaleLowerCase();

            return name.includes(needle) || owner.includes(needle) || status.includes(needle);
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

        void brandsService
            .clients(brandId)
            .then((data) => {
                if (!cancelled) {
                    setRows(data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(t('brands.clientsLoadFailed'));
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
    }, [brandId, open, t]);

    return (
        <BaseModal
            open={open}
            onClose={onClose}
            size="xl"
            title={t('brands.clientsModalTitle', { brand: brandName })}
            description={t('brands.clientsModalDescription')}
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
                            placeholder={t('brands.clientsSearchPlaceholder')}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {rows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('brands.clientsEmpty')}
                        </p>
                    ) : visibleRows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('brands.clientsNoSearchResults')}
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
                                            {t('clients.relatedCompany')}
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {t('common.status')}
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {t('brands.ownerCompany')}
                                        </th>
                                        <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                            {t('common.actions')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line bg-surface">
                                    {visibleRows.map((row) => {
                                        const name = row.related_company_name || t('common.emDash');
                                        const statusLabel = t(`relationships.statuses.${row.status}`, {
                                            defaultValue: row.status,
                                        });
                                        const variant = badgeVariantForRelationshipStatus(row.status);
                                        const badgeClass =
                                            badgeVariantClassName[variant] ?? badgeVariantClassName.neutral;

                                        return (
                                            <tr key={row.id}>
                                                <td className="px-4 py-2.5 text-ink-muted">{row.id}</td>
                                                <td className="px-4 py-2.5 font-medium text-ink">{name}</td>
                                                <td className="px-4 py-2.5">
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-semibold',
                                                            badgeClass,
                                                        )}
                                                    >
                                                        {statusLabel}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2.5 text-ink-muted">
                                                    {row.owner_company_name || t('common.emDash')}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div className="flex items-center justify-end">
                                                        {canEdit ? (
                                                            <Link
                                                                href={clientsService.editPath(row.id)}
                                                                className="inline-flex size-8 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-brand/40 hover:text-brand"
                                                                aria-label={t('common.editItem', { name })}
                                                            >
                                                                <Pencil className="size-3.5" aria-hidden />
                                                            </Link>
                                                        ) : null}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
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
