import type { UserOption } from './common';

export type WorkOrderStatusOption = UserOption & {
    is_open?: boolean;
    confirms_estimate?: boolean;
    rejects_to_estimate?: boolean;
    requires_confirmation?: boolean;
    requires_justification?: boolean;
};

export type WorkOrderStatusListItem = {
    id: number;
    name: string;
    kind: 'estimate' | 'work_order';
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
    is_default: boolean;
    confirms_estimate: boolean;
    rejects_to_estimate: boolean;
    is_post_confirm_default: boolean;
    sets_sent_at: boolean;
    created_at: string | null;
};

export type WorkOrderStatusTransitionForm = {
    to_status_id: number;
    requires_confirmation: boolean;
    requires_justification: boolean;
};

export type WorkOrderStatusFormData = {
    id: number;
    name: string;
    kind: 'estimate' | 'work_order';
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
    is_default: boolean;
    confirms_estimate: boolean;
    rejects_to_estimate: boolean;
    is_post_confirm_default: boolean;
    sets_sent_at: boolean;
    transitions: WorkOrderStatusTransitionForm[];
};
