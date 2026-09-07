<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Users;

use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $companyIds = $this->input('company_ids', []);

        if (! is_array($companyIds)) {
            $companyIds = [];
        }

        $this->merge([
            'company_ids' => array_values(array_filter(array_map(
                static fn ($id) => is_numeric($id) ? (int) $id : null,
                $companyIds,
            ))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');
        $guard = config('auth.defaults.guard', 'web');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($user->id),
            ],
            'username' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'username')->whereNull('deleted_at')->ignore($user->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'locale' => ['nullable', 'string', Rule::in(Locale::supported())],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                'not_in:'.$user->id,
            ],
            'team_leader_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                'not_in:'.$user->id,
            ],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->whereNull('deleted_at')],
            'timezone_id' => ['nullable', 'integer', Rule::exists('timezones', 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:50'],
            'telephony_phone_number' => ['nullable', 'string', 'max:30'],
            'pbx_extension' => ['nullable', 'string', 'max:20'],
            'telegram_user_id' => ['nullable', 'string', 'max:255'],
            'external_hr_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_internal_employee' => ['sometimes', 'boolean'],
            'is_team_account' => ['sometimes', 'boolean'],
            'is_preventive_specialist' => ['nullable', 'boolean'],
            'performance_factor' => ['sometimes', 'numeric', 'between:0,999.99'],
            'invoiced_revenue_target' => ['nullable', 'numeric', 'between:0,99999999.99'],
            'quality_score' => ['sometimes', 'numeric'],
            'balance' => ['sometimes', 'numeric'],
            'budget_approval_limit' => ['sometimes', 'numeric', 'between:0,99999999.99'],
            'sso_only' => ['sometimes', 'boolean'],
            'must_change_password' => ['sometimes', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where(
                    fn ($query) => $query->where('guard_name', $guard)->whereNull('deleted_at'),
                ),
            ],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => [
                'integer',
                Rule::in($this->actorCompanyIds()),
            ],
        ];
    }

    /**
     * @return list<int>
     */
    private function actorCompanyIds(): array
    {
        $actor = $this->user();

        if ($actor === null) {
            return [0];
        }

        $ids = $actor->companies()
            ->wherePivot('is_active', true)
            ->pluck('companies.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $ids === [] ? [0] : $ids;
    }
}
