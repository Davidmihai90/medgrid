<?php

namespace App\Domain\Destination\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Destination\Enums\DestinationRuleVersionStatus;
use App\Domain\Destination\Exceptions\DestinationConflict;
use App\Models\DestinationRuleSet;
use App\Models\DestinationRuleSetVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DestinationRuleService
{
    public function __construct(private DestinationRuleDefinitionValidator $validator, private AuditRecorder $audit) {}

    public function createDraft(DestinationRuleSet $ruleSet, User $actor, array $definition): DestinationRuleSetVersion
    {
        $validated = $this->validator->validate($definition);

        return DB::transaction(function () use ($ruleSet, $actor, $validated) {
            $locked = DestinationRuleSet::lockForUpdate()->findOrFail($ruleSet->id);
            $version = ((int) $locked->versions()->max('version')) + 1;
            $draft = DestinationRuleSetVersion::create([
                'organization_id' => $locked->organization_id,
                'destination_rule_set_id' => $locked->id,
                'version' => $version,
                'status' => DestinationRuleVersionStatus::Draft,
                'definition' => $validated,
                'created_by' => $actor->id,
            ]);
            $this->audit->record('destination.rule_version.created', $draft, $locked->organization, ['rule_set_id' => $locked->id, 'version' => $version], actor: $actor);

            return $draft;
        });
    }

    public function activate(DestinationRuleSetVersion $version, User $actor): DestinationRuleSetVersion
    {
        return DB::transaction(function () use ($version, $actor) {
            $locked = DestinationRuleSetVersion::lockForUpdate()->with('ruleSet.organization')->findOrFail($version->id);
            if ($locked->status !== DestinationRuleVersionStatus::Draft) {
                throw new DestinationConflict('Only a draft destination rule version can be activated.');
            }

            $this->validator->validate($locked->definition);
            DestinationRuleSetVersion::query()
                ->where('destination_rule_set_id', $locked->destination_rule_set_id)
                ->where('status', DestinationRuleVersionStatus::Active->value)
                ->update(['status' => DestinationRuleVersionStatus::Retired->value, 'retired_at' => now()]);
            $locked->update(['status' => DestinationRuleVersionStatus::Active, 'activated_by' => $actor->id, 'activated_at' => now()]);
            $this->audit->record('destination.rule_version.activated', $locked, $locked->ruleSet->organization, ['rule_set_id' => $locked->destination_rule_set_id, 'version' => $locked->version], actor: $actor);

            return $locked->refresh();
        });
    }
}
