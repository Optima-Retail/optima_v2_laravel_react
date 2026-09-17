import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Search, Trash2 } from 'lucide-react';
import { CompanyOptionLabel } from '@/components/companies/CompanyOptionLabel';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';
import {
    emptyWorkOrderLine,
    emptyWorkOrderTask,
    emptyWorkOrderTechnician,
    type WorkOrderFormValues,
} from '@/components/work-orders/WorkOrderForm';
import { TechnicianSearchModal } from '@/components/estimates/TechnicianSearchModal';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { MultiSelect } from '@/components/ui/MultiSelect';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { Toggle } from '@/components/ui/Toggle';
import { RichTextEditor } from '@/components/ui/RichTextEditor';
import { cn } from '@/support/cn';
import { formatDateTime } from '@/support/datetime';
import { useToastStore } from '@/stores/toastStore';
import type { CompanyOption, UserOption, WorkOrderArticleOption } from '@/support/types/domain/common';
import type { EstablishmentOption } from '@/support/types/domain/establishment';
import type { WorkOrderStatusOption } from '@/support/types/domain/work-order-status';

export type EstimateRequesterOption = UserOption & {
    company_id?: number;
};

export type EstimateFormMode = 'create' | 'edit';

export type EstimateFormSection = 'details' | 'tasks' | 'technicians' | 'rates' | 'notes' | 'lines' | 'all';

type EstimateFormProps = {
    mode: EstimateFormMode;
    section?: EstimateFormSection;
    values: WorkOrderFormValues;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    fieldsLocked?: boolean;
    statusOptions?: WorkOrderStatusOption[];
    typeOptions: UserOption[];
    userOptions: UserOption[];
    establishmentOptions: EstablishmentOption[];
    requesterOptions: EstimateRequesterOption[];
    technicianOptions: CompanyOption[];
    articleOptions: WorkOrderArticleOption[];
    sourceLabel?: string | null;
    estimateNum?: string | null;
    workOrderNum?: string | null;
    currencyLabel?: string | null;
    createdAt?: string | null;
    sentAt?: string | null;
    closedAt?: string | null;
    onChange: (key: keyof WorkOrderFormValues, value: WorkOrderFormValues[keyof WorkOrderFormValues]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    hideSubmit?: boolean;
    lockedHint?: string | null;
    actions?: ReactNode;
};

function toSelectOptions(options: UserOption[]) {
    return options.map((option) => ({
        value: String(option.id),
        label: option.label,
        color: option.color ?? null,
    }));
}

function lineNetTotal(quantity: string, unitPrice: string): string {
    const qty = Number.parseFloat(quantity);
    const price = Number.parseFloat(unitPrice);

    if (!Number.isFinite(qty) || !Number.isFinite(price)) {
        return '';
    }

    return (qty * price).toFixed(2);
}

function applyArticleToLine(
    line: WorkOrderFormValues['lines'][number],
    articleId: string,
    articleOptions: WorkOrderArticleOption[],
) {
    const article = articleOptions.find((option) => String(option.id) === articleId);

    return {
        ...line,
        article_id: articleId,
        description: article?.description?.trim() ? article.description : line.description,
        unit_price: article?.unit_price !== undefined && article.unit_price !== null ? String(article.unit_price) : line.unit_price,
    };
}

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <section className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <div>
                <h2 className="text-base font-semibold text-ink">{title}</h2>
                {description ? <p className="mt-1 text-sm text-ink-muted">{description}</p> : null}
            </div>
            {children}
        </section>
    );
}

/** Shared estimate create/edit form aligned with optima_prod presupuesto details. */
export function EstimateForm({
    mode,
    section = 'all',
    values,
    errors,
    processing,
    fieldsLocked = false,
    statusOptions = [],
    typeOptions,
    userOptions,
    establishmentOptions,
    requesterOptions,
    technicianOptions,
    articleOptions,
    sourceLabel = null,
    estimateNum = null,
    workOrderNum = null,
    currencyLabel = null,
    createdAt = null,
    sentAt = null,
    closedAt = null,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    hideSubmit = false,
    lockedHint = null,
    actions,
}: EstimateFormProps) {
    const { t, i18n } = useTranslation();
    const pushToast = useToastStore((state) => state.push);
    const isCreate = mode === 'create';
    // Create only exposes details; other sections appear as tabs after save.
    const [technicianPickerIndex, setTechnicianPickerIndex] = useState<number | null>(null);
    const [extraTechnicianOptions, setExtraTechnicianOptions] = useState<CompanyOption[]>([]);
    const resolvedTechnicianOptions = useMemo(
        () => {
            const byId = new Map<number, CompanyOption>();
            [...technicianOptions, ...extraTechnicianOptions].forEach((option) => {
                byId.set(option.id, option);
            });

            return Array.from(byId.values());
        },
        [technicianOptions, extraTechnicianOptions],
    );
    const activeSection: EstimateFormSection = isCreate ? 'details' : section;
    const showDetails = activeSection === 'all' || activeSection === 'details';
    const showTasks = !isCreate && (activeSection === 'all' || activeSection === 'tasks');
    const showTechnicians = !isCreate && (activeSection === 'all' || activeSection === 'technicians');
    const showNotes = !isCreate && (activeSection === 'all' || activeSection === 'notes');
    const showLines = !isCreate && (activeSection === 'all' || activeSection === 'lines');
    const hasEstablishment = Boolean(values.establishment_id);
    const gated = isCreate && !hasEstablishment;
    const locked = fieldsLocked || gated;

    const selectedEstablishment = useMemo(
        () => establishmentOptions.find((option) => String(option.id) === values.establishment_id) ?? null,
        [establishmentOptions, values.establishment_id],
    );

    const establishmentSelect = useMemo(
        () =>
            establishmentOptions.map((option) => ({
                value: String(option.id),
                label: option.label,
            })),
        [establishmentOptions],
    );

    const filteredRequesters = useMemo(() => {
        if (!selectedEstablishment) {
            return requesterOptions;
        }

        return requesterOptions.filter(
            (option) => !option.company_id || option.company_id === selectedEstablishment.company_id,
        );
    }, [requesterOptions, selectedEstablishment]);

    const resolvedCurrencyLabel =
        currencyLabel
        || selectedEstablishment?.currency_label
        || '';

    function setEstablishment(value: string) {
        onChange('establishment_id', value);

        if (!value) {
            onChange('requester_id', '');
            onChange('currency_id', '');

            return;
        }

        const next = establishmentOptions.find((option) => String(option.id) === value);
        onChange('currency_id', next?.currency_id ? String(next.currency_id) : '');

        const currentRequester = requesterOptions.find((option) => String(option.id) === values.requester_id);

        if (
            currentRequester?.company_id
            && next
            && currentRequester.company_id !== next.company_id
        ) {
            onChange('requester_id', '');
        }
    }

    const actionsSection = showLines
        ? 'lines'
        : showNotes
          ? 'notes'
          : showTechnicians
            ? 'technicians'
            : showTasks
              ? 'tasks'
              : showDetails
                ? 'details'
                : null;

    const formActions =
        actions || !hideSubmit ? (
            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line pt-4">
                {actions}
                {!hideSubmit ? (
                    <Button type="submit" loading={processing} disabled={gated && !processing}>
                        {submitIcon}
                        {submitLabel}
                    </Button>
                ) : null}
            </div>
        ) : null;

    return (
        <FieldHelpScope table="work_orders">
            <form onSubmit={onSubmit} className="space-y-5">
                {showDetails && sourceLabel ? (
                    <p className="rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-ink-muted">
                        {t('workOrders.sourceDocument')}: {sourceLabel}
                    </p>
                ) : null}

                {fieldsLocked && showDetails ? (
                    <p className="rounded-lg border border-line bg-canvas px-3 py-2 text-sm text-ink-muted">
                        {lockedHint || t('workOrders.fieldsLockedHint')}
                    </p>
                ) : null}

                {showDetails ? (
                <Section
                    title={t('estimates.sectionDetails')}
                    description={gated ? t('estimates.selectEstablishmentFirst') : undefined}
                >
                    <div className="grid gap-5 lg:grid-cols-3">
                        <div className="space-y-5">
                            <Field label={t('workOrders.subject')} htmlFor="subject" error={errors.subject} required>
                                <Input
                                    id="subject"
                                    value={values.subject}
                                    invalid={Boolean(errors.subject)}
                                    disabled={locked}
                                    onChange={(event) => onChange('subject', event.target.value)}
                                />
                            </Field>

                            {!isCreate ? (
                                <Field label={t('workOrders.estimateNum')} htmlFor="estimate_num_readonly">
                                    <Input
                                        id="estimate_num_readonly"
                                        value={estimateNum ?? values.code}
                                        readOnly
                                        disabled
                                    />
                                </Field>
                            ) : null}

                            {!isCreate && workOrderNum ? (
                                <Field label={t('workOrders.workOrderNum')} htmlFor="work_order_num_readonly">
                                    <Input id="work_order_num_readonly" value={workOrderNum} readOnly disabled />
                                </Field>
                            ) : null}

                            <Field label={t('workOrders.reference')} htmlFor="reference" error={errors.reference}>
                                <Input
                                    id="reference"
                                    value={values.reference}
                                    invalid={Boolean(errors.reference)}
                                    disabled={locked}
                                    onChange={(event) => onChange('reference', event.target.value)}
                                />
                            </Field>

                            {!isCreate && sourceLabel ? (
                                <Field label={t('estimates.workOrder')} htmlFor="source_readonly">
                                    <Input id="source_readonly" value={sourceLabel} readOnly disabled />
                                </Field>
                            ) : null}

                            {isCreate ? (
                                <Field
                                    label={t('workOrders.establishment')}
                                    htmlFor="establishment_id"
                                    error={errors.establishment_id}
                                    required
                                >
                                    <SearchableSelect
                                        id="establishment_id"
                                        value={values.establishment_id}
                                        invalid={Boolean(errors.establishment_id)}
                                        onChange={setEstablishment}
                                        emptyLabel={t('common.select')}
                                        options={establishmentSelect}
                                    />
                                </Field>
                            ) : (
                                <Field label={t('workOrders.establishment')} htmlFor="establishment_readonly">
                                    <Input
                                        id="establishment_readonly"
                                        value={selectedEstablishment?.label ?? ''}
                                        readOnly
                                        disabled
                                    />
                                </Field>
                            )}

                            <Field label={t('estimates.brand')} htmlFor="brand_readonly">
                                <Input
                                    id="brand_readonly"
                                    value={selectedEstablishment?.brand_name ?? ''}
                                    readOnly
                                    disabled
                                />
                            </Field>

                            <Field label={t('estimates.client')} htmlFor="client_readonly">
                                <div
                                    id="client_readonly"
                                    className="flex h-10 items-center rounded-lg border border-line bg-canvas px-3 text-sm text-ink-muted"
                                >
                                    {selectedEstablishment?.company_name ? (
                                        <CompanyOptionLabel
                                            name={selectedEstablishment.company_name}
                                            logoUrl={selectedEstablishment.company_logo_url}
                                            size="sm"
                                        />
                                    ) : (
                                        <span>{t('common.emDash')}</span>
                                    )}
                                </div>
                            </Field>
                        </div>

                        <div className="space-y-5">
                            {isCreate ? (
                                <Field label={t('workOrders.status')} htmlFor="status_readonly">
                                    <Input
                                        id="status_readonly"
                                        value=""
                                        readOnly
                                        disabled
                                        placeholder={t('estimates.statusAutomaticHint')}
                                    />
                                    <p className="mt-1 text-xs text-ink-muted">{t('estimates.statusAutomaticHint')}</p>
                                </Field>
                            ) : (
                                <Field
                                    label={t('workOrders.status')}
                                    htmlFor="status_id"
                                    error={errors.status_id ?? errors.status_justification}
                                    required
                                >
                                    <SearchableSelect
                                        id="status_id"
                                        value={values.status_id}
                                        invalid={Boolean(errors.status_id)}
                                        onChange={(value) => onChange('status_id', value)}
                                        emptyLabel={t('common.select')}
                                        options={toSelectOptions(statusOptions)}
                                    />
                                </Field>
                            )}

                            <Field
                                label={t('workOrders.type')}
                                htmlFor="work_order_type_id"
                                error={errors.work_order_type_id}
                                required={isCreate}
                            >
                                <SearchableSelect
                                    id="work_order_type_id"
                                    value={values.work_order_type_id}
                                    invalid={Boolean(errors.work_order_type_id)}
                                    disabled={locked}
                                    onChange={(value) => onChange('work_order_type_id', value)}
                                    emptyLabel={t('common.select')}
                                    options={toSelectOptions(typeOptions)}
                                />
                            </Field>

                            <Field label={t('estimates.createdAt')} htmlFor="created_at_readonly">
                                <Input
                                    id="created_at_readonly"
                                    value={formatDateTime(createdAt, i18n.language)}
                                    readOnly
                                    disabled
                                />
                            </Field>

                            <Field label={t('estimates.dueAt')} htmlFor="due_at_readonly">
                                <Input
                                    id="due_at_readonly"
                                    value={formatDateTime(isCreate ? null : values.due_at, i18n.language)}
                                    readOnly
                                    disabled
                                    placeholder={isCreate ? t('estimates.dueAtAutomaticHint') : undefined}
                                />
                                {isCreate ? (
                                    <p className="mt-1 text-xs text-ink-muted">{t('estimates.dueAtAutomaticHint')}</p>
                                ) : null}
                            </Field>

                            <Field label={t('estimates.sentAt')} htmlFor="sent_at_readonly">
                                <Input
                                    id="sent_at_readonly"
                                    value={formatDateTime(sentAt, i18n.language)}
                                    readOnly
                                    disabled
                                />
                            </Field>

                            <Field label={t('estimates.closedAt')} htmlFor="closed_at_readonly">
                                <Input
                                    id="closed_at_readonly"
                                    value={formatDateTime(closedAt, i18n.language)}
                                    readOnly
                                    disabled
                                />
                            </Field>
                        </div>

                        <div className="space-y-5">
                            <Field label={t('estimates.currency')} htmlFor="currency_readonly">
                                <Input id="currency_readonly" value={resolvedCurrencyLabel} readOnly disabled />
                            </Field>

                            <Field label={t('workOrders.requester')} htmlFor="requester_id" error={errors.requester_id}>
                                <SearchableSelect
                                    id="requester_id"
                                    value={values.requester_id}
                                    invalid={Boolean(errors.requester_id)}
                                    disabled={locked}
                                    onChange={(value) => onChange('requester_id', value)}
                                    emptyLabel={t('common.select')}
                                    options={toSelectOptions(filteredRequesters)}
                                />
                            </Field>

                            <Field
                                label={t('workOrders.responsibleUser')}
                                htmlFor="responsible_user_id"
                                error={errors.responsible_user_id}
                            >
                                <SearchableSelect
                                    id="responsible_user_id"
                                    value={values.responsible_user_id}
                                    invalid={Boolean(errors.responsible_user_id)}
                                    disabled={locked}
                                    onChange={(value) => onChange('responsible_user_id', value)}
                                    emptyLabel={t('common.select')}
                                    options={toSelectOptions(userOptions)}
                                />
                            </Field>

                            <Field
                                label={t('workOrders.collaborators')}
                                htmlFor="collaborator_ids"
                                error={errors.collaborator_ids}
                            >
                                <MultiSelect
                                    id="collaborator_ids"
                                    value={values.collaborator_ids}
                                    disabled={locked}
                                    onChange={(next) => onChange('collaborator_ids', next)}
                                    options={toSelectOptions(userOptions)}
                                    placeholder={t('workOrders.collaboratorsPlaceholder')}
                                />
                            </Field>
                        </div>
                    </div>
                {actionsSection === 'details' ? formActions : null}
                </Section>
                ) : null}

                {showTasks ? (
                <Section
                    title={t('estimates.sectionTasks')}
                    description={t('estimates.sectionTasksDescription')}
                >
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm text-ink-muted">{t('estimates.tasks')}</p>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={locked}
                            onClick={() => onChange('tasks', [...values.tasks, emptyWorkOrderTask()])}
                        >
                            <Plus className="size-3.5" aria-hidden />
                            {t('estimates.addTask')}
                        </Button>
                    </div>
                    {values.tasks.length === 0 ? (
                        <p className="text-sm text-ink-muted">{t('estimates.tasksEmpty')}</p>
                    ) : (
                        <div className="space-y-3">
                            {values.tasks.map((task, index) => (
                                <div
                                    key={task.id ?? `task-${index}`}
                                    className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12"
                                >
                                    <Field
                                        label={t('common.title')}
                                        htmlFor={`task-title-${index}`}
                                        className="sm:col-span-4"
                                        error={errors[`tasks.${index}.title`]}
                                        required
                                    >
                                        <Input
                                            id={`task-title-${index}`}
                                            value={task.title}
                                            invalid={Boolean(errors[`tasks.${index}.title`])}
                                            disabled={locked}
                                            onChange={(event) => {
                                                const next = [...values.tasks];
                                                next[index] = { ...task, title: event.target.value };
                                                onChange('tasks', next);
                                            }}
                                        />
                                    </Field>
                                    <Field
                                        label={t('common.description')}
                                        htmlFor={`task-description-${index}`}
                                        className="sm:col-span-7"
                                        error={errors[`tasks.${index}.description`]}
                                    >
                                        <textarea
                                            id={`task-description-${index}`}
                                            rows={2}
                                            value={task.description}
                                            disabled={locked}
                                            onChange={(event) => {
                                                const next = [...values.tasks];
                                                next[index] = { ...task, description: event.target.value };
                                                onChange('tasks', next);
                                            }}
                                            className={cn(
                                                'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink shadow-sm transition',
                                                'focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20',
                                                'disabled:cursor-not-allowed disabled:bg-canvas disabled:text-ink-muted disabled:opacity-80',
                                                errors[`tasks.${index}.description`] ? 'border-danger' : 'border-line',
                                            )}
                                        />
                                    </Field>
                                    <div className="flex items-end sm:col-span-1">
                                        <Button
                                            type="button"
                                            variant="danger"
                                            disabled={locked}
                                            onClick={() =>
                                                onChange(
                                                    'tasks',
                                                    values.tasks.filter((_, row) => row !== index),
                                                )
                                            }
                                        >
                                            <Trash2 className="size-3.5" aria-hidden />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                {actionsSection === 'tasks' ? formActions : null}
                </Section>
                ) : null}

                {showTechnicians ? (
                <Section title={t('estimates.sectionTechnicians')} description={t('estimates.sectionTechniciansDescription')}>
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm text-ink-muted">{t('workOrders.technicians')}</p>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={locked || !values.establishment_id}
                            onClick={() => {
                                const next = [...values.technicians, emptyWorkOrderTechnician()];
                                onChange('technicians', next);
                                setTechnicianPickerIndex(next.length - 1);
                            }}
                        >
                            <Plus className="size-3.5" aria-hidden />
                            {t('workOrders.addTechnician')}
                        </Button>
                    </div>
                    {!values.establishment_id ? (
                        <p className="text-sm text-ink-muted">{t('estimates.technicianSearchNeedEstablishment')}</p>
                    ) : null}
                    {values.technicians.length === 0 ? (
                        <p className="text-sm text-ink-muted">{t('workOrders.techniciansEmpty')}</p>
                    ) : (
                        <div className="space-y-3">
                            {values.technicians.map((technician, index) => {
                                const selected = resolvedTechnicianOptions.find(
                                    (option) => String(option.id) === technician.company_relationship_id,
                                );

                                return (
                                <div
                                    key={technician.id ?? `tech-${index}`}
                                    className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12"
                                >
                                    <div className="flex items-end sm:col-span-2">
                                        <Toggle
                                            name={`technicians.${index}.is_selected`}
                                            checked={technician.is_selected}
                                            disabled={locked}
                                            onCheckedChange={(checked) => {
                                                const next = [...values.technicians];
                                                next[index] = { ...technician, is_selected: checked };
                                                onChange('technicians', next);
                                            }}
                                            checkedLabel={t('workOrders.selected')}
                                            uncheckedLabel={t('workOrders.notSelected')}
                                        />
                                    </div>
                                    <Field
                                        label={t('workOrders.technician')}
                                        htmlFor={`tech-${index}`}
                                        className="sm:col-span-3"
                                        error={errors[`technicians.${index}.company_relationship_id`]}
                                    >
                                        <div className="flex gap-2">
                                            <div className="flex min-h-10 min-w-0 flex-1 items-center rounded-lg border border-line bg-canvas/40 px-3 text-sm text-ink">
                                                {selected ? (
                                                    <CompanyOptionLabel
                                                        name={selected.label}
                                                        logoUrl={selected.logo_url}
                                                        size="sm"
                                                    />
                                                ) : (
                                                    <span className="text-ink-muted">{t('common.select')}</span>
                                                )}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                disabled={locked || !values.establishment_id}
                                                onClick={() => setTechnicianPickerIndex(index)}
                                                aria-label={t('estimates.technicianSearchOpen')}
                                            >
                                                <Search className="size-4" aria-hidden />
                                            </Button>
                                        </div>
                                    </Field>
                                    <Field
                                        label={t('workOrders.quoteNet')}
                                        htmlFor={`tech-quote-${index}`}
                                        className="sm:col-span-2"
                                        error={errors[`technicians.${index}.quote_net_amount`]}
                                    >
                                        <Input
                                            id={`tech-quote-${index}`}
                                            type="number"
                                            step="0.01"
                                            value={technician.quote_net_amount}
                                            disabled={locked || !technician.company_relationship_id}
                                            onChange={(event) => {
                                                const next = [...values.technicians];
                                                const net = event.target.value;
                                                next[index] = {
                                                    ...technician,
                                                    quote_net_amount: net,
                                                    // Until FX rates are wired, Total € mirrors the quote net (EUR docs).
                                                    quote_total_euros: net,
                                                };
                                                onChange('technicians', next);
                                            }}
                                        />
                                    </Field>
                                    <Field
                                        label={t('estimates.technicianQuotedAt')}
                                        htmlFor={`tech-quoted-at-${index}`}
                                        className="sm:col-span-2"
                                        error={errors[`technicians.${index}.quoted_at`]}
                                    >
                                        <Input
                                            id={`tech-quoted-at-${index}`}
                                            type="date"
                                            value={technician.quoted_at}
                                            disabled={locked || !technician.company_relationship_id}
                                            onChange={(event) => {
                                                const next = [...values.technicians];
                                                next[index] = { ...technician, quoted_at: event.target.value };
                                                onChange('technicians', next);
                                            }}
                                        />
                                    </Field>
                                    <Field
                                        label={t('estimates.technicianQuoteTotalEuros')}
                                        htmlFor={`tech-quote-euros-${index}`}
                                        className="sm:col-span-2"
                                        error={errors[`technicians.${index}.quote_total_euros`]}
                                    >
                                        <Input
                                            id={`tech-quote-euros-${index}`}
                                            type="number"
                                            step="0.01"
                                            value={technician.quote_total_euros}
                                            disabled
                                            readOnly
                                        />
                                    </Field>
                                    <div className="flex items-end justify-end sm:col-span-1">
                                        <Button
                                            type="button"
                                            variant="danger"
                                            disabled={locked}
                                            onClick={() =>
                                                onChange(
                                                    'technicians',
                                                    values.technicians.filter((_, row) => row !== index),
                                                )
                                            }
                                            aria-label={t('common.delete')}
                                        >
                                            <Trash2 className="size-3.5" aria-hidden />
                                        </Button>
                                    </div>
                                </div>
                                );
                            })}
                        </div>
                    )}

                    <TechnicianSearchModal
                        open={technicianPickerIndex !== null}
                        establishmentId={values.establishment_id ? Number(values.establishment_id) : null}
                        establishmentName={
                            establishmentOptions.find((option) => String(option.id) === values.establishment_id)?.label
                            ?? null
                        }
                        workOrderTypeId={values.work_order_type_id ? Number(values.work_order_type_id) : null}
                        onClose={() => setTechnicianPickerIndex(null)}
                        onSelect={(picked) => {
                            if (technicianPickerIndex === null) {
                                return;
                            }

                            const alreadyUsed = values.technicians.some(
                                (row, rowIndex) =>
                                    rowIndex !== technicianPickerIndex
                                    && String(row.company_relationship_id) === String(picked.id),
                            );

                            if (alreadyUsed) {
                                pushToast(t('estimates.technicianAlreadyAdded'), 'error');
                                return;
                            }

                            const next = [...values.technicians];
                            const current = next[technicianPickerIndex];
                            if (!current) {
                                return;
                            }

                            next[technicianPickerIndex] = {
                                ...current,
                                company_relationship_id: String(picked.id),
                            };
                            onChange('technicians', next);

                            if (!resolvedTechnicianOptions.some((option) => option.id === picked.id)) {
                                setExtraTechnicianOptions((currentOptions) => [
                                    ...currentOptions,
                                    {
                                        id: picked.id,
                                        label: picked.label,
                                        logo_url: picked.logo_url ?? null,
                                    },
                                ]);
                            }
                        }}
                    />
                {actionsSection === 'technicians' ? formActions : null}
                </Section>
                ) : null}

                {showNotes ? (
                <Section title={t('estimates.sectionNotes')}>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label={t('workOrders.notes')} htmlFor="notes" error={errors.notes} className="sm:col-span-2">
                            <RichTextEditor
                                id="notes"
                                value={values.notes}
                                invalid={Boolean(errors.notes)}
                                disabled={locked}
                                onChange={(html) => onChange('notes', html)}
                            />
                        </Field>
                        <Field
                            label={t('workOrders.internalNotes')}
                            htmlFor="internal_notes"
                            error={errors.internal_notes}
                            className="sm:col-span-2"
                        >
                            <RichTextEditor
                                id="internal_notes"
                                value={values.internal_notes}
                                invalid={Boolean(errors.internal_notes)}
                                disabled={locked}
                                onChange={(html) => onChange('internal_notes', html)}
                            />
                        </Field>
                        <Toggle
                            name="notes_alert"
                            checked={values.notes_alert}
                            disabled={locked}
                            onCheckedChange={(checked) => onChange('notes_alert', checked)}
                            checkedLabel={t('estimates.notesAlert')}
                            uncheckedLabel={t('estimates.notesAlertOff')}
                        />
                        <Toggle
                            name="internal_notes_alert"
                            checked={values.internal_notes_alert}
                            disabled={locked}
                            onCheckedChange={(checked) => onChange('internal_notes_alert', checked)}
                            checkedLabel={t('estimates.internalNotesAlert')}
                            uncheckedLabel={t('estimates.internalNotesAlertOff')}
                        />
                    </div>
                {actionsSection === 'notes' ? formActions : null}
                </Section>
                ) : null}

                {showLines ? (
                <Section title={t('estimates.sectionLines')} description={t('estimates.sectionLinesDescription')}>
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm text-ink-muted">{t('workOrders.lines')}</p>
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={locked}
                            onClick={() => onChange('lines', [...values.lines, emptyWorkOrderLine()])}
                        >
                            <Plus className="size-3.5" aria-hidden />
                            {t('workOrders.addLine')}
                        </Button>
                    </div>
                    {values.lines.length === 0 ? (
                        <p className="text-sm text-ink-muted">{t('workOrders.linesEmpty')}</p>
                    ) : (
                        <div className="space-y-3">
                            {values.lines.map((line, index) => (
                                <div
                                    key={line.id ?? `new-${index}`}
                                    className="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-12"
                                >
                                    <Field
                                        label={t('workOrders.article')}
                                        htmlFor={`line-article-${index}`}
                                        className="sm:col-span-2"
                                        error={errors[`lines.${index}.article_id`]}
                                    >
                                        <SearchableSelect
                                            id={`line-article-${index}`}
                                            value={line.article_id}
                                            disabled={locked}
                                            onChange={(value) => {
                                                const next = [...values.lines];
                                                next[index] = applyArticleToLine(line, value, articleOptions);
                                                onChange('lines', next);
                                            }}
                                            emptyLabel={t('common.select')}
                                            options={toSelectOptions(articleOptions)}
                                        />
                                    </Field>
                                    <Field
                                        label={t('workOrders.lineDescription')}
                                        htmlFor={`line-description-${index}`}
                                        className="sm:col-span-3"
                                        error={errors[`lines.${index}.description`]}
                                    >
                                        <Input
                                            id={`line-description-${index}`}
                                            value={line.description}
                                            disabled={locked}
                                            onChange={(event) => {
                                                const next = [...values.lines];
                                                next[index] = { ...line, description: event.target.value };
                                                onChange('lines', next);
                                            }}
                                        />
                                    </Field>
                                    <Field
                                        label={t('workOrders.quantity')}
                                        htmlFor={`line-qty-${index}`}
                                        className="sm:col-span-2"
                                        error={errors[`lines.${index}.quantity`]}
                                    >
                                        <Input
                                            id={`line-qty-${index}`}
                                            type="number"
                                            min={0}
                                            step="0.001"
                                            value={line.quantity}
                                            disabled={locked}
                                            onChange={(event) => {
                                                const next = [...values.lines];
                                                next[index] = { ...line, quantity: event.target.value };
                                                onChange('lines', next);
                                            }}
                                        />
                                    </Field>
                                    <Field
                                        label={t('workOrders.unitPrice')}
                                        htmlFor={`line-price-${index}`}
                                        className="sm:col-span-2"
                                        error={errors[`lines.${index}.unit_price`]}
                                    >
                                        <Input
                                            id={`line-price-${index}`}
                                            type="number"
                                            step="0.01"
                                            value={line.unit_price}
                                            disabled={locked}
                                            onChange={(event) => {
                                                const next = [...values.lines];
                                                next[index] = { ...line, unit_price: event.target.value };
                                                onChange('lines', next);
                                            }}
                                        />
                                    </Field>
                                    <Field
                                        label={t('workOrders.lineTotal')}
                                        htmlFor={`line-total-${index}`}
                                        className="sm:col-span-2"
                                    >
                                        <Input
                                            id={`line-total-${index}`}
                                            type="number"
                                            step="0.01"
                                            value={lineNetTotal(line.quantity, line.unit_price)}
                                            disabled
                                            readOnly
                                        />
                                    </Field>
                                    <div className="flex items-end sm:col-span-1">
                                        <Button
                                            type="button"
                                            variant="danger"
                                            disabled={locked}
                                            onClick={() =>
                                                onChange(
                                                    'lines',
                                                    values.lines.filter((_, row) => row !== index),
                                                )
                                            }
                                        >
                                            <Trash2 className="size-3.5" aria-hidden />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                {actionsSection === 'lines' ? formActions : null}
                </Section>
                ) : null}
            </form>
        </FieldHelpScope>
    );
}

/** @deprecated Use EstimateForm with mode="create" */
export const EstimateCreateForm = EstimateForm;
