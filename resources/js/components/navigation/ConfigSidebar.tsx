import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Cable,
    CircleHelp,
    CircleDot,
    Clock,
    Coins,
    Flag,
    Globe2,
    Hash,
    Landmark,
    Languages,
    Layers,
    Shield,
    Users,
    UsersRound,
} from 'lucide-react';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import i18n from '@/i18n';
import { useCan } from '@/hooks/useAuth';
import { cn } from '@/support/cn';

type ConfigItem = {
    href: string;
    labelKey: string;
    matches: string[];
    icon: typeof Users;
    permission?: string;
    anyPermissions?: string[];
};

const configItems: ConfigItem[] = [
    {
        href: '/config/banks',
        labelKey: 'nav.banks',
        matches: ['/config/banks'],
        icon: Landmark,
        permission: 'banks.view',
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
        href: '/config/work-order-statuses',
        labelKey: 'nav.statuses',
        matches: ['/config/contract-statuses', '/config/evaluation-statuses', '/config/incident-statuses', '/config/work-order-statuses'],
        icon: CircleDot,
        anyPermissions: ['contract_statuses.view', 'evaluation_statuses.view', 'incident_statuses.view', 'work_order_statuses.view'],
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
        href: '/config/rating-types',
        labelKey: 'nav.types',
        matches: [
            '/config/rating-types',
            '/config/establishment-types',
            '/config/work-order-types',
            '/config/incident-types',
        ],
        icon: Layers,
        anyPermissions: [
            'rating_types.view',
            'establishment_types.view',
            'work_order_types.view',
            'incident_types.view',
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
    '/config/rating-types': 'rating_types.view',
    '/config/establishment-types': 'establishment_types.view',
    '/config/work-order-types': 'work_order_types.view',
    '/config/incident-types': 'incident_types.view',
    '/config/contract-statuses': 'contract_statuses.view',
    '/config/evaluation-statuses': 'evaluation_statuses.view',
    '/config/incident-statuses': 'incident_statuses.view',
    '/config/work-order-statuses': 'work_order_statuses.view',
    '/config/client-priorities': 'client_priorities.view',
    '/config/incident-priorities': 'incident_priorities.view',
};

const matchLabelKey: Record<string, string> = {
    '/config/rating-types': 'ratingTypes.resourcePlural',
    '/config/establishment-types': 'establishmentTypes.resourcePlural',
    '/config/work-order-types': 'workOrderTypes.resourcePlural',
    '/config/incident-types': 'incidentTypes.resourcePlural',
    '/config/contract-statuses': 'contractStatuses.resourcePlural',
    '/config/evaluation-statuses': 'evaluationStatuses.resourcePlural',
    '/config/incident-statuses': 'incidentStatuses.resourcePlural',
    '/config/work-order-statuses': 'workOrderStatuses.resourcePlural',
    '/config/client-priorities': 'clientPriorities.resourcePlural',
    '/config/incident-priorities': 'incidentPriorities.resourcePlural',
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
        'teams.view': useCan('teams.view'),
        'languages.view': useCan('languages.view'),
        'client_priorities.view': useCan('client_priorities.view'),
        'incident_priorities.view': useCan('incident_priorities.view'),
        'incident_types.view': useCan('incident_types.view'),
        'integrations.view': useCan('integrations.view'),
        'rating_types.view': useCan('rating_types.view'),
        'field_helps.view': useCan('field_helps.view'),
        'establishment_types.view': useCan('establishment_types.view'),
        'banks.view': useCan('banks.view'),
        'timezones.view': useCan('timezones.view'),
        'countries.view': useCan('countries.view'),
        'provinces.view': useCan('provinces.view'),
        'series.view': useCan('series.view'),
        'numbering_patterns.view': useCan('numbering_patterns.view'),
        'work_order_statuses.view': useCan('work_order_statuses.view'),
        'contract_statuses.view': useCan('contract_statuses.view'),
        'evaluation_statuses.view': useCan('evaluation_statuses.view'),
        'incident_statuses.view': useCan('incident_statuses.view'),
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

    const items = useMemo(() => {
        return configItems
            .filter((item) => itemIsVisible(item, permissions))
            .map((item) => ({
                ...item,
                href: resolveHref(item, permissions),
                label: t(item.labelKey),
            }))
            .sort((a, b) => a.label.localeCompare(b.label, i18n.language, { sensitivity: 'base' }));
    }, [i18n.language, permissions, t]);

    if (items.length === 0) {
        return null;
    }

    return (
        <aside className="flex h-full w-48 shrink-0 flex-col border-r border-line bg-surface">
            <div className="shrink-0 border-b border-line px-3 py-2.5">
                <p className="truncate text-sm font-semibold text-ink">{t('nav.configuration')}</p>
            </div>

            <nav className="app-scroll flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto p-2">
                {items.map((item) => {
                    const active = item.matches.some((match) => url.startsWith(match));
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.labelKey}
                            href={item.href}
                            className={cn(
                                'inline-flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
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
        </aside>
    );
}
