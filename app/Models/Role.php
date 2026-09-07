<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use SoftDeletes;

    /**
     * Soft-delete the role after releasing its unique name.
     */
    public function softDeleteSafely(): bool
    {
        $this->name = $this->uniqueSoftDeletedName($this->name);
        $this->save();

        return (bool) $this->delete();
    }

    private function uniqueSoftDeletedName(string $name): string
    {
        $suffix = '__deleted_'.$this->getKey().'_'.now()->timestamp;
        $max = 125;
        $baseMax = max(1, $max - strlen($suffix));

        return substr($name, 0, $baseMax).$suffix;
    }
}
