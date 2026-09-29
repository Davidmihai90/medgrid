<?php

namespace App\Models;

use App\Domain\Destination\Enums\DestinationRuleVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['organization_id', 'destination_rule_set_id', 'version', 'status', 'definition', 'created_by', 'activated_by', 'activated_at', 'retired_at'])]
class DestinationRuleSetVersion extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            $immutable = in_array($version->getRawOriginal('status'), [DestinationRuleVersionStatus::Active->value, DestinationRuleVersionStatus::Retired->value], true);
            if ($immutable && $version->isDirty(['organization_id', 'destination_rule_set_id', 'version', 'definition', 'created_by'])) {
                throw new LogicException('Active and retired destination rule definitions are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Destination rule versions are append-only.'));
    }

    protected function casts(): array
    {
        return ['status' => DestinationRuleVersionStatus::class, 'definition' => 'array', 'activated_at' => 'immutable_datetime', 'retired_at' => 'immutable_datetime'];
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(DestinationRuleSet::class, 'destination_rule_set_id');
    }
}
