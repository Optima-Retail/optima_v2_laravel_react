<?php

declare(strict_types=1);

namespace App\Domain\Auth\Permissions;

use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

final class PolicyPermissionDiscoverer
{
    /**
     * Maps Laravel policy method names to permission ability suffixes.
     *
     * @var array<string, string>
     */
    private const ABILITY_MAP = [
        'viewAny' => 'view',
        'view' => 'view',
        'create' => 'create',
        'update' => 'update',
        'delete' => 'delete',
        'restore' => 'restore',
        'forceDelete' => 'force-delete',
        'replicate' => 'replicate',
    ];

    /**
     * Irregular resource keys (match table / product naming, not English pluralization).
     *
     * @var array<string, string>
     */
    private const RESOURCE_KEY_OVERRIDES = [
        'TaskToPerformPolicy' => 'tasks_to_perform',
    ];

    /**
     * @return list<string>
     */
    public function discover(): array
    {
        $permissions = [];

        foreach ($this->policyClasses() as $policyClass) {
            foreach ($this->abilitiesFor($policyClass) as $ability) {
                $permissions[] = $this->permissionName($policyClass, $ability);
            }
        }

        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return $permissions;
    }

    /**
     * @return list<class-string>
     */
    public function policyClasses(): array
    {
        $path = app_path('Policies');

        if (! is_dir($path)) {
            return [];
        }

        $classes = [];

        foreach ((new Finder)->files()->in($path)->name('*Policy.php') as $file) {
            $class = 'App\\Policies\\'.$file->getBasename('.php');

            if (! class_exists($class)) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param  class-string  $policyClass
     * @return list<string>
     */
    public function abilitiesFor(string $policyClass): array
    {
        $reflection = new ReflectionClass($policyClass);
        $abilities = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $policyClass || $method->isConstructor() || $method->isStatic()) {
                continue;
            }

            $name = $method->getName();

            if (str_starts_with($name, '__')) {
                continue;
            }

            $abilities[] = self::ABILITY_MAP[$name] ?? Str::kebab($name);
        }

        return array_values(array_unique($abilities));
    }

    /**
     * @param  class-string  $policyClass
     */
    public function resourceKey(string $policyClass): string
    {
        $basename = class_basename($policyClass);

        if (isset(self::RESOURCE_KEY_OVERRIDES[$basename])) {
            return self::RESOURCE_KEY_OVERRIDES[$basename];
        }

        return (string) Str::of($basename)
            ->beforeLast('Policy')
            ->snake()
            ->plural();
    }

    /**
     * @param  class-string  $policyClass
     */
    public function permissionName(string $policyClass, string $ability): string
    {
        return $this->resourceKey($policyClass).'.'.$ability;
    }
}
