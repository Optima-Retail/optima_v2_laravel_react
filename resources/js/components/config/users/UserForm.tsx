import type { FormEvent, ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import type { UserFormOptions } from '@/support/types/domain/user';
import type { SharedPageProps } from '@/types';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type UserFormValues = {
    name: string;
    email: string;
    username: string;
    locale: string;
    manager_id: string;
    team_leader_id: string;
    team_id: string;
    timezone_id: string;
    brand_id: string;
    phone: string;
    telephony_phone_number: string;
    pbx_extension: string;
    telegram_user_id: string;
    external_hr_id: string;
    is_active: boolean;
    is_internal_employee: boolean;
    is_team_account: boolean;
    is_preventive_specialist: boolean;
    performance_factor: string;
    invoiced_revenue_target: string;
    quality_score: string;
    balance: string;
    budget_approval_limit: string;
    sso_only: boolean;
    must_change_password: boolean;
    password: string;
    password_confirmation: string;
    roles: string[];
    company_ids: string[];
};

type UserFormProps = {
    mode: 'create' | 'edit';
    values: UserFormValues;
    errors: Partial<Record<keyof UserFormValues, string>>;
    processing: boolean;
    roleOptions: string[];
    formOptions: UserFormOptions;
    onChange: (key: keyof UserFormValues, value: UserFormValues[keyof UserFormValues]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function UserForm({
    mode,
    values,
    errors,
    processing,
    roleOptions,
    formOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: UserFormProps) {
    const { t } = useTranslation();
    const { supportedLocales } = usePage<SharedPageProps>().props;

    return (
        <FieldHelpScope table="users">
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                <Input
                    id="name"
                    value={values.name}
                    autoComplete="name"
                    invalid={Boolean(errors.name)}
                    onChange={(event) => onChange('name', event.target.value)}
                />
            </Field>

            <Field label={t('common.email')} htmlFor="email" error={errors.email} required>
                <Input
                    id="email"
                    type="email"
                    value={values.email}
                    autoComplete="username"
                    invalid={Boolean(errors.email)}
                    onChange={(event) => onChange('email', event.target.value)}
                />
            </Field>

            <div className="grid gap-5 sm:grid-cols-2">
                <Field label={t('users.username')} htmlFor="username" error={errors.username}>
                    <Input
                        id="username"
                        value={values.username}
                        invalid={Boolean(errors.username)}
                        onChange={(event) => onChange('username', event.target.value)}
                    />
                </Field>

                <Field label={t('users.locale')} htmlFor="locale" error={errors.locale}>
                    <Select
                        id="locale"
                        value={values.locale}
                        invalid={Boolean(errors.locale)}
                        onChange={(event) => onChange('locale', event.target.value)}
                    >
                        {supportedLocales.map((option) => (
                            <option key={option.code} value={option.code}>
                                {t(`locale.${option.code}`)}
                            </option>
                        ))}
                    </Select>
                </Field>

                <Field label={t('users.manager')} htmlFor="manager_id" error={errors.manager_id}>
                    <SearchableSelect
                        id="manager_id"
                        value={values.manager_id}
                        invalid={Boolean(errors.manager_id)}
                        onChange={(managerId) => onChange('manager_id', managerId)}
                        emptyLabel={t('users.noManager')}
                        options={formOptions.users.map((user) => ({
                            value: String(user.id),
                            label: user.label,
                        }))}
                    />
                </Field>

                <Field label={t('users.teamLeader')} htmlFor="team_leader_id" error={errors.team_leader_id}>
                    <SearchableSelect
                        id="team_leader_id"
                        value={values.team_leader_id}
                        invalid={Boolean(errors.team_leader_id)}
                        onChange={(teamLeaderId) => onChange('team_leader_id', teamLeaderId)}
                        emptyLabel={t('users.noTeamLeader')}
                        options={formOptions.users.map((user) => ({
                            value: String(user.id),
                            label: user.label,
                        }))}
                    />
                </Field>

                <Field label={t('users.team')} htmlFor="team_id" error={errors.team_id}>
                    <SearchableSelect
                        id="team_id"
                        value={values.team_id}
                        invalid={Boolean(errors.team_id)}
                        onChange={(teamId) => onChange('team_id', teamId)}
                        emptyLabel={t('users.noTeam')}
                        options={formOptions.teams.map((team) => ({
                            value: String(team.id),
                            label: team.label,
                        }))}
                    />
                </Field>

                <Field label={t('users.timezone')} htmlFor="timezone_id" error={errors.timezone_id}>
                    <SearchableSelect
                        id="timezone_id"
                        value={values.timezone_id}
                        invalid={Boolean(errors.timezone_id)}
                        onChange={(timezoneId) => onChange('timezone_id', timezoneId)}
                        emptyLabel={t('users.defaultTimezone')}
                        options={formOptions.timezones.map((timezone) => ({
                            value: String(timezone.id),
                            label: timezone.label,
                        }))}
                    />
                </Field>

                <Field label={t('users.brand')} htmlFor="brand_id" error={errors.brand_id}>
                    <SearchableSelect
                        id="brand_id"
                        value={values.brand_id}
                        invalid={Boolean(errors.brand_id)}
                        onChange={(brandId) => onChange('brand_id', brandId)}
                        emptyLabel={t('users.noBrand')}
                        options={formOptions.brands.map((brand) => ({
                            value: String(brand.id),
                            label: brand.label,
                        }))}
                    />
                </Field>

                <Field label={t('users.phone')} htmlFor="phone" error={errors.phone}>
                    <Input
                        id="phone"
                        value={values.phone}
                        invalid={Boolean(errors.phone)}
                        onChange={(event) => onChange('phone', event.target.value)}
                    />
                </Field>
            </div>

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Field label={t('users.telephonyNumber')} htmlFor="telephony_phone_number" error={errors.telephony_phone_number}>
                    <Input
                        id="telephony_phone_number"
                        inputMode="tel"
                        value={values.telephony_phone_number}
                        invalid={Boolean(errors.telephony_phone_number)}
                        onChange={(event) => onChange('telephony_phone_number', event.target.value)}
                    />
                </Field>

                <Field label={t('users.pbxExtension')} htmlFor="pbx_extension" error={errors.pbx_extension}>
                    <Input
                        id="pbx_extension"
                        value={values.pbx_extension}
                        invalid={Boolean(errors.pbx_extension)}
                        onChange={(event) => onChange('pbx_extension', event.target.value)}
                    />
                </Field>

                <Field label={t('users.telegramUserId')} htmlFor="telegram_user_id" error={errors.telegram_user_id}>
                    <Input
                        id="telegram_user_id"
                        value={values.telegram_user_id}
                        invalid={Boolean(errors.telegram_user_id)}
                        onChange={(event) => onChange('telegram_user_id', event.target.value)}
                    />
                </Field>

                <Field label={t('users.externalHrId')} htmlFor="external_hr_id" error={errors.external_hr_id}>
                    <Input
                        id="external_hr_id"
                        value={values.external_hr_id}
                        invalid={Boolean(errors.external_hr_id)}
                        onChange={(event) => onChange('external_hr_id', event.target.value)}
                    />
                </Field>
            </div>

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Field label={t('users.performanceFactor')} htmlFor="performance_factor" error={errors.performance_factor}>
                    <Input
                        id="performance_factor"
                        type="number"
                        step="0.01"
                        value={values.performance_factor}
                        invalid={Boolean(errors.performance_factor)}
                        onChange={(event) => onChange('performance_factor', event.target.value)}
                    />
                </Field>

                <Field label={t('users.invoicedRevenueTarget')} htmlFor="invoiced_revenue_target" error={errors.invoiced_revenue_target}>
                    <Input
                        id="invoiced_revenue_target"
                        type="number"
                        step="0.01"
                        value={values.invoiced_revenue_target}
                        invalid={Boolean(errors.invoiced_revenue_target)}
                        onChange={(event) => onChange('invoiced_revenue_target', event.target.value)}
                    />
                </Field>

                <Field label={t('users.qualityScore')} htmlFor="quality_score" error={errors.quality_score}>
                    <Input
                        id="quality_score"
                        type="number"
                        step="0.01"
                        value={values.quality_score}
                        invalid={Boolean(errors.quality_score)}
                        onChange={(event) => onChange('quality_score', event.target.value)}
                    />
                </Field>

                <Field label={t('users.balance')} htmlFor="balance" error={errors.balance}>
                    <Input
                        id="balance"
                        type="number"
                        step="0.01"
                        value={values.balance}
                        invalid={Boolean(errors.balance)}
                        onChange={(event) => onChange('balance', event.target.value)}
                    />
                </Field>

                <Field label={t('users.budgetApprovalLimit')} htmlFor="budget_approval_limit" error={errors.budget_approval_limit}>
                    <Input
                        id="budget_approval_limit"
                        type="number"
                        step="0.01"
                        value={values.budget_approval_limit}
                        invalid={Boolean(errors.budget_approval_limit)}
                        onChange={(event) => onChange('budget_approval_limit', event.target.value)}
                    />
                </Field>
            </div>

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Field label={t('common.status')} htmlFor="is_active">
                    <Toggle
                        id="is_active"
                        checked={values.is_active}
                        onCheckedChange={(checked) => onChange('is_active', checked)}
                        checkedLabel={t('common.active')}
                        uncheckedLabel={t('common.inactive')}
                    />
                </Field>

                <Field label={t('users.internalEmployee')} htmlFor="is_internal_employee">
                    <Toggle
                        id="is_internal_employee"
                        checked={values.is_internal_employee}
                        onCheckedChange={(checked) => onChange('is_internal_employee', checked)}
                        checkedLabel={t('common.yes')}
                        uncheckedLabel={t('common.no')}
                    />
                </Field>

                <Field label={t('users.teamAccount')} htmlFor="is_team_account">
                    <Toggle
                        id="is_team_account"
                        checked={values.is_team_account}
                        onCheckedChange={(checked) => onChange('is_team_account', checked)}
                        checkedLabel={t('common.yes')}
                        uncheckedLabel={t('common.no')}
                    />
                </Field>

                <Field label={t('users.preventiveSpecialist')} htmlFor="is_preventive_specialist">
                    <Toggle
                        id="is_preventive_specialist"
                        checked={values.is_preventive_specialist}
                        onCheckedChange={(checked) => onChange('is_preventive_specialist', checked)}
                        checkedLabel={t('common.yes')}
                        uncheckedLabel={t('common.no')}
                    />
                </Field>

                <Field label={t('users.authenticationMode')} htmlFor="sso_only">
                    <Toggle
                        id="sso_only"
                        checked={values.sso_only}
                        onCheckedChange={(checked) => onChange('sso_only', checked)}
                        checkedLabel={t('users.ssoOnly')}
                        uncheckedLabel={t('users.passwordOrSso')}
                    />
                </Field>

                <Field label={t('users.passwordPolicy')} htmlFor="must_change_password">
                    <Toggle
                        id="must_change_password"
                        checked={values.must_change_password}
                        onCheckedChange={(checked) => onChange('must_change_password', checked)}
                        checkedLabel={t('users.changeAtNextLogin')}
                        uncheckedLabel={t('users.noForcedChange')}
                    />
                </Field>
            </div>

            <Field
                label={mode === 'edit' ? t('users.newPassword') : t('users.password')}
                htmlFor="password"
                error={errors.password}
                required={mode === 'create'}
            >
                <Input
                    id="password"
                    type="password"
                    value={values.password}
                    autoComplete="new-password"
                    placeholder={mode === 'edit' ? t('users.keepCurrent') : undefined}
                    invalid={Boolean(errors.password)}
                    onChange={(event) => onChange('password', event.target.value)}
                />
            </Field>

            <Field
                label={t('users.confirmPassword')}
                htmlFor="password_confirmation"
                error={errors.password_confirmation}
            >
                <Input
                    id="password_confirmation"
                    type="password"
                    value={values.password_confirmation}
                    autoComplete="new-password"
                    onChange={(event) => onChange('password_confirmation', event.target.value)}
                />
            </Field>

            <Field label={t('common.roles')} htmlFor="roles" error={errors.roles} required>
                <MultiSelect
                    id="roles"
                    value={values.roles}
                    onChange={(roles) => onChange('roles', roles)}
                    placeholder={t('users.rolesPlaceholder')}
                    invalid={Boolean(errors.roles)}
                    options={roleOptions.map((role) => ({ value: role, label: role }))}
                />
            </Field>

            {formOptions.companies.length > 0 ? (
                <Field label={t('users.companies')} htmlFor="company_ids" error={errors.company_ids}>
                    <MultiSelect
                        id="company_ids"
                        value={values.company_ids}
                        onChange={(companyIds) => onChange('company_ids', companyIds)}
                        placeholder={t('users.companiesPlaceholder')}
                        invalid={Boolean(errors.company_ids)}
                        options={formOptions.companies.map((company) => ({
                            value: String(company.id),
                            label: company.label,
                        }))}
                    />
                </Field>
            ) : null}

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                {actions}
                <Button type="submit" loading={processing}>
                    {submitIcon}
                    {submitLabel}
                </Button>
            </div>
        </form>
        </FieldHelpScope>
    );
}
