<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Config\Provinces;

use App\Models\Country;
use App\Models\Province;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SyncCountryProvincesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('create', Province::class)
            || $user->can('update', Province::class)
            || $user->can('delete', Province::class);
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('provinces');

        if (! is_array($rows)) {
            return;
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            $id = $row['id'] ?? null;

            $normalized[] = [
                'id' => $id === null || $id === '' ? null : (int) $id,
                'name' => $name,
                'code' => $code === '' ? null : $code,
            ];
        }

        $this->merge(['provinces' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Country $country */
        $country = $this->route('country');

        return [
            'provinces' => ['required', 'array'],
            'provinces.*.id' => [
                'nullable',
                'integer',
                Rule::exists('provinces', 'id')
                    ->where(fn ($query) => $query->where('country_id', $country->id)->whereNull('deleted_at')),
            ],
            'provinces.*.name' => ['required', 'string', 'max:255'],
            'provinces.*.code' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Country $country */
            $country = $this->route('country');
            $rows = $this->input('provinces', []);

            if (! is_array($rows)) {
                return;
            }

            $names = [];
            $codes = [];

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $nameKey = mb_strtolower(trim((string) ($row['name'] ?? '')));
                if ($nameKey !== '') {
                    if (isset($names[$nameKey])) {
                        $validator->errors()->add("provinces.{$index}.name", __('validation.unique', ['attribute' => 'name']));
                    } else {
                        $names[$nameKey] = true;

                        $nameTaken = Province::query()
                            ->where('country_id', $country->id)
                            ->whereRaw('LOWER(name) = ?', [$nameKey])
                            ->when($id > 0, fn ($query) => $query->whereKeyNot($id))
                            ->exists();

                        if ($nameTaken) {
                            $validator->errors()->add("provinces.{$index}.name", __('validation.unique', ['attribute' => 'name']));
                        }
                    }
                }

                $code = trim((string) ($row['code'] ?? ''));
                if ($code === '') {
                    continue;
                }

                $codeKey = mb_strtolower($code);
                if (isset($codes[$codeKey])) {
                    $validator->errors()->add("provinces.{$index}.code", __('validation.unique', ['attribute' => 'code']));

                    continue;
                }

                $codes[$codeKey] = true;

                $codeTaken = Province::query()
                    ->where('country_id', $country->id)
                    ->whereRaw('LOWER(code) = ?', [$codeKey])
                    ->when($id > 0, fn ($query) => $query->whereKeyNot($id))
                    ->exists();

                if ($codeTaken) {
                    $validator->errors()->add("provinces.{$index}.code", __('validation.unique', ['attribute' => 'code']));
                }
            }
        });
    }
}
