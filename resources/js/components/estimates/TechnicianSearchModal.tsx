import { useEffect, useMemo, useState } from 'react';
import { CalendarDays, History, Ban, Search, Star, TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CompanyOptionLabel } from '@/components/companies/CompanyOptionLabel';
import { Button } from '@/components/ui/Button';
import { BaseModal } from '@/components/ui/BaseModal';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { Toggle } from '@/components/ui/Toggle';
import { cn } from '@/support/cn';
import type { CompanyOption, UserOption } from '@/support/types/domain/common';

export type TechnicianSearchResult = CompanyOption & {
    phone?: string | null;
    optima_score?: number | null;
    customer_score?: number | null;
    average_score?: number | null;
    distance_km?: number | null;
    is_favorite?: boolean;
    is_blacklisted?: boolean;
    has_history?: boolean;
    has_health_and_safety?: boolean;
};

type TechnicianSearchTab = 'nearby' | 'history' | 'rating' | 'all';

type TechnicianSearchModalProps = {
    open: boolean;
    establishmentId: number | null;
    establishmentName?: string | null;
    workOrderTypeId?: number | null;
    onClose: () => void;
    onSelect: (technician: TechnicianSearchResult) => void;
};

type SearchResponse = {
    data: TechnicianSearchResult[];
    meta: {
        establishment_name?: string;
        has_coordinates?: boolean;
        selected_service_type_ids?: number[];
        service_type_options?: UserOption[];
    };
    current_page: number;
    last_page: number;
    total: number;
};

const TABS: { id: TechnicianSearchTab; icon: typeof Search }[] = [
    { id: 'nearby', icon: CalendarDays },
    { id: 'history', icon: History },
    { id: 'rating', icon: Star },
    { id: 'all', icon: TriangleAlert },
];

export function TechnicianSearchModal({
    open,
    establishmentId,
    establishmentName,
    workOrderTypeId,
    onClose,
    onSelect,
}: TechnicianSearchModalProps) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<TechnicianSearchTab>('nearby');
    const [search, setSearch] = useState('');
    const [radiusKm, setRadiusKm] = useState(50);
    const [prlOk, setPrlOk] = useState(false);
    const [serviceTypeIds, setServiceTypeIds] = useState<string[]>([]);
    const [serviceTypeOptions, setServiceTypeOptions] = useState<UserOption[]>([]);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<SearchResponse | null>(null);

    const title = useMemo(() => {
        const place = establishmentName || result?.meta.establishment_name;

        return place
            ? t('estimates.technicianSearchTitleAt', { name: place })
            : t('estimates.technicianSearchTitle');
    }, [establishmentName, result?.meta.establishment_name, t]);

    useEffect(() => {
        if (!open) {
            return;
        }

        setTab('nearby');
        setSearch('');
        setRadiusKm(50);
        setPrlOk(false);
        setServiceTypeIds([]);
        setPage(1);
        setResult(null);
        setError(null);
    }, [open, establishmentId]);

    useEffect(() => {
        if (!open || !establishmentId) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            setError(null);

            try {
                const params = new URLSearchParams();
                params.set('establishment_id', String(establishmentId));
                params.set('tab', tab);
                params.set('page', String(page));
                params.set('per_page', '25');
                params.set('radius_km', String(radiusKm));
                params.set('prl_ok', prlOk ? '1' : '0');
                if (workOrderTypeId) {
                    params.set('work_order_type_id', String(workOrderTypeId));
                }
                if (search.trim() !== '') {
                    params.set('search', search.trim());
                }
                serviceTypeIds.forEach((id) => params.append('service_type_ids[]', id));

                const response = await fetch(`/estimates/technician-search?${params.toString()}`, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = (await response.json()) as SearchResponse;
                setResult(payload);
                if (payload.meta.service_type_options) {
                    setServiceTypeOptions(payload.meta.service_type_options);
                    if (serviceTypeIds.length === 0 && (payload.meta.selected_service_type_ids?.length ?? 0) > 0) {
                        setServiceTypeIds((payload.meta.selected_service_type_ids ?? []).map(String));
                    }
                }
            } catch (err) {
                if ((err as Error).name === 'AbortError') {
                    return;
                }
                setError(t('estimates.technicianSearchFailed'));
                setResult(null);
            } finally {
                setLoading(false);
            }
        }, 250);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
        // Intentionally omit serviceTypeIds bootstrap from deps to avoid loops when meta seeds them.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, establishmentId, workOrderTypeId, tab, search, radiusKm, prlOk, page, serviceTypeIds.join(',')]);

    function applyFilters() {
        setPage(1);
        setSearch((value) => value.trim());
    }

    return (
        <BaseModal
            open={open}
            onClose={onClose}
            size="full"
            title={title}
            description={t('estimates.technicianSearchDescription')}
        >
            <div className="space-y-4">
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    {TABS.map(({ id, icon: Icon }) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => {
                                setTab(id);
                                setPage(1);
                            }}
                            className={cn(
                                'inline-flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition',
                                tab === id
                                    ? 'border-brand bg-brand-soft text-brand'
                                    : 'border-line bg-surface text-ink-muted hover:bg-canvas hover:text-ink',
                                id === 'all' && tab !== id && 'border-danger/40 text-danger',
                            )}
                        >
                            <Icon className="size-4 shrink-0" aria-hidden />
                            {t(`estimates.technicianSearchTabs.${id}`)}
                        </button>
                    ))}
                </div>

                <div className="grid gap-3 lg:grid-cols-12">
                    <div className="lg:col-span-4">
                        <label className="mb-1.5 block text-sm font-semibold text-ink" htmlFor="tech-search-text">
                            {t('estimates.technicianSearchText')}
                        </label>
                        <Input
                            id="tech-search-text"
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setPage(1);
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    applyFilters();
                                }
                            }}
                            placeholder={t('estimates.technicianSearchTextPlaceholder')}
                        />
                    </div>
                    <div className="lg:col-span-4">
                        <label className="mb-1.5 block text-sm font-semibold text-ink" htmlFor="tech-search-services">
                            {t('estimates.technicianSearchServices')}
                        </label>
                        <MultiSelect
                            id="tech-search-services"
                            value={serviceTypeIds}
                            onChange={(next) => {
                                setServiceTypeIds(next);
                                setPage(1);
                            }}
                            options={serviceTypeOptions.map((option) => ({
                                value: String(option.id),
                                label: option.label,
                            }))}
                            placeholder={t('common.select')}
                        />
                    </div>
                    <div className={cn('lg:col-span-2', tab === 'all' && 'opacity-50')}>
                        <label className="mb-1.5 block text-sm font-semibold text-ink" htmlFor="tech-search-radius">
                            {t('estimates.technicianSearchRadius')}
                        </label>
                        <Input
                            id="tech-search-radius"
                            type="number"
                            min={0}
                            max={500}
                            value={String(radiusKm)}
                            disabled={tab === 'all'}
                            onChange={(event) => {
                                setRadiusKm(Number(event.target.value) || 0);
                                setPage(1);
                            }}
                        />
                    </div>
                    <div className="flex items-end lg:col-span-2">
                        <Toggle
                            name="prl_ok"
                            checked={prlOk}
                            onCheckedChange={(checked) => {
                                setPrlOk(checked);
                                setPage(1);
                            }}
                            checkedLabel={t('estimates.technicianSearchPrl')}
                            uncheckedLabel={t('estimates.technicianSearchPrlOff')}
                        />
                    </div>
                </div>

                {!establishmentId ? (
                    <p className="rounded-xl border border-line bg-canvas/50 px-4 py-3 text-sm text-ink-muted">
                        {t('estimates.technicianSearchNeedEstablishment')}
                    </p>
                ) : null}

                {error ? <p className="text-sm text-danger">{error}</p> : null}

                {result && result.meta.has_coordinates === false ? (
                    <p className="rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        {t('estimates.technicianSearchNoCoordinates')}
                    </p>
                ) : null}

                <div className="overflow-hidden rounded-xl border border-line">
                    <table className="min-w-full divide-y divide-line text-sm">
                        <thead className="bg-canvas/60">
                            <tr className="text-left text-ink-muted">
                                <th className="px-3 py-2 font-semibold">{t('common.actions')}</th>
                                <th className="px-3 py-2 font-semibold">{t('workOrders.technician')}</th>
                                <th className="px-3 py-2 font-semibold">{t('estimates.technicianPhone')}</th>
                                <th className="px-3 py-2 font-semibold">{t('estimates.technicianOptimaScore')}</th>
                                <th className="px-3 py-2 font-semibold">{t('estimates.technicianCustomerScore')}</th>
                                <th className="px-3 py-2 font-semibold">{t('estimates.technicianDistance')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line bg-surface">
                            {loading ? (
                                <tr>
                                    <td colSpan={6} className="px-3 py-8 text-center text-ink-muted">
                                        {t('common.loading')}
                                    </td>
                                </tr>
                            ) : (result?.data.length ?? 0) === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-3 py-8 text-center text-ink-muted">
                                        {t('estimates.technicianSearchEmpty')}
                                    </td>
                                </tr>
                            ) : (
                                result?.data.map((technician) => (
                                    <tr
                                        key={technician.id}
                                        className={cn(
                                            'hover:bg-canvas/40',
                                            technician.is_blacklisted && 'bg-danger/5',
                                            technician.is_favorite && !technician.is_blacklisted && 'bg-amber-50/70',
                                        )}
                                    >
                                        <td className="px-3 py-2">
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                onClick={() => {
                                                    onSelect(technician);
                                                    onClose();
                                                }}
                                            >
                                                {t('common.select')}
                                            </Button>
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <CompanyOptionLabel
                                                    name={technician.label}
                                                    logoUrl={technician.logo_url}
                                                    size="sm"
                                                />
                                                {technician.is_favorite ? (
                                                    <Star
                                                        className="size-3.5 shrink-0 fill-[#EAB308] text-[#EAB308]"
                                                        aria-label={t('estimates.technicianFavorite')}
                                                    />
                                                ) : null}
                                                {technician.is_blacklisted ? (
                                                    <span
                                                        className="inline-flex items-center gap-1 rounded-full border border-danger/30 bg-danger/10 px-2 py-0.5 text-[11px] font-semibold text-danger"
                                                        title={t('estimates.technicianBlacklistedHint')}
                                                    >
                                                        <Ban className="size-3 shrink-0" aria-hidden />
                                                        {t('estimates.technicianBlacklisted')}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-ink-muted">{technician.phone || '—'}</td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {technician.optima_score ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {technician.customer_score ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 tabular-nums">
                                            {technician.distance_km !== null && technician.distance_km !== undefined
                                                ? `${technician.distance_km} km`
                                                : '—'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {result && result.last_page > 1 ? (
                    <div className="flex items-center justify-between gap-3 text-sm text-ink-muted">
                        <span>
                            {result.current_page} / {result.last_page} ({result.total})
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={page <= 1 || loading}
                                onClick={() => setPage((current) => Math.max(1, current - 1))}
                            >
                                {t('common.previous')}
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={page >= result.last_page || loading}
                                onClick={() => setPage((current) => current + 1)}
                            >
                                {t('common.next')}
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>
        </BaseModal>
    );
}
