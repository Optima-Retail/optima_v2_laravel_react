import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BaseModal } from '@/components/ui/BaseModal';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import type { WorkOrderStatusOption } from '@/support/types/domain/work-order-status';

type EstimateBulkStatusModalProps = {
    open: boolean;
    selectedCount: number;
    statusOptions: WorkOrderStatusOption[];
    processing?: boolean;
    onClose: () => void;
    onConfirm: (payload: { statusId: number; justification: string }) => void;
};

export function EstimateBulkStatusModal({
    open,
    selectedCount,
    statusOptions,
    processing = false,
    onClose,
    onConfirm,
}: EstimateBulkStatusModalProps) {
    const { t } = useTranslation();
    const [statusId, setStatusId] = useState('');
    const [justification, setJustification] = useState('');

    useEffect(() => {
        if (!open) {
            setStatusId('');
            setJustification('');
        }
    }, [open]);

    const selected = statusOptions.find((option) => String(option.id) === statusId);
    const requiresJustification = Boolean(selected?.requires_justification);
    const canSubmit =
        statusId !== '' &&
        (!requiresJustification || justification.trim().length >= 10) &&
        !processing;

    const selectOptions = useMemo(
        () =>
            statusOptions.map((option) => ({
                value: String(option.id),
                label: option.label,
                color: option.color ?? null,
            })),
        [statusOptions],
    );

    function handleClose() {
        if (processing) {
            return;
        }

        onClose();
    }

    return (
        <BaseModal
            open={open}
            onClose={handleClose}
            closeDisabled={processing}
            size="md"
            title={t('estimates.bulkStatusTitle')}
            description={t('estimates.bulkStatusDescription', { count: selectedCount })}
            footer={
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" disabled={processing} onClick={handleClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="button"
                        loading={processing}
                        disabled={!canSubmit}
                        onClick={() => {
                            if (!canSubmit) {
                                return;
                            }

                            onConfirm({
                                statusId: Number(statusId),
                                justification: justification.trim(),
                            });
                        }}
                    >
                        {selected?.confirms_estimate
                            ? t('estimates.approveConfirm')
                            : t('estimates.bulkStatusConfirm')}
                    </Button>
                </div>
            }
        >
            <div className="space-y-4">
                <Field label={t('estimates.status')} htmlFor="bulk-status-id" required helpField={false}>
                    <SearchableSelect
                        id="bulk-status-id"
                        value={statusId}
                        onChange={setStatusId}
                        options={selectOptions}
                        emptyLabel={t('common.select')}
                        disabled={processing}
                    />
                </Field>

                <Field
                    label={t('workOrders.statusJustification')}
                    htmlFor="bulk-status-justification"
                    required={requiresJustification}
                    helpField={false}
                >
                    <textarea
                        id="bulk-status-justification"
                        value={justification}
                        disabled={processing}
                        rows={3}
                        placeholder={t('workOrders.statusJustificationPlaceholder')}
                        onChange={(event) => setJustification(event.target.value)}
                        className="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 disabled:opacity-60"
                    />
                </Field>
            </div>
        </BaseModal>
    );
}
