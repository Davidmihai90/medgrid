<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'code', 'name', 'is_active', 'is_synthetic'])] class AssessmentTemplate extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_synthetic' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AssessmentTemplateVersion::class);
    }
}
