<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V3;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
final class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tradename' => $this->tradename,
            'slug' => $this->slug,
            'tax_id' => $this->tax_id,
            'kind' => $this->kind->value,
            'country_id' => $this->country_id,
            'residence_country_id' => $this->residence_country_id,
            'person_type' => $this->person_type?->value,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'employee_count' => $this->employee_count,
            'is_active' => $this->is_active,
            'brand_id' => $this->brand_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
