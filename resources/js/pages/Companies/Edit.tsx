import { FormEvent, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Save, Trash2, UserMinus, UserPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CompanyForm } from '@/components/companies/CompanyForm';
import { PageHeader } from '@/components/page/PageHeader';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { confirmAction } from '@/helpers/confirm';
import { AppLayout } from '@/layouts/AppLayout';
import { companiesService } from '@/services';
import { tableBodyCellClass, tableHeadCellClass } from '@/support/table';
import type { CompanyFormData, ProvinceOption, UserOption } from '@/support/types/domain';

type CompanyMember = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
};

type EditCompanyProps = {
    company: CompanyFormData;
    countryOptions: UserOption[];
    provinceOptions: ProvinceOption[];
    brandOptions: UserOption[];
    languageOptions: UserOption[];
    members: CompanyMember[];
    assignableUserOptions: UserOption[];
    can: {
        delete: boolean;
        manage_users: boolean;
    };
};

export default function EditCompany({
    company,
    countryOptions,
    provinceOptions,
    brandOptions,
    languageOptions,
    members,
    assignableUserOptions,
    can,
}: EditCompanyProps) {
    const { t } = useTranslation();
    const [selectedUserId, setSelectedUserId] = useState('');
    const form = useForm({
        name: company.name,
        tradename: company.tradename ?? '',
        tax_id: company.tax_id ?? '',
        kind: company.kind,
        country_id: company.country_id ? String(company.country_id) : '',
        residence_country_id: company.residence_country_id ? String(company.residence_country_id) : '',
        person_type: company.person_type ?? '',
        email: company.email ?? '',
        phone: company.phone ?? '',
        website: company.website ?? '',
        address_line_1: company.address_line_1 ?? '',
        address_line_2: company.address_line_2 ?? '',
        city: company.city ?? '',
        province_id: company.province_id ? String(company.province_id) : '',
        postal_code: company.postal_code ?? '',
        employee_count: company.employee_count !== null ? String(company.employee_count) : '',
        is_active: company.is_active,
        brand_id: company.brand_id ? String(company.brand_id) : '',
        language_id: company.language_id ? String(company.language_id) : '',
        latitude: company.latitude !== null && company.latitude !== undefined ? String(company.latitude) : '',
        longitude: company.longitude !== null && company.longitude !== undefined ? String(company.longitude) : '',
        legacy_erp_id: company.legacy_erp_id !== null && company.legacy_erp_id !== undefined ? String(company.legacy_erp_id) : '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        companiesService.update(company.id, form);
    }

    async function destroyCompany() {
        const confirmed = await confirmAction({
            title: t('common.deleteTitle', { resource: t('companies.resource') }),
            message: t('common.deleteMessage', { name: company.name }),
            confirmLabel: t('common.delete'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        companiesService.destroy(company.id);
    }

    function assignUser() {
        if (!selectedUserId) {
            return;
        }

        companiesService.assignUser(company.id, Number(selectedUserId));
        setSelectedUserId('');
    }

    async function unlinkUser(member: CompanyMember) {
        const confirmed = await confirmAction({
            title: t('companies.users.unlinkUser'),
            message: t('common.deleteMessage', { name: member.name }),
            confirmLabel: t('companies.users.unlinkUser'),
            tone: 'danger',
        });

        if (!confirmed) {
            return;
        }

        companiesService.unlinkUser(company.id, member.id);
    }

    const usersPanel = can.manage_users ? (
        <div className="space-y-4">
            <p className="text-sm text-ink-muted">{t('companies.users.usersDescription')}</p>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                <Field label={t('companies.users.selectUser')} htmlFor="assign_user_id" className="min-w-0 flex-1">
                    <SearchableSelect
                        id="assign_user_id"
                        value={selectedUserId}
                        onChange={setSelectedUserId}
                        emptyLabel={t('common.none')}
                        options={assignableUserOptions.map((option) => ({
                            value: String(option.id),
                            label: option.label,
                        }))}
                    />
                </Field>
                <Button type="button" onClick={assignUser} disabled={!selectedUserId}>
                    <UserPlus className="size-4" aria-hidden />
                    {t('companies.users.assignUser')}
                </Button>
            </div>

            <div className="overflow-hidden rounded-xl border border-line">
                {members.length > 0 ? (
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-line bg-canvas text-xs uppercase tracking-[0.08em] text-ink-muted">
                            <tr>
                                <th className={tableHeadCellClass}>{t('common.name')}</th>
                                <th className={tableHeadCellClass}>{t('common.email')}</th>
                                <th className={`${tableHeadCellClass} text-right`}>{t('common.actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {members.map((member) => (
                                <tr key={member.id} className="border-b border-line last:border-b-0">
                                    <td className={`${tableBodyCellClass} font-medium text-ink`}>{member.name}</td>
                                    <td className={`${tableBodyCellClass} text-ink-muted`}>{member.email}</td>
                                    <td className={tableBodyCellClass}>
                                        <div className="flex justify-end">
                                            <button
                                                type="button"
                                                onClick={() => unlinkUser(member)}
                                                className="inline-flex size-8 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-danger/40 hover:text-danger"
                                                aria-label={t('companies.users.unlinkUser')}
                                            >
                                                <UserMinus className="size-3.5" aria-hidden />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <p className="px-4 py-8 text-center text-sm text-ink-muted">
                        {t('common.empty', { resource: t('users.resourcePlural') })}
                    </p>
                )}
            </div>
        </div>
    ) : undefined;

    return (
        <AppLayout title={t('common.editResource', { resource: t('companies.resource') })}>
            <Head title={t('common.editItem', { name: company.name })} />
            <div className="w-full space-y-6">
                <PageHeader
                    eyebrow={t('companies.title')}
                    title={t('common.editResource', { resource: t('companies.resource') })}
                    description={t('common.updateDetails', { name: company.name })}
                    backHref={companiesService.indexPath}
                    backLabel={t('common.backTo', { resource: t('companies.resourcePlural') })}
                />

                <CompanyForm
                    values={form.data}
                    errors={form.errors}
                    processing={form.processing}
                    countryOptions={countryOptions}
                    provinceOptions={provinceOptions}
                    brandOptions={brandOptions}
                    languageOptions={languageOptions}
                    onChange={(key, value) => form.setData(key, value)}
                    onSubmit={submit}
                    submitLabel={t('common.save')}
                    submitIcon={<Save className="size-4" aria-hidden />}
                    usersPanel={usersPanel}
                    actions={
                        can.delete ? (
                            <Button type="button" variant="danger" onClick={destroyCompany}>
                                <Trash2 className="size-4" aria-hidden />
                                {t('common.delete')}
                            </Button>
                        ) : null
                    }
                />
            </div>
        </AppLayout>
    );
}
