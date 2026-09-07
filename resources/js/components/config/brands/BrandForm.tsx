import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import type { UserOption } from '@/support/types/domain';

export type BrandFormValues = {
    name: string;
    account_manager_id: string;
    commercial_manager_id: string;
    collaborator_ids: string[];
    loyalty_meeting_frequency: string;
    is_quality_control_contactable: boolean;
    send_debt_reminders: boolean;
};

type BrandFormProps = {
    values: BrandFormValues;
    errors: Partial<Record<keyof BrandFormValues, string>>;
    processing: boolean;
    userOptions: UserOption[];
    onChange: (key: keyof BrandFormValues, value: string | boolean | string[]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function BrandForm({
    values,
    errors,
    processing,
    userOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: BrandFormProps) {
    const { t } = useTranslation();

    return (
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label={t('common.name')} htmlFor="name" error={errors.name} className="sm:col-span-2" required>
                    <Input
                        id="name"
                        value={values.name}
                        invalid={Boolean(errors.name)}
                        onChange={(event) => onChange('name', event.target.value.toUpperCase())}
                    />
                </Field>

                <Field
                    label={t('brands.collaborators')}
                    htmlFor="collaborator_ids"
                    error={errors.collaborator_ids}
                    className="sm:col-span-2"
                >
                    <MultiSelect
                        id="collaborator_ids"
                        value={values.collaborator_ids}
                        onChange={(collaboratorIds) => onChange('collaborator_ids', collaboratorIds)}
                        options={userOptions.map((user) => ({
                            value: String(user.id),
                            label: user.label,
                        }))}
                        placeholder={t('brands.collaboratorsPlaceholder')}
                        invalid={Boolean(errors.collaborator_ids)}
                    />
                </Field>

                <Field label={t('brands.accountManager')} htmlFor="account_manager_id" error={errors.account_manager_id}>
                    <SearchableSelect
                        id="account_manager_id"
                        value={values.account_manager_id}
                        invalid={Boolean(errors.account_manager_id)}
                        onChange={(accountManagerId) => onChange('account_manager_id', accountManagerId)}
                        emptyLabel={t('brands.noAccountManager')}
                        options={userOptions.map((user) => ({
                            value: String(user.id),
                            label: user.label,
                        }))}
                    />
                </Field>

                <Field
                    label={t('brands.commercialManager')}
                    htmlFor="commercial_manager_id"
                    error={errors.commercial_manager_id}
                >
                    <SearchableSelect
                        id="commercial_manager_id"
                        value={values.commercial_manager_id}
                        invalid={Boolean(errors.commercial_manager_id)}
                        onChange={(commercialManagerId) => onChange('commercial_manager_id', commercialManagerId)}
                        emptyLabel={t('brands.noCommercialManager')}
                        options={userOptions.map((user) => ({
                            value: String(user.id),
                            label: user.label,
                        }))}
                    />
                </Field>

                <Field
                    label={t('brands.loyaltyMeetingFrequency')}
                    htmlFor="loyalty_meeting_frequency"
                    error={errors.loyalty_meeting_frequency}
                >
                    <Input
                        id="loyalty_meeting_frequency"
                        value={values.loyalty_meeting_frequency}
                        placeholder={t('brands.meetingFrequencyPlaceholder')}
                        invalid={Boolean(errors.loyalty_meeting_frequency)}
                        onChange={(event) => onChange('loyalty_meeting_frequency', event.target.value)}
                    />
                </Field>

                <Field label={t('brands.qualityControlContact')} htmlFor="is_quality_control_contactable">
                    <Toggle
                        id="is_quality_control_contactable"
                        checked={values.is_quality_control_contactable}
                        onCheckedChange={(checked) => onChange('is_quality_control_contactable', checked)}
                        checkedLabel={t('brands.contactable')}
                        uncheckedLabel={t('brands.notContactable')}
                    />
                </Field>

                <Field label={t('brands.debtReminders')} htmlFor="send_debt_reminders">
                    <Toggle
                        id="send_debt_reminders"
                        checked={values.send_debt_reminders}
                        onCheckedChange={(checked) => onChange('send_debt_reminders', checked)}
                        checkedLabel={t('brands.sendReminders')}
                        uncheckedLabel={t('brands.doNotSendReminders')}
                    />
                </Field>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                {actions}
                <Button type="submit" loading={processing}>
                    {submitIcon}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
