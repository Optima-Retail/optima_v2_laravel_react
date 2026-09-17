import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Textarea } from '@/components/ui/Textarea';
import { Toggle } from '@/components/ui/Toggle';
import { ImageFieldUpload } from '@/components/forms/ImageFieldUpload';
import { SignaturePad } from '@/components/forms/SignaturePad';

export type FormFieldPayload = Record<string, unknown> | null;

export type FormFieldValue = {
    id: number;
    type: string;
    label: string | null;
    value: string;
    placeholder: string | null;
    is_required: boolean;
    is_visible: boolean;
    is_locked: boolean;
    conditional_field_id: number | null;
    payload: FormFieldPayload;
};

type MaterialItem = { name: string; quantity: string };
type TechnicianEntry = { name: string; hours: string };

type FormFieldInputProps = {
    formId: number;
    field: FormFieldValue;
    onChangeValue: (value: string) => void;
    onChangePayload: (payload: FormFieldPayload) => void;
    error?: string;
    payloadError?: string;
};

export function FormFieldInput({ formId, field, onChangeValue, onChangePayload, error, payloadError }: FormFieldInputProps) {
    const { t } = useTranslation();
    const disabled = field.is_locked;
    const htmlId = `field-${field.id}`;

    switch (field.type) {
        case 'nota':
            return (
                <Textarea
                    id={htmlId}
                    value={field.value}
                    disabled={disabled}
                    invalid={Boolean(error)}
                    placeholder={field.placeholder ?? undefined}
                    onChange={(event) => onChangeValue(event.target.value)}
                />
            );

        case 'fecha':
            return (
                <Input
                    id={htmlId}
                    type="date"
                    value={field.value}
                    disabled={disabled}
                    invalid={Boolean(error)}
                    onChange={(event) => onChangeValue(event.target.value)}
                />
            );

        case 'fecha-hora':
            return (
                <Input
                    id={htmlId}
                    type="datetime-local"
                    value={field.value}
                    disabled={disabled}
                    invalid={Boolean(error)}
                    onChange={(event) => onChangeValue(event.target.value)}
                />
            );

        case 'numero':
            return (
                <Input
                    id={htmlId}
                    type="number"
                    value={field.value}
                    disabled={disabled}
                    invalid={Boolean(error)}
                    onChange={(event) => onChangeValue(event.target.value)}
                />
            );

        case 'si-no':
            return (
                <Toggle
                    id={htmlId}
                    helpField={false}
                    checked={field.value === 'si'}
                    disabled={disabled}
                    onCheckedChange={(checked) => onChangeValue(checked ? 'si' : 'no')}
                    checkedLabel={t('forms.yes')}
                    uncheckedLabel={t('forms.no')}
                />
            );

        case 'imagen':
            return (
                <ImageFieldUpload
                    formId={formId}
                    fieldId={field.id}
                    target="value"
                    hasFile={field.value !== ''}
                    disabled={disabled}
                    onUploaded={(result) => onChangeValue(result.value ?? '')}
                />
            );

        case 'firma':
            return (
                <SignaturePad
                    formId={formId}
                    fieldId={field.id}
                    hasSignature={field.value !== ''}
                    disabled={disabled}
                    onUploaded={(result) => onChangeValue(result.value ?? '')}
                />
            );

        case 'materiales':
            return (
                <MaterialesEditor
                    payload={field.payload}
                    disabled={disabled}
                    error={payloadError}
                    onChange={onChangePayload}
                />
            );

        case 'tecnicos':
            return (
                <TecnicosEditor
                    payload={field.payload}
                    disabled={disabled}
                    error={payloadError}
                    onChange={onChangePayload}
                />
            );

        case 'trabajo':
            return (
                <TrabajoEditor
                    formId={formId}
                    field={field}
                    disabled={disabled}
                    error={payloadError}
                    onChange={onChangePayload}
                />
            );

        case 'cabecera':
            return <p className="text-sm font-semibold text-ink">{field.label}</p>;

        case 'condicional':
        case 'subformulario':
        case 'nueva-incidencia':
            return <p className="text-sm italic text-ink-muted">{t('forms.structuralFieldNote')}</p>;

        default:
            return (
                <Input
                    id={htmlId}
                    value={field.value}
                    disabled={disabled}
                    invalid={Boolean(error)}
                    onChange={(event) => onChangeValue(event.target.value)}
                />
            );
    }
}

function MaterialesEditor({
    payload,
    disabled,
    error,
    onChange,
}: {
    payload: FormFieldPayload;
    disabled?: boolean;
    error?: string;
    onChange: (payload: FormFieldPayload) => void;
}) {
    const { t } = useTranslation();
    const items = (Array.isArray(payload?.items) ? (payload?.items as MaterialItem[]) : []) ?? [];

    function update(next: MaterialItem[]) {
        onChange({ ...(payload ?? {}), items: next });
    }

    return (
        <div className="space-y-2">
            {items.map((item, index) => (
                <div key={index} className="flex flex-wrap items-center gap-2">
                    <Input
                        value={item.name}
                        disabled={disabled}
                        placeholder={t('forms.materialName')}
                        className="flex-1"
                        onChange={(event) => {
                            const next = [...items];
                            next[index] = { ...item, name: event.target.value };
                            update(next);
                        }}
                    />
                    <Input
                        type="number"
                        value={item.quantity}
                        disabled={disabled}
                        placeholder={t('forms.materialQuantity')}
                        className="w-28"
                        onChange={(event) => {
                            const next = [...items];
                            next[index] = { ...item, quantity: event.target.value };
                            update(next);
                        }}
                    />
                    <Button
                        type="button"
                        variant="danger"
                        size="sm"
                        disabled={disabled}
                        onClick={() => update(items.filter((_, i) => i !== index))}
                    >
                        <Trash2 className="size-3.5" aria-hidden />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="secondary"
                size="sm"
                disabled={disabled}
                onClick={() => update([...items, { name: '', quantity: '' }])}
            >
                <Plus className="size-3.5" aria-hidden />
                {t('forms.addMaterial')}
            </Button>
            {error ? <p className="text-sm text-danger">{error}</p> : null}
        </div>
    );
}

function TecnicosEditor({
    payload,
    disabled,
    error,
    onChange,
}: {
    payload: FormFieldPayload;
    disabled?: boolean;
    error?: string;
    onChange: (payload: FormFieldPayload) => void;
}) {
    const { t } = useTranslation();
    const entries = (Array.isArray(payload?.entries) ? (payload?.entries as TechnicianEntry[]) : []) ?? [];

    function update(next: TechnicianEntry[]) {
        onChange({ ...(payload ?? {}), entries: next });
    }

    return (
        <div className="space-y-2">
            {entries.map((entry, index) => (
                <div key={index} className="flex flex-wrap items-center gap-2">
                    <Input
                        value={entry.name}
                        disabled={disabled}
                        placeholder={t('forms.technicianName')}
                        className="flex-1"
                        onChange={(event) => {
                            const next = [...entries];
                            next[index] = { ...entry, name: event.target.value };
                            update(next);
                        }}
                    />
                    <Input
                        type="number"
                        value={entry.hours}
                        disabled={disabled}
                        placeholder={t('forms.technicianHours')}
                        className="w-28"
                        onChange={(event) => {
                            const next = [...entries];
                            next[index] = { ...entry, hours: event.target.value };
                            update(next);
                        }}
                    />
                    <Button
                        type="button"
                        variant="danger"
                        size="sm"
                        disabled={disabled}
                        onClick={() => update(entries.filter((_, i) => i !== index))}
                    >
                        <Trash2 className="size-3.5" aria-hidden />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="secondary"
                size="sm"
                disabled={disabled}
                onClick={() => update([...entries, { name: '', hours: '' }])}
            >
                <Plus className="size-3.5" aria-hidden />
                {t('forms.addTechnician')}
            </Button>
            {error ? <p className="text-sm text-danger">{error}</p> : null}
        </div>
    );
}

function TrabajoEditor({
    formId,
    field,
    disabled,
    error,
    onChange,
}: {
    formId: number;
    field: FormFieldValue;
    disabled?: boolean;
    error?: string;
    onChange: (payload: FormFieldPayload) => void;
}) {
    const { t } = useTranslation();
    const note = typeof field.payload?.note === 'string' ? field.payload.note : '';
    const hasBefore = Boolean(field.payload?.before);
    const hasAfter = Boolean(field.payload?.after);

    return (
        <div className="space-y-3">
            <Textarea
                value={note}
                disabled={disabled}
                placeholder={t('forms.workNotePlaceholder')}
                onChange={(event) => onChange({ ...(field.payload ?? {}), note: event.target.value })}
            />
            <div className="grid gap-3 sm:grid-cols-2">
                <div>
                    <p className="mb-1.5 text-xs font-semibold text-ink-muted">{t('forms.workBeforePhoto')}</p>
                    <ImageFieldUpload
                        formId={formId}
                        fieldId={field.id}
                        target="before"
                        hasFile={hasBefore}
                        disabled={disabled}
                        onUploaded={(result) => onChange(result.payload ?? field.payload)}
                    />
                </div>
                <div>
                    <p className="mb-1.5 text-xs font-semibold text-ink-muted">{t('forms.workAfterPhoto')}</p>
                    <ImageFieldUpload
                        formId={formId}
                        fieldId={field.id}
                        target="after"
                        hasFile={hasAfter}
                        disabled={disabled}
                        onUploaded={(result) => onChange(result.payload ?? field.payload)}
                    />
                </div>
            </div>
            {error ? <p className="text-sm text-danger">{error}</p> : null}
        </div>
    );
}
