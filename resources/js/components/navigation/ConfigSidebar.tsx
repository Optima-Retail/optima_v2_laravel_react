import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Briefcase,
    Building2,
    Cable,
    Car,
    CircleHelp,
    CircleDot,
    ClipboardList,
    Clock,
    Coins,
    ChevronLeft,
    ChevronRight,
    Flag,
    Globe2,
    Hash,
    Landmark,
    Languages,
    Layers,
    ListChecks,
    Package,
    Shield,
    Users,
    UsersRound,
    Wallet,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import i18n from '@/i18n';
import { useCan } from '@/hooks/useAuth';
import { Select } from '@/components/ui/Select';
import { cn } from '@/support/cn';

type ConfigItem = {
    href: string;
    labelKey: string;
    matches: string[];
    icon: typeof Users;
    permission?: string;
    anyPermissions?: string[];
};

/** Fallback row height: py-2 + text-sm line + gap-0.5 */
const CONFIG_SIDEBAR_ROW_ESTIMATE_PX = 38;
const CONFIG_SIDEBAR_MIN_PAGE_SIZE = 1;

function estimateInitialPageSize(): number {
    if (typeof window === 'undefined') {
        return 12;
    }

    // Viewport minus app chrome / sidebar header / pagination footer reserve.
    return Math.max(CONFIG_SIDEBAR_MIN_PAGE_SIZE, Math.floor((window.innerHeight - 180) / CONFIG_SIDEBAR_ROW_ESTIMATE_PX));
}

const configItems: ConfigItem[] = [
    {
        href: '/config/banks',
        labelKey: 'nav.banks',
        matches: ['/config/banks'],
        icon: Landmark,
        permission: 'banks.view',
    },
    {
        href: '/config/articles',
        labelKey: 'nav.articles',
        matches: ['/config/articles'],
        icon: Package,
        permission: 'articles.view',
    },
    {
        href: '/config/currencies',
        labelKey: 'nav.currencies',
        matches: ['/config/currencies'],
        icon: Coins,
        permission: 'currencies.view',
    },
    {
        href: '/config/delegations',
        labelKey: 'nav.delegations',
        matches: ['/config/delegations'],
        icon: Building2,
        permission: 'delegations.view',
    },
    {
        href: '/config/field-helps',
        labelKey: 'nav.fieldHelps',
        matches: ['/config/field-helps'],
        icon: CircleHelp,
        permission: 'field_helps.view',
    },
    {
        href: '/config/form-bibles',
        labelKey: 'nav.formBibles',
        matches: ['/config/form-bibles'],
        icon: BookOpen,
        permission: 'form_bibles.view',
    },
    {
        href: '/config/countries',
        labelKey: 'nav.countries',
        matches: ['/config/countries'],
        icon: Globe2,
        permission: 'countries.view',
    },
    {
        href: '/config/integrations',
        labelKey: 'nav.integrations',
        matches: ['/config/integrations'],
        icon: Cable,
        permission: 'integrations.view',
    },
    {
        href: '/config/languages',
        labelKey: 'nav.languages',
        matches: ['/config/languages'],
        icon: Languages,
        permission: 'languages.view',
    },
    {
        href: '/config/job-titles',
        labelKey: 'nav.jobTitles',
        matches: ['/config/job-titles'],
        icon: Briefcase,
        permission: 'job_titles.view',
    },
    {
        href: '/config/cost-centers',
        labelKey: 'nav.costCenters',
        matches: ['/config/cost-centers'],
        icon: Wallet,
        permission: 'cost_centers.view',
    },
    {
        href: '/config/vehicles',
        labelKey: 'nav.vehicles',
        matches: ['/config/vehicles'],
        icon: Car,
        permission: 'vehicles.view',
    },
    {
        href: '/config/tasks-to-perform',
        labelKey: 'nav.tasksToPerform',
        matches: ['/config/tasks-to-perform'],
        icon: ClipboardList,
        permission: 'tasks_to_perform.view',
    },
    {
        href: '/config/checklists',
        labelKey: 'nav.checklists',
        matches: ['/config/checklists'],
        icon: ListChecks,
        permission: 'checklists.view',
    },
    {
        href: '/config/client-priorities',
        labelKey: 'nav.priorities',
        matches: ['/config/client-priorities', '/config/incident-priorities'],
        icon: Flag,
        anyPermissions: ['client_priorities.view', 'incident_priorities.view'],
    },
    {
        href: '/config/roles',
        labelKey: 'nav.roles',
        matches: ['/config/roles'],
        icon: Shield,
        permission: 'roles.view',
    },
    {
        href: '/config/series',
        labelKey: 'nav.series',
        matches: ['/config/series'],
        icon: Hash,
        permission: 'series.view',
    },
    {
        href: '/config/numbering-patterns',
        labelKey: 'nav.numberingPatterns',
        matches: ['/config/numbering-patterns'],
        icon: Hash,
        permission: 'numbering_patterns.view',
    },
    {
        href: '/config/payment-methods',
        labelKey: 'nav.paymentMethods',
        matches: ['/config/payment-methods'],
        icon: Coins,
        permission: 'payment_methods.view',
    },
    {
        href: '/config/payment-documents',
        labelKey: 'nav.paymentDocuments',
        matches: ['/config/payment-documents'],
        icon: Landmark,
        permission: 'payment_documents.view',
    },
    {
        href: '/config/work-order-statuses',
        labelKey: 'nav.statuses',
        matches: [
            '/config/contract-statuses',
            '/config/evaluation-statuses',
            '/config/form-statuses',
            '/config/incident-statuses',
            '/config/technician-incident-statuses',
            '/config/work-order-statuses',
        ],
        icon: CircleDot,
        anyPermissions: [
            'contract_statuses.view',
            'evaluation_statuses.view',
            'form_statuses.view',
            'incident_statuses.view',
            'technician_incident_statuses.view',
            'work_order_statuses.view',
        ],
    },
    {
        href: '/config/teams',
        labelKey: 'nav.teams',
        matches: ['/config/teams'],
        icon: UsersRound,
        permission: 'teams.view',
    },
    {
        href: '/config/timezones',
        labelKey: 'nav.timezones',
        matches: ['/config/timezones'],
        icon: Clock,
        permission: 'timezones.view',
    },
    {
        href: '/config/establishment-types',
        labelKey: 'nav.types',
        matches: [
            '/config/establishment-types',
            '/config/work-order-types',
            '/config/service-types',
            '/config/global-service-types',
            '/config/incident-types',
            '/config/technician-incident-types',
            '/config/form-types',
            '/config/technician-attendance-confirmation-types',
            '/config/other-expense-types',
            '/config/expense-types',
            '/config/indirect-cost-types',
            '/config/compliment-types',
        ],
        icon: Layers,
        anyPermissions: [
            'establishment_types.view',
            'work_order_types.view',
            'service_types.view',
            'global_service_types.view',
            'incident_types.view',
            'technician_incident_types.view',
            'form_types.view',
            'technician_attendance_confirmation_types.view',
            'other_expense_types.view',
            'expense_types.view',
            'indirect_cost_types.view',
            'compliment_types.view',
        ],
    },
    {
        href: '/config/users',
        labelKey: 'nav.users',
        matches: ['/config/users'],
        icon: Users,
        permission: 'users.view',
    },
];

const matchPermission: Record<string, string> = {
    '/config/establishment-types': 'establishment_types.view',
    '/config/work-order-types': 'work_order_types.view',
    '/config/service-types': 'service_types.view',
    '/config/global-service-types': 'global_service_types.view',
    '/config/incident-types': 'incident_types.view',
    '/config/technician-incident-types': 'technician_incident_types.view',
    '/config/form-types': 'form_types.view',
    '/config/technician-attendance-confirmation-types': 'technician_attendance_confirmation_types.view',
    '/config/other-expense-types': 'other_expense_types.view',
    '/config/expense-types': 'expense_types.view',
    '/config/indirect-cost-types': 'indirect_cost_types.view',
    '/config/compliment-types': 'compliment_types.view',
    '/config/contract-statuses': 'contract_statuses.view',
    '/config/evaluation-statuses': 'evaluation_statuses.view',
    '/config/form-statuses': 'form_statuses.view',
    '/config/incident-statuses': 'incident_statuses.view',
    '/config/technician-incident-statuses': 'technician_incident_statuses.view',
    '/config/work-order-statuses': 'work_order_statuses.view',
    '/config/client-priorities': 'client_priorities.view',
    '/config/incident-priorities': 'incident_priorities.view',
    '/config/checklists': 'checklists.view',
};

const matchLabelKey: Record<string, string> = {
    '/config/establishment-types': 'establishmentTypes.resourcePlural',
    '/config/work-order-types': 'workOrderTypes.resourcePlural',
    '/config/service-types': 'serviceTypes.resourcePlural',
    '/config/global-service-types': 'globalServiceTypes.resourcePlural',
    '/config/incident-types': 'incidentTypes.resourcePlural',
    '/config/technician-incident-types': 'technicianIncidentTypes.resourcePlural',
    '/config/form-types': 'formTypes.resourcePlural',
    '/config/technician-attendance-confirmation-types':
        'technicianAttendanceConfirmationTypes.resourcePlural',
    '/config/other-expense-types': 'otherExpenseTypes.resourcePlural',
    '/config/expense-types': 'expenseTypes.resourcePlural',
    '/config/indirect-cost-types': 'indirectCostTypes.resourcePlural',
    '/config/compliment-types': 'complimentTypes.resourcePlural',
    '/config/contract-statuses': 'contractStatuses.resourcePlural',
    '/config/evaluation-statuses': 'evaluationStatuses.resourcePlural',
    '/config/form-statuses': 'formStatuses.resourcePlural',
    '/config/incident-statuses': 'incidentStatuses.resourcePlural',
    '/config/technician-incident-statuses': 'technicianIncidentStatuses.resourcePlural',
    '/config/work-order-statuses': 'workOrderStatuses.resourcePlural',
    '/config/client-priorities': 'clientPriorities.resourcePlural',
    '/config/incident-priorities': 'incidentPriorities.resourcePlural',
    '/config/checklists': 'checklists.resourcePlural',
};

function itemIsVisible(item: ConfigItem, permissions: Record<string, boolean>): boolean {
    if (item.anyPermissions?.length) {
        return item.anyPermissions.some((permission) => permissions[permission]);
    }

    return Boolean(item.permission && permissions[item.permission]);
}

function resolveHref(item: ConfigItem, permissions: Record<string, boolean>): string {
    if (!item.anyPermissions?.length) {
        return item.href;
    }

    const candidates = item.matches
        .filter((match) => {
            const permission = matchPermission[match];

            return permission ? permissions[permission] : false;
        })
        .map((match) => ({
            href: match,
            label: i18n.t(matchLabelKey[match] ?? item.labelKey),
        }))
        .sort((a, b) => a.label.localeCompare(b.label, i18n.language, { sensitivity: 'base' }));

    return candidates[0]?.href ?? item.href;
}

export function isConfigRoute(url: string): boolean {
    return url.startsWith('/config');
}

export function useConfigPermissions(): Record<string, boolean> {
    return {
        'users.view': useCan('users.view'),
        'roles.view': useCan('roles.view'),
        'work_order_types.view': useCan('work_order_types.view'),
        'service_types.view': useCan('service_types.view'),
        'global_service_types.view': useCan('global_service_types.view'),
        'form_types.view': useCan('form_types.view'),
        'technician_attendance_confirmation_types.view': useCan(
            'technician_attendance_confirmation_types.view',
        ),
        'teams.view': useCan('teams.view'),
        'languages.view': useCan('languages.view'),
        'job_titles.view': useCan('job_titles.view'),
        'cost_centers.view': useCan('cost_centers.view'),
        'other_expense_types.view': useCan('other_expense_types.view'),
        'expense_types.view': useCan('expense_types.view'),
        'indirect_cost_types.view': useCan('indirect_cost_types.view'),
        'compliment_types.view': useCan('compliment_types.view'),
        'form_bibles.view': useCan('form_bibles.view'),
        'vehicles.view': useCan('vehicles.view'),
        'tasks_to_perform.view': useCan('tasks_to_perform.view'),
        'checklists.view': useCan('checklists.view'),
        'client_priorities.view': useCan('client_priorities.view'),
        'incident_priorities.view': useCan('incident_priorities.view'),
        'incident_types.view': useCan('incident_types.view'),
        'technician_incident_types.view': useCan('technician_incident_types.view'),
        'integrations.view': useCan('integrations.view'),
        'field_helps.view': useCan('field_helps.view'),
        'establishment_types.view': useCan('establishment_types.view'),
        'banks.view': useCan('banks.view'),
        'articles.view': useCan('articles.view'),
        'timezones.view': useCan('timezones.view'),
        'countries.view': useCan('countries.view'),
        'provinces.view': useCan('provinces.view'),
        'series.view': useCan('series.view'),
        'numbering_patterns.view': useCan('numbering_patterns.view'),
        'payment_methods.view': useCan('payment_methods.view'),
        'payment_documents.view': useCan('payment_documents.view'),
        'work_order_statuses.view': useCan('work_order_statuses.view'),
        'contract_statuses.view': useCan('contract_statuses.view'),
        'evaluation_statuses.view': useCan('evaluation_statuses.view'),
        'form_statuses.view': useCan('form_statuses.view'),
        'incident_statuses.view': useCan('incident_statuses.view'),
        'technician_incident_statuses.view': useCan('technician_incident_statuses.view'),
        'currencies.view': useCan('currencies.view'),
        'delegations.view': useCan('delegations.view'),
    };
}

export function firstConfigHref(permissions: Record<string, boolean>): string | null {
    const items = configItems
        .filter((item) => itemIsVisible(item, permissions))
        .map((item) => ({
            href: resolveHref(item, permissions),
            label: i18n.t(item.labelKey),
        }))
        .sort((a, b) => a.label.localeCompare(b.label, i18n.language, { sensitivity: 'base' }));

    return items[0]?.href ?? null;
}

export function ConfigSidebar() {
    const { t, i18n } = useTranslation();
    const { url } = usePage();
    const permissions = useConfigPermissions();
    const navRef = useRef<HTMLElement>(null);
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(estimateInitialPageSize);

    const permissionKey = useMemo(
        () =>
            Object.entries(permissions)
                .filter(([, allowed]) => allowed)
                .map(([name]) => name)
                .sort()
                .join('|'),
        [permissions],
    );

    const items = useMemo(() => {
        return configItems
            .filter((item) => itemIsVisible(item, permissions))
            .map((item) => ({
                ...item,
                href: resolveHref(item, permissions),
                label: t(item.labelKey),
            }))
            .sort((a, b) => a.label.localeCompare(b.label, i18n.language, { sensitivity: 'base' }));
        // permissionKey is the stable signal; permissions is read for values.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [i18n.language, permissionKey, t]);

    // Fit as many rows as the nav column can hold (no fixed page of 10).
    useEffect(() => {
        const nav = navRef.current;

        if (!nav || typeof ResizeObserver === 'undefined') {
            return;
        }

        const measure = () => {
            const styles = window.getComputedStyle(nav);
            const paddingY = (parseFloat(styles.paddingTop) || 0) + (parseFloat(styles.paddingBottom) || 0);
            const available = Math.max(0, nav.clientHeight - paddingY);
            const firstLink = nav.querySelector('a');
            const gap = parseFloat(styles.rowGap || styles.gap || '0') || 0;
            const rowHeight = firstLink
                ? firstLink.getBoundingClientRect().height + gap
                : CONFIG_SIDEBAR_ROW_ESTIMATE_PX;
            const fit = Math.max(
                CONFIG_SIDEBAR_MIN_PAGE_SIZE,
                Math.floor(available / Math.max(rowHeight, 1)),
            );

            setPageSize((current) => (current === fit ? current : fit));
        };

        const observer = new ResizeObserver(() => {
            measure();
        });

        observer.observe(nav);
        measure();

        return () => observer.disconnect();
    }, [items.length]);

    const lastPage = Math.max(1, Math.ceil(items.length / pageSize));

    // Jump to the active item's page only when the route (or page size) changes.
    useEffect(() => {
        const activeIndex = items.findIndex((item) => item.matches.some((match) => url.startsWith(match)));

        if (activeIndex >= 0) {
            setPage(Math.floor(activeIndex / pageSize) + 1);

            return;
        }

        setPage((current) => Math.min(Math.max(current, 1), lastPage));
        // items is derived from permissionKey; url/pageSize are the intentional triggers.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, permissionKey, pageSize, lastPage]);

    const currentPage = Math.min(Math.max(page, 1), lastPage);
    const pageItems = items.slice((currentPage - 1) * pageSize, currentPage * pageSize);
    const canGoPrev = currentPage > 1;
    const canGoNext = currentPage < lastPage;

    if (items.length === 0) {
        return null;
    }

    return (
        <aside className="flex h-full w-48 shrink-0 flex-col border-r border-line bg-surface">
            <div className="shrink-0 border-b border-line px-3 py-2.5">
                <p className="truncate text-sm font-semibold text-ink">{t('nav.configuration')}</p>
            </div>

            <nav ref={navRef} className="flex min-h-0 flex-1 flex-col gap-0.5 overflow-hidden p-2">
                {pageItems.map((item) => {
                    const active = item.matches.some((match) => url.startsWith(match));
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.labelKey}
                            href={item.href}
                            className={cn(
                                'inline-flex shrink-0 items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
                                active
                                    ? 'bg-brand-soft text-brand'
                                    : 'text-ink-muted hover:bg-canvas hover:text-ink',
                            )}
                        >
                            <Icon className="size-4 shrink-0" aria-hidden />
                            {item.label}
                        </Link>
                    );
                })}
            </nav>

            {lastPage > 1 ? (
                <div className="shrink-0 border-t border-line px-2 py-2">
                    <div className="flex items-center justify-between gap-1">
                        <button
                            type="button"
                            disabled={!canGoPrev}
                            onClick={() => setPage((current) => Math.max(1, current - 1))}
                            className={cn(
                                'inline-flex size-8 items-center justify-center rounded-lg border border-line transition-colors',
                                canGoPrev
                                    ? 'bg-surface text-ink hover:bg-canvas'
                                    : 'cursor-not-allowed bg-canvas text-ink-muted opacity-50',
                            )}
                            aria-label={t('pagination.previous')}
                        >
                            <ChevronLeft className="size-4" aria-hidden />
                        </button>

                        <Select
                            value={String(currentPage)}
                            onChange={(event) => setPage(Number(event.target.value))}
                            className="h-8 w-auto min-w-14 py-1 text-center"
                            aria-label={t('pagination.label')}
                        >
                            {Array.from({ length: lastPage }, (_, index) => {
                                const pageNumber = index + 1;

                                return (
                                    <option key={pageNumber} value={pageNumber}>
                                        {pageNumber}
                                    </option>
                                );
                            })}
                        </Select>

                        <button
                            type="button"
                            disabled={!canGoNext}
                            onClick={() => setPage((current) => Math.min(lastPage, current + 1))}
                            className={cn(
                                'inline-flex size-8 items-center justify-center rounded-lg border border-line transition-colors',
                                canGoNext
                                    ? 'bg-surface text-ink hover:bg-canvas'
                                    : 'cursor-not-allowed bg-canvas text-ink-muted opacity-50',
                            )}
                            aria-label={t('pagination.next')}
                        >
                            <ChevronRight className="size-4" aria-hidden />
                        </button>
                    </div>
                </div>
            ) : null}
        </aside>
    );
}
