<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleClient extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'article_id',
        'company_relationship_id',
        'sale_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * @return BelongsTo<CompanyRelationship, $this>
     */
    public function companyRelationship(): BelongsTo
    {
        return $this->belongsTo(CompanyRelationship::class);
    }
}
