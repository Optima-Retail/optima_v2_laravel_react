<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Roles;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $guard = config('auth.defaults.guard', 'web');

        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'alpha_dash',
                Rule::unique('roles', 'name')->where(
                    fn ($query) => $query->where('guard_name', $guard)->whereNull('deleted_at'),
                ),
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
