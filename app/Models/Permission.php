<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use SoftDeletes;

    /**
     * Soft-delete the permission after releasing its unique name.
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
