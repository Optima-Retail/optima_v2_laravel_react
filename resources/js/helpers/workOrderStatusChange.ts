import type { TFunction } from 'i18next';
import { confirmWithComment } from '@/helpers/confirm';
import type { WorkOrderStatusOption } from '@/support/types/domain/work-order-status';

export async function confirmWorkOrderStatusChange(options: {
    t: TFunction;
    statusOptions: WorkOrderStatusOption[];
    currentStatusId: string;
    nextStatusId: string;
}): Promise<{ confirmed: boolean; justification: string }> {
    const selected = options.statusOptions.find((item) => String(item.id) === options.nextStatusId);
    const changed = options.nextStatusId !== String(options.currentStatusId);

    if (!changed || !selected) {
        return { confirmed: true, justification: '' };
    }

    const needsComment = Boolean(selected.requires_justification);
    const needsConfirm =
        Boolean(selected.requires_confirmation) ||
        Boolean(selected.confirms_estimate) ||
        Boolean(selected.rejects_to_estimate) ||
        needsComment;

    if (!needsConfirm) {
        return { confirmed: true, justification: '' };
    }

    let title = options.t('workOrders.statusChangeTitle');
    let message = options.t('workOrders.statusChangeMessage');
    let confirmLabel = options.t('common.save');

    if (selected.confirms_estimate) {
        title = options.t('estimates.approveTitle');
        message = options.t('estimates.approveMessage');
        confirmLabel = options.t('estimates.approveConfirm');
    } else if (selected.rejects_to_estimate) {
        title = options.t('workOrders.rejectTitle');
        message = options.t('workOrders.rejectMessage');
        confirmLabel = options.t('workOrders.rejectConfirm');
    }

    const result = await confirmWithComment({
        title,
        message,
        confirmLabel,
        requireComment: needsComment,
        minCommentLength: 10,
        commentLabel: options.t('workOrders.statusJustification'),
        commentPlaceholder: options.t('workOrders.statusJustificationPlaceholder'),
    });

    return {
        confirmed: result.confirmed,
        justification: result.comment,
    };
}
