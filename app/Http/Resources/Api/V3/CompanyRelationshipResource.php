<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V3;

use App\Models\CompanyRelationship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyRelationship
 */
final class CompanyRelationshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_company_id' => $this->owner_company_id,
            'related_company_id' => $this->related_company_id,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'classification' => $this->classification->value,
            'owner_reference' => $this->owner_reference,
            'related_reference' => $this->related_reference,
            'brand_id' => $this->brand_id,
            'external_code' => $this->external_code,
            'notes' => $this->notes,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'related_company' => $this->whenLoaded('relatedCompany', fn () => [
                'id' => $this->relatedCompany?->id,
                'name' => $this->relatedCompany?->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
