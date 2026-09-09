import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { ConfigNavTabs, type ConfigNavTab } from '@/components/config/ConfigNavTabs';
import { useCan } from '@/hooks/useAuth';

export type TypesConfigTabId = 'establishment' | 'rating' | 'work-order' | 'incident';

type TypesConfigTabsProps = {
    activeId: TypesConfigTabId;
};

export function TypesConfigTabs({ activeId }: TypesConfigTabsProps) {
    const { t, i18n } = useTranslation();
    const canEstablishment = useCan('establishment_types.view');
    const canRating = useCan('rating_types.view');
    const canWorkOrder = useCan('work_order_types.view');
    const canIncident = useCan('incident_types.view');

    const tabs = useMemo(() => {
        const items: ConfigNavTab[] = [];

        if (canEstablishment) {
            items.push({
                id: 'establishment',
                href: '/config/establishment-types',
                label: t('establishmentTypes.resourcePlural'),
            });
        }

        if (canRating) {
            items.push({
                id: 'rating',
                href: '/config/rating-types',
                label: t('ratingTypes.resourcePlural'),
            });
        }

        if (canWorkOrder) {
            items.push({
                id: 'work-order',
                href: '/config/work-order-types',
                label: t('workOrderTypes.resourcePlural'),
            });
        }

        if (canIncident) {
            items.push({
                id: 'incident',
                href: '/config/incident-types',
                label: t('incidentTypes.resourcePlural'),
            });
        }

        return items.sort((a, b) => a.label.localeCompare(b.label, i18n.language, { sensitivity: 'base' }));
    }, [canEstablishment, canIncident, canRating, canWorkOrder, i18n.language, t]);

    return <ConfigNavTabs tabs={tabs} activeId={activeId} />;
}
