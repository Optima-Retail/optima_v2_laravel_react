import { Link, usePage } from '@inertiajs/react';
import { Building2, Cable, ClipboardList, Clock, Coins, Globe2, Hash, Landmark, Languages, Shield, Star, Users, UsersRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useCan } from '@/hooks/useAuth';
import { cn } from '@/support/cn';

type ConfigItem = {
    href: string;
    labelKey: string;
    match: string;
    icon: typeof Users;
    permission: string;
};

const configItems: ConfigItem[] = [
    { href: '/config/users', labelKey: 'nav.users', match: '/config/users', icon: Users, permission: 'users.view' },
    { href: '/config/roles', labelKey: 'nav.roles', match: '/config/roles', icon: Shield, permission: 'roles.view' },
    {
        href: '/config/work-order-types',
        labelKey: 'nav.workOrderTypes',
        match: '/config/work-order-types',
        icon: ClipboardList,
        permission: 'work_order_types.view',
    },
    {
        href: '/config/teams',
        labelKey: 'nav.teams',
        match: '/config/teams',
        icon: UsersRound,
        permission: 'teams.view',
    },
    {
        href: '/config/languages',
        labelKey: 'nav.languages',
        match: '/config/languages',
        icon: Languages,
        permission: 'languages.view',
    },
    {
        href: '/config/integrations',
        labelKey: 'nav.integrations',
        match: '/config/integrations',
        icon: Cable,
        permission: 'integrations.view',
    },
    {
        href: '/config/rating-types',
        labelKey: 'nav.ratingTypes',
        match: '/config/rating-types',
        icon: Star,
        permission: 'rating_types.view',
    },
    {
        href: '/config/banks',
        labelKey: 'nav.banks',
        match: '/config/banks',
        icon: Landmark,
        permission: 'banks.view',
    },
    {
        href: '/config/timezones',
        labelKey: 'nav.timezones',
        match: '/config/timezones',
        icon: Clock,
        permission: 'timezones.view',
    },
    {
        href: '/config/countries',
        labelKey: 'nav.countries',
        match: '/config/countries',
        icon: Globe2,
        permission: 'countries.view',
    },
    {
        href: '/config/series',
        labelKey: 'nav.series',
        match: '/config/series',
        icon: Hash,
        permission: 'series.view',
    },
    {
        href: '/config/currencies',
        labelKey: 'nav.currencies',
        match: '/config/currencies',
        icon: Coins,
        permission: 'currencies.view',
    },
    {
        href: '/config/delegations',
        labelKey: 'nav.delegations',
        match: '/config/delegations',
        icon: Building2,
        permission: 'delegations.view',
    },
];

export function isConfigRoute(url: string): boolean {
    return url.startsWith('/config');
}

export function firstConfigHref(
    canViewUsers: boolean,
    canViewRoles: boolean,
    canViewWorkOrderTypes = false,
    canViewTeams = false,
    canViewLanguages = false,
    canViewIntegrations = false,
    canViewRatingTypes = false,
    canViewBanks = false,
    canViewTimezones = false,
    canViewCountries = false,
    canViewSeries = false,
    canViewCurrencies = false,
    canViewDelegations = false,
): string | null {
    if (canViewUsers) {
        return '/config/users';
    }

    if (canViewRoles) {
        return '/config/roles';
    }

    if (canViewWorkOrderTypes) {
        return '/config/work-order-types';
    }

    if (canViewTeams) {
        return '/config/teams';
    }

    if (canViewLanguages) {
        return '/config/languages';
    }

    if (canViewIntegrations) {
        return '/config/integrations';
    }

    if (canViewRatingTypes) {
        return '/config/rating-types';
    }

    if (canViewBanks) {
        return '/config/banks';
    }

    if (canViewTimezones) {
        return '/config/timezones';
    }

    if (canViewCountries) {
        return '/config/countries';
    }

    if (canViewSeries) {
        return '/config/series';
    }

    if (canViewCurrencies) {
        return '/config/currencies';
    }

    if (canViewDelegations) {
        return '/config/delegations';
    }

    return null;
}

export function ConfigSidebar() {
    const { t } = useTranslation();
    const { url } = usePage();
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

    const permissions: Record<string, boolean> = {
        'users.view': canViewUsers,
        'roles.view': canViewRoles,
        'work_order_types.view': canViewWorkOrderTypes,
        'teams.view': canViewTeams,
        'languages.view': canViewLanguages,
        'integrations.view': canViewIntegrations,
        'rating_types.view': canViewRatingTypes,
        'banks.view': canViewBanks,
        'timezones.view': canViewTimezones,
        'countries.view': canViewCountries,
        'series.view': canViewSeries,
        'currencies.view': canViewCurrencies,
        'delegations.view': canViewDelegations,
    };

    const items = configItems.filter((item) => permissions[item.permission]);

    if (items.length === 0) {
        return null;
    }

    return (
        <aside className="w-48 shrink-0 border-r border-line bg-surface">
            <div className="border-b border-line px-3 py-2.5">
                <p className="truncate text-sm font-semibold text-ink">{t('nav.configuration')}</p>
            </div>

            <nav className="flex flex-col gap-0.5 p-2">
                {items.map((item) => {
                    const active = url.startsWith(item.match);
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'inline-flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
                                active
                                    ? 'bg-brand-soft text-brand'
                                    : 'text-ink-muted hover:bg-canvas hover:text-ink',
                            )}
                        >
                            <Icon className="size-4 shrink-0" aria-hidden />
                            {t(item.labelKey)}
                        </Link>
                    );
                })}
            </nav>
        </aside>
    );
}
