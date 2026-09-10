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
    | 'form'
    | 'attendance-confirmation';

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
    const canForm = useCan('form_types.view');
    const canAttendanceConfirmation = useCan('technician_attendance_confirmation_types.view');

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

        return items.sort((a, b) => a.label.localeCompare(b.label, i18n.language, { sensitivity: 'base' }));
    }, [
        canAttendanceConfirmation,
        canEstablishment,
        canForm,
        canGlobalService,
        canIncident,
        canService,
        canWorkOrder,
        i18n.language,
        t,
    ]);

    return <ConfigNavTabs tabs={tabs} activeId={activeId} />;
}
