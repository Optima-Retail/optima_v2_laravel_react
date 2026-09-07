<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Roles;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role $role */
        $role = $this->route('role');

        return $this->user()?->can('update', $role) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');
        $guard = config('auth.defaults.guard', 'web');

        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'alpha_dash',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', $guard)->whereNull('deleted_at'))
                    ->ignore($role->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(
                    fn ($query) => $query->where('guard_name', $guard)->whereNull('deleted_at'),
                ),
            ],
        ];
    }
}
