import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { ConfigNavTabs, type ConfigNavTab } from '@/components/config/ConfigNavTabs';
import { useCan } from '@/hooks/useAuth';

export type StatusConfigTabId = 'work-order' | 'contract' | 'evaluation' | 'incident' | 'form';

type StatusConfigTabsProps = {
    activeId: StatusConfigTabId;
};

/** Status hub tabs — more entity status catalogs can be added later. */
export function StatusConfigTabs({ activeId }: StatusConfigTabsProps) {
    const { t, i18n } = useTranslation();
    const canWorkOrder = useCan('work_order_statuses.view');
    const canContract = useCan('contract_statuses.view');
    const canEvaluation = useCan('evaluation_statuses.view');
    const canIncident = useCan('incident_statuses.view');
    const canForm = useCan('form_statuses.view');

    const tabs = useMemo(() => {
        const items: ConfigNavTab[] = [];

        if (canWorkOrder) {
            items.push({
                id: 'work-order',
                href: '/config/work-order-statuses',
                label: t('workOrderStatuses.resourcePlural'),
            });
        }

        if (canContract) {
            items.push({
                id: 'contract',
                href: '/config/contract-statuses',
                label: t('contractStatuses.resourcePlural'),
            });
        }

        if (canEvaluation) {
            items.push({
                id: 'evaluation',
                href: '/config/evaluation-statuses',
                label: t('evaluationStatuses.resourcePlural'),
            });
        }

        if (canIncident) {
            items.push({
                id: 'incident',
                href: '/config/incident-statuses',
                label: t('incidentStatuses.resourcePlural'),
            });
        }

        if (canForm) {
            items.push({
                id: 'form',
                href: '/config/form-statuses',
                label: t('formStatuses.resourcePlural'),
            });
        }

        return items.sort((a, b) => a.label.localeCompare(b.label, i18n.language, { sensitivity: 'base' }));
    }, [canContract, canEvaluation, canForm, canIncident, canWorkOrder, i18n.language, t]);

    return <ConfigNavTabs tabs={tabs} activeId={activeId} alwaysShow />;
}
