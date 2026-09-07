import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { UserForm } from '@/components/config/users/UserForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { usersService } from '@/services';
import type { UserFormData, UserFormOptions } from '@/support/types/domain';

type EditUserProps = {
    user: UserFormData;
    roleOptions: string[];
    formOptions: UserFormOptions;
    can: {
        delete: boolean;
    };
};

export default function EditUser({ user, roleOptions, formOptions, can }: EditUserProps) {
    const { t } = useTranslation();
    const form = useForm({
        name: user.name,
        email: user.email,
        username: user.username ?? '',
        locale: user.locale ?? '',
        manager_id: user.manager_id !== null ? String(user.manager_id) : '',
        team_leader_id: user.team_leader_id !== null ? String(user.team_leader_id) : '',
        team_id: user.team_id !== null ? String(user.team_id) : '',
        timezone_id: user.timezone_id !== null ? String(user.timezone_id) : '',
        brand_id: user.brand_id !== null ? String(user.brand_id) : '',
        phone: user.phone ?? '',
        telephony_phone_number: user.telephony_phone_number !== null ? String(user.telephony_phone_number) : '',
        pbx_extension: user.pbx_extension ?? '',
        telegram_user_id: user.telegram_user_id ?? '',
        external_hr_id: user.external_hr_id ?? '',
        is_active: user.is_active,
        is_internal_employee: user.is_internal_employee,
        is_team_account: user.is_team_account,
        is_preventive_specialist: user.is_preventive_specialist ?? false,
        performance_factor: user.performance_factor,
        invoiced_revenue_target: user.invoiced_revenue_target ?? '',
        quality_score: user.quality_score,
        balance: user.balance,
        budget_approval_limit: user.budget_approval_limit,
        sso_only: user.sso_only,
        must_change_password: user.must_change_password,
        password: '',
        password_confirmation: '',
        roles: user.roles,
        company_ids: (user.company_ids ?? []).map(String),
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        usersService.update(user.id, form);
    }

    async function destroyUser() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('users.resource') }),
            message: t('common.deleteMessage', { name: user.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        usersService.destroy(user.id);
    }

    return (
        <AppLayout title={t('common.editResource', { resource: t('users.resource') })}>
            <Head title={t('common.editItem', { name: user.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('users.title')}
                    title={t('users.editTitle')}
                    description={t('users.editDescription', { name: user.name })}
                    backHref={usersService.indexPath}
                    backLabel={t('common.backTo', { resource: t('users.resourcePlural') })}
                />

                <UserForm
                    mode="edit"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    roleOptions={roleOptions}
                    formOptions={formOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyUser}>
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
