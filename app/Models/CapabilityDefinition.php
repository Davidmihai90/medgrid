<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'active', 'is_synthetic'])]
class CapabilityDefinition extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_synthetic' => 'boolean'];
    }

    public function hospitalCapabilities(): HasMany
    {
        return $this->hasMany(HospitalCapability::class);
    }
}
