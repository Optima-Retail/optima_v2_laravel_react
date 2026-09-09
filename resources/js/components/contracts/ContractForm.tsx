import { useMemo, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';

export type ContractFormValues = {
    code: string;
    description: string;
    work_order_subject: string;
    company_id: string;
    responsible_user_id: string;
    contract_status_id: string;
    language_id: string;
    signed_at: string;
    canceled_at: string;
    establishment_ids: string[];
};

type ContractFormProps = {
    values: ContractFormValues;
    errors: Partial<Record<keyof ContractFormValues, string>>;
    processing: boolean;
    codeDisabled?: boolean;
    companyOptions: UserOption[];
    contractStatusOptions: UserOption[];
    languageOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    onChange: (key: keyof ContractFormValues, value: string | string[]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

function toSelectOptions(options: UserOption[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
        color: option.color ?? null,
    }));
}

export function defaultContractFormValues(
    overrides: Partial<ContractFormValues> = {},
): ContractFormValues {
    return {
        code: '',
        description: '',
        work_order_subject: '',
        company_id: '',
        responsible_user_id: '',
        contract_status_id: '',
        language_id: '',
        signed_at: '',
        canceled_at: '',
        establishment_ids: [],
        ...overrides,
    };
}

export function ContractForm({
    values,
    errors,
    processing,
    codeDisabled = false,
    companyOptions,
    contractStatusOptions,
    languageOptions,
    userOptions,
    establishmentOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: ContractFormProps) {
    const { t } = useTranslation();

    const filteredEstablishments = useMemo(() => {
        if (!values.company_id) {
            return [];
        }

        return establishmentOptions
            .filter((option) => String(option.company_id) === values.company_id)
            .map((option) => ({
                value: String(option.id),
                label: option.label,
            }));
    }, [establishmentOptions, values.company_id]);

    return (
        <FieldHelpScope table="contracts">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('contracts.description')} htmlFor="description" error={errors.description} className="sm:col-span-2" required>
                        <Input
                            id="description"
                            value={values.description}
                            invalid={Boolean(errors.description)}
                            onChange={(event) => onChange('description', event.target.value)}
                        />
                    </Field>

                    <Field label={t('common.code')} htmlFor="code" error={errors.code}>
                        <Input
                            id="code"
                            value={values.code}
                            invalid={Boolean(errors.code)}
                            disabled={codeDisabled}
                            readOnly={codeDisabled}
                            onChange={(event) => onChange('code', event.target.value)}
                        />
                        {codeDisabled ? (
                            <p className="text-xs text-ink-muted">{t('contracts.codeAutomaticHint')}</p>
                        ) : null}
                    </Field>

                    <Field label={t('contracts.signedAt')} htmlFor="signed_at" error={errors.signed_at}>
                        <Input
                            id="signed_at"
                            type="date"
                            value={values.signed_at}
                            invalid={Boolean(errors.signed_at)}
                            onChange={(event) => onChange('signed_at', event.target.value)}
                        />
                    </Field>

                    <Field label={t('contracts.canceledAt')} htmlFor="canceled_at" error={errors.canceled_at}>
                        <Input
                            id="canceled_at"
                            type="date"
                            value={values.canceled_at}
                            invalid={Boolean(errors.canceled_at)}
                            onChange={(event) => onChange('canceled_at', event.target.value)}
                        />
                    </Field>

                    <Field label={t('contracts.client')} htmlFor="company_id" error={errors.company_id} required>
                        <SearchableSelect
                            id="company_id"
                            value={values.company_id}
                            invalid={Boolean(errors.company_id)}
                            onChange={(value) => {
                                onChange('company_id', value);
                                onChange('establishment_ids', []);
                            }}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(companyOptions)}
                        />
                    </Field>

                    <Field label={t('contracts.status')} htmlFor="contract_status_id" error={errors.contract_status_id} required>
                        <SearchableSelect
                            id="contract_status_id"
                            value={values.contract_status_id}
                            invalid={Boolean(errors.contract_status_id)}
                            onChange={(value) => onChange('contract_status_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(contractStatusOptions)}
                        />
                    </Field>

                    <Field label={t('contracts.responsibleUser')} htmlFor="responsible_user_id" error={errors.responsible_user_id} required>
                        <SearchableSelect
                            id="responsible_user_id"
                            value={values.responsible_user_id}
                            invalid={Boolean(errors.responsible_user_id)}
                            onChange={(value) => onChange('responsible_user_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(userOptions)}
                        />
                    </Field>

                    <Field label={t('contracts.language')} htmlFor="language_id" error={errors.language_id}>
                        <SearchableSelect
                            id="language_id"
                            value={values.language_id}
                            invalid={Boolean(errors.language_id)}
                            onChange={(value) => onChange('language_id', value)}
                            emptyLabel={t('common.select')}
                            options={toSelectOptions(languageOptions)}
                        />
                    </Field>

                    <Field
                        label={t('contracts.workOrderSubject')}
                        htmlFor="work_order_subject"
                        error={errors.work_order_subject}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="work_order_subject"
                            value={values.work_order_subject}
                            invalid={Boolean(errors.work_order_subject)}
                            onChange={(event) => onChange('work_order_subject', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('contracts.establishments')}
                        htmlFor="establishment_ids"
                        error={errors.establishment_ids}
                        className="sm:col-span-2"
                    >
                        <MultiSelect
                            id="establishment_ids"
                            value={values.establishment_ids}
                            onChange={(ids) => onChange('establishment_ids', ids)}
                            options={filteredEstablishments}
                            placeholder={
                                values.company_id
                                    ? t('contracts.establishmentsPlaceholder')
                                    : t('contracts.selectClientFirst')
                            }
                            invalid={Boolean(errors.establishment_ids)}
                            disabled={!values.company_id}
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
        </FieldHelpScope>
    );
}
