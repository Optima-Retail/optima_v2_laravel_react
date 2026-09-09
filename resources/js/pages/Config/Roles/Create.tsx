import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { ShieldPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { RoleForm } from '@/components/config/roles/RoleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { rolesService } from '@/services';
import type { PermissionGroup } from '@/support/types/domain/role';

type CreateRoleProps = {
    permissionGroups: PermissionGroup[];
};

export default function CreateRole({ permissionGroups }: CreateRoleProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: '',
        permissions: [] as string[],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        rolesService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('roles.resource') })}>
            <Head title={t('common.newItem', { resource: t('roles.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('roles.title')}
                    title={t('common.createItem', { resource: t('roles.resource') })}
                    description={t('roles.createDescription')}
                    backHref={rolesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('roles.resourcePlural') })}
                />

                <RoleForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    permissionGroups={permissionGroups}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('roles.resource') })}
                    submitIcon={<ShieldPlus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
