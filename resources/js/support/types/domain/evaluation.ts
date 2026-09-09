export type EvaluationListItem = {
    id: number;
    subject: string | null;
    establishment_name: string | null;
    status_name: string | null;
    status_color: string | null;
    responsible_user_name: string | null;
    next_action_at: string | null;
    visit_count: number;
    call_count: number;
    created_at: string | null;
};

export type EvaluationFormData = {
    id: number;
    subject: string | null;
    public_id: string;
    establishment_id: number;
    evaluation_status_id: number | null;
    responsible_user_id: number | null;
    next_action_at: string | null;
    facility_question: string | null;
    technician_question: string | null;
    visit_count: number;
    call_count: number;
    qc_duration_minutes: number | null;
    first_contact_attempt_at: string | null;
    closed_at: string | null;
};
