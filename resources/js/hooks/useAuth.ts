import { usePage } from '@inertiajs/react';
import type { SharedPageProps } from '@/types';

export function useSharedProps(): SharedPageProps {
    return usePage<SharedPageProps>().props;
}

export function useAuthUser() {
    return useSharedProps().auth.user;
}

export function useCan(permission: string): boolean {
    const user = useAuthUser();

    return Boolean(user?.permissions.includes(permission) || user?.roles.includes('admin'));
}
