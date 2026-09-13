export type TechnicianIncidentTypeListItem = {
    id: number;
    name: string;
    due_days: number;
    send_mail_to_technician: boolean;
    created_at: string | null;
};

export type TechnicianIncidentTypeFormData = {
    id: number;
    name: string;
    due_days: number;
    send_mail_to_technician: boolean;
};
