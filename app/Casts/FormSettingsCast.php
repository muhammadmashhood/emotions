<?php

namespace App\Casts;

use App\Domain\Forms\FormSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<FormSettings, FormSettings|array>
 */
class FormSettingsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): FormSettings
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return FormSettings::fromArray(is_array($decoded) ? $decoded : null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof FormSettings) {
            $value = $value->toArray();
        }

        return json_encode(FormSettings::fromArray(is_array($value) ? $value : null)->toArray(), JSON_THROW_ON_ERROR);
    }
}
