import { FormEvent } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { UserForm } from '@/components/config/users/UserForm';
import { PageHeader } from '@/components/page/PageHeader';
import { AppLayout } from '@/layouts/AppLayout';
import { usersService } from '@/services';
import type { UserFormOptions } from '@/support/types/domain';
import type { SharedPageProps } from '@/types';

type CreateUserProps = {
    roleOptions: string[];
    formOptions: UserFormOptions;
};

export default function CreateUser({ roleOptions, formOptions }: CreateUserProps) {
    const { t } = useTranslation();
    const { locale } = usePage<SharedPageProps>().props;
    const form = useForm({
        name: '',
        email: '',
        username: '',
        locale: locale || 'en',
        manager_id: '',
        team_leader_id: '',
        team_id: '',
        timezone_id: '',
        brand_id: '',
        phone: '',
        telephony_phone_number: '',
        pbx_extension: '',
        telegram_user_id: '',
        external_hr_id: '',
        is_active: true,
        is_internal_employee: false,
        is_team_account: false,
        is_preventive_specialist: false,
        performance_factor: '1.00',
        invoiced_revenue_target: '',
        quality_score: '0.00',
        balance: '0.00',
        budget_approval_limit: '0.00',
        sso_only: false,
        must_change_password: false,
        password: '',
        password_confirmation: '',
        roles: ['user'] as string[],
        company_ids: [] as string[],
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        usersService.store(form);
    }

    return (
        <AppLayout title={t('common.newItem', { resource: t('users.resource') })}>
            <Head title={t('common.newItem', { resource: t('users.resource') })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('users.title')}
                    title={t('users.createTitle')}
                    description={t('users.createDescription')}
                    backHref={usersService.indexPath}
                    backLabel={t('common.backTo', { resource: t('users.resourcePlural') })}
                />

                <UserForm
                    mode="create"
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    roleOptions={roleOptions}
                    formOptions={formOptions}
                    onChange={(key, value) => form.setData((data) => ({ ...data, [key]: value }))}
                    onSubmit={submit}
                    submitLabel={t('common.createItem', { resource: t('users.resource') })}
                    submitIcon={<UserPlus className="size-4" aria-hidden />}
                />
            </div>
        </AppLayout>
    );
}
