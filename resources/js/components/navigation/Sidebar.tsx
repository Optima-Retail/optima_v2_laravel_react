import { Link, router, usePage } from '@inertiajs/react';
import { Building2, LayoutDashboard, Settings, Tags, Truck, UserRound, Warehouse } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { firstConfigHref, isConfigRoute } from '@/components/navigation/ConfigSidebar';
import { useCan } from '@/hooks/useAuth';
import { useUiStore } from '@/stores/uiStore';
import { cn } from '@/support/cn';
import { SHELL_HEADER_CLASS } from '@/support/shell';
import type { SharedPageProps } from '@/types';
import logoCollapsed from '../../../img/logo/logo-2.png';
import logoExpanded from '../../../img/logo/logo-3.png';

type NavItem = {
    key: string;
    href?: string;
    labelKey: string;
    match?: string | null;
    icon: typeof LayoutDashboard;
    external?: boolean;
    action?: 'config';
};

const nav: NavItem[] = [
    { key: 'overview', href: '/dashboard', labelKey: 'nav.overview', match: '/dashboard', icon: LayoutDashboard },
    { key: 'companies', href: '/companies', labelKey: 'nav.companies', match: '/companies', icon: Building2 },
    { key: 'clients', href: '/clients', labelKey: 'nav.clients', match: '/clients', icon: UserRound },
    { key: 'suppliers', href: '/suppliers', labelKey: 'nav.suppliers', match: '/suppliers', icon: Truck },
    { key: 'establishments', href: '/establishments', labelKey: 'nav.establishments', match: '/establishments', icon: Warehouse },
    { key: 'brands', href: '/brands', labelKey: 'nav.brands', match: '/brands', icon: Tags },
    { key: 'config', labelKey: 'nav.configuration', icon: Settings, action: 'config' },
];

export function Sidebar() {
    const { t } = useTranslation();
    const { url, props } = usePage<SharedPageProps>();
    const canViewUsers = useCan('users.view');
    const canViewRoles = useCan('roles.view');
    const canViewWorkOrderTypes = useCan('work_order_types.view');
    const canViewTeams = useCan('teams.view');
    const canViewLanguages = useCan('languages.view');
    const canViewIntegrations = useCan('integrations.view');
    const canViewRatingTypes = useCan('rating_types.view');
    const canViewBanks = useCan('banks.view');
    const canViewTimezones = useCan('timezones.view');
    const canViewCountries = useCan('countries.view');
    const canViewSeries = useCan('series.view');
    const canViewCurrencies = useCan('currencies.view');
    const canViewDelegations = useCan('delegations.view');
    const canViewBrands = useCan('brands.view');
    const canViewCompanies = useCan('companies.view');
    const canViewRelationships = useCan('company_relationships.view');
    const canViewEstablishments = useCan('establishments.view');
    const hasCompanyContext = Boolean(props.auth.company);
    const sidebarOpen = useUiStore((state) => state.sidebarOpen);
    const setSidebarOpen = useUiStore((state) => state.setSidebarOpen);
    const configActive = isConfigRoute(url);
    const configHref = firstConfigHref(
        canViewUsers,
        canViewRoles,
        canViewWorkOrderTypes,
        canViewTeams,
        canViewLanguages,
        canViewIntegrations,
        canViewRatingTypes,
        canViewBanks,
        canViewTimezones,
        canViewCountries,
        canViewSeries,
        canViewCurrencies,
        canViewDelegations,
    );

    function openConfig() {
        if (!configHref) {
            return;
        }

        if (!configActive) {
            router.visit(configHref);
        }
    }

    return (
        <>
            {sidebarOpen ? (
                <button
                    type="button"
                    className="fixed inset-0 z-20 bg-ink/20 lg:hidden"
                    aria-label={t('nav.closeSidebar')}
                    onClick={() => setSidebarOpen(false)}
                />
            ) : null}

            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-30 flex h-screen flex-col border-r border-line bg-surface transition-[width,opacity] duration-200 lg:sticky lg:top-0',
                    sidebarOpen
                        ? 'w-60 opacity-100'
                        : 'w-0 overflow-hidden border-r-0 opacity-0 lg:w-16 lg:overflow-visible lg:border-r lg:opacity-100',
                )}
            >
                <div className={cn(SHELL_HEADER_CLASS, 'justify-center overflow-hidden px-3')}>
                    <Link href="/dashboard" className="flex items-center justify-center" aria-label={props.app.name}>
                        <img
                            src={sidebarOpen ? logoExpanded : logoCollapsed}
                            alt={props.app.name}
                            className={cn(
                                'object-contain',
                                sidebarOpen ? 'h-7 w-auto max-w-[9.5rem]' : 'size-7',
                            )}
                        />
                    </Link>
                </div>

                <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto p-2">
                    {nav.map((item) => {
                        if (item.key === 'brands' && !canViewBrands) {
                            return null;
                        }

                        if (item.key === 'companies' && !canViewCompanies) {
                            return null;
                        }

                        if (item.key === 'clients' && (!canViewRelationships || !hasCompanyContext)) {
                            return null;
                        }

                        if (item.key === 'suppliers' && (!canViewRelationships || !hasCompanyContext)) {
                            return null;
                        }

                        if (item.key === 'establishments' && (!canViewEstablishments || !hasCompanyContext)) {
                            return null;
                        }

                        if (item.action === 'config' && !configHref) {
                            return null;
                        }

                        const Icon = item.icon;
                        const label = t(item.labelKey);
                        const active =
                            item.action === 'config'
                                ? configActive
                                : item.match
                                  ? url.startsWith(item.match)
                                  : false;

                        const className = cn(
                            'inline-flex items-center rounded-lg text-sm font-medium transition-colors',
                            sidebarOpen ? 'gap-2.5 px-2.5 py-2' : 'justify-center px-2 py-2',
                            active
                                ? 'bg-brand-soft text-brand'
                                : 'text-ink-muted hover:bg-canvas hover:text-ink',
                        );

                        if (item.action === 'config') {
                            return (
                                <button
                                    key={item.key}
                                    type="button"
                                    title={label}
                                    onClick={openConfig}
                                    className={className}
                                >
                                    <Icon className="size-4 shrink-0" aria-hidden />
                                    {sidebarOpen ? label : null}
                                </button>
                            );
                        }

                        if (item.external && item.href) {
                            return (
                                <a
                                    key={item.key}
                                    href={item.href}
                                    target="_blank"
                                    rel="noreferrer"
                                    title={label}
                                    className={cn(className, !active && 'hover:bg-brand-soft hover:text-brand')}
                                >
                                    <Icon className="size-4 shrink-0" aria-hidden />
                                    {sidebarOpen ? label : null}
                                </a>
                            );
                        }

                        if (!item.href) {
                            return null;
                        }

                        return (
                            <Link key={item.key} href={item.href} title={label} className={className}>
                                <Icon className="size-4 shrink-0" aria-hidden />
                                {sidebarOpen ? label : null}
                            </Link>
                        );
                    })}
                </nav>
            </aside>
        </>
    );
}
