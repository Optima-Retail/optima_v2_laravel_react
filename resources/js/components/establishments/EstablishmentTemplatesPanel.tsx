import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import type { UserOption } from '@/support/types/domain/common';
import type { EstablishmentFormTemplateLinkValues } from '@/support/types/domain/establishment';

type EstablishmentTemplatesPanelProps = {
    links: EstablishmentFormTemplateLinkValues[];
    workOrderTypeOptions: UserOption[];
    formTemplateOptions: UserOption[];
    errors: Record<string, string | undefined>;
    onChange: (rows: EstablishmentFormTemplateLinkValues[]) => void;
};

function newTempKey(): string {
    return `tmp_${Math.random().toString(36).slice(2, 10)}`;
}

export function emptyFormTemplateLink(): EstablishmentFormTemplateLinkValues {
    return {
        id: null,
        temp_key: newTempKey(),
        form_template_id: '',
        work_order_type_id: '',
    };
}

function toSelectOptions(options: UserOption[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
    }));
}

export function EstablishmentTemplatesPanel({
    links,
    workOrderTypeOptions,
    formTemplateOptions,
    errors,
    onChange,
}: EstablishmentTemplatesPanelProps) {
    const { t } = useTranslation();

    function updateRow(index: number, key: keyof EstablishmentFormTemplateLinkValues, value: string) {
        onChange(
            links.map((row, rowIndex) => (rowIndex === index ? { ...row, [key]: value } : row)),
        );
    }

    function removeRow(index: number) {
        onChange(links.filter((_, rowIndex) => rowIndex !== index));
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-ink">{t('establishments.templates.title')}</h2>
                    <p className="mt-1 text-sm text-ink-muted">{t('establishments.templates.description')}</p>
                </div>
                <Button type="button" variant="secondary" onClick={() => onChange([...links, emptyFormTemplateLink()])}>
                    <Plus className="size-4" aria-hidden />
                    {t('establishments.templates.addRow')}
                </Button>
            </div>

            {links.length === 0 ? (
                <div className="rounded-xl border border-dashed border-line px-4 py-10 text-center text-sm text-ink-muted">
                    {t('establishments.templates.empty')}
                </div>
            ) : (
                <ul className="space-y-3">
                    {links.map((row, index) => (
                        <li
                            key={row.id ?? row.temp_key}
                            className="grid gap-3 rounded-xl border border-line p-4 sm:grid-cols-[1fr_1fr_auto]"
                        >
                            <Field
                                label={t('establishments.templates.workOrderType')}
                                htmlFor={`form-template-link-wot-${index}`}
                                error={errors[`form_template_links.${index}.work_order_type_id`]}
                                required
                            >
                                <SearchableSelect
                                    id={`form-template-link-wot-${index}`}
                                    value={row.work_order_type_id}
                                    invalid={Boolean(errors[`form_template_links.${index}.work_order_type_id`])}
                                    onChange={(value) => updateRow(index, 'work_order_type_id', value)}
                                    emptyLabel={t('common.select')}
                                    options={toSelectOptions(workOrderTypeOptions)}
                                />
                            </Field>

                            <Field
                                label={t('establishments.templates.formTemplate')}
                                htmlFor={`form-template-link-ft-${index}`}
                                error={errors[`form_template_links.${index}.form_template_id`]}
                                required
                            >
                                <SearchableSelect
                                    id={`form-template-link-ft-${index}`}
                                    value={row.form_template_id}
                                    invalid={Boolean(errors[`form_template_links.${index}.form_template_id`])}
                                    onChange={(value) => updateRow(index, 'form_template_id', value)}
                                    emptyLabel={t('common.select')}
                                    options={toSelectOptions(formTemplateOptions)}
                                />
                            </Field>

                            <div className="flex items-end">
                                <Button type="button" variant="danger" onClick={() => removeRow(index)}>
                                    <Trash2 className="size-3.5" aria-hidden />
                                    {t('common.delete')}
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
