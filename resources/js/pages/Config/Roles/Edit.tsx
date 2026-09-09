import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { RoleForm } from '@/components/config/roles/RoleForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { rolesService } from '@/services';
import type { PermissionGroup, RoleFormData } from '@/support/types/domain/role';

type EditRoleProps = {
    role: RoleFormData;
    permissionGroups: PermissionGroup[];
    can: {
        delete: boolean;
    };
};

export default function EditRole({ role, permissionGroups, can }: EditRoleProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: role.name,
        permissions: role.permissions,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        rolesService.update(role.id, form);
    }

    async function destroyRole() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('roles.resource') }),
            message: t('common.deleteMessage', { name: role.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        rolesService.destroy(role.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('roles.resource') })}>
            <Head title={t('common.editItem', { name: role.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('roles.title')}
                    title={t('common.editResource', { resource: t('roles.resource') })}
                    description={t('roles.editDescription', { name: role.name })}
                    backHref={rolesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('roles.resourcePlural') })}
                />

                <RoleForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    permissionGroups={permissionGroups}
                    nameDisabled={role.is_system}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyRole}>
                                <Trash2 className="size-4" aria-hidden />
                                {t('common.delete')}
                            </Button>
                        ) : null
                    }
                />
            </div>
        </AppLayout>
    );
}
