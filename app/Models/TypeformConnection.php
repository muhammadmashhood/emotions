<?php

namespace App\Models;

use Database\Factories\TypeformConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $label
 * @property string $base_url
 * @property string $access_token
 * @property string|null $account_alias
 * @property Carbon|null $last_verified_at
 * @property string|null $last_error
 */
class TypeformConnection extends Model
{
    /** @use HasFactory<TypeformConnectionFactory> */
    use HasFactory;

    protected $fillable = ['label', 'base_url', 'access_token', 'account_alias', 'last_verified_at', 'last_error'];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'last_verified_at' => 'datetime',
        ];
    }

    /** @return HasMany<Form, $this> */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /** Masked token for display: "tfp_…abcd". */
    public function maskedToken(): string
    {
        $token = (string) $this->access_token;

        return mb_substr($token, 0, 4).'…'.mb_substr($token, -4);
    }
}
