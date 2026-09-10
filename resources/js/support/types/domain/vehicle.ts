export type VehicleListItem = {
    id: number;
    brand: string | null;
    model: string | null;
    license_plate: string | null;
    company_relationship_id: number | null;
    technician_label: string | null;
    created_at: string | null;
};

export type VehicleFormData = {
    id: number;
    brand: string | null;
    model: string | null;
    license_plate: string | null;
    company_relationship_id: number | null;
};
