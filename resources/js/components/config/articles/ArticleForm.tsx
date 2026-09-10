import { useMemo, type FormEvent, type ReactNode } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Toggle } from '@/components/ui/Toggle';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import type { UserOption } from '@/support/types/domain/common';
import type { ArticleClientRow, ArticleTranslationRow } from '@/support/types/domain/article';

export type ArticleFormValues = {
    code: string;
    is_deletable: boolean;
    translations: ArticleTranslationRow[];
    clients: ArticleClientRow[];
};

type ArticleFormProps = {
    mode: 'create' | 'edit';
    values: ArticleFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    languageOptions: UserOption[];
    clientOptions: UserOption[];
    onChange: (patch: Partial<ArticleFormValues>) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function ArticleForm({
    values,
    errors,
    processing,
    languageOptions,
    clientOptions,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: ArticleFormProps) {
    const { t } = useTranslation();

    const usedLanguageIds = useMemo(
        () => new Set(values.translations.map((row) => String(row.language_id)).filter((id) => id !== '')),
        [values.translations],
    );

    const availableLanguages = useMemo(
        () => languageOptions.filter((option) => !usedLanguageIds.has(String(option.id))),
        [languageOptions, usedLanguageIds],
    );

    const usedClientIds = useMemo(
        () =>
            new Set(
                values.clients
                    .map((row) => String(row.company_relationship_id))
                    .filter((id) => id !== ''),
            ),
        [values.clients],
    );

    const availableClients = useMemo(
        () => clientOptions.filter((option) => !usedClientIds.has(String(option.id))),
        [clientOptions, usedClientIds],
    );

    function updateTranslation(index: number, patch: Partial<ArticleTranslationRow>): void {
        onChange({
            translations: values.translations.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...patch } : row,
            ),
        });
    }

    function removeTranslation(index: number): void {
        onChange({
            translations: values.translations.filter((_, rowIndex) => rowIndex !== index),
        });
    }

    function addTranslation(): void {
        const next = availableLanguages[0];
        if (!next) {
            return;
        }

        onChange({
            translations: [
                ...values.translations,
                { language_id: next.id, name: '', description: '' },
            ],
        });
    }

    function languageChoicesForRow(index: number): UserOption[] {
        const current = String(values.translations[index]?.language_id ?? '');

        return languageOptions.filter(
            (option) => String(option.id) === current || !usedLanguageIds.has(String(option.id)),
        );
    }

    function updateClient(index: number, patch: Partial<ArticleClientRow>): void {
        onChange({
            clients: values.clients.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...patch } : row,
            ),
        });
    }

    function removeClient(index: number): void {
        onChange({
            clients: values.clients.filter((_, rowIndex) => rowIndex !== index),
        });
    }

    function addClient(): void {
        const next = availableClients[0];
        if (!next) {
            return;
        }

        onChange({
            clients: [
                ...values.clients,
                { company_relationship_id: next.id, sale_price: '0.00' },
            ],
        });
    }

    function clientChoicesForRow(index: number): UserOption[] {
        const current = String(values.clients[index]?.company_relationship_id ?? '');

        return clientOptions.filter(
            (option) => String(option.id) === current || !usedClientIds.has(String(option.id)),
        );
    }

    return (
        <FieldHelpScope table="articles">
            <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
                <div className="grid gap-5 sm:grid-cols-2">
                    <Field label={t('articles.code')} htmlFor="code" error={errors.code} required>
                        <Input
                            id="code"
                            value={values.code}
                            invalid={Boolean(errors.code)}
                            onChange={(event) => onChange({ code: event.target.value })}
                        />
                    </Field>

                    <Field label={t('articles.isDeletable')} htmlFor="is_deletable" error={errors.is_deletable}>
                        <Toggle
                            id="is_deletable"
                            checked={values.is_deletable}
                            onCheckedChange={(checked) => onChange({ is_deletable: checked })}
                            checkedLabel={t('common.yes')}
                            uncheckedLabel={t('common.no')}
                        />
                    </Field>
                </div>

                {errors.translations ? <p className="text-sm text-danger">{errors.translations}</p> : null}

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-semibold text-ink">{t('articles.translations')}</h3>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={availableLanguages.length === 0}
                            onClick={addTranslation}
                        >
                            <Plus className="size-4" aria-hidden />
                            {t('articles.addTranslation')}
                        </Button>
                    </div>

                    <div className="overflow-x-auto rounded-xl border border-line">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-canvas text-ink-muted">
                                <tr>
                                    <th className="px-3 py-2 font-semibold">{t('articles.language')}</th>
                                    <th className="px-3 py-2 font-semibold">{t('common.name')}</th>
                                    <th className="px-3 py-2 font-semibold">{t('articles.descriptionLabel')}</th>
                                    <th className="px-3 py-2 font-semibold">
                                        <span className="sr-only">{t('common.actions')}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {values.translations.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-3 py-4 text-muted">
                                            {t('articles.noTranslations')}
                                        </td>
                                    </tr>
                                ) : (
                                    values.translations.map((row, index) => (
                                        <tr
                                            key={`${row.language_id}-${index}`}
                                            className="border-t border-line align-top"
                                        >
                                            <td className="px-3 py-2">
                                                <Select
                                                    aria-label={t('articles.language')}
                                                    value={row.language_id === '' ? '' : String(row.language_id)}
                                                    invalid={Boolean(errors[`translations.${index}.language_id`])}
                                                    onChange={(event) =>
                                                        updateTranslation(index, {
                                                            language_id: event.target.value
                                                                ? Number(event.target.value)
                                                                : '',
                                                        })
                                                    }
                                                >
                                                    <option value="">{t('common.select')}</option>
                                                    {languageChoicesForRow(index).map((option) => (
                                                        <option key={option.id} value={option.id}>
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </Select>
                                                {errors[`translations.${index}.language_id`] ? (
                                                    <p className="mt-1 text-xs text-danger">
                                                        {errors[`translations.${index}.language_id`]}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Input
                                                    aria-label={t('common.name')}
                                                    value={row.name}
                                                    invalid={Boolean(errors[`translations.${index}.name`])}
                                                    onChange={(event) =>
                                                        updateTranslation(index, { name: event.target.value })
                                                    }
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                <Input
                                                    aria-label={t('articles.descriptionLabel')}
                                                    value={row.description}
                                                    invalid={Boolean(errors[`translations.${index}.description`])}
                                                    onChange={(event) =>
                                                        updateTranslation(index, {
                                                            description: event.target.value,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                <Button
                                                    type="button"
                                                    variant="danger"
                                                    aria-label={t('common.remove', {
                                                        label: t('articles.language'),
                                                    })}
                                                    onClick={() => removeTranslation(index)}
                                                >
                                                    <Trash2 className="size-4" aria-hidden />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {errors.clients ? <p className="text-sm text-danger">{errors.clients}</p> : null}

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-semibold text-ink">{t('articles.clientRates')}</h3>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={availableClients.length === 0}
                            onClick={addClient}
                        >
                            <Plus className="size-4" aria-hidden />
                            {t('articles.addClientRate')}
                        </Button>
                    </div>

                    <div className="overflow-x-auto rounded-xl border border-line">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-canvas text-ink-muted">
                                <tr>
                                    <th className="px-3 py-2 font-semibold">{t('articles.client')}</th>
                                    <th className="px-3 py-2 font-semibold">{t('articles.salePrice')}</th>
                                    <th className="px-3 py-2 font-semibold">
                                        <span className="sr-only">{t('common.actions')}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {values.clients.length === 0 ? (
                                    <tr>
                                        <td colSpan={3} className="px-3 py-4 text-muted">
                                            {t('articles.noClientRates')}
                                        </td>
                                    </tr>
                                ) : (
                                    values.clients.map((row, index) => (
                                        <tr
                                            key={`${row.company_relationship_id}-${index}`}
                                            className="border-t border-line align-top"
                                        >
                                            <td className="px-3 py-2">
                                                <Select
                                                    aria-label={t('articles.client')}
                                                    value={
                                                        row.company_relationship_id === ''
                                                            ? ''
                                                            : String(row.company_relationship_id)
                                                    }
                                                    invalid={Boolean(
                                                        errors[`clients.${index}.company_relationship_id`],
                                                    )}
                                                    onChange={(event) =>
                                                        updateClient(index, {
                                                            company_relationship_id: event.target.value
                                                                ? Number(event.target.value)
                                                                : '',
                                                        })
                                                    }
                                                >
                                                    <option value="">{t('common.select')}</option>
                                                    {clientChoicesForRow(index).map((option) => (
                                                        <option key={option.id} value={option.id}>
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </Select>
                                                {errors[`clients.${index}.company_relationship_id`] ? (
                                                    <p className="mt-1 text-xs text-danger">
                                                        {errors[`clients.${index}.company_relationship_id`]}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Input
                                                    aria-label={t('articles.salePrice')}
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={row.sale_price}
                                                    invalid={Boolean(errors[`clients.${index}.sale_price`])}
                                                    onChange={(event) =>
                                                        updateClient(index, { sale_price: event.target.value })
                                                    }
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                <Button
                                                    type="button"
                                                    variant="danger"
                                                    aria-label={t('common.remove', {
                                                        label: t('articles.client'),
                                                    })}
                                                    onClick={() => removeClient(index)}
                                                >
                                                    <Trash2 className="size-4" aria-hidden />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
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
