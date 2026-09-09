import { useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Input } from '@/components/ui/Input';
import { toggleGroupItems, toggleItem } from '@/helpers/array';
import { cn } from '@/support/cn';
import type { PermissionGroup } from '@/support/types/domain/role';
import { FieldHelpScope } from '@/components/field-help/FieldHelpScope';

export type RoleFormValues = {
    name: string;
    permissions: string[];
};

type RoleFormProps = {
    mode: 'create' | 'edit';
    values: RoleFormValues;
    errors: Partial<Record<keyof RoleFormValues, string>>;
    processing: boolean;
    permissionGroups: PermissionGroup[];
    nameDisabled?: boolean;
    onChange: (key: keyof RoleFormValues, value: RoleFormValues[keyof RoleFormValues]) => void;
    onSubmit: (event: FormEvent) => void;
    submitLabel: string;
    submitIcon?: ReactNode;
    actions?: ReactNode;
};

export function RoleForm({
    mode,
    values,
    errors,
    processing,
    permissionGroups,
    nameDisabled = false,
    onChange,
    onSubmit,
    submitLabel,
    submitIcon,
    actions,
}: RoleFormProps) {
    const { t } = useTranslation();
    const initiallyOpen = useMemo(
        () =>
            permissionGroups
                .filter((group) => group.permissions.some((permission) => values.permissions.includes(permission)))
                .map((group) => group.resource),
        // Only seed from the initial selection once.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    const [openGroups, setOpenGroups] = useState<string[]>(() =>
        initiallyOpen.length > 0 ? initiallyOpen : permissionGroups.slice(0, 3).map((group) => group.resource),
    );

    function togglePermission(permission: string) {
        onChange('permissions', toggleItem(values.permissions, permission));
    }

    function toggleGroup(group: PermissionGroup) {
        onChange('permissions', toggleGroupItems(values.permissions, group.permissions));
    }

    function toggleAccordion(resource: string) {
        setOpenGroups((current) =>
            current.includes(resource) ? current.filter((item) => item !== resource) : [...current, resource],
        );
    }

    return (
        <FieldHelpScope table="roles">
        <form onSubmit={onSubmit} className="space-y-5 rounded-2xl border border-line bg-surface p-6 sm:p-8">
            <Field label={t('common.name')} htmlFor="name" error={errors.name} required>
                <Input
                    id="name"
                    value={values.name}
                    placeholder={mode === 'create' ? t('roles.namePlaceholder') : undefined}
                    invalid={Boolean(errors.name)}
                    disabled={nameDisabled}
                    onChange={(event) => onChange('name', event.target.value)}
                />
            </Field>

            <fieldset className="space-y-3">
                <legend className="text-sm font-semibold text-ink">{t('roles.permissions')}</legend>
                <p className="text-sm text-ink-muted">
                    {mode === 'create' ? t('roles.permissionsCreate') : t('roles.permissionsEdit')}
                </p>

                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                    {permissionGroups.map((group) => {
                        const open = openGroups.includes(group.resource);
                        const selectedCount = group.permissions.filter((permission) =>
                            values.permissions.includes(permission),
                        ).length;
                        const panelId = `permission-group-${group.resource}`;

                        return (
                            <div
                                key={group.resource}
                                className="flex flex-col overflow-hidden rounded-xl border border-line bg-canvas/40"
                            >
                                <div className="flex items-center gap-2 px-3 py-2.5">
                                    <button
                                        type="button"
                                        onClick={() => toggleAccordion(group.resource)}
                                        className="flex min-w-0 flex-1 items-center gap-2 text-left"
                                        aria-expanded={open}
                                        aria-controls={panelId}
                                    >
                                        <ChevronDown
                                            className={cn(
                                                'size-4 shrink-0 text-ink-muted transition-transform',
                                                open ? 'rotate-0' : '-rotate-90',
                                            )}
                                            aria-hidden
                                        />
                                        <span className="truncate font-semibold text-ink">{group.resource}</span>
                                        <span className="shrink-0 rounded-md bg-surface px-1.5 py-0.5 text-xs font-medium text-ink-muted">
                                            {selectedCount}/{group.permissions.length}
                                        </span>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => toggleGroup(group)}
                                        className="shrink-0 text-xs font-medium text-brand transition-colors hover:text-brand-strong"
                                    >
                                        {selectedCount === group.permissions.length ? t('roles.clear') : t('roles.all')}
                                    </button>
                                </div>

                                {open ? (
                                    <div id={panelId} className="border-t border-line bg-surface px-2 py-2">
                                        <div className="flex flex-col gap-0.5">
                                            {group.permissions.map((permission) => {
                                                const active = values.permissions.includes(permission);

                                                return (
                                                    <button
                                                        key={permission}
                                                        type="button"
                                                        onClick={() => togglePermission(permission)}
                                                        className={cn(
                                                            'w-full rounded-md px-2 py-1.5 text-left text-sm font-mono transition-colors',
                                                            active
                                                                ? 'bg-brand-soft font-medium text-brand'
                                                                : 'text-ink-muted hover:bg-canvas hover:text-ink',
                                                        )}
                                                    >
                                                        {permission}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        );
                    })}
                </div>

                {errors.permissions ? <p className="text-sm text-danger">{errors.permissions}</p> : null}
            </fieldset>

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
