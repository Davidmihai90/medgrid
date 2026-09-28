# MEDGRID v0.2.0 — M1 Dispatch

## 1. Purpose

M1 introduces the first operational MEDGRID workflow:

**Emergency intake → EmergencyCase → Dispatch triage → Ambulance assignment → Realtime mission delivery**

The objective is to build a reliable real-time dispatch foundation without implementing the medical workflows belonging to later milestones.

M1 must transform MEDGRID from an infrastructure foundation into an operational dispatch platform.

The system must remain:

- server authoritative;
- organization isolated;
- realtime capable;
- auditable;
- concurrency safe;
- explainable;
- resilient to connection loss;
- usable from an operational dispatch workstation.

M1 must not implement clinical decision support.

---

# 2. Milestone Boundary

M1 implements:

- emergency case creation;
- emergency intake;
- dispatch-oriented case lifecycle;
- priority classification;
- incident location;
- dispatcher notes;
- vehicles;
- vehicle operational status;
- crew assignment foundation;
- ambulance assignment to emergency cases;
- reassignment;
- assignment cancellation;
- case timeline;
- realtime dispatch updates;
- realtime mission delivery;
- dispatch command center;
- active case monitoring;
- basic dispatch metrics;
- authorization;
- audit;
- concurrency protection;
- synthetic demonstration data;
- automated tests.

M1 does NOT implement:

- patient clinical records;
- vital signs;
- medical assessments;
- diagnosis;
- treatment recommendations;
- medications;
- destination recommendation;
- hospital capability evaluation;
- hospital capacity;
- live GPS tracking;
- route calculation;
- traffic;
- offline clinical synchronization;
- Incident Room;
- chat;
- PTT;
- WebRTC;
- hospital handover;
- real 112 integration;
- AI decision support.

Those belong to later milestones.

---

# 3. Core Workflow

The M1 operational flow is:

```text
Emergency received
        ↓
EmergencyCase created
        ↓
Dispatcher reviews case
        ↓
Priority assigned
        ↓
Location confirmed
        ↓
Available units evaluated by dispatcher
        ↓
Ambulance assigned
        ↓
Assignment persisted
        ↓
Realtime notification sent
        ↓
Ambulance receives mission
        ↓
Assignment acknowledged/accepted
        ↓
Dispatch continues monitoring
```

The dispatcher remains responsible for operational decisions.

MEDGRID does not autonomously decide which ambulance must be dispatched.

---

# 4. EmergencyCase

`EmergencyCase` becomes a primary MEDGRID aggregate.

Suggested fields:

```text
id
public_id
case_number

organization_id

source
external_reference nullable

status
priority

incident_type nullable

caller_name nullable
caller_phone nullable

location_text
latitude nullable
longitude nullable

location_accuracy nullable

summary
dispatcher_notes nullable

received_at
triaged_at nullable
assigned_at nullable
closed_at nullable

created_by
updated_by

created_at
updated_at
```

Exact implementation may evolve if the existing architecture requires it.

---

# 5. Case Identifiers

Every emergency case must have:

1. internal database identifier;
2. public technical identifier;
3. human-readable operational case number.

Example:

`MG-2026-000184`

The human-readable number must be:

- unique;
- generated server-side;
- concurrency safe;
- immutable;
- suitable for display and verbal communication.

Do not generate it by counting existing records.

Sequence generation must remain safe under concurrent case creation.

---

# 6. Emergency Source

M1 must support at minimum:

```text
MANUAL
SIMULATION
```

Architecture must allow future sources such as:

```text
112_INTEGRATION
HOSPITAL_TRANSFER
OTHER_SYSTEM
```

but these future integrations must NOT be implemented.

Never claim MEDGRID is currently connected to Romanian 112 infrastructure.

---

# 7. Case Status

Use explicit state transitions.

Initial M1 lifecycle:

```text
RECEIVED
TRIAGED
UNIT_ASSIGNED
UNIT_ACCEPTED
EN_ROUTE_TO_SCENE
ON_SCENE
CANCELLED
CLOSED
```

M1 primarily owns:

```text
RECEIVED
TRIAGED
UNIT_ASSIGNED
UNIT_ACCEPTED
```

`EN_ROUTE_TO_SCENE` and `ON_SCENE` may exist as foundational operational states if required for coherent dispatch monitoring, but do not implement medical workflows around them.

Later milestones will extend the lifecycle.

---

# 8. State Machine

Do NOT allow arbitrary:

```php
$case->status = ...
```

from controllers or forms.

Transitions must be explicit domain operations.

Examples:

```text
ReceiveEmergencyCase
TriageEmergencyCase
AssignVehicleToCase
AcceptMissionAssignment
CancelVehicleAssignment
ReassignVehicle
CancelEmergencyCase
CloseEmergencyCase
```

Every transition must validate:

- current state;
- actor permission;
- organization context;
- required information;
- concurrency state.

Invalid transitions must fail explicitly.

---

# 9. Priority

M1 must implement an operational priority model without pretending to encode official medical triage protocols.

Initial generic priorities:

```text
P1 — Critical
P2 — High
P3 — Moderate
P4 — Low
P5 — Non-Urgent
UNKNOWN
```

These are MEDGRID operational/demo classifications until formally configured and clinically/governmentally validated.

Do not claim these correspond exactly to official Romanian emergency classification.

Priority must be:

- visually prominent;
- represented by text/icon as well as color;
- auditable when changed.

Changing priority should create an operational timeline event.

---

# 10. Incident Type

M1 may support configurable generic incident categories.

Examples for synthetic/demo use:

```text
MEDICAL
TRAUMA
TRAFFIC_ACCIDENT
FIRE_RELATED
PUBLIC_INCIDENT
TRANSFER_REQUEST
OTHER
UNKNOWN
```

Do not encode diagnostic conclusions.

---

# 11. Incident Location

M1 stores incident location.

Required:

```text
location_text
```

Optional when available:

```text
latitude
longitude
location_accuracy
```

If PostGIS is already prepared, use appropriate spatial modeling where justified.

However:

M1 does NOT implement:

- live routing;
- traffic;
- navigation;
- ambulance GPS tracking.

Do not calculate fake road ETA from straight-line distance.

---

# 12. Case Timeline

Every EmergencyCase has an append-oriented operational timeline.

Use a persistent `CaseEvent` or equivalent model.

Suggested fields:

```text
id
public_id

organization_id
emergency_case_id

event_type
event_version

actor_type
actor_id

occurred_at

summary
metadata

correlation_id

created_at
```

Timeline records should normally be immutable.

---

# 13. Timeline Events

Initial events may include:

```text
EmergencyCaseCreated
EmergencyCaseTriaged
CasePriorityChanged
CaseLocationUpdated

VehicleAssigned
VehicleAssignmentCancelled
VehicleReassigned

MissionDelivered
MissionAccepted

CaseCancelled
CaseClosed
```

Events must describe facts that occurred.

Do not create timeline entries merely because a page was viewed.

---

# 14. Timeline Presentation

Dispatchers must be able to see a chronological case timeline.

Example:

```text
18:41:02
Emergency case received

18:41:19
Priority changed to P1 — Critical
by Dispatcher Demo

18:42:03
Ambulance B-142 assigned
by Dispatcher Demo

18:42:04
Mission delivered to B-142

18:42:31
Mission accepted
```

The timeline must distinguish:

- human actions;
- system events;
- assignment events;
- status changes.

---

# 15. Vehicles

Introduce the operational `Vehicle` model.

Suggested fields:

```text
id
public_id

organization_id

callsign
registration_number nullable

vehicle_type
status

display_name

active

created_at
updated_at
```

Example callsigns:

```text
B-142
B-317
SMURD-21
AMB-08
```

Use synthetic values in seed data.

---

# 16. Vehicle Types

M1 may use configurable/general types such as:

```text
AMBULANCE
ADVANCED_AMBULANCE
RAPID_RESPONSE
OTHER
```

Do not claim these map exactly to official Romanian ambulance classifications unless later configured from authoritative data.

---

# 17. Vehicle Operational Status

Initial operational states:

```text
OFFLINE
AVAILABLE
RESERVED
ASSIGNED
EN_ROUTE_SCENE
ON_SCENE
UNAVAILABLE
```

Later milestones may add:

```text
TRANSPORTING
AT_HOSPITAL
```

Do not introduce those workflows in M1 unless required only as enum compatibility.

---

# 18. Vehicle Availability

A dispatcher may assign only an eligible operational vehicle.

At minimum:

- active;
- same authorized organization/context;
- status allows assignment;
- no incompatible active assignment.

Availability must be enforced server-side.

Do not rely on a disabled UI button as the security/concurrency mechanism.

---

# 19. Crew Foundation

M1 introduces the minimum structure necessary to associate personnel with a vehicle.

Suggested:

`VehicleCrewAssignment`

Fields may include:

```text
id
vehicle_id
user_id
organization_id
role
started_at
ended_at nullable
```

The purpose is to know which authenticated operational users belong to the current vehicle crew.

Do not implement medical crew workflows yet.

---

# 20. Vehicle Assignment

Create a dedicated assignment model.

Suggested:

`CaseVehicleAssignment`

Fields:

```text
id
public_id

organization_id
emergency_case_id
vehicle_id

status

assigned_by
assigned_at

acknowledged_by nullable
acknowledged_at nullable

cancelled_by nullable
cancelled_at nullable
cancellation_reason nullable

created_at
updated_at
```

---

# 21. Assignment States

Suggested:

```text
PENDING
DELIVERED
ACCEPTED
CANCELLED
COMPLETED
```

Exact implementation may differ if a cleaner domain representation is identified.

Do not confuse:

case status

with:

assignment status

with:

vehicle status.

These are separate concepts.

---

# 22. Assignment Transaction

Vehicle assignment is a critical transaction.

The following must behave atomically:

1. verify case;
2. verify case organization;
3. verify vehicle;
4. verify vehicle organization;
5. verify vehicle availability;
6. verify no incompatible assignment exists;
7. create assignment;
8. update vehicle operational state;
9. transition case state;
10. append case event;
11. create audit record;
12. dispatch domain event after commit.

A partial assignment state is unacceptable.

---

# 23. Concurrency

Assume multiple dispatchers may operate simultaneously.

Example race:

Dispatcher A selects B-142.

Dispatcher B also selects B-142.

Both click Assign within milliseconds.

Only one assignment may succeed.

Use appropriate:

- database transactions;
- constraints;
- row-level locking;
- optimistic concurrency;
- or equivalent safe mechanisms.

The losing dispatcher must receive a clear operational message:

```text
B-142 is no longer available.
Refresh/select another unit.
```

Do not silently overwrite assignments.

---

# 24. Reassignment

Dispatchers with permission may reassign a case.

Reassignment must preserve history.

Never overwrite the previous assignment as though it never existed.

Expected conceptual behavior:

```text
Old assignment → CANCELLED
New assignment → created
Vehicle states updated
Case timeline appended
Audit appended
Realtime updates broadcast
```

A reassignment reason should be supported.

---

# 25. Assignment Cancellation

Cancellation requires:

- authorization;
- reason;
- safe state validation.

Cancellation must:

- preserve assignment history;
- update vehicle availability when appropriate;
- append timeline;
- audit;
- broadcast state change.

---

# 26. Mission Acceptance

M1 includes the minimum mission acknowledgement workflow.

An authenticated crew member assigned to the relevant vehicle may acknowledge/accept the mission.

Acceptance must verify:

- assignment exists;
- assignment belongs to current organization;
- user is authorized;
- user belongs to active crew where required;
- assignment is still active;
- mission has not been cancelled/reassigned.

Acceptance should:

- update assignment;
- transition case appropriately;
- append timeline;
- audit;
- broadcast.

This is operational acknowledgement only.

Do not implement patient assessment.

---

# 27. Dispatch Command Center

Create the first major operational MEDGRID interface:

# Dispatch Command Center

Route:

`/dispatch`

This must NOT look like a generic CRUD dashboard.

It should feel like a real operational command interface.

---

# 28. Dispatch Layout

Recommended desktop structure:

```text
┌─────────────────────────────────────────────────────────────┐
│ MEDGRID   DISPATCH      Organization       LIVE      User  │
├─────────────────┬──────────────────────────┬────────────────┤
│                 │                          │                │
│ ACTIVE CASES    │ CASE / INCIDENT VIEW     │ UNITS          │
│                 │                          │                │
│ P1              │ MG-2026-000184           │ AVAILABLE      │
│ P2              │ Priority                 │ B-142          │
│ P3              │ Location                 │ B-317          │
│                 │ Summary                  │                │
│                 │ Timeline                 │ ASSIGNED       │
│                 │                          │ AMB-08         │
│                 │ [ASSIGN UNIT]            │                │
│                 │                          │                │
└─────────────────┴──────────────────────────┴────────────────┘
```

This is conceptual, not pixel-binding.

Optimize for:

- fast scanning;
- minimal navigation;
- large operational information;
- multiple simultaneous cases;
- clear unit availability;
- realtime updates.

---

# 29. Active Case List

Each case card/row should prominently display:

- case number;
- priority;
- status;
- incident type;
- location;
- time since received;
- assigned vehicle if any;
- important realtime state.

Sort primarily by operational relevance.

Do not hide critical cases because of pagination defaults.

---

# 30. Case Detail Panel

Selecting a case should provide:

- case number;
- priority;
- status;
- source;
- received time;
- incident type;
- location;
- summary;
- assigned vehicle;
- timeline;
- relevant dispatcher actions.

Avoid unnecessary page navigation during active operations.

---

# 31. Unit Panel

The unit panel should display vehicles grouped by operational state.

Examples:

```text
AVAILABLE
RESERVED
ASSIGNED
OFFLINE
UNAVAILABLE
```

Each vehicle should show:

- callsign;
- type;
- state;
- crew summary where permitted;
- current assignment where applicable.

No GPS position yet.

---

# 32. New Emergency Case

Provide an efficient dispatch-oriented intake workflow.

Required information should be intentionally minimal.

Possible fields:

```text
source
priority
incident type
location
caller information
summary
dispatcher notes
```

Avoid forcing information that may not yet be known.

Emergency cases must support incomplete information.

---

# 33. Unknown Information

Emergency systems frequently operate with incomplete information.

Do not fake missing values.

Use explicit states such as:

```text
Unknown caller
Unknown incident type
Location being confirmed
Priority pending
```

Do not use fabricated defaults that look authoritative.

---

# 34. Realtime Channels

M1 introduces:

```text
dispatch.{organizationId}
case.{caseId}
vehicle.{vehicleId}
```

All protected operational channels must be private or presence channels as appropriate.

Authorization must be server-side.

Cross-organization subscriptions must fail.

---

# 35. Realtime Events

Potential M1 broadcast events:

```text
EmergencyCaseCreated
EmergencyCaseUpdated
EmergencyCaseTriaged
CasePriorityChanged

VehicleStatusChanged
VehicleAssigned
VehicleAssignmentCancelled
VehicleReassigned

MissionDelivered
MissionAccepted
```

Do not broadcast full Eloquent models.

Use explicit minimal payloads.

---

# 36. Realtime Recovery

Realtime is not the source of truth.

After reconnect:

1. reauthenticate;
2. rejoin authorized channels;
3. reload authoritative state;
4. reconcile the UI.

A missed WebSocket message must not permanently corrupt the dispatch screen.

---

# 37. Mission Delivery

Assignment persistence and realtime delivery are separate facts.

A successful database assignment does NOT mean the vehicle client received the WebSocket event.

The system should distinguish where practical:

```text
ASSIGNED
DELIVERED
ACCEPTED
```

Do not claim delivery merely because broadcast dispatch was attempted.

---

# 38. Ambulance Mission Surface

M1 may add a minimal mission reception surface under:

`/ambulance`

Only enough to:

- display assigned mission;
- show case number;
- priority;
- incident location;
- dispatch summary;
- acknowledge/accept mission.

Do NOT implement:

- patient;
- vitals;
- assessments;
- destination;
- navigation;
- medical workflows.

Those belong to M2+.

---

# 39. Notifications

Critical assignment information should use MEDGRID realtime/in-app mechanisms.

Email must not be required for emergency mission delivery.

M1 may create persistent operational notifications if consistent with the existing notification architecture.

---

# 40. Permissions

Add granular M1 permissions.

Suggested:

```text
cases.view
cases.create
cases.update
cases.triage
cases.cancel
cases.close

vehicles.view
vehicles.manage

crew.view
crew.manage

assignments.view
assignments.create
assignments.cancel
assignments.reassign
assignments.accept
```

Exact naming may be adjusted for consistency with M0 conventions.

Do not use role names directly as the primary authorization mechanism.

---

# 41. Authorization

Policies must protect:

- EmergencyCase;
- Vehicle;
- VehicleCrewAssignment;
- CaseVehicleAssignment;
- timeline access;
- assignment actions;
- mission acceptance.

Organization context must be enforced server-side.

---

# 42. Cross-Organization Security

Mandatory tests must prove:

Organization A cannot:

- read Organization B cases;
- update Organization B cases;
- subscribe to Organization B dispatch channel;
- subscribe to Organization B case channels;
- assign Organization B vehicles;
- accept Organization B missions;
- read Organization B timeline.

This is mandatory.

---

# 43. Audit

Audit at minimum:

- emergency case creation;
- priority changes;
- meaningful case edits;
- assignment;
- reassignment;
- assignment cancellation;
- mission acceptance;
- case cancellation;
- case closure;
- administrative vehicle changes.

Audit and case timeline remain separate concepts.

---

# 44. Case Timeline vs Audit

Case timeline answers:

> What operationally happened to this emergency?

Audit answers:

> Who performed or attempted sensitive system actions?

Do not merge them into one table merely because both contain timestamps.

---

# 45. Dispatcher Notes

Dispatcher notes may contain sensitive operational information.

Apply:

- authorization;
- validation;
- safe rendering;
- no unnecessary logs;
- no URL/query-string exposure.

Do not broadcast notes globally unless the recipient actually requires them.

---

# 46. API

Extend `/api/v1`.

Potential resources:

```text
GET    /api/v1/cases
POST   /api/v1/cases

GET    /api/v1/cases/{case}
PATCH  /api/v1/cases/{case}

GET    /api/v1/cases/{case}/timeline

POST   /api/v1/cases/{case}/triage

POST   /api/v1/cases/{case}/assignments
POST   /api/v1/cases/{case}/assignments/{assignment}/cancel
POST   /api/v1/cases/{case}/assignments/{assignment}/accept

GET    /api/v1/vehicles
GET    /api/v1/vehicles/{vehicle}
```

Exact route structure may adapt to existing M0 conventions.

Use:

- Form Requests;
- API Resources;
- policies;
- consistent errors;
- idempotency where appropriate.

---

# 47. Idempotency

Critical commands should tolerate duplicate client submissions where practical.

Especially consider:

- case creation;
- vehicle assignment;
- mission acceptance.

A double-click must not create duplicate assignments.

---

# 48. Operational Error Messages

Errors must be understandable to dispatch personnel.

Prefer:

```text
Unit B-142 was assigned by another dispatcher.
```

over:

```text
SQLSTATE[23505]
```

Internal details belong in secure logs.

---

# 49. Database Integrity

Use appropriate:

- foreign keys;
- unique constraints;
- indexes;
- check constraints where useful;
- organization-aware indexes.

Consider indexes for:

```text
organization + status
organization + priority
organization + received_at
organization + vehicle status
case + assignment status
vehicle + assignment status
case timeline ordering
```

Do not add indexes blindly; verify expected query patterns.

---

# 50. Timestamps

Persist canonical timestamps in UTC.

Display according to organization timezone.

For events received from clients or integrations later, distinguish:

```text
occurred_at
received_at
```

where relevant.

---

# 51. Realtime Performance

Avoid broadcasting every insignificant database change.

Broadcast operational facts.

Avoid giant payloads.

Avoid organization-wide events when only a case-specific audience needs them.

---

# 52. Visual Priority

Priority indicators must not rely only on color.

Example:

```text
P1
CRITICAL
```

with visual emphasis.

Accessibility remains mandatory.

---

# 53. Realtime State

Continue using the M0 connection indicator:

```text
LIVE
RECONNECTING
DEGRADED
OFFLINE
```

Dispatchers must be able to tell when displayed information may not be realtime.

---

# 54. Degraded Realtime Mode

If Reverb is unavailable but the database/API remains operational:

- do not pretend realtime is LIVE;
- allow safe persistent actions where appropriate;
- show DEGRADED;
- refresh authoritative state through API/navigation;
- do not lose persisted assignments.

---

# 55. Database Failure

If the authoritative database is unavailable:

critical state-changing operations must fail safely.

Do not queue an emergency assignment locally and display it as confirmed when the server has not persisted it.

---

# 56. Redis Failure

Redis failure must not silently create inconsistent assignments.

Database transactional integrity remains authoritative.

Report degraded infrastructure through existing health mechanisms.

---

# 57. Synthetic Demo Scenario

Create a useful synthetic dispatch environment.

Example:

Organization:

`MEDGRID Bucharest Demo EMS`

Vehicles:

```text
B-142
B-317
AMB-08
RR-21
```

Mix statuses:

```text
AVAILABLE
AVAILABLE
ASSIGNED
OFFLINE
```

Create several synthetic emergency cases representing different priorities/states.

All people, telephone numbers, addresses and incident data must be fictional/demo data.

---

# 58. Demo Safety

The UI must clearly indicate non-production/demo environments using the M0 environment indicator.

Do not create demo data that could be mistaken for a real emergency.

---

# 59. Automated Tests

M1 requires substantial automated testing.

At minimum:

## Case tests

- authorized case creation;
- unauthorized creation denied;
- unique case number generation;
- organization isolation;
- valid transition;
- invalid transition rejected;
- priority change timeline event.

## Vehicle tests

- organization-scoped vehicle visibility;
- inactive vehicle cannot be assigned;
- unavailable vehicle cannot be assigned.

## Assignment tests

- available vehicle assignment succeeds;
- unavailable vehicle assignment fails;
- cross-organization assignment fails;
- duplicate assignment fails;
- assignment updates case;
- assignment updates vehicle;
- timeline event created;
- audit created.

## Concurrency tests

Prove or meaningfully test the concurrency mechanism preventing incompatible double assignment.

## Cancellation tests

- authorized cancellation succeeds;
- cancellation reason preserved;
- vehicle released when appropriate;
- timeline preserved.

## Reassignment tests

- old assignment remains historical;
- new assignment created;
- vehicle states correct;
- timeline correct.

## Mission acceptance

- assigned crew member can accept;
- unrelated user cannot accept;
- cancelled assignment cannot be accepted;
- duplicate acceptance is safely handled.

## Realtime authorization

- dispatch organization isolation;
- case channel isolation;
- vehicle channel isolation.

## API

Test authorization, validation and organization isolation for new API endpoints.

---

# 60. Browser / UI Testing

Verify the Dispatch Command Center on:

- desktop;
- common laptop resolution;
- tablet-width layout.

The primary dispatch experience is desktop/laptop.

The ambulance mission surface must be tablet-friendly.

---

# 61. Performance

Avoid N+1 queries on:

- active case list;
- vehicle panel;
- case timeline;
- assignments.

Use pagination where appropriate for historical data.

Active operational data may require bounded realtime views rather than conventional CRUD pagination.

---

# 62. Observability

Extend M0 observability for:

- case creation failures;
- assignment failures;
- concurrency conflicts;
- realtime delivery failures;
- queue failures;
- unauthorized operational actions.

Use correlation IDs.

Do not log sensitive case content unnecessarily.

---

# 63. Documentation

Update README only where setup or operational development commands change.

Document important M1 architecture decisions.

If a significant architectural decision is made, add an ADR under:

`docs/adr/`

Do not rewrite MASTER_SPEC casually.

---

# 64. Migration Safety

M1 migrations must be forward-safe.

Do not destroy M0 data.

Do not use development shortcuts that require resetting the database for ordinary upgrades.

`migrate:fresh --seed` may still be used only for explicit development/test verification against confirmed MEDGRID development databases.

---

# 65. M1 Acceptance Criteria

M1 is complete only when all of the following are true:

### Emergency Cases

- case can be created;
- case number is concurrency-safe;
- priority/status/location work;
- state transitions are explicit;
- timeline persists operational facts.

### Vehicles

- vehicles exist as organization-scoped resources;
- operational status works;
- availability is server-enforced;
- crew foundation exists.

### Dispatch

- dispatcher sees active cases;
- dispatcher sees units;
- dispatcher can inspect a case;
- dispatcher can assign a valid unit;
- invalid assignment is rejected;
- reassignment works;
- cancellation works.

### Mission

- assigned crew can receive mission information;
- assignment is delivered realtime where connection exists;
- authorized crew can accept;
- acceptance updates authoritative state.

### Concurrency

- two dispatchers cannot successfully create incompatible simultaneous assignments for the same vehicle.

### Realtime

- dispatch channel works;
- case channel works;
- vehicle channel works;
- unauthorized cross-org subscriptions fail;
- reconnect can recover authoritative state.

### Security

- organization isolation verified;
- policies verified;
- permissions verified;
- sensitive actions audited.

### Quality

- migrations succeed;
- seeders succeed;
- tests pass;
- frontend build passes;
- formatting passes;
- no secrets committed;
- no M2 functionality implemented.

---

# 66. Verification

Before completion run all relevant checks.

At minimum:

```text
php artisan test
npm run build
Laravel Pint
composer audit
npm audit
```

Verify:

- PostgreSQL;
- PostGIS;
- Redis;
- queue;
- Reverb;
- realtime authorization;
- assignment concurrency;
- synthetic seed environment.

Do not invent verification results.

---

# 67. Git

Before completion:

```text
git status
git diff
```

Review:

- scope;
- secrets;
- accidental files;
- debug code;
- unrelated changes.

Do not automatically commit.

Do not tag.

Do not push.

Suggested commit after owner review:

`feat: MEDGRID v0.2.0 Dispatch`

Suggested tag:

`v0.2.0`

---

# 68. Completion Report

At M1 completion report:

## Implemented

## Domain Model

## Emergency Case Lifecycle

## Vehicle / Crew Model

## Assignment Model

## Concurrency Strategy

## Dispatch UI

## Ambulance Mission Surface

## Realtime

## Authorization

## Audit / Timeline

## Database / Migrations

## API

## Tests

Provide exact test results.

## Build / Quality

Provide exact results.

## Known Limitations

## Security Review

## Git Status

## M1 Acceptance

Explicitly state whether every M1 acceptance criterion is satisfied.

Then STOP.

Do not begin M2.

---

# 69. M1 Forbidden Shortcuts

Do not:

- implement arbitrary case status editing;
- assign unavailable vehicles;
- rely only on frontend availability;
- overwrite assignment history;
- merge audit and timeline;
- fake realtime delivery;
- fabricate ETA;
- implement fake GPS;
- invent official medical protocols;
- create patient clinical workflows;
- implement autonomous dispatch;
- introduce AI dispatch recommendations;
- bypass organization authorization;
- expose entire Eloquent models over WebSockets;
- silently resolve assignment races using last-write-wins;
- begin M2.

---

# 70. M1 Final Rule

M1 is not a CRUD milestone.

It establishes the operational integrity of MEDGRID dispatch.

Prioritize:

**correct state → safe concurrency → authorization → persistence → realtime → operational UX**

over feature quantity.

The core invariant is:

> A dispatcher must always be able to distinguish what MEDGRID knows, what MEDGRID does not know, what has actually been persisted, which ambulance is truly assigned, and whether the displayed operational state is currently realtime.

When uncertain, preserve operational truth rather than creating the appearance of certainty.