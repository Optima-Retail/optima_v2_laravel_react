<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Domain\Companies\Enums\TechnicianRatingSource;
use App\Models\CompanyRelationship;
use App\Models\TechnicianRating;
use Illuminate\Support\Facades\DB;

final class TechnicianRatingService
{
    /**
     * @return list<array{
     *     id: int,
     *     company_relationship_id: int,
     *     work_order_id: int|null,
     *     score: int,
     *     notes: string|null,
     *     source: string,
     *     created_at: string|null
     * }>
     */
    public function forTechnician(CompanyRelationship $relationship, int $limit = 25): array
    {
        return TechnicianRating::query()
            ->where('company_relationship_id', $relationship->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (TechnicianRating $rating): array => $this->toRow($rating))
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     score: int,
     *     notes?: string|null,
     *     source: string,
     *     work_order_id?: int|null
     * }  $data
     * @return array{
     *     id: int,
     *     company_relationship_id: int,
     *     work_order_id: int|null,
     *     score: int,
     *     notes: string|null,
     *     source: string,
     *     created_at: string|null,
     *     aggregates: array{
     *         optima_score: string|null,
     *         customer_score: string|null,
     *         average_score: string|null,
     *         optima_score_count: int,
     *         customer_score_count: int
     *     }
     * }
     */
    public function upsert(CompanyRelationship $relationship, array $data): array
    {
        return DB::transaction(function () use ($relationship, $data): array {
            $source = TechnicianRatingSource::from((string) $data['source']);
            $workOrderId = isset($data['work_order_id']) && $data['work_order_id'] !== '' && $data['work_order_id'] !== null
                ? (int) $data['work_order_id']
                : null;

            $query = TechnicianRating::query()
                ->where('company_relationship_id', $relationship->id)
                ->where('source', $source->value);

            if ($workOrderId === null) {
                $query->whereNull('work_order_id');
            } else {
                $query->where('work_order_id', $workOrderId);
            }

            /** @var TechnicianRating|null $rating */
            $rating = $query->first();

            $attributes = [
                'company_relationship_id' => $relationship->id,
                'work_order_id' => $workOrderId,
                'score' => (int) $data['score'],
                'notes' => $data['notes'] ?? null,
                'source' => $source,
            ];

            if ($rating !== null) {
                $rating->update($attributes);
            } else {
                $rating = TechnicianRating::query()->create($attributes);
            }

            $aggregates = $this->recomputeScores($relationship);

            return [
                ...$this->toRow($rating->fresh() ?? $rating),
                'aggregates' => $aggregates,
            ];
        });
    }

    /**
     * @return array{
     *     optima_score: string|null,
     *     customer_score: string|null,
     *     average_score: string|null,
     *     optima_score_count: int,
     *     customer_score_count: int
     * }
     */
    public function recomputeScores(CompanyRelationship $relationship): array
    {
        $optimaCount = TechnicianRating::query()
            ->where('company_relationship_id', $relationship->id)
            ->where('source', TechnicianRatingSource::Optima->value)
            ->count();
        $optimaAvg = $optimaCount > 0
            ? TechnicianRating::query()
                ->where('company_relationship_id', $relationship->id)
                ->where('source', TechnicianRatingSource::Optima->value)
                ->avg('score')
            : null;

        $customerCount = TechnicianRating::query()
            ->where('company_relationship_id', $relationship->id)
            ->where('source', TechnicianRatingSource::Customer->value)
            ->count();
        $customerAvg = $customerCount > 0
            ? TechnicianRating::query()
                ->where('company_relationship_id', $relationship->id)
                ->where('source', TechnicianRatingSource::Customer->value)
                ->avg('score')
            : null;

        $totalCount = $optimaCount + $customerCount;
        $average = $totalCount > 0
            ? TechnicianRating::query()
                ->where('company_relationship_id', $relationship->id)
                ->avg('score')
            : null;

        $payload = [
            'optima_score' => $optimaAvg !== null ? round((float) $optimaAvg, 2) : null,
            'customer_score' => $customerAvg !== null ? round((float) $customerAvg, 2) : null,
            'average_score' => $average !== null ? round((float) $average, 2) : null,
            'optima_score_count' => $optimaCount,
            'customer_score_count' => $customerCount,
        ];

        $relationship->update($payload);

        return [
            'optima_score' => $payload['optima_score'] !== null ? number_format($payload['optima_score'], 2, '.', '') : null,
            'customer_score' => $payload['customer_score'] !== null ? number_format($payload['customer_score'], 2, '.', '') : null,
            'average_score' => $payload['average_score'] !== null ? number_format($payload['average_score'], 2, '.', '') : null,
            'optima_score_count' => $optimaCount,
            'customer_score_count' => $customerCount,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     company_relationship_id: int,
     *     work_order_id: int|null,
     *     score: int,
     *     notes: string|null,
     *     source: string,
     *     created_at: string|null
     * }
     */
    private function toRow(TechnicianRating $rating): array
    {
        $source = $rating->source;

        return [
            'id' => $rating->id,
            'company_relationship_id' => $rating->company_relationship_id,
            'work_order_id' => $rating->work_order_id,
            'score' => (int) $rating->score,
            'notes' => $rating->notes,
            'source' => $source instanceof TechnicianRatingSource ? $source->value : (string) $source,
            'created_at' => $rating->created_at?->toIso8601String(),
        ];
    }
}
