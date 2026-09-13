import type { FormEvent, ReactNode } from 'react';
import { useRef } from 'react';
import { Paperclip } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { Select } from '@/components/ui/Select';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import type { SelectOption } from '@/components/ui/SearchableSelect';

export type ComplimentFormValues = {
    subject_type: string;
    brand_id: string;
    company_relationship_id: string;
    establishment_id: string;
    compliment_type_id: string;
    comment: string;
    user_ids: string[];
    score: string;
    file: File | null;
};

type Option = { id: number; label: string };

type ComplimentFormProps = {
    values: ComplimentFormValues;
    errors: Partial<Record<keyof ComplimentFormValues, string>>;
    processing: boolean;
    typeOptions: Option[];
    brandOptions: Option[];
    customerOptions: Option[];
    establishmentOptions: Option[];
    userOptions: Option[];
    showAttachmentField?: boolean;
    onChange: <K extends keyof ComplimentFormValues>(key: K, value: ComplimentFormValues[K]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

function toSelectOptions(options: Option[]): SelectOption[] {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
    }));
}

export function defaultComplimentFormValues(
    overrides: Partial<ComplimentFormValues> = {},
): ComplimentFormValues {
    return {
        subject_type: 'establishment',
        brand_id: '',
        company_relationship_id: '',
        establishment_id: '',
        compliment_type_id: '',
        comment: '',
        user_ids: [],
        score: '1',
        file: null,
        ...overrides,
    };
}

export function ComplimentForm({
    values,
    errors,
    processing,
    typeOptions,
    brandOptions,
    customerOptions,
    establishmentOptions,
    userOptions,
    showAttachmentField = false,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: ComplimentFormProps) {
    const { t } = useTranslation();
    const fileRef = useRef<HTMLInputElement>(null);

    return (
        <FieldHelpScope table="compliments">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('compliments.subjectType')} htmlFor="subject_type" error={errors.subject_type} required>
                        <Select
                            id="subject_type"
                            value={values.subject_type}
                            invalid={Boolean(errors.subject_type)}
                            onChange={(event) => onChange('subject_type', event.target.value)}
                        >
                            <option value="brand">{t('compliments.subjectTypes.brand')}</option>
                            <option value="customer">{t('compliments.subjectTypes.customer')}</option>
                            <option value="establishment">{t('compliments.subjectTypes.establishment')}</option>
                        </Select>
                    </Field>

                    <Field
                        label={t('compliments.type')}
                        htmlFor="compliment_type_id"
                        error={errors.compliment_type_id}
                        required
                    >
                        <Select
                            id="compliment_type_id"
                            value={values.compliment_type_id}
                            invalid={Boolean(errors.compliment_type_id)}
                            onChange={(event) => onChange('compliment_type_id', event.target.value)}
                        >
                            <option value="">{t('common.select')}</option>
                            {typeOptions.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    {values.subject_type === 'brand' ? (
                        <Field label={t('compliments.brand')} htmlFor="brand_id" error={errors.brand_id} required>
                            <Select
                                id="brand_id"
                                value={values.brand_id}
                                invalid={Boolean(errors.brand_id)}
                                onChange={(event) => onChange('brand_id', event.target.value)}
                            >
                                <option value="">{t('common.select')}</option>
                                {brandOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    ) : null}

                    {values.subject_type === 'customer' ? (
                        <Field
                            label={t('compliments.customer')}
                            htmlFor="company_relationship_id"
                            error={errors.company_relationship_id}
                            required
                        >
                            <Select
                                id="company_relationship_id"
                                value={values.company_relationship_id}
                                invalid={Boolean(errors.company_relationship_id)}
                                onChange={(event) => onChange('company_relationship_id', event.target.value)}
                            >
                                <option value="">{t('common.select')}</option>
                                {customerOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    ) : null}

                    {values.subject_type === 'establishment' ? (
                        <Field
                            label={t('compliments.establishment')}
                            htmlFor="establishment_id"
                            error={errors.establishment_id}
                            required
                        >
                            <Select
                                id="establishment_id"
                                value={values.establishment_id}
                                invalid={Boolean(errors.establishment_id)}
                                onChange={(event) => onChange('establishment_id', event.target.value)}
                            >
                                <option value="">{t('common.select')}</option>
                                {establishmentOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    ) : null}

                    <Field label={t('compliments.score')} htmlFor="score" error={errors.score} required>
                        <Input
                            id="score"
                            type="number"
                            min={1}
                            value={values.score}
                            invalid={Boolean(errors.score)}
                            onChange={(event) => onChange('score', event.target.value)}
                        />
                    </Field>

                    <Field
                        label={t('compliments.users')}
                        htmlFor="user_ids"
                        error={errors.user_ids}
                        required
                        className="sm:col-span-2"
                    >
                        <MultiSelect
                            id="user_ids"
                            value={values.user_ids}
                            onChange={(userIds) => onChange('user_ids', userIds)}
                            options={toSelectOptions(userOptions)}
                            placeholder={t('compliments.usersPlaceholder')}
                            invalid={Boolean(errors.user_ids)}
                        />
                    </Field>

                    {showAttachmentField ? (
                        <Field
                            label={t('compliments.attachment')}
                            htmlFor="file"
                            error={errors.file}
                            className="sm:col-span-2"
                        >
                            <div className="flex flex-wrap items-center gap-3">
                                <input
                                    ref={fileRef}
                                    id="file"
                                    type="file"
                                    className="hidden"
                                    onChange={(event) => onChange('file', event.target.files?.[0] ?? null)}
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => fileRef.current?.click()}
                                >
                                    <Paperclip className="size-4" aria-hidden />
                                    {t('compliments.chooseAttachment')}
                                </Button>
                                <span className="text-sm text-ink-muted">
                                    {values.file?.name ?? t('compliments.noAttachmentSelected')}
                                </span>
                            </div>
                        </Field>
                    ) : null}

                    <Field
                        label={t('compliments.comment')}
                        htmlFor="comment"
                        error={errors.comment}
                        className="sm:col-span-2"
                    >
                        <textarea
                            id="comment"
                            rows={4}
                            value={values.comment}
                            onChange={(event) => onChange('comment', event.target.value)}
                            className="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
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
