import i18n from '@/i18n';

export type BreadcrumbItem = {
    label: string;
    href?: string;
};

const labelKeys: Record<string, string> = {
    dashboard: 'nav.overview',
    config: 'nav.configuration',
    users: 'nav.users',
    roles: 'nav.roles',
    'work-order-types': 'nav.workOrderTypes',
    'form-types': 'nav.formTypes',
    'technician-attendance-confirmation-types': 'nav.technicianAttendanceConfirmationTypes',
    priorities: 'nav.priorities',
    'work-order-statuses': 'nav.statuses',
    'estimate-statuses': 'nav.statuses',
    'contract-statuses': 'nav.statuses',
    checklists: 'nav.checklists',
    'cost-centers': 'nav.costCenters',
    'other-expense-types': 'nav.types',
    'technician-incident-types': 'nav.types',
    'technician-incident-statuses': 'nav.statuses',
    'expense-types': 'nav.types',
    'indirect-cost-types': 'nav.types',
    teams: 'nav.teams',
    languages: 'nav.languages',
    integrations: 'nav.integrations',
    'establishment-types': 'nav.establishmentTypes',
    banks: 'nav.banks',
    timezones: 'nav.timezones',
    countries: 'nav.countries',
    provinces: 'nav.provinces',
    series: 'nav.series',
    'numbering-patterns': 'nav.numberingPatterns',
    currencies: 'nav.currencies',
    delegations: 'nav.delegations',
    brands: 'nav.brands',
    companies: 'nav.companies',
    clients: 'nav.clients',
    suppliers: 'nav.suppliers',
    technicians: 'nav.technicians',
    'technician-incidents': 'technicianIncidents.title',
    relationships: 'nav.relationships',
    establishments: 'nav.establishments',
    contracts: 'nav.contracts',
    estimates: 'nav.estimates',
    'work-orders': 'nav.workOrders',
    create: 'nav.create',
    edit: 'nav.edit',
};

function humanize(segment: string): string {
    if (labelKeys[segment]) {
        return i18n.t(labelKeys[segment]);
    }

    if (/^\d+$/.test(segment)) {
        return i18n.t('nav.details');
    }

    return segment
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

/**
 * Builds breadcrumbs from the current Inertia URL path.
 */
export function breadcrumbsFromUrl(url: string, title?: string): BreadcrumbItem[] {
    const path = url.split('?')[0] ?? '/';
    const segments = path.split('/').filter(Boolean);

    const items: BreadcrumbItem[] = [{ label: i18n.t('nav.home'), href: '/dashboard' }];

    if (segments.length === 0) {
        return title ? [{ label: title }] : items;
    }

    let href = '';

    segments.forEach((segment, index) => {
        const isLast = index === segments.length - 1;
        const isId = /^\d+$/.test(segment);

        if (isId) {
            return;
        }

        href = `${href}/${segment}`;
        items.push({
            label: isLast && title ? title : humanize(segment),
            href: isLast ? undefined : href,
        });
    });

    return items;
}
