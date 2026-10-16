<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHospital;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $hospital_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 */
class Branch extends Model
{
    use BelongsToHospital;

    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    protected $fillable = ['hospital_id', 'name', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
