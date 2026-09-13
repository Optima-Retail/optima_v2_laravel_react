import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { ConfigNavTabs, type ConfigNavTab } from '@/components/config/ConfigNavTabs';
import { useCan } from '@/hooks/useAuth';

export type PriorityConfigTabId = 'client' | 'incident' | 'technician-request';

type PriorityConfigTabsProps = {
    activeId: PriorityConfigTabId;
};

/** Priority hub tabs — client company catalog and incident resolution priorities. */
export function PriorityConfigTabs({ activeId }: PriorityConfigTabsProps) {
    const { t, i18n } = useTranslation();
    const canClient = useCan('client_priorities.view');
    const canIncident = useCan('incident_priorities.view');
    const canTechnicianRequest = useCan('technician_request_priorities.view');

    const tabs = useMemo(() => {
        const items: ConfigNavTab[] = [];

        if (canClient) {
            items.push({
                id: 'client',
                href: '/config/client-priorities',
                label: t('clientPriorities.resourcePlural'),
            });
        }

        if (canIncident) {
            items.push({
                id: 'incident',
                href: '/config/incident-priorities',
                label: t('incidentPriorities.resourcePlural'),
            });
        }

        if (canTechnicianRequest) {
            items.push({
                id: 'technician-request',
                href: '/config/technician-request-priorities',
                label: t('technicianRequestPriorities.resourcePlural'),
            });
        }

        return items.sort((a, b) => a.label.localeCompare(b.label, i18n.language, { sensitivity: 'base' }));
    }, [canClient, canIncident, canTechnicianRequest, i18n.language, t]);

    return <ConfigNavTabs tabs={tabs} activeId={activeId} alwaysShow />;
}
