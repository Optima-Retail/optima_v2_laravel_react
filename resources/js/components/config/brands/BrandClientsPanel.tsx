import { Link } from '@inertiajs/react';
import { Building2, Pencil } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CompanyOptionLabel } from '@/components/companies/CompanyOptionLabel';
import { badgeVariantForRelationshipStatus } from '@/components/ui/Badge';
import type { BrandClientRow } from '@/services/brands';
import { clientsService } from '@/services/clients';
import { cn } from '@/support/cn';

type BrandClientsPanelProps = {
    clients: BrandClientRow[];
    canEdit: boolean;
};

const badgeVariantClassName: Record<string, string> = {
    success: 'bg-success/10 text-success',
    neutral: 'border border-line bg-canvas text-ink-muted',
    danger: 'bg-danger/10 text-danger',
    warning: 'bg-amber-100 text-amber-800',
    brand: 'bg-brand-soft text-brand',
};

export function BrandClientsPanel({ clients, canEdit }: BrandClientsPanelProps) {
    const { t } = useTranslation();

    return (
        <div className="space-y-4 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div>
                <h2 className="text-base font-semibold text-ink">{t('brands.clientsTabTitle')}</h2>
                <p className="mt-1 text-sm text-ink-muted">{t('brands.clientsTabDescription')}</p>
            </div>

            {clients.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-line px-6 py-12 text-center">
                    <span className="flex size-12 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                        <Building2 className="size-5" aria-hidden />
                    </span>
                    <p className="text-sm text-ink-muted">{t('brands.clientsEmpty')}</p>
                </div>
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
                            {clients.map((row) => {
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
                                        <td className="px-4 py-2.5 font-medium text-ink">
                                            <CompanyOptionLabel
                                                name={name}
                                                logoUrl={row.related_company_logo_url}
                                                size="sm"
                                            />
                                        </td>
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
    );
}
