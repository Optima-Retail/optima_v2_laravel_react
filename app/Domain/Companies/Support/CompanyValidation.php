<?php

declare(strict_types=1);

namespace App\Domain\Companies\Support;

use App\Domain\Companies\Enums\CompanyKind;
use App\Domain\Companies\Enums\CompanyRelationshipClassification;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Domain\Companies\Enums\PersonType;
use App\Domain\Companies\Enums\WorkOrderGroupingBasis;
use App\Models\Province;
use Closure;
use Illuminate\Validation\Rule;

final class CompanyValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function companyRules(?int $ignoreId = null): array
    {
        $uniqueTax = Rule::unique('companies', 'tax_id')->whereNull('deleted_at');
        $uniqueSlug = Rule::unique('companies', 'slug')->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $uniqueTax = $uniqueTax->ignore($ignoreId);
            $uniqueSlug = $uniqueSlug->ignore($ignoreId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'tradename' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:64', $uniqueSlug],
            'tax_id' => ['nullable', 'string', 'max:32', $uniqueTax],
            'kind' => ['required', Rule::enum(CompanyKind::class)],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'residence_country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'person_type' => ['nullable', Rule::enum(PersonType::class)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province_id' => self::provinceIdRules(),
            'postal_code' => ['nullable', 'string', 'max:20'],
            'employee_count' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'language_id' => ['nullable', 'integer', Rule::exists('languages', 'id')->whereNull('deleted_at')],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'legacy_erp_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @param  list<int>  $accessibleCompanyIds
     * @return array<string, mixed>
     */
    public static function establishmentRules(?int $ignoreId, array $accessibleCompanyIds): array
    {
        $companyId = $accessibleCompanyIds === [] ? [0] : $accessibleCompanyIds;

        $uniqueCode = Rule::unique('establishments', 'code')
            ->where(fn ($query) => $query
                ->where('company_id', request()->integer('company_id'))
                ->whereNull('deleted_at'));

        if ($ignoreId !== null) {
            $uniqueCode = $uniqueCode->ignore($ignoreId);
        }

        $liveUser = Rule::exists('users', 'id')->whereNull('deleted_at');

        return [
            'company_id' => ['required', 'integer', Rule::in($companyId)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', $uniqueCode],
            'store_code' => ['nullable', 'string', 'max:64'],
            'alternate_store_code' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'emails' => ['nullable', 'string'],
            'recipient_emails' => ['nullable', 'string'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province_id' => self::provinceIdRules(),
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'timezone_id' => ['nullable', 'integer', Rule::exists('timezones', 'id')->whereNull('deleted_at')],
            'language_id' => ['nullable', 'integer', Rule::exists('languages', 'id')->whereNull('deleted_at')],
            'establishment_type_id' => ['nullable', 'integer', Rule::exists('establishment_types', 'id')->whereNull('deleted_at')],
            'delegation_id' => ['nullable', 'integer', Rule::exists('delegations', 'id')->whereNull('deleted_at')],
            'series_id' => ['nullable', 'integer', Rule::exists('series', 'id')->whereNull('deleted_at')],
            'billing_company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'responsible_user_id' => ['nullable', 'integer', $liveUser],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', 'distinct', $liveUser],
            'is_active' => ['required', 'boolean'],
            'is_client_priority' => ['nullable', 'boolean'],
            'is_reviewed' => ['nullable', 'boolean'],
            'is_email_reviewed' => ['nullable', 'boolean'],
            'has_site_health_and_safety' => ['nullable', 'boolean'],
            'has_customer_health_and_safety' => ['nullable', 'boolean'],
            'is_quality_control_contactable' => ['nullable', 'boolean'],
            'has_parking' => ['nullable', 'boolean'],
            'is_ulez_zone' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'tax_rate' => ['nullable', 'numeric'],
            'tax_included' => ['nullable', 'boolean'],
            'legacy_erp_id' => ['nullable', 'string', 'max:64'],
            'integration_external_id' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
            'notes_alert' => ['nullable', 'boolean'],
            'internal_notes' => ['nullable', 'string'],
            'internal_notes_alert' => ['nullable', 'boolean'],
            'voicebot_time_slots' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function relationshipRules(int $ownerCompanyId, ?int $ignoreId = null): array
    {
        $creatingRelated = request()->input('related_mode') === 'new';

        $uniquePair = Rule::unique('company_relationships')
            ->where(fn ($query) => $query
                ->where('owner_company_id', $ownerCompanyId)
                ->where('kind', request()->input('kind'))
                ->where('deleted_token', '')
                ->whereNull('deleted_at'));

        if ($ignoreId !== null) {
            $uniquePair = $uniquePair->ignore($ignoreId);
        }

        $relatedCompanyIdRules = $creatingRelated
            ? ['nullable', 'integer']
            : [
                'required',
                'integer',
                'different:owner_company_id',
                Rule::notIn([$ownerCompanyId]),
                Rule::exists('companies', 'id')->whereNull('deleted_at'),
                $uniquePair,
            ];

        return [
            'related_mode' => ['required', Rule::in(['existing', 'new'])],
            'related_company_id' => $relatedCompanyIdRules,
            'related_company' => [$creatingRelated ? 'required' : 'nullable', 'array'],
            'related_company.name' => [$creatingRelated ? 'required' : 'nullable', 'string', 'max:255'],
            'related_company.tradename' => ['nullable', 'string', 'max:255'],
            'related_company.tax_id' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('companies', 'tax_id')->whereNull('deleted_at'),
            ],
            'related_company.email' => ['nullable', 'email', 'max:255'],
            'related_company.phone' => ['nullable', 'string', 'max:50'],
            'kind' => ['required', Rule::enum(CompanyRelationshipKind::class)],
            'status' => ['required', Rule::enum(CompanyRelationshipStatus::class)],
            'classification' => ['required', Rule::enum(CompanyRelationshipClassification::class)],
            'owner_reference' => ['nullable', 'string', 'max:80'],
            'related_reference' => ['nullable', 'string', 'max:80'],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->whereNull('deleted_at'),
            ],
            'external_code' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            ...self::relationshipProfileRules(),
        ];
    }

    /**
     * Tenant-scoped party profile fields collapsed from FM clientes / tecnicos / proveedores.
     *
     * @return array<string, mixed>
     */
    public static function relationshipProfileRules(): array
    {
        $liveUser = Rule::exists('users', 'id')->whereNull('deleted_at');

        return [
            'delegation_id' => ['nullable', 'integer', Rule::exists('delegations', 'id')->whereNull('deleted_at')],
            'billing_language_id' => ['nullable', 'integer', Rule::exists('languages', 'id')->whereNull('deleted_at')],
            'series_id' => ['nullable', 'integer', Rule::exists('series', 'id')->whereNull('deleted_at')],
            'priority_ids' => ['nullable', 'array'],
            'priority_ids.*' => ['integer', Rule::exists('client_priorities', 'id')->whereNull('deleted_at')],
            'collaborator_ids' => ['nullable', 'array'],
            'collaborator_ids.*' => ['integer', 'distinct', $liveUser],
            'service_type_ids' => ['nullable', 'array'],
            'service_type_ids.*' => ['integer', 'distinct', Rule::exists('service_types', 'id')->whereNull('deleted_at')],
            'global_service_type_ids' => ['nullable', 'array'],
            'global_service_type_ids.*' => ['integer', 'distinct', Rule::exists('global_service_types', 'id')->whereNull('deleted_at')],
            'alternative_delegation_ids' => ['nullable', 'array'],
            'alternative_delegation_ids.*' => ['integer', 'distinct', Rule::exists('delegations', 'id')->whereNull('deleted_at')],
            'integration_id' => ['nullable', 'integer', Rule::exists('integrations', 'id')->whereNull('deleted_at')],
            'integration_external_id' => ['nullable', 'string', 'max:80'],
            'reported_customer_relationship_id' => ['nullable', 'integer', Rule::exists('company_relationships', 'id')->whereNull('deleted_at')],
            'corrective_work_order_owner_id' => ['nullable', 'integer', $liveUser],
            'preventive_work_order_owner_id' => ['nullable', 'integer', $liveUser],
            'quality_owner_id' => ['nullable', 'integer', $liveUser],
            'account_owner_id' => ['nullable', 'integer', $liveUser],
            'commercial_owner_id' => ['nullable', 'integer', $liveUser],
            'sourced_by_user_id' => ['nullable', 'integer', $liveUser],
            'internal_notes' => ['nullable', 'string'],
            'notes_alert' => ['nullable', 'boolean'],
            'internal_notes_alert' => ['nullable', 'boolean'],
            'onboarding_notes' => ['nullable', 'string'],
            'billing_comments' => ['nullable', 'string'],
            'rates_notes' => ['nullable', 'string'],
            'archetype' => ['nullable', 'string'],
            'tax_rate' => ['nullable', 'numeric'],
            'is_reviewed' => ['nullable', 'boolean'],
            'is_email_reviewed' => ['nullable', 'boolean'],
            'is_invoicing_reviewed' => ['nullable', 'boolean'],
            'invoicing_reviewed_at' => ['nullable', 'date'],
            'quote_close_days' => ['nullable', 'integer', 'min:0'],
            'recurring_meeting_frequency' => ['nullable', 'integer', 'min:0'],
            'sales_feedback_meeting_frequency' => ['nullable', 'integer', 'min:0'],
            'group_zero_cost_work_orders' => ['nullable', 'boolean'],
            'load_materials_on_corrective' => ['nullable', 'boolean'],
            'group_preventive_and_corrective' => ['nullable', 'boolean'],
            'group_preventives_by' => ['nullable', Rule::enum(WorkOrderGroupingBasis::class)],
            'group_correctives_by' => ['nullable', Rule::enum(WorkOrderGroupingBasis::class)],
            'invoice_at_month_end' => ['nullable', 'boolean'],
            'requires_purchase_order' => ['nullable', 'boolean'],
            'requires_requester' => ['nullable', 'boolean'],
            'is_franchise' => ['nullable', 'boolean'],
            'requires_justification' => ['nullable', 'boolean'],
            'auto_send_invoices' => ['nullable', 'boolean'],
            'send_invoices_individually' => ['nullable', 'boolean'],
            'send_debt_reminders' => ['nullable', 'boolean'],
            'is_quality_control_contactable' => ['nullable', 'boolean'],
            'requires_client_informed_check' => ['nullable', 'boolean'],
            'requires_intervention_scheduled_check' => ['nullable', 'boolean'],
            'requires_budget_approval_limit' => ['nullable', 'boolean'],
            'is_intercompany' => ['nullable', 'boolean'],
            'optima_score' => ['nullable', 'numeric'],
            'customer_score' => ['nullable', 'numeric'],
            'average_score' => ['nullable', 'numeric'],
            'optima_score_count' => ['nullable', 'integer', 'min:0'],
            'customer_score_count' => ['nullable', 'integer', 'min:0'],
            'has_health_and_safety' => ['nullable', 'boolean'],
            'is_field_technician' => ['nullable', 'boolean'],
            'is_creditor' => ['nullable', 'boolean'],
            'is_vip' => ['nullable', 'boolean'],
            'is_available_24h' => ['nullable', 'boolean'],
            'day_start_at' => ['nullable', 'date_format:H:i:s'],
            'day_end_at' => ['nullable', 'date_format:H:i:s'],
            'has_garnishment' => ['nullable', 'boolean'],
            'whatsapp_messaging_authorized' => ['nullable', 'boolean'],
            'registered_at' => ['nullable', 'date'],
            'legacy_status_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function companyNullableKeys(): array
    {
        return [
            'tradename', 'slug', 'tax_id', 'country_id', 'residence_country_id',
            'person_type', 'email', 'phone', 'website', 'address_line_1',
            'address_line_2', 'city', 'province_id', 'postal_code', 'employee_count',
            'brand_id', 'language_id', 'latitude', 'longitude', 'legacy_erp_id',
        ];
    }

    /**
     * @return list<string>
     */
    public static function relationshipNullableKeys(): array
    {
        return [
            'owner_reference', 'related_reference', 'brand_id', 'external_code',
            'notes', 'starts_at', 'ends_at', 'related_company_id',
            'delegation_id', 'billing_language_id', 'series_id',
            'integration_id', 'integration_external_id', 'reported_customer_relationship_id',
            'corrective_work_order_owner_id', 'preventive_work_order_owner_id',
            'quality_owner_id', 'account_owner_id', 'commercial_owner_id', 'sourced_by_user_id',
            'internal_notes', 'onboarding_notes', 'billing_comments', 'rates_notes',
            'archetype', 'tax_rate', 'invoicing_reviewed_at', 'quote_close_days',
            'recurring_meeting_frequency', 'sales_feedback_meeting_frequency',
            'group_preventives_by', 'group_correctives_by', 'optima_score',
            'customer_score', 'average_score', 'day_start_at', 'day_end_at',
            'registered_at', 'legacy_status_id',
        ];
    }

    /**
     * @return list<string>
     */
    public static function relationshipBooleanKeys(): array
    {
        return [
            'notes_alert', 'internal_notes_alert', 'is_reviewed', 'is_email_reviewed',
            'is_invoicing_reviewed', 'group_zero_cost_work_orders', 'load_materials_on_corrective',
            'group_preventive_and_corrective', 'invoice_at_month_end', 'requires_purchase_order',
            'requires_requester', 'is_franchise', 'requires_justification', 'auto_send_invoices',
            'send_invoices_individually', 'send_debt_reminders', 'is_quality_control_contactable',
            'requires_client_informed_check', 'requires_intervention_scheduled_check',
            'requires_budget_approval_limit', 'is_intercompany', 'has_health_and_safety',
            'is_field_technician', 'is_creditor', 'is_vip', 'is_available_24h',
            'has_garnishment', 'whatsapp_messaging_authorized',
        ];
    }

    /**
     * @return list<string>
     */
    public static function establishmentBooleanKeys(): array
    {
        return [
            'is_active', 'is_client_priority', 'is_reviewed', 'is_email_reviewed',
            'has_site_health_and_safety', 'has_customer_health_and_safety',
            'is_quality_control_contactable', 'has_parking', 'is_ulez_zone',
            'tax_included', 'notes_alert', 'internal_notes_alert',
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function provinceIdRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('provinces', 'id')->whereNull('deleted_at'),
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                $countryId = request()->input('country_id');

                if ($countryId === null || $countryId === '') {
                    return;
                }

                $matches = Province::query()
                    ->whereKey($value)
                    ->where('country_id', (int) $countryId)
                    ->exists();

                if (! $matches) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function blankToNull(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === '') {
                $payload[$key] = null;
            }
        }

        return $payload;
    }
}
