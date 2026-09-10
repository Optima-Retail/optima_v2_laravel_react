import { useTranslation } from 'react-i18next';
import { ClientEstablishmentsPanel } from '@/components/clients/ClientEstablishmentsPanel';
import { Button } from '@/components/ui/Button';
import { BaseModal } from '@/components/ui/BaseModal';

type ClientEstablishmentsModalProps = {
    open: boolean;
    relationshipId: number;
    clientName: string;
    canEdit: boolean;
    onClose: () => void;
};

export function ClientEstablishmentsModal({
    open,
    relationshipId,
    clientName,
    canEdit,
    onClose,
}: ClientEstablishmentsModalProps) {
    const { t } = useTranslation();

    return (
        <BaseModal
            open={open}
            onClose={onClose}
            size="xl"
            title={t('clients.establishmentsModalTitle', { client: clientName })}
            footer={
                <Button type="button" variant="secondary" onClick={onClose}>
                    {t('common.close')}
                </Button>
            }
        >
            {open ? (
                <ClientEstablishmentsPanel
                    relationshipId={relationshipId}
                    canEdit={canEdit}
                    embedded
                />
            ) : null}
        </BaseModal>
    );
}
