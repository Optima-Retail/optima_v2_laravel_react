<?php

declare(strict_types=1);

namespace App\Domain\Companies\Support;

use App\Domain\Companies\Enums\CompanyKind;
use App\Domain\Companies\Enums\CompanyRelationshipClassification;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Companies\Enums\CompanyRelationshipStatus;
use App\Domain\Companies\Enums\PersonType;
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
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'employee_count' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
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

        return [
            'company_id' => ['required', 'integer', Rule::in($companyId)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', $uniqueCode],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'timezone_id' => ['nullable', 'integer', Rule::exists('timezones', 'id')->whereNull('deleted_at')],
            'delegation_id' => ['nullable', 'integer', Rule::exists('delegations', 'id')->whereNull('deleted_at')],
            'is_active' => ['required', 'boolean'],
            'billing_company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
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
