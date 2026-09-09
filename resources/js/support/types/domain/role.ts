export type PermissionGroup = {
    resource: string;
    permissions: string[];
};

export type RoleListItem = {
    id: number;
    name: string;
    users_count: number;
    permissions_count: number;
    is_system: boolean;
    permissions: string[];
};

export type RoleFormData = {
    id: number;
    name: string;
    permissions: string[];
    is_system: boolean;
};
