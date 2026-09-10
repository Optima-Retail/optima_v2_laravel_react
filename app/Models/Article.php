<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'is_deletable',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_deletable' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ArticleLanguage, $this>
     */
    public function languages(): HasMany
    {
        return $this->hasMany(ArticleLanguage::class);
    }

    /**
     * @return HasMany<ArticleClient, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(ArticleClient::class);
    }
}
