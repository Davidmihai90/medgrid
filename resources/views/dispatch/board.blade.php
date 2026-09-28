@extends('layouts.app')
@section('title', 'Dispatch | MEDGRID')

@section('content')
<header class="page-head">
    <div>
        <p class="eyebrow">Live operations</p>
        <h2>Dispatch board</h2>
        <p>Cases, unit readiness and operational timeline.</p>
    </div>
    <button class="btn btn-primary" type="button" x-data @click="$dispatch('open-case-form')">
        <i class="icon" data-lucide="plus"></i>
        New case
    </button>
</header>

@if($errors->any())
    <div class="alert alert-error" role="alert">
        <strong>Action not completed.</strong>
        <ul class="error-list">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div
    class="dispatch-grid"
    data-dispatch-board
    x-data="{ assignmentModal: @js(old('assignment_action')) }"
>
    <section class="dispatch-column">
        <div class="section-head">
            <h3>Active cases</h3>
            <span class="status">{{ $cases->count() }}</span>
        </div>
        <div class="case-list">
            @forelse($cases as $case)
                <a class="case-row {{ $selected?->is($case) ? 'selected' : '' }}" href="{{ route('area.dispatch', ['case' => $case->id]) }}">
                    <span class="priority priority-{{ strtolower($case->priority->value) }}">{{ $case->priority->value }}</span>
                    <span>
                        <strong>{{ $case->case_number }}</strong>
                        <small>{{ $case->incident_type }} · {{ $case->location_text }}</small>
                    </span>
                    <span class="case-state">{{ str_replace('_', ' ', $case->status->value) }}</span>
                </a>
            @empty
                <div class="empty">No active cases.</div>
            @endforelse
        </div>
    </section>

    <section class="dispatch-column detail">
        @if($selected)
            <div class="section-head">
                <div>
                    <p class="eyebrow">{{ $selected->case_number }}</p>
                    <h3>{{ $selected->incident_type }}</h3>
                </div>
                <span class="priority priority-{{ strtolower($selected->priority->value) }}">{{ $selected->priority->value }}</span>
            </div>

            <dl class="case-facts">
                <div>
                    <dt>Status</dt>
                    <dd>{{ str_replace('_', ' ', $selected->status->value) }}</dd>
                </div>
                <div>
                    <dt>Received</dt>
                    <dd>{{ $selected->received_at->timezone($currentOrganization->timezone)->format('H:i:s') }}</dd>
                </div>
                <div class="wide">
                    <dt>Location</dt>
                    <dd>{{ $selected->location_text }}</dd>
                </div>
                <div class="wide">
                    <dt>Summary</dt>
                    <dd>{{ $selected->summary }}</dd>
                </div>
            </dl>

            @if(in_array($selected->status->value, ['RECEIVED', 'TRIAGED']))
                <form class="compact-form" method="post" action="{{ route('dispatch.cases.triage', $selected) }}">
                    @csrf
                    <label>
                        Priority
                        <select name="priority" required>
                            @foreach($priorities as $priority)
                                @if($priority->value !== 'UNKNOWN')
                                    <option value="{{ $priority->value }}" @selected($selected->priority === $priority)>{{ $priority->value }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Dispatcher notes
                        <textarea name="dispatcher_notes">{{ old('dispatcher_notes', $selected->dispatcher_notes) }}</textarea>
                    </label>
                    <button class="btn btn-secondary" type="submit">
                        <i class="icon" data-lucide="clipboard-check"></i>
                        Record triage
                    </button>
                </form>
            @endif

            @if($selected->status->value === 'TRIAGED')
                <form class="compact-form" method="post" action="{{ route('dispatch.assignments.store', $selected) }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::ulid() }}">
                    <label>
                        Available unit
                        <select name="vehicle_id" required>
                            <option value="">Select unit</option>
                            @foreach($vehicles->where('status', $available) as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ $vehicle->callsign }} · {{ $vehicle->display_name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit">
                        <i class="icon" data-lucide="radio-tower"></i>
                        Assign unit
                    </button>
                </form>
            @endif

            @if($activeAssignment)
                <section class="assignment-panel" aria-labelledby="active-assignment-heading">
                    <div>
                        <p class="eyebrow">Active assignment</p>
                        <h3 id="active-assignment-heading">{{ $activeAssignment->vehicle->callsign }}</h3>
                        <p>
                            {{ $activeAssignment->vehicle->display_name }}
                            <span class="status">{{ str_replace('_', ' ', $activeAssignment->status->value) }}</span>
                        </p>
                    </div>
                    <div class="actions">
                        @can('reassign', $activeAssignment)
                            <button class="btn btn-secondary" type="button" @click="assignmentModal = 'reassign'">
                                <i class="icon" data-lucide="refresh-cw"></i>
                                Reassign
                            </button>
                        @endcan
                        @can('cancel', $activeAssignment)
                            <button class="btn btn-danger" type="button" @click="assignmentModal = 'cancel'">
                                <i class="icon" data-lucide="ban"></i>
                                Cancel assignment
                            </button>
                        @endcan
                    </div>
                </section>

                @can('reassign', $activeAssignment)
                    <div x-show="assignmentModal === 'reassign'" x-cloak class="modal-backdrop">
                        <form class="modal card" method="post" action="{{ route('dispatch.assignments.update', $activeAssignment) }}" @click.outside="assignmentModal = null">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="assignment_action" value="reassign">
                            <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::ulid() }}">
                            <div class="section-head">
                                <div>
                                    <p class="eyebrow">Reassign unit</p>
                                    <h3>{{ $selected->case_number }} · {{ $activeAssignment->vehicle->callsign }}</h3>
                                </div>
                                <button class="icon-btn" type="button" @click="assignmentModal = null" title="Close">
                                    <i class="icon" data-lucide="x"></i>
                                </button>
                            </div>
                            <p class="modal-warning">
                                The current assignment will be closed and the selected available unit will receive a new mission.
                            </p>
                            <div class="field">
                                <label for="replacement-vehicle">Replacement unit</label>
                                <select id="replacement-vehicle" name="vehicle_id" required @disabled($replacementVehicles->isEmpty())>
                                    <option value="">Select an available unit</option>
                                    @foreach($replacementVehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') === $vehicle->id)>
                                            {{ $vehicle->callsign }} · {{ $vehicle->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($replacementVehicles->isEmpty())
                                    <span class="help">No replacement unit is currently available.</span>
                                @endif
                            </div>
                            <div class="field">
                                <label for="reassignment-reason">Operational reason</label>
                                <textarea id="reassignment-reason" name="reason" required minlength="3" maxlength="1000">{{ old('reason') }}</textarea>
                            </div>
                            <label class="confirmation">
                                <input type="checkbox" required>
                                <span>I confirm this reassignment for {{ $selected->case_number }}.</span>
                            </label>
                            <div class="actions">
                                <button class="btn btn-primary" type="submit" @disabled($replacementVehicles->isEmpty())>
                                    <i class="icon" data-lucide="refresh-cw"></i>
                                    Confirm reassignment
                                </button>
                                <button class="btn btn-secondary" type="button" @click="assignmentModal = null">Keep current unit</button>
                            </div>
                        </form>
                    </div>
                @endcan

                @can('cancel', $activeAssignment)
                    <div x-show="assignmentModal === 'cancel'" x-cloak class="modal-backdrop">
                        <form class="modal card" method="post" action="{{ route('dispatch.assignments.destroy', $activeAssignment) }}" @click.outside="assignmentModal = null">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="assignment_action" value="cancel">
                            <div class="section-head">
                                <div>
                                    <p class="eyebrow">Cancel assignment</p>
                                    <h3>{{ $selected->case_number }} · {{ $activeAssignment->vehicle->callsign }}</h3>
                                </div>
                                <button class="icon-btn" type="button" @click="assignmentModal = null" title="Close">
                                    <i class="icon" data-lucide="x"></i>
                                </button>
                            </div>
                            <p class="modal-warning">
                                The unit will return to available status and this case will require a new assignment.
                            </p>
                            <div class="field">
                                <label for="cancellation-reason">Operational reason</label>
                                <textarea id="cancellation-reason" name="reason" required minlength="3" maxlength="1000">{{ old('reason') }}</textarea>
                            </div>
                            <label class="confirmation">
                                <input type="checkbox" required>
                                <span>I confirm cancellation for {{ $selected->case_number }}.</span>
                            </label>
                            <div class="actions">
                                <button class="btn btn-danger" type="submit">
                                    <i class="icon" data-lucide="ban"></i>
                                    Confirm cancellation
                                </button>
                                <button class="btn btn-secondary" type="button" @click="assignmentModal = null">Keep assignment</button>
                            </div>
                        </form>
                    </div>
                @endcan
            @endif

            <div class="timeline">
                <div class="section-head"><h3>Timeline</h3></div>
                @foreach($selected->events as $event)
                    <article>
                        <time>{{ $event->occurred_at->timezone($currentOrganization->timezone)->format('H:i:s') }}</time>
                        <div>
                            <strong>{{ $event->summary }}</strong>
                            <small>{{ $event->event_type }}</small>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="empty">Select a case to inspect it.</div>
        @endif
    </section>

    <aside class="dispatch-column units">
        <div class="section-head"><h3>Units</h3></div>
        @foreach($vehicles as $vehicle)
            <article class="unit-row">
                <div>
                    <strong>{{ $vehicle->callsign }}</strong>
                    <small>{{ $vehicle->display_name }}</small>
                </div>
                <span class="status {{ strtolower($vehicle->status->value) }}">{{ str_replace('_', ' ', $vehicle->status->value) }}</span>
                <small>{{ $vehicle->crewAssignments->pluck('user.name')->join(', ') ?: 'No active crew' }}</small>
            </article>
        @endforeach
    </aside>
</div>

<div x-data="{ open: false }" @open-case-form.window="open = true" x-show="open" x-cloak class="modal-backdrop">
    <form class="modal card" method="post" action="{{ route('dispatch.cases.store') }}" @click.outside="open = false">
        @csrf
        <div class="section-head">
            <h3>New emergency case</h3>
            <button class="icon-btn" type="button" @click="open = false" title="Close">
                <i class="icon" data-lucide="x"></i>
            </button>
        </div>
        <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::ulid() }}">
        <div class="form-grid">
            <div class="field">
                <label>Incident type</label>
                <input name="incident_type" required maxlength="120">
            </div>
            <div class="field">
                <label>Location</label>
                <input name="location_text" required maxlength="255">
            </div>
            <div class="field full">
                <label>Operational summary</label>
                <textarea name="summary" required maxlength="4000"></textarea>
            </div>
            <div class="field">
                <label>Caller name</label>
                <input name="caller_name" maxlength="255">
            </div>
            <div class="field">
                <label>Caller phone</label>
                <input name="caller_phone" maxlength="255">
            </div>
        </div>
        <div class="actions">
            <button class="btn btn-primary" type="submit">
                <i class="icon" data-lucide="plus"></i>
                Create case
            </button>
            <button class="btn btn-secondary" type="button" @click="open = false">Cancel</button>
        </div>
    </form>
</div>
@endsection
