<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Series extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'color',
        'is_selectable',
        'credit_note_series_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_selectable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Series, $this>
     */
    public function creditNoteSeries(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credit_note_series_id');
    }

    /**
     * Soft-delete after releasing the unique key.
     */
    public function softDeleteSafely(): bool
    {
        $this->key = $this->uniqueSoftDeletedKey($this->key);
        $this->credit_note_series_id = null;
        $this->save();

        return (bool) $this->delete();
    }

    private function uniqueSoftDeletedKey(string $key): string
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $max = 64;
        $baseMax = max(1, $max - strlen($suffix));

        return substr($key, 0, $baseMax).$suffix;
    }
}
