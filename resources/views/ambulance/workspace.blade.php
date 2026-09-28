@extends('layouts.app')
@section('title', 'Ambulance | MEDGRID')

@section('content')
<div
    class="ambulance-shell"
    data-ambulance-board
    @if($encounter) data-encounter-id="{{ $encounter->id }}" @endif
    x-data="{ critical: false, vitalType: 'HEART_RATE' }"
    :data-critical="critical"
>
    <header class="ambulance-command">
        <div>
            <p class="eyebrow">Ambulance operations</p>
            <h2>{{ $mission ? $mission->vehicle->callsign.' / '.$mission->emergencyCase->case_number : 'Crew workspace' }}</h2>
            @if($mission)
                <p>{{ $mission->emergencyCase->incident_type }} · {{ $mission->emergencyCase->location_text }}</p>
            @else
                <p>No active mission is assigned to this crew.</p>
            @endif
        </div>
        <div class="command-actions">
            <div class="sync-state" data-sync-status data-state="synced" aria-live="polite">
                <i class="icon" data-lucide="cloud-upload"></i>
                <span data-sync-label>SYNCED</span>
                <b data-sync-count hidden>0</b>
            </div>
            <button
                class="critical-toggle"
                type="button"
                :aria-pressed="critical"
                @click="critical = !critical; document.body.classList.toggle('critical-mode', critical)"
            >
                <i class="icon" data-lucide="shield-alert"></i>
                <span x-text="critical ? 'EXIT CRITICAL MODE' : 'CRITICAL MODE'"></span>
            </button>
        </div>
    </header>

    @if($missions->count() > 1)
        <nav class="mission-tabs secondary-zone" aria-label="Active missions">
            @foreach($missions as $item)
                <a href="{{ route('area.ambulance', ['mission' => $item->id]) }}" aria-current="{{ $mission?->is($item) ? 'page' : 'false' }}">
                    <span>{{ $item->vehicle->callsign }}</span>
                    <strong>{{ $item->emergencyCase->case_number }}</strong>
                </a>
            @endforeach
        </nav>
    @endif

    @if(!$mission)
        <div class="card empty">No active mission is assigned to your crew.</div>
    @else
        @php
            $case = $mission->emergencyCase;
            $hasCompletedAssessment = $case->encounters->flatMap->assessments->contains(fn ($item) => $item->status->value === 'COMPLETED');
            $nextState = match($case->status->value) {
                'UNIT_ACCEPTED' => ['EN_ROUTE_TO_SCENE', 'Start response', 'navigation'],
                'EN_ROUTE_TO_SCENE' => ['ON_SCENE', 'Arrived on scene', 'map-pin'],
                'ON_SCENE' => ['PATIENT_CONTACT', 'Patient contact', 'user-round'],
                'PATIENT_CONTACT' => ['ASSESSMENT', 'Begin assessment phase', 'clipboard-plus'],
                'ASSESSMENT' => $hasCompletedAssessment ? ['DESTINATION_PENDING', 'Ready for destination support', 'arrow-right'] : null,
                default => null,
            };
        @endphp

        <section class="mission-banner">
            <span class="priority priority-{{ strtolower($case->priority->value) }}">{{ $case->priority->value }}</span>
            <div>
                <p class="eyebrow">Current mission</p>
                <h3>{{ $case->incident_type }}</h3>
                <p><i class="icon" data-lucide="map-pin"></i>{{ $case->location_text }}</p>
            </div>
            <div class="mission-state">
                <small>CASE STATE</small>
                <strong>{{ str_replace('_', ' ', $case->status->value) }}</strong>
            </div>
            @if($nextState)
                @can(\App\Domain\Identity\Support\Permissions::AmbulanceWorkflowUpdate)
                    <form method="post" action="{{ route('ambulance.workflow', ['case' => $case]) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $nextState[0] }}">
                        <button class="btn btn-primary action-large" type="submit">
                            <i class="icon" data-lucide="{{ $nextState[2] }}"></i>{{ $nextState[1] }}
                        </button>
                    </form>
                @endcan
            @endif
        </section>

        @if(in_array($mission->status->value, ['PENDING', 'DELIVERED']))
            <section class="mission-acceptance">
                <strong>Mission acceptance required</strong>
                <div class="actions">
                    @if($mission->status->value === 'PENDING')
                        <form method="post" action="{{ route('ambulance.delivery', $mission) }}">
                            @csrf
                            <button class="btn btn-secondary" type="submit"><i class="icon" data-lucide="eye"></i>Acknowledge</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('ambulance.acceptance', $mission) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit"><i class="icon" data-lucide="check"></i>Accept mission</button>
                    </form>
                </div>
            </section>
        @endif

        @if($case->encounters->isEmpty())
            <section class="patient-start">
                <div>
                    <p class="eyebrow">Patient encounter</p>
                    <h3>No patient encounter recorded</h3>
                    <p>Patient contact must be established before an encounter can be created.</p>
                </div>
                @if(in_array($case->status->value, ['PATIENT_CONTACT', 'ASSESSMENT']))
                    @can(\App\Domain\Identity\Support\Permissions::EncountersCreate)
                        <form method="post" action="{{ route('ambulance.encounters.store', ['case' => $case]) }}" class="patient-start-form">
                            @csrf
                            <label>
                                Identity status
                                <select name="identity_status">
                                    <option value="UNIDENTIFIED">Unidentified</option>
                                    <option value="PARTIALLY_IDENTIFIED">Partially identified</option>
                                    <option value="IDENTIFIED">Identified</option>
                                </select>
                            </label>
                            <label>
                                First name
                                <input name="first_name" maxlength="120" autocomplete="off">
                            </label>
                            <label>
                                Last name
                                <input name="last_name" maxlength="120" autocomplete="off">
                            </label>
                            <button class="btn btn-primary action-large" type="submit"><i class="icon" data-lucide="user-plus"></i>Create encounter</button>
                        </form>
                    @endcan
                @endif
            </section>
        @else
            <nav class="patient-tabs" aria-label="Patients in this case">
                @foreach($case->encounters as $item)
                    <a href="{{ route('area.ambulance', ['mission' => $mission->id, 'encounter' => $item->id]) }}" aria-current="{{ $encounter?->is($item) ? 'page' : 'false' }}">
                        <span>{{ $item->encounter_number }}</span>
                        <strong>{{ str_replace('_', ' ', $item->condition_level->value) }}</strong>
                    </a>
                @endforeach
                @if(in_array($case->status->value, ['PATIENT_CONTACT', 'ASSESSMENT']))
                    @can(\App\Domain\Identity\Support\Permissions::EncountersCreate)
                        <form method="post" action="{{ route('ambulance.encounters.store', ['case' => $case]) }}">
                            @csrf
                            <input type="hidden" name="identity_status" value="UNIDENTIFIED">
                            <button class="icon-btn" type="submit" title="Add another patient" aria-label="Add another patient"><i class="icon" data-lucide="user-plus"></i></button>
                        </form>
                    @endcan
                @endif
            </nav>
        @endif

        @if($encounter)
            <section class="patient-bar">
                <div class="patient-identity">
                    <span class="patient-avatar"><i class="icon" data-lucide="user-round"></i></span>
                    <div>
                        <p class="eyebrow">{{ $encounter->encounter_number }}</p>
                        <h3>
                            @if($encounter->patient?->first_name || $encounter->patient?->last_name)
                                {{ trim($encounter->patient?->first_name.' '.$encounter->patient?->last_name) }}
                            @else
                                Unidentified patient
                            @endif
                        </h3>
                        <p>{{ str_replace('_', ' ', $encounter->patient?->identity_status->value ?? 'UNIDENTIFIED') }}</p>
                    </div>
                </div>
                <form method="post" action="{{ route('ambulance.condition.update', $encounter) }}" class="condition-control">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="entity_version" value="{{ $encounter->version }}">
                    <label for="condition-level">Condition</label>
                    <select id="condition-level" name="condition_level">
                        @foreach(\App\Domain\Clinical\Enums\ConditionLevel::cases() as $level)
                            <option value="{{ $level->value }}" @selected($encounter->condition_level === $level)>{{ $level->value }}</option>
                        @endforeach
                    </select>
                    @can(\App\Domain\Identity\Support\Permissions::EncountersUpdate)
                        <button class="icon-btn" type="submit" title="Save condition" aria-label="Save condition"><i class="icon" data-lucide="check"></i></button>
                    @endcan
                </form>
            </section>

            <div class="clinical-grid">
                <section class="clinical-main">
                    <div class="section-title">
                        <div>
                            <p class="eyebrow">Latest observations</p>
                            <h3>Vital signs</h3>
                        </div>
                        <span class="timestamp"><i class="icon" data-lucide="clock-3"></i>Server-recorded history</span>
                    </div>

                    <div class="vital-grid">
                        @forelse($latestVitals as $vital)
                            <article class="vital-tile">
                                <span>{{ str_replace('_', ' ', $vital->type->value) }}</span>
                                <strong>
                                    @if($vital->type->value === 'BLOOD_PRESSURE')
                                        {{ rtrim(rtrim($vital->value_numeric, '0'), '.') }}/{{ rtrim(rtrim($vital->secondary_value_numeric, '0'), '.') }}
                                    @elseif($vital->type->value === 'GCS')
                                        {{ $vital->gcs_total }}
                                    @else
                                        {{ rtrim(rtrim($vital->value_numeric, '0'), '.') }}
                                    @endif
                                </strong>
                                <small>{{ $vital->unit }} · {{ $vital->measured_at->format('H:i') }}</small>
                            </article>
                        @empty
                            <div class="vital-empty">No observations recorded</div>
                        @endforelse
                    </div>

                    @can(\App\Domain\Identity\Support\Permissions::VitalsCreate)
                        <form
                            method="post"
                            action="{{ route('ambulance.vitals.store', $encounter) }}"
                            class="vital-entry"
                            data-offline-vital-form
                            data-encounter-id="{{ $encounter->id }}"
                        >
                            @csrf
                            <input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::ulid() }}">
                            <div class="section-title">
                                <div>
                                    <p class="eyebrow">New observation</p>
                                    <h3>Record vital</h3>
                                </div>
                            </div>
                            <div class="vital-form-grid">
                                <label>
                                    Type
                                    <select name="type" x-model="vitalType" required>
                                        @foreach(\App\Domain\Clinical\Enums\VitalType::cases() as $type)
                                            <option value="{{ $type->value }}">{{ str_replace('_', ' ', $type->value) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label x-show="vitalType !== 'GCS'">
                                    <span x-text="vitalType === 'BLOOD_PRESSURE' ? 'Systolic' : 'Value'"></span>
                                    <input name="value_numeric" type="number" step="any" inputmode="decimal" :required="vitalType !== 'GCS'">
                                </label>
                                <label x-show="vitalType === 'BLOOD_PRESSURE'">
                                    Diastolic
                                    <input name="secondary_value_numeric" type="number" step="any" inputmode="decimal" :required="vitalType === 'BLOOD_PRESSURE'">
                                </label>
                                <label x-show="vitalType !== 'GCS'">
                                    Unit
                                    <select name="unit">
                                        <option value="">Select</option>
                                        <option value="bpm">bpm</option>
                                        <option value="mmHg">mmHg</option>
                                        <option value="%">%</option>
                                        <option value="breaths/min">breaths/min</option>
                                        <option value="°C">°C</option>
                                        <option value="mg/dL">mg/dL</option>
                                        <option value="mmol/L">mmol/L</option>
                                        <option value="/10">/10</option>
                                    </select>
                                </label>
                                <template x-if="vitalType === 'GCS'">
                                    <div class="gcs-fields">
                                        <label>Eye<input name="gcs_eye" type="number" min="1" max="4" inputmode="numeric"></label>
                                        <label>Verbal<input name="gcs_verbal" type="number" min="1" max="5" inputmode="numeric"></label>
                                        <label>Motor<input name="gcs_motor" type="number" min="1" max="6" inputmode="numeric"></label>
                                    </div>
                                </template>
                                <label>
                                    Measured at
                                    <input name="measured_at" type="datetime-local" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                                </label>
                                <button class="btn btn-primary action-large" type="submit"><i class="icon" data-lucide="activity"></i>Save observation</button>
                            </div>
                        </form>
                    @endcan

                    <div class="history secondary-zone">
                        <div class="section-title">
                            <div>
                                <p class="eyebrow">Append-only record</p>
                                <h3>Observation history</h3>
                            </div>
                        </div>
                        @forelse($encounter->vitals as $vital)
                            <article class="{{ $vital->superseded_at ? 'superseded' : '' }}">
                                <time>{{ $vital->measured_at->format('H:i:s') }}</time>
                                <strong>{{ str_replace('_', ' ', $vital->type->value) }}</strong>
                                <span>
                                    @if($vital->type->value === 'BLOOD_PRESSURE')
                                        {{ rtrim(rtrim($vital->value_numeric, '0'), '.') }}/{{ rtrim(rtrim($vital->secondary_value_numeric, '0'), '.') }}
                                    @elseif($vital->type->value === 'GCS')
                                        {{ $vital->gcs_total }}
                                    @else
                                        {{ rtrim(rtrim($vital->value_numeric, '0'), '.') }}
                                    @endif
                                    {{ $vital->unit }}
                                </span>
                                <small>{{ $vital->source->value }}{{ $vital->superseded_at ? ' · CORRECTED' : '' }}</small>
                            </article>
                        @empty
                            <p class="empty-row">No history yet.</p>
                        @endforelse
                    </div>
                </section>

                <aside class="clinical-side">
                    <section class="assessment-panel">
                        <div class="section-title">
                            <div>
                                <p class="eyebrow">Structured record</p>
                                <h3>Assessment</h3>
                            </div>
                        </div>
                        @if($assessment)
                            <div class="assessment-head">
                                <div>
                                    <strong>{{ $assessment->templateVersion->template->name }}</strong>
                                    <small>Version {{ $assessment->templateVersion->version }} · {{ $assessment->status->value }}</small>
                                </div>
                                @if($assessment->templateVersion->template->is_synthetic)
                                    <span class="synthetic-label">SYNTHETIC</span>
                                @endif
                            </div>
                            @if($assessment->status->value === 'IN_PROGRESS')
                                <form method="post" action="{{ route('ambulance.assessments.responses', $assessment) }}" class="assessment-form">
                                    @csrf
                                    @method('PUT')
                                    @foreach($assessment->templateVersion->definition['fields'] ?? [] as $field)
                                        @php($stored = $assessment->responses->firstWhere('field_key', $field['key'])?->value['value'] ?? '')
                                        <label>
                                            {{ $field['label'] }} @if($field['required'] ?? false)<span aria-hidden="true">*</span>@endif
                                            <textarea name="responses[{{ $field['key'] }}]" @required($field['required'] ?? false)>{{ $stored }}</textarea>
                                        </label>
                                    @endforeach
                                    <div class="actions">
                                        <button class="btn btn-secondary" type="submit"><i class="icon" data-lucide="save"></i>Save</button>
                                    </div>
                                </form>
                                <form method="post" action="{{ route('ambulance.assessments.complete', $assessment) }}">
                                    @csrf
                                    <button class="btn btn-primary action-large" type="submit"><i class="icon" data-lucide="clipboard-check"></i>Complete assessment</button>
                                </form>
                            @else
                                <dl class="assessment-readonly">
                                    @foreach($assessment->templateVersion->definition['fields'] ?? [] as $field)
                                        <div><dt>{{ $field['label'] }}</dt><dd>{{ $assessment->responses->firstWhere('field_key', $field['key'])?->value['value'] ?: 'Not recorded' }}</dd></div>
                                    @endforeach
                                </dl>
                            @endif
                        @elseif($templates->isNotEmpty())
                            <div class="template-list">
                                @foreach($templates as $version)
                                    <form method="post" action="{{ route('ambulance.assessments.start', ['encounter' => $encounter, 'version' => $version]) }}">
                                        @csrf
                                        <div>
                                            <strong>{{ $version->template->name }}</strong>
                                            <small>Version {{ $version->version }}{{ $version->template->is_synthetic ? ' · SYNTHETIC' : '' }}</small>
                                        </div>
                                        <button class="icon-btn" type="submit" title="Start assessment" aria-label="Start assessment"><i class="icon" data-lucide="arrow-right"></i></button>
                                    </form>
                                @endforeach
                            </div>
                        @else
                            <p class="empty-row">No active assessment template.</p>
                        @endif
                    </section>

                    <section class="identity-panel secondary-zone">
                        <div class="section-title">
                            <div>
                                <p class="eyebrow">Identity state</p>
                                <h3>Patient identity</h3>
                            </div>
                        </div>
                        @can(\App\Domain\Identity\Support\Permissions::PatientsUpdate)
                            <form method="post" action="{{ route('ambulance.patients.update', ['encounter' => $encounter, 'patient' => $encounter->patient]) }}" class="identity-form">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="entity_version" value="{{ $encounter->patient->version }}">
                                <label class="field">Status
                                    <select name="identity_status">
                                        @foreach(\App\Domain\Clinical\Enums\PatientIdentityStatus::cases() as $identityStatus)
                                            <option value="{{ $identityStatus->value }}" @selected($encounter->patient->identity_status === $identityStatus)>{{ str_replace('_', ' ', $identityStatus->value) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="identity-grid">
                                    <label class="field">First name<input name="first_name" value="{{ $encounter->patient->first_name }}" maxlength="120" autocomplete="off"></label>
                                    <label class="field">Last name<input name="last_name" value="{{ $encounter->patient->last_name }}" maxlength="120" autocomplete="off"></label>
                                    <label class="field">Estimated age min<input name="estimated_age_min" value="{{ $encounter->patient->estimated_age_min }}" type="number" min="0" max="130" inputmode="numeric"></label>
                                    <label class="field">Estimated age max<input name="estimated_age_max" value="{{ $encounter->patient->estimated_age_max }}" type="number" min="0" max="130" inputmode="numeric"></label>
                                </div>
                                <label class="field">Sex<input name="sex" value="{{ $encounter->patient->sex }}" maxlength="32" autocomplete="off"></label>
                                <button class="btn btn-secondary" type="submit"><i class="icon" data-lucide="save"></i>Save identity</button>
                            </form>
                        @endcan
                    </section>
                    <section class="notes-panel secondary-zone">
                        <div class="section-title">
                            <div>
                                <p class="eyebrow">Clinical record</p>
                                <h3>Notes</h3>
                            </div>
                        </div>
                        @can(\App\Domain\Identity\Support\Permissions::ClinicalNotesCreate)
                            <form method="post" action="{{ route('ambulance.notes.store', $encounter) }}">
                                @csrf
                                <input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::ulid() }}">
                                <label class="field">
                                    <span class="sr-only">Clinical note</span>
                                    <textarea name="body" maxlength="5000" required placeholder="Record a clinical note"></textarea>
                                </label>
                                <button class="btn btn-secondary" type="submit"><i class="icon" data-lucide="plus"></i>Add note</button>
                            </form>
                        @endcan
                        <div class="note-list">
                            @forelse($encounter->notes as $note)
                                <article><time>{{ $note->recorded_at->format('H:i') }}</time><p>{{ $note->body }}</p></article>
                            @empty
                                <p class="empty-row">No notes recorded.</p>
                            @endforelse
                        </div>
                    </section>
                </aside>
            </div>
        @endif
    @endif
</div>
@endsection
