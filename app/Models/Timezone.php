<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Timezone extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'timezone',
    ];

    /**
     * Soft-delete the timezone after releasing its unique IANA identifier.
     */
    public function softDeleteSafely(): bool
    {
        $this->timezone = $this->uniqueSoftDeletedTimezone($this->timezone);
        $this->save();

        return (bool) $this->delete();
    }

    private function uniqueSoftDeletedTimezone(string $timezone): string
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $max = 255;
        $baseMax = max(1, $max - strlen($suffix));

        return substr($timezone, 0, $baseMax).$suffix;
    }
}
