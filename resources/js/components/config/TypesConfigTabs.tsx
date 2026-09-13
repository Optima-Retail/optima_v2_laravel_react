import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { ConfigNavTabs, type ConfigNavTab } from '@/components/config/ConfigNavTabs';
import { useCan } from '@/hooks/useAuth';

export type TypesConfigTabId =
    | 'establishment'
    | 'work-order'
    | 'service'
    | 'global-service'
    | 'incident'
    | 'technician-incident'
    | 'form'
    | 'attendance-confirmation'
    | 'other-expense'
    | 'expense'
    | 'indirect-cost'
    | 'compliment';

type TypesConfigTabsProps = {
    activeId: TypesConfigTabId;
};

export function TypesConfigTabs({ activeId }: TypesConfigTabsProps) {
    const { t, i18n } = useTranslation();
    const canEstablishment = useCan('establishment_types.view');
    const canWorkOrder = useCan('work_order_types.view');
    const canService = useCan('service_types.view');
    const canGlobalService = useCan('global_service_types.view');
    const canIncident = useCan('incident_types.view');
    const canTechnicianIncident = useCan('technician_incident_types.view');
    const canForm = useCan('form_types.view');
    const canAttendanceConfirmation = useCan('technician_attendance_confirmation_types.view');
    const canOtherExpense = useCan('other_expense_types.view');
    const canExpense = useCan('expense_types.view');
    const canIndirectCost = useCan('indirect_cost_types.view');
    const canCompliment = useCan('compliment_types.view');

    const tabs = useMemo(() => {
        const items: ConfigNavTab[] = [];

        if (canEstablishment) {
            items.push({
                id: 'establishment',
                href: '/config/establishment-types',
                label: t('establishmentTypes.resourcePlural'),
            });
        }

        if (canWorkOrder) {
            items.push({
                id: 'work-order',
                href: '/config/work-order-types',
                label: t('workOrderTypes.resourcePlural'),
            });
        }

        if (canService) {
            items.push({
                id: 'service',
                href: '/config/service-types',
                label: t('serviceTypes.resourcePlural'),
            });
        }

        if (canGlobalService) {
            items.push({
                id: 'global-service',
                href: '/config/global-service-types',
                label: t('globalServiceTypes.resourcePlural'),
            });
        }

        if (canIncident) {
            items.push({
                id: 'incident',
                href: '/config/incident-types',
                label: t('incidentTypes.resourcePlural'),
            });
        }

        if (canTechnicianIncident) {
            items.push({
                id: 'technician-incident',
                href: '/config/technician-incident-types',
                label: t('technicianIncidentTypes.resourcePlural'),
            });
        }

        if (canForm) {
            items.push({
                id: 'form',
                href: '/config/form-types',
                label: t('formTypes.resourcePlural'),
            });
        }

        if (canAttendanceConfirmation) {
            items.push({
                id: 'attendance-confirmation',
                href: '/config/technician-attendance-confirmation-types',
                label: t('technicianAttendanceConfirmationTypes.resourcePlural'),
            });
        }

        if (canOtherExpense) {
            items.push({
                id: 'other-expense',
                href: '/config/other-expense-types',
                label: t('otherExpenseTypes.resourcePlural'),
            });
        }

        if (canExpense) {
            items.push({
                id: 'expense',
                href: '/config/expense-types',
                label: t('expenseTypes.resourcePlural'),
            });
        }

        if (canIndirectCost) {
            items.push({
                id: 'indirect-cost',
                href: '/config/indirect-cost-types',
                label: t('indirectCostTypes.resourcePlural'),
            });
        }

        if (canCompliment) {
            items.push({
                id: 'compliment',
                href: '/config/compliment-types',
                label: t('complimentTypes.resourcePlural'),
            });
        }

        return items.sort((a, b) => a.label.localeCompare(b.label, i18n.language, { sensitivity: 'base' }));
    }, [
        canAttendanceConfirmation,
        canCompliment,
        canEstablishment,
        canExpense,
        canForm,
        canGlobalService,
        canIncident,
        canIndirectCost,
        canOtherExpense,
        canService,
        canTechnicianIncident,
        canWorkOrder,
        i18n.language,
        t,
    ]);

    return <ConfigNavTabs tabs={tabs} activeId={activeId} />;
}
