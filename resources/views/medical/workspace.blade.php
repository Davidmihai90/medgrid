@extends('layouts.app')
@section('title', 'Destination Coordination | MEDGRID')
@section('content')
<div class="medical-shell" data-medical-board data-encounter-id="{{ $encounter?->id }}">
    <header class="medical-command">
        <div>
            <p class="eyebrow">M4 Destination Support</p>
            <h2>Medical coordination</h2>
            <p>Explicit operational compatibility evidence. Final destination decisions remain human.</p>
        </div>
        <label class="medical-selector">
            Active case
            <select onchange="if(this.value) location.href=this.value">
                @forelse($cases as $item)
                    <option value="{{ route('area.medical', ['case' => $item->id]) }}" @selected($case?->is($item))>{{ $item->case_number }} · {{ $item->priority->value }} · {{ $item->status->value }}</option>
                @empty
                    <option>No active cases with encounters</option>
                @endforelse
            </select>
        </label>
    </header>

    @if(!$case || !$encounter)
        <section class="medical-empty">
            <i class="icon" data-lucide="stethoscope"></i>
            <h3>No patient encounter is ready</h3>
            <p>Destination support begins only after an explicit patient encounter exists.</p>
        </section>
    @else
        <nav class="patient-tabs" aria-label="Patients in selected case">
            @foreach($case->encounters as $item)
                <a href="{{ route('area.medical', ['case' => $case->id, 'encounter' => $item->id]) }}" aria-current="{{ $encounter->is($item) ? 'page' : 'false' }}">
                    <span>{{ $item->encounter_number }}</span>
                    <strong>{{ $item->patient?->first_name || $item->patient?->last_name ? trim($item->patient?->first_name.' '.$item->patient?->last_name) : 'Unidentified patient' }}</strong>
                </a>
            @endforeach
        </nav>

        @php($currentSelection = $encounter->destinationSelections->firstWhere('superseded_at', null))
        <section class="destination-strip {{ $currentSelection ? 'has-destination' : '' }}">
            <div>
                <p class="eyebrow">{{ $case->case_number }} · {{ $encounter->encounter_number }}</p>
                <h3>{{ $currentSelection ? $currentSelection->hospital->name : 'Destination pending' }}</h3>
                <p>{{ $currentSelection ? str_replace('_', ' ', $currentSelection->selection_type->value) : 'No human destination decision has been recorded.' }}</p>
            </div>
            <div class="destination-state">
                <span>CASE STATE</span>
                <strong>{{ str_replace('_', ' ', $case->status->value) }}</strong>
                <small>Decision version {{ $encounter->destination_version }}</small>
            </div>
        </section>

        <div class="medical-grid">
            <aside class="medical-panel requirements-panel">
                <div class="section-title">
                    <div><p class="eyebrow">Explicit inputs</p><h3>Requirements</h3></div>
                    <span class="state-pill">{{ $encounter->destinationRequirements->where('status.value', 'ACTIVE')->count() }} ACTIVE</span>
                </div>
                <div class="requirement-list">
                    @forelse($encounter->destinationRequirements as $requirement)
                        <article class="{{ $requirement->status->value !== 'ACTIVE' ? 'historical' : '' }}">
                            <div>
                                <strong>{{ $requirement->target_code }}</strong>
                                <span>{{ str_replace('_', ' ', $requirement->type->value) }}</span>
                                <small>{{ $requirement->source->value }} · {{ $requirement->status->value }} · {{ $requirement->created_at->format('H:i') }}</small>
                            </div>
                            @if($requirement->status->value === 'ACTIVE')
                                @can(\App\Domain\Identity\Support\Permissions::DestinationRequirementsManage)
                                    <details class="row-editor">
                                        <summary class="icon-btn" title="Change or cancel requirement" aria-label="Change or cancel requirement"><i class="icon" data-lucide="pencil"></i></summary>
                                        <div class="requirement-editor">
                                            <form method="post" action="{{ route('medical.requirements.replace', ['encounter' => $encounter, 'requirement' => $requirement]) }}">
                                                @csrf @method('PUT')
                                                <label>Type<select name="type">@foreach(\App\Domain\Destination\Enums\DestinationRequirementType::cases() as $type)<option value="{{ $type->value }}" @selected($type === $requirement->type)>{{ str_replace('_', ' ', $type->value) }}</option>@endforeach</select></label>
                                                <label>Code<input name="target_code" value="{{ $requirement->target_code }}" required maxlength="100"></label>
                                                <label>Importance<select name="importance">@foreach(\App\Domain\Destination\Enums\DestinationRequirementImportance::cases() as $importance)<option value="{{ $importance->value }}" @selected($importance === $requirement->importance)>{{ $importance->value }}</option>@endforeach</select></label>
                                                <input type="hidden" name="source" value="MANUAL">
                                                <label class="wide">Notes<textarea name="notes" maxlength="2000">{{ $requirement->notes }}</textarea></label>
                                                <button class="btn btn-secondary" type="submit"><i class="icon" data-lucide="save"></i>Supersede</button>
                                            </form>
                                            <form method="post" action="{{ route('medical.requirements.cancel', ['encounter' => $encounter, 'requirement' => $requirement]) }}" class="cancel-form">
                                                @csrf @method('DELETE')
                                                <label>Cancellation reason<input name="reason" required minlength="3" maxlength="2000"></label>
                                                <button class="btn btn-danger" type="submit"><i class="icon" data-lucide="ban"></i>Cancel</button>
                                            </form>
                                        </div>
                                    </details>
                                @endcan
                            @endif
                        </article>
                    @empty
                        <p class="empty-row">No explicit destination requirements.</p>
                    @endforelse
                </div>

                @can(\App\Domain\Identity\Support\Permissions::DestinationRequirementsManage)
                    <form method="post" action="{{ route('medical.requirements.store', $encounter) }}" class="requirement-form" x-data="{type:'CAPABILITY_REQUIRED'}">
                        @csrf
                        <label>Requirement type
                            <select name="type" x-model="type">
                                @foreach(\App\Domain\Destination\Enums\DestinationRequirementType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ str_replace('_', ' ', $type->value) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Operational code<input name="target_code" placeholder="e.g. CT" required maxlength="100"></label>
                        <input type="hidden" name="importance" :value="type === 'CAPABILITY_PREFERRED' ? 'PREFERRED' : 'REQUIRED'">
                        <input type="hidden" name="source" value="MANUAL">
                        <label>Notes<textarea name="notes" maxlength="2000"></textarea></label>
                        <button class="btn btn-secondary" type="submit"><i class="icon" data-lucide="plus"></i>Add requirement</button>
                    </form>
                @endcan
            </aside>

            <main class="medical-panel evaluation-panel">
                <div class="section-title">
                    <div><p class="eyebrow">Deterministic evidence</p><h3>Hospital compatibility</h3></div>
                    @can(\App\Domain\Identity\Support\Permissions::DestinationEvaluationsCreate)
                        <form method="post" action="{{ route('medical.evaluations.store', $encounter) }}" class="evaluation-action">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::ulid() }}">
                            <label><span class="sr-only">Active rule version</span><select name="rule_version_id" required>@foreach($ruleVersions as $version)<option value="{{ $version->id }}">{{ $version->ruleSet->name }} · v{{ $version->version }}</option>@endforeach</select></label>
                            <button class="btn btn-primary" type="submit" @disabled($ruleVersions->isEmpty() || $encounter->destinationRequirements->where('status.value', 'ACTIVE')->isEmpty())><i class="icon" data-lucide="refresh-cw"></i>Evaluate</button>
                        </form>
                    @endcan
                </div>

                @php($evaluation = $encounter->destinationEvaluations->first())
                @if($evaluation)
                    <div class="evaluation-meta">
                        <span class="state-pill state-{{ strtolower($evaluation->status->value) }}">{{ $evaluation->status->value }}</span>
                        <span>Reference {{ $evaluation->reference_time->format('Y-m-d H:i:s') }} UTC</span>
                        <span>{{ $evaluation->ruleVersion->ruleSet->name }} v{{ $evaluation->ruleVersion->version }}</span>
                    </div>
                    <div class="candidate-list">
                        @foreach($evaluation->candidates->sortBy(fn($candidate) => [array_search($candidate->outcome->value, ['ELIGIBLE','UNKNOWN','INELIGIBLE']), $candidate->hospital->name]) as $candidate)
                            <article class="candidate-row outcome-{{ strtolower($candidate->outcome->value) }}">
                                <div class="candidate-summary">
                                    <div>
                                        <strong>{{ $candidate->hospital->name }}</strong>
                                        <small>{{ $candidate->hospital->code }} · neutral display order</small>
                                    </div>
                                    <span class="outcome">{{ $candidate->outcome->value }}</span>
                                    <p>{{ $candidate->explanation_summary }}</p>
                                </div>
                                <details class="evidence">
                                    <summary><i class="icon" data-lucide="eye"></i>View evidence</summary>
                                    <div class="evidence-list">
                                        @foreach($candidate->evidence_data['checks'] ?? [] as $check)
                                            <div>
                                                <span class="state-pill state-{{ strtolower($check['result']) }}">{{ $check['result'] }}</span>
                                                <strong>{{ $check['rule_id'] }}</strong>
                                                <span>{{ $check['target_code'] ?? 'Operational state' }}</span>
                                                <small>{{ $check['reason'] }}{{ ($check['hard_requirement'] ?? true) ? '' : ' Preferred only; does not determine eligibility.' }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                                @can(\App\Domain\Identity\Support\Permissions::DestinationSelectionSelect)
                                    <form method="post" action="{{ route('medical.selections.store', $encounter) }}" class="selection-form">
                                        @csrf
                                        <input type="hidden" name="hospital_id" value="{{ $candidate->hospital_id }}">
                                        <input type="hidden" name="candidate_id" value="{{ $candidate->id }}">
                                        <input type="hidden" name="expected_destination_version" value="{{ $encounter->destination_version }}">
                                        <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::ulid() }}">
                                        @if($candidate->outcome->value !== 'ELIGIBLE' || $currentSelection)
                                            <label>Decision reason<textarea name="reason" required minlength="3" maxlength="3000" placeholder="Required for override or destination change"></textarea></label>
                                        @endif
                                        <button class="btn {{ $candidate->outcome->value === 'ELIGIBLE' ? 'btn-primary' : 'btn-secondary' }}" type="submit">
                                            <i class="icon" data-lucide="check"></i>{{ $currentSelection ? 'Change destination' : 'Select destination' }}
                                        </button>
                                    </form>
                                @endcan
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="evaluation-empty"><i class="icon" data-lucide="clipboard-check"></i><h4>No evaluation recorded</h4><p>Add explicit requirements, then run the active rule version.</p></div>
                @endif
            </main>

            <aside class="medical-panel decision-panel">
                <div class="section-title"><div><p class="eyebrow">Human authority</p><h3>Decision history</h3></div></div>
                <div class="decision-history">
                    @forelse($encounter->destinationSelections as $selection)
                        <article class="{{ $selection->superseded_at ? 'historical' : 'current' }}">
                            <time>{{ $selection->created_at->format('H:i:s') }}</time>
                            <div><strong>{{ $selection->hospital->name }}</strong><span>{{ str_replace('_', ' ', $selection->selection_type->value) }}</span>@if($selection->reason)<p>{{ $selection->reason }}</p>@endif</div>
                        </article>
                    @empty
                        <p class="empty-row">No destination decision history.</p>
                    @endforelse
                </div>
                @can(\App\Domain\Identity\Support\Permissions::DestinationSelectionOverride)
                    <details class="manual-selection">
                        <summary class="btn btn-secondary"><i class="icon" data-lucide="shield-alert"></i>Manual path</summary>
                        <form method="post" action="{{ route('medical.selections.store', $encounter) }}">
                            @csrf
                            <input type="hidden" name="expected_destination_version" value="{{ $encounter->destination_version }}">
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::ulid() }}">
                            <label>Hospital<select name="hospital_id" required>@foreach($hospitals as $hospital)<option value="{{ $hospital->id }}">{{ $hospital->name }}</option>@endforeach</select></label>
                            <label>Explicit reason<textarea name="reason" required minlength="3" maxlength="3000"></textarea></label>
                            <button class="btn btn-danger" type="submit" @disabled($hospitals->isEmpty())>Record manual decision</button>
                        </form>
                    </details>
                @endcan
            </aside>
        </div>
    @endif
</div>
@endsection