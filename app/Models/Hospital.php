<?php

namespace App\Models;

use Database\Factories\HospitalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property bool $is_active
 * @property bool $require_review
 * @property string|null $typeform_workspace_id
 * @property string|null $typeform_workspace_name
 */
class Hospital extends Model
{
    /** @use HasFactory<HospitalFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'logo_path', 'is_active', 'require_review', 'typeform_workspace_id', 'typeform_workspace_name'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'require_review' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Hospital $hospital): void {
            $hospital->uuid ??= (string) Str::uuid();
            if (empty($hospital->slug)) {
                $hospital->slug = static::uniqueSlug($hospital->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'hospital';
        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Form, $this> */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
