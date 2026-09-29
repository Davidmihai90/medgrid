@extends('layouts.app')
@section('title', 'Hospital Command | MEDGRID')

@section('content')
<div class="hospital-shell" data-hospital-board @if($hospital) data-hospital-id="{{ $hospital->id }}" @endif>
    <header class="hospital-command">
        <div>
            <p class="eyebrow">Hospital network operations</p>
            <h2>Hospital Command</h2>
            <p>Current operational facts, provenance and incoming notifications.</p>
        </div>
        @if($hospitals->isNotEmpty())
            <form method="get" action="{{ route('area.hospital') }}" class="hospital-selector">
                <label for="hospital-context">Hospital context</label>
                <select id="hospital-context" name="hospital" onchange="this.form.submit()">
                    @foreach($hospitals as $availableHospital)
                        <option value="{{ $availableHospital->id }}" @selected($hospital?->is($availableHospital))>{{ $availableHospital->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </header>

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <strong>Hospital update not completed.</strong>
            <ul class="error-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if(!$hospital)
        <section class="hospital-empty"><i class="icon" data-lucide="hospital"></i><h3>No hospital access</h3><p>Your current organization has no active hospital assignment available to this account.</p></section>
    @else
        @php
            $receiving = $snapshot['receiving'];
            $timezone = $hospital->timezone;
        @endphp
        <section class="receiving-strip state-{{ strtolower($receiving['value']) }}">
            <div class="receiving-identity">
                <span class="hospital-monogram">{{ mb_substr($hospital->short_name ?? $hospital->name, 0, 2) }}</span>
                <div>
                    <p class="eyebrow">{{ $hospital->code }} · {{ $hospital->status->value }}</p>
                    <h3>{{ $hospital->name }}</h3>
                    <p>{{ $hospital->address }}</p>
                </div>
            </div>
            <div class="receiving-state">
                <span>Receiving</span>
                <strong>{{ str_replace('_', ' ', $receiving['value']) }}</strong>
                <small>
                    {{ $receiving['last_confirmed_at'] ? 'Confirmed '.\Carbon\CarbonImmutable::parse($receiving['last_confirmed_at'])->diffForHumans() : 'Never confirmed' }}
                    · {{ $receiving['freshness'] }}
                </small>
            </div>
            @can('updateAvailability', $hospital)
                <details class="command-editor">
                    <summary class="btn btn-secondary"><i class="icon" data-lucide="pencil"></i>Update receiving</summary>
                    <form method="post" action="{{ route('hospital.receiving.store', $hospital) }}">
                        @csrf
                        <input type="hidden" name="expected_version" value="{{ $hospital->version }}">
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                        <input type="hidden" name="source" value="MANUAL">
                        <input type="hidden" name="effective_at" value="{{ now()->utc()->toIso8601String() }}">
                        <label>Status<select name="status" required>@foreach(['OPEN','LIMITED','NOT_RECEIVING','UNKNOWN'] as $state)<option value="{{ $state }}">{{ str_replace('_',' ',$state) }}</option>@endforeach</select></label>
                        <label>Reason<select name="reason_code"><option value="">None</option>@foreach(['CAPACITY','STAFFING','EQUIPMENT','MAINTENANCE','TEMPORARY_RESTRICTION','INTERNAL_INCIDENT','OTHER'] as $reason)<option value="{{ $reason }}">{{ str_replace('_',' ',$reason) }}</option>@endforeach</select></label>
                        <label class="wide">Context<textarea name="reason_text" maxlength="1000"></textarea></label>
                        <button class="btn btn-primary" type="submit"><i class="icon" data-lucide="save"></i>Record status</button>
                    </form>
                </details>
            @endcan
        </section>

        <div class="hospital-grid">
            <section class="hospital-panel capabilities-panel">
                <header class="section-title">
                    <div><p class="eyebrow">Structural capability vs live state</p><h3>Capabilities</h3></div>
                    <span class="status">{{ count($snapshot['capabilities']) }}</span>
                </header>
                <div class="operational-list">
                    @forelse($snapshot['capabilities'] as $capability)
                        <article class="operational-row">
                            <div>
                                <strong>{{ $capability['name'] }}</strong>
                                <small>{{ $capability['department'] ?? 'Hospital-wide' }} · {{ $capability['enabled'] ? 'CONFIGURED' : 'DISABLED' }}</small>
                            </div>
                            <div class="fact-state">
                                <span class="state-pill state-{{ strtolower($capability['availability']['value']) }}">{{ $capability['availability']['value'] }}</span>
                                <small>{{ $capability['availability']['freshness'] }}@if($capability['availability']['last_confirmed_at']) · {{ \Carbon\CarbonImmutable::parse($capability['availability']['last_confirmed_at'])->diffForHumans() }}@endif</small>
                            </div>
                            @can('updateAvailability', $hospital)
                                <details class="row-editor">
                                    <summary class="icon-btn" title="Update {{ $capability['name'] }}"><i class="icon" data-lucide="pencil"></i></summary>
                                    <form method="post" action="{{ route('hospital.capability-availability.store', $capability['id']) }}">
                                        @csrf
                                        <input type="hidden" name="expected_version" value="{{ $hospital->version }}">
                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                                        <input type="hidden" name="source" value="MANUAL">
                                        <input type="hidden" name="effective_at" value="{{ now()->utc()->toIso8601String() }}">
                                        <label>Status<select name="status">@foreach(['AVAILABLE','LIMITED','UNAVAILABLE','UNKNOWN'] as $state)<option>{{ $state }}</option>@endforeach</select></label>
                                        <label>Reason<select name="reason_code"><option value="">None</option>@foreach(['CAPACITY','STAFFING','EQUIPMENT','MAINTENANCE','TEMPORARY_RESTRICTION','INTERNAL_INCIDENT','OTHER'] as $reason)<option>{{ $reason }}</option>@endforeach</select></label>
                                        <label class="wide">Context<textarea name="reason_text"></textarea></label>
                                        <button class="btn btn-primary" type="submit"><i class="icon" data-lucide="save"></i>Record</button>
                                    </form>
                                </details>
                            @endcan
                        </article>
                    @empty
                        <p class="empty-row">No configured capabilities.</p>
                    @endforelse
                </div>
            </section>

            <section class="hospital-panel incoming-panel">
                <header class="section-title">
                    <div><p class="eyebrow">Explicit notifications only</p><h3>Incoming</h3></div>
                    <span class="status">{{ $incoming->count() }}</span>
                </header>
                <div class="incoming-list">
                    @forelse($incoming as $notification)
                        <article class="incoming-row">
                            <div class="incoming-priority priority priority-{{ strtolower($notification->emergencyCase->priority->value) }}">{{ $notification->emergencyCase->priority->value }}</div>
                            <div>
                                <strong>{{ $notification->emergencyCase->case_number }}</strong>
                                <span>{{ $notification->emergencyCase->incident_type }}</span>
                                <small>{{ $notification->emergencyCase->encounters_count }} patient(s) · ETA unavailable</small>
                                @if($notification->encounter)<small>Condition {{ $notification->encounter->condition_level->value }}</small>@endif
                            </div>
                            <div class="incoming-action">
                                <span class="state-pill">{{ $notification->status->value }}</span>
                                @if($notification->status->value !== 'ACKNOWLEDGED')
                                    @can('acknowledgeIncoming', $hospital)
                                        <form method="post" action="{{ route('hospital.notifications.acknowledge', $notification) }}">
                                            @csrf
                                            <button class="btn btn-primary" type="submit"><i class="icon" data-lucide="check"></i>Acknowledge</button>
                                        </form>
                                    @endcan
                                @else
                                    <small>{{ $notification->acknowledged_at->timezone($timezone)->format('H:i') }}</small>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="empty-row">No incoming case notifications.</p>
                    @endforelse
                </div>
                <footer class="semantic-note"><i class="icon" data-lucide="shield-alert"></i>Acknowledgement confirms receipt only. It is not clinical acceptance, bed reservation or handover.</footer>
            </section>

            <section class="hospital-panel resources-panel">
                <header class="section-title">
                    <div><p class="eyebrow">Operational abstractions</p><h3>Resources</h3></div>
                    @can('updateResources', $hospital)
                        <details class="command-editor compact">
                            <summary class="btn btn-secondary"><i class="icon" data-lucide="plus"></i>Record resource</summary>
                            <form method="post" action="{{ route('hospital.resource-state.store', [$hospital, $resourceDefinitions->first()?->id ?? 'missing']) }}" data-resource-form>
                                @csrf
                                <input type="hidden" name="expected_version" value="{{ $hospital->version }}">
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                                <input type="hidden" name="source" value="MANUAL">
                                <input type="hidden" name="effective_at" value="{{ now()->utc()->toIso8601String() }}">
                                <label>Resource<select data-resource-select>@foreach($resourceDefinitions as $definition)<option value="{{ route('hospital.resource-state.store', [$hospital, $definition]) }}">{{ $definition->name }}</option>@endforeach</select></label>
                                <label>Status<select name="status">@foreach(['AVAILABLE','LIMITED','FULL','UNAVAILABLE','UNKNOWN'] as $state)<option>{{ $state }}</option>@endforeach</select></label>
                                <label>Total<input type="number" name="total_capacity" min="0"></label>
                                <label>Available<input type="number" name="available_capacity" min="0"></label>
                                <label class="wide">Notes<textarea name="notes"></textarea></label>
                                <button class="btn btn-primary" type="submit"><i class="icon" data-lucide="save"></i>Record</button>
                            </form>
                        </details>
                    @endcan
                </header>
                <div class="resource-grid">
                    @forelse($snapshot['resources'] as $resource)
                        <article>
                            <span>{{ $resource['name'] }}</span>
                            <strong>{{ $resource['available_capacity'] === null ? 'UNKNOWN' : $resource['available_capacity'].' available' }}</strong>
                            <span class="state-pill state-{{ strtolower($resource['status']) }}">{{ $resource['status'] }}</span>
                            <small>{{ $resource['freshness'] }}@if($resource['last_confirmed_at']) · {{ \Carbon\CarbonImmutable::parse($resource['last_confirmed_at'])->diffForHumans() }}@endif</small>
                        </article>
                    @empty
                        <p class="empty-row">No resource reports.</p>
                    @endforelse
                </div>
            </section>

            <section class="hospital-panel restrictions-panel">
                <header class="section-title">
                    <div><p class="eyebrow">Temporary operational limits</p><h3>Restrictions</h3></div>
                    @can('updateAvailability', $hospital)
                        <details class="command-editor compact">
                            <summary class="btn btn-secondary"><i class="icon" data-lucide="plus"></i>Add restriction</summary>
                            <form method="post" action="{{ route('hospital.restrictions.store', $hospital) }}">
                                @csrf
                                <input type="hidden" name="expected_version" value="{{ $hospital->version }}">
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::ulid() }}">
                                <input type="hidden" name="source" value="MANUAL">
                                <input type="hidden" name="effective_at" value="{{ now()->utc()->toIso8601String() }}">
                                <label>Reason<select name="reason_code" required>@foreach(['CAPACITY','STAFFING','EQUIPMENT','MAINTENANCE','TEMPORARY_RESTRICTION','INTERNAL_INCIDENT','OTHER'] as $reason)<option>{{ $reason }}</option>@endforeach</select></label>
                                <label>Title<input name="title" maxlength="160" required></label>
                                <label class="wide">Description<textarea name="description"></textarea></label>
                                <button class="btn btn-primary" type="submit"><i class="icon" data-lucide="save"></i>Record</button>
                            </form>
                        </details>
                    @endcan
                </header>
                <div class="restriction-list">
                    @forelse($snapshot['restrictions'] as $restriction)
                        <article><i class="icon" data-lucide="triangle-alert"></i><div><strong>{{ $restriction['title'] }}</strong><small>{{ str_replace('_',' ',$restriction['reason_code']) }}@if($restriction['expires_at']) · expires {{ \Carbon\CarbonImmutable::parse($restriction['expires_at'])->diffForHumans() }}@endif</small><p>{{ $restriction['description'] }}</p></div></article>
                    @empty
                        <p class="empty-row">No active operational restrictions.</p>
                    @endforelse
                </div>
            </section>

            <section class="hospital-panel activity-panel">
                <header class="section-title"><div><p class="eyebrow">Append-only reports</p><h3>Recent activity</h3></div><span class="status">History</span></header>
                <div class="hospital-activity">
                    @forelse($history as $item)
                        <article><time>{{ $item['at']->timezone($timezone)->format('H:i') }}</time><div><strong>{{ str_replace('_',' ',$item['label']) }}</strong>@if($item['detail'])<small>{{ $item['detail'] }}</small>@endif</div></article>
                    @empty
                        <p class="empty-row">No operational history.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
</div>
@endsection