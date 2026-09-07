<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RatingType extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'max_score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
        ];
    }

    /**
     * Soft-delete the rating type after releasing its unique code.
     */
    public function softDeleteSafely(): bool
    {
        $this->code = $this->uniqueSoftDeletedCode($this->code);
        $this->save();

        return (bool) $this->delete();
    }

    private function uniqueSoftDeletedCode(string $code): string
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $max = 64;
        $baseMax = max(1, $max - strlen($suffix));

        return substr($code, 0, $baseMax).$suffix;
    }
}
