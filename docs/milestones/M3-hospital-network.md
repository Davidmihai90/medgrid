# MEDGRID v0.4.0 — M3 Hospital Network

## 1. Purpose

M3 introduces the hospital side of MEDGRID.

The objective is to create a reliable operational representation of hospitals, their stable capabilities, their dynamic availability and their ability to receive incoming emergency cases.

The primary concept is:

```text
Hospital identity
        ↓
Departments / services
        ↓
Capabilities
        ↓
Operational availability
        ↓
Resource state
        ↓
Receiving status
        ↓
Incoming emergency visibility
```

M3 creates the authoritative hospital network foundation required by the future Destination Support Engine.

M3 must NOT implement destination recommendation or autonomous hospital selection.

---

# 2. Core Architectural Rule

The most important M3 invariant is:

```text
CAPABILITY ≠ AVAILABILITY
```

A hospital may HAVE a capability without that capability being AVAILABLE right now.

Example:

```text
Hospital:
Neurosurgery capability: YES

Current operational availability:
Neurosurgery receiving: NO
Reason: operating capacity unavailable
```

These facts must never be represented by one ambiguous boolean.

---

# 3. Milestone Scope

M3 implements:

- hospital organizations/entities;
- hospital operational profiles;
- departments/services;
- hospital capabilities;
- capability configuration;
- dynamic availability;
- receiving status;
- resource categories;
- resource availability;
- manual operational updates;
- availability freshness;
- stale-data detection;
- status reasons;
- temporary restrictions;
- hospital command interface;
- incoming emergency visibility;
- hospital acknowledgement foundation;
- hospital-specific realtime;
- authorization;
- audit;
- history;
- API;
- synthetic hospital network;
- observability;
- tests.

---

# 4. M3 Exclusions

Do NOT implement:

- destination recommendation;
- hospital ranking;
- automatic destination selection;
- travel-time scoring;
- traffic;
- routing;
- autonomous dispatch;
- AI clinical reasoning;
- AI hospital selection;
- hospital handover;
- bed reservation workflow;
- full hospital information system integration;
- real national healthcare integration;
- Incident Room;
- PTT/WebRTC;
- billing;
- EHR/EMR;
- laboratory systems;
- pharmacy systems.

These belong to later milestones or external systems.

---

# 5. Hospital Model

Introduce an explicit Hospital domain model.

Suggested fields:

```text
id
public_id

organization_id

code
name
short_name nullable

status

address
latitude nullable
longitude nullable

timezone

phone nullable

active

created_at
updated_at
```

Exact fields should follow existing MEDGRID conventions.

---

# 6. Hospital Identity vs Organization

Do not automatically assume:

```text
Organization = Hospital
```

MEDGRID organizations may represent:

- EMS organizations;
- hospital networks;
- individual hospitals;
- regional operators;
- future administrative structures.

Hospital must therefore be an explicit domain entity.

A single organization may potentially manage multiple hospitals.

---

# 7. Hospital Status

Suggested high-level administrative states:

```text
ACTIVE
INACTIVE
MAINTENANCE
```

Do not use this administrative status as the dynamic receiving state.

Hospital operational receiving status is separate.

---

# 8. Hospital Department

Model hospital departments/services explicitly.

Suggested fields:

```text
id
public_id

organization_id
hospital_id

code
name
description nullable

active

created_at
updated_at
```

Examples in synthetic/demo data may include:

```text
Emergency Department
Cardiology
Neurology
Neurosurgery
General Surgery
Orthopedics
Pediatrics
Obstetrics
Burn Care
Intensive Care
```

Do not claim these demo departments represent official Romanian hospital classification.

---

# 9. Capability Model

Hospital capabilities describe what the institution is structurally/equipment/staff configured to provide.

Capabilities change relatively infrequently.

Examples:

```text
EMERGENCY_DEPARTMENT
TRAUMA
CARDIOLOGY
NEUROLOGY
NEUROSURGERY
GENERAL_SURGERY
ORTHOPEDICS
PEDIATRICS
OBSTETRICS
BURN_CARE
INTENSIVE_CARE
CT
MRI
CATH_LAB
```

This list is a MEDGRID operational taxonomy.

Do not claim it is an official Romanian medical classification unless validated later.

---

# 10. Capability Definition

Prefer a reusable capability catalog rather than arbitrary free-text capabilities.

Conceptually:

```text
CapabilityDefinition
HospitalCapability
```

CapabilityDefinition defines the meaning.

HospitalCapability associates it with a hospital/department.

---

# 11. HospitalCapability

Suggested fields:

```text
id
public_id

organization_id
hospital_id
department_id nullable
capability_definition_id

enabled

notes nullable

effective_from nullable
effective_until nullable

created_at
updated_at
```

`enabled = true` means the hospital has/configures the capability.

It does NOT mean it is currently available.

---

# 12. Capability Availability

Dynamic operational state belongs separately.

Conceptually:

```text
HospitalCapabilityAvailability
```

Suggested fields:

```text
id
public_id

organization_id
hospital_id
hospital_capability_id

status

reason_code nullable
reason_text nullable

effective_at
expires_at nullable

reported_by
source

created_at
```

Prefer append-oriented availability records/history where practical.

---

# 13. Availability States

Suggested operational states:

```text
AVAILABLE
LIMITED
UNAVAILABLE
UNKNOWN
```

Never turn missing availability information into AVAILABLE.

Default unknown truth is:

`UNKNOWN`.

---

# 14. Receiving Status

A hospital also requires a high-level receiving state.

Suggested:

```text
OPEN
LIMITED
NOT_RECEIVING
UNKNOWN
```

This is separate from individual capability availability.

Example:

```text
Hospital receiving: OPEN

Neurosurgery:
UNAVAILABLE

CT:
AVAILABLE
```

---

# 15. Receiving State Model

Use an explicit append-oriented operational record such as:

```text
HospitalReceivingStatus
```

Suggested fields:

```text
id
public_id

organization_id
hospital_id

status

reason_code nullable
reason_text nullable

effective_at
expires_at nullable

reported_by
source

created_at
```

Current status should be derived/projected from latest valid authoritative state.

Preserve history.

---

# 16. Operational History

Do not overwrite hospital availability history.

If availability changes:

```text
AVAILABLE
    ↓
LIMITED
    ↓
UNAVAILABLE
    ↓
AVAILABLE
```

the history must remain queryable.

Future M4 needs to know what MEDGRID knew at a specific time.

---

# 17. Availability Provenance

Every dynamic availability record must preserve:

- who reported it;
- when it became effective;
- when MEDGRID received it;
- source;
- reason where appropriate.

Future integrations may supply automated status.

M3 initially supports manual/synthetic sources.

---

# 18. Availability Source

Suggested:

```text
MANUAL
SIMULATION
INTEGRATION
```

Do not use `INTEGRATION` unless an actual integration exists.

Synthetic seed data should use:

`SIMULATION`.

---

# 19. Freshness

Operational availability becomes less trustworthy with age.

MEDGRID must track freshness.

For each dynamic status, retain timestamps sufficient to determine:

```text
FRESH
AGING
STALE
UNKNOWN
```

Do not silently treat stale information as current.

---

# 20. Freshness Policy

Do not invent clinically authoritative expiration periods.

M3 should provide configurable operational freshness thresholds.

Example configuration may exist for demo/testing, clearly labelled as MEDGRID operational configuration.

Do not claim the values are official medical standards.

---

# 21. Stale State

When information exceeds configured freshness:

the UI must indicate:

`STALE`

rather than silently continuing to display it as current.

The underlying last reported value may still be visible with its age.

Example:

```text
CT: AVAILABLE
Last confirmed 47 minutes ago
STALE
```

---

# 22. Expiring Status

Availability updates may include:

`expires_at`.

After expiration:

do not continue treating the record as current.

Current projection becomes UNKNOWN or falls back according to an explicitly documented safe rule.

Never automatically assume AVAILABLE.

---

# 23. Reasons

Availability and receiving changes should support structured reason codes plus optional text.

Possible synthetic operational reason taxonomy:

```text
CAPACITY
STAFFING
EQUIPMENT
MAINTENANCE
TEMPORARY_RESTRICTION
INTERNAL_INCIDENT
OTHER
```

Do not encode diagnosis or patient-specific decisions into these reason codes.

---

# 24. Resource Model

M3 introduces operational hospital resources.

Do not attempt to build a complete hospital inventory system.

Focus only on resources relevant to emergency receiving awareness.

---

# 25. Resource Definition

Conceptually:

```text
HospitalResourceDefinition
HospitalResourceState
```

Potential resource categories:

```text
EMERGENCY_BEDS
ICU_BEDS
PEDIATRIC_BEDS
ISOLATION_BEDS
OPERATING_ROOMS
VENTILATOR_CAPACITY
```

These are MEDGRID operational abstractions.

Do not claim exact compatibility with a specific national hospital system.

---

# 26. Resource State

Suggested fields:

```text
id
public_id

organization_id
hospital_id
resource_definition_id

total_capacity nullable
available_capacity nullable

status

effective_at
expires_at nullable

reported_by
source

notes nullable

created_at
```

Preserve historical reports.

---

# 27. Resource Truth

If capacity is unknown:

store UNKNOWN/null.

Do not convert missing values to:

`0`

unless the hospital explicitly reported zero.

`unknown capacity`

and:

`zero available capacity`

are completely different operational facts.

---

# 28. Resource Status

Suggested:

```text
AVAILABLE
LIMITED
FULL
UNAVAILABLE
UNKNOWN
```

Numeric capacity and status may coexist.

Do not automatically derive clinically meaningful conclusions unless rules are explicit and safe.

---

# 29. Hospital Operational Snapshot

Provide a service/query model that produces an authoritative current snapshot:

```text
HospitalOperationalSnapshot
```

Conceptually containing:

- hospital identity;
- receiving status;
- status freshness;
- capabilities;
- capability availability;
- capability freshness;
- resources;
- resource freshness;
- restrictions;
- last updated information.

This is a projection.

Historical tables remain authoritative.

---

# 30. Snapshot Rules

Snapshot generation must be deterministic.

Given the same persisted history and same reference time/configuration, it should produce the same result.

This will become important in M4.

---

# 31. No Destination Recommendation

M3 may expose facts such as:

```text
Hospital A:
Receiving OPEN
CT AVAILABLE
Neurosurgery AVAILABLE
ICU LIMITED
```

It must NOT say:

```text
Recommended destination: Hospital A
```

or:

```text
Hospital A is best for this patient
```

That belongs to M4 and remains constrained by clinical/protocol authority.

---

# 32. Hospital Command

Create:

`/hospital`

as an operational Hospital Command workspace.

Do not build a generic admin CRUD page.

The interface should prioritize:

- hospital receiving status;
- freshness;
- capabilities;
- current availability;
- resources;
- restrictions;
- incoming emergencies;
- realtime state;
- last update information.

---

# 33. Hospital Selection

Users associated with multiple hospitals must operate within an explicit hospital context.

Do not rely on hidden frontend filtering.

Hospital context must be server validated.

---

# 34. Hospital Command Layout

Conceptual desktop layout:

```text
┌─────────────────────────────────────────────────────────┐
│ MEDGRID HOSPITAL COMMAND        LIVE        HOSPITAL A │
├─────────────────────────────────────────────────────────┤
│ RECEIVING                                             │
│ OPEN · confirmed 4 min ago                            │
├────────────────────────────┬────────────────────────────┤
│ CAPABILITIES               │ INCOMING                  │
│                            │                           │
│ CT             AVAILABLE   │ MG-2026-000184            │
│ Neurosurgery   LIMITED     │ P1 · ETA unavailable      │
│ ICU            AVAILABLE   │ Patient condition CRITICAL│
│ Cath Lab       UNAVAILABLE │                           │
├────────────────────────────┼────────────────────────────┤
│ RESOURCES                  │ ACTIVITY                  │
│ ICU beds       3 available │ ...                       │
│ ED beds        LIMITED     │                           │
└────────────────────────────┴────────────────────────────┘
```

Conceptual only.

Do not fabricate ETA if routing data does not exist.

---

# 35. Hospital Operators

Hospital operational users may update:

- receiving status;
- capability availability;
- resource state;
- temporary restrictions.

Authorization must be granular.

---

# 36. Permissions

Suggested M3 permissions:

```text
hospitals.view
hospitals.manage

hospital_departments.view
hospital_departments.manage

hospital_capabilities.view
hospital_capabilities.manage

hospital_availability.view
hospital_availability.update

hospital_resources.view
hospital_resources.update

hospital_incoming.view
hospital_incoming.acknowledge
```

Adapt naming to existing MEDGRID conventions.

---

# 37. Policies

Implement explicit authorization for:

- Hospital;
- HospitalDepartment;
- HospitalCapability;
- CapabilityAvailability;
- ReceivingStatus;
- ResourceState;
- incoming case visibility.

Authorization must enforce:

- organization;
- hospital context;
- permission;
- resource relationship.

---

# 38. Cross-Organization Isolation

Mandatory.

Organization A must not:

- view Organization B hospitals;
- modify Organization B receiving status;
- modify Organization B capabilities;
- modify Organization B resources;
- view protected Organization B hospital operational data;
- subscribe to Organization B hospital realtime channels.

Use tenant-hiding `404` where consistent with existing MEDGRID behavior.

---

# 39. Hospital Context Isolation

Even within the same organization, permissions may eventually restrict users to specific hospitals.

M3 should avoid architecture that assumes every organization member automatically manages every hospital.

Implement hospital assignment/access context if required by the current RBAC design.

---

# 40. Incoming Emergency Cases

M3 introduces hospital visibility into incoming emergency cases.

Do not yet implement destination recommendation.

Incoming cases should only appear when an explicit workflow/action associates the case with the hospital.

---

# 41. Hospital Notification Foundation

Introduce a domain entity such as:

```text
HospitalCaseNotification
```

or equivalent.

This represents:

`MEDGRID has notified Hospital X about Case Y`.

It is NOT equivalent to final handover.

---

# 42. Notification Suggested Fields

```text
id
public_id

organization_id
hospital_id
emergency_case_id
patient_encounter_id nullable

status

notified_at

acknowledged_at nullable
acknowledged_by nullable

cancelled_at nullable

created_by
created_at
updated_at
```

---

# 43. Hospital Notification States

Suggested:

```text
PENDING
DELIVERED
ACKNOWLEDGED
CANCELLED
```

Do not claim DELIVERED merely because a WebSocket event was queued.

Use the same delivery-truth discipline established in M1.

---

# 44. Notification Scope

M3 notification contains only the minimum operational/clinical information authorized for the hospital.

Avoid exposing full ambulance clinical records automatically.

Potential summary:

- case number;
- priority;
- incident type;
- patient count;
- patient condition level;
- selected relevant operational summary;
- transport state if known.

Do not include sensitive identifiers unless required and authorized.

---

# 45. Incoming Case Acknowledgement

Authorized hospital staff may acknowledge receipt.

Acknowledgement means:

`Hospital operator has seen/acknowledged the notification.`

It does NOT mean:

- patient accepted clinically;
- bed reserved;
- destination medically approved;
- handover completed.

Keep semantics precise.

---

# 46. Destination Relationship

M3 may provide the technical foundation for associating an incoming case with a hospital.

However:

the dispatcher/medical workflow remains responsible for that association.

M3 must not choose the hospital automatically.

---

# 47. Realtime Channels

Add protected channels such as:

```text
hospital.{hospitalId}
```

Reuse:

```text
case.{caseId}
user.{userId}
organization.{organizationId}
```

where appropriate.

Do not create public operational channels.

---

# 48. Realtime Events

Potential events:

```text
HospitalReceivingStatusChanged
HospitalCapabilityAvailabilityChanged
HospitalResourceStateChanged
HospitalRestrictionChanged
IncomingCaseNotificationCreated
IncomingCaseNotificationDelivered
IncomingCaseNotificationAcknowledged
```

Use explicit minimal payloads.

---

# 49. Realtime Data Minimization

Hospital-wide channels should not receive unnecessary patient clinical data.

Broadcast identifiers/status summaries and allow authorized clients to reload details through HTTP/API.

---

# 50. Realtime Recovery

Continue existing MEDGRID rule:

```text
Reconnect
    ↓
Reauthorize
    ↓
Resubscribe
    ↓
Reload authoritative HTTP state
    ↓
Reconcile
```

Realtime is not authoritative storage.

---

# 51. Hospital Availability History

Hospital Command must allow authorized users to understand recent status changes.

Example:

```text
14:02 Receiving OPEN
14:17 ICU LIMITED
14:31 CT UNAVAILABLE — equipment
14:48 CT AVAILABLE
```

Do not overwrite history.

---

# 52. Operational Restrictions

Support temporary restrictions where useful.

Conceptually:

```text
HospitalOperationalRestriction
```

Examples:

- temporary intake limitation;
- equipment outage;
- internal operational incident.

Do not encode patient-specific clinical recommendations here.

---

# 53. Restriction Lifecycle

Suggested:

```text
ACTIVE
EXPIRED
CANCELLED
```

Preserve history.

Support effective/expiration timestamps.

---

# 54. Concurrency

Hospital operational status may be updated by multiple operators.

Use concurrency protection.

Do not allow stale browser state to silently overwrite newer status.

Use:

- transactions;
- row locking where appropriate;
- optimistic versioning;
- version checks;
- constraints.

Return a clear conflict response.

---

# 55. Optimistic Versioning

Mutable operational entities/snapshots should expose version information where useful.

Example:

```text
expected_version: 17
```

If current server version is 18:

return:

`409 HOSPITAL_STATE_CONFLICT`

rather than silently overwriting.

---

# 56. Idempotency

Important hospital updates should support idempotency where appropriate.

Repeated submission due to network retry must not create duplicate identical history events unintentionally.

---

# 57. API

Extend `/api/v1`.

Potential endpoints:

```text
GET  /hospitals
GET  /hospitals/{hospital}

GET  /hospitals/{hospital}/snapshot

GET  /hospitals/{hospital}/departments

GET  /hospitals/{hospital}/capabilities
POST /hospitals/{hospital}/capabilities

GET  /hospitals/{hospital}/availability
POST /hospitals/{hospital}/receiving-status

POST /hospital-capabilities/{capability}/availability

GET  /hospitals/{hospital}/resources
POST /hospitals/{hospital}/resources/{resource}/state

GET  /hospitals/{hospital}/incoming

POST /hospital-notifications/{notification}/acknowledge
```

Exact routes must follow existing MEDGRID conventions.

---

# 58. API Conventions

Use:

- Form Requests;
- API Resources;
- policies;
- thin controllers;
- domain actions/services;
- organization scoping;
- hospital scoping;
- consistent errors;
- correlation IDs;
- idempotency where appropriate.

---

# 59. Status Validation

Reject invalid operational states.

Do not silently convert arbitrary strings.

Use enums/value objects consistent with existing architecture.

---

# 60. Timestamps

Preserve:

- effective time;
- recorded/server time;
- expiry time where applicable.

A status may become effective before it reaches MEDGRID.

Future integrations will depend on this distinction.

---

# 61. Timezones

Persist canonical timestamps in UTC.

Display according to organization/hospital timezone.

Do not persist ambiguous local timestamps.

---

# 62. Audit

Audit sensitive/operational actions including:

- hospital creation/configuration;
- capability changes;
- receiving status updates;
- capability availability updates;
- resource updates;
- restriction creation/cancellation;
- incoming notification acknowledgement.

Do not copy entire operational payloads into audit metadata unnecessarily.

---

# 63. Timeline

Hospital availability history and CaseEvent remain distinct.

Case timeline may record facts such as:

```text
Hospital notified
Hospital notification acknowledged
```

Do not dump every hospital resource update into every emergency case timeline.

---

# 64. Data Freshness UI

Every dynamic operational fact shown in Hospital Command should make freshness understandable.

Examples:

```text
Updated 2 min ago

Last confirmed 34 min ago · AGING

Last confirmed 2h ago · STALE
```

Do not rely only on color.

---

# 65. Unknown State UI

UNKNOWN must be visually explicit.

Never display unknown availability as green/available.

---

# 66. Synthetic Hospital Network

Seed a realistic but clearly fictional hospital network.

Example synthetic names:

```text
MEDGRID Central Hospital
MEDGRID North Emergency Center
MEDGRID Pediatric Institute
MEDGRID Trauma Center
```

Do not use real Romanian hospitals unless specifically required later.

---

# 67. Synthetic Variation

Seed different operational states.

Example:

```text
Central Hospital
Receiving OPEN
CT AVAILABLE
ICU LIMITED

North Emergency Center
Receiving LIMITED
CT AVAILABLE
Trauma AVAILABLE

Pediatric Institute
Receiving OPEN
Pediatrics AVAILABLE
Adult Trauma UNKNOWN

Trauma Center
Receiving OPEN
Trauma AVAILABLE
Neurosurgery UNAVAILABLE
```

This makes M4 development possible later.

---

# 68. No Fake Integrations

Do not claim hospital status comes from:

- Romanian Ministry of Health;
- DSU;
- STS;
- 112;
- hospital HIS;
- national systems

unless an actual authorized integration exists.

M3 uses manual/simulation data.

---

# 69. Observability

Add operational visibility for:

- stale hospital status;
- failed status updates;
- concurrency conflicts;
- failed hospital notification;
- acknowledgement failures;
- realtime failures;
- unauthorized hospital access.

Do not log sensitive patient payloads.

---

# 70. Health

Extend health checks only where useful.

Do not mark the entire MEDGRID platform unhealthy merely because one hospital has stale availability.

That is an operational data condition, not necessarily platform infrastructure failure.

---

# 71. Performance

Review query behavior for:

- hospital list;
- snapshot generation;
- current capability availability;
- current resource states;
- incoming cases;
- recent history.

Avoid N+1.

Use indexes appropriate for:

```text
hospital + effective_at
capability + effective_at
resource + effective_at
hospital + incoming status
```

Do not prematurely introduce Elasticsearch or external analytics infrastructure.

---

# 72. Snapshot Performance

Current snapshot calculation must remain efficient.

If projection tables/caching are introduced, PostgreSQL history remains authoritative.

Any cache must be safely rebuildable.

---

# 73. Dispatch Visibility

Dispatch may receive limited hospital network visibility in M3 if useful.

It may display factual operational state.

Example:

```text
Hospital Central
OPEN
CT AVAILABLE
Neurosurgery LIMITED
Updated 3 min ago
```

Do NOT rank hospitals.

Do NOT display:

```text
BEST HOSPITAL
RECOMMENDED
#1 DESTINATION
```

---

# 74. Ambulance Visibility

Ambulance may receive limited factual hospital state only if required for M3 workflow.

Do not implement destination selection UX yet.

---

# 75. Accessibility

Hospital Command must support:

- keyboard;
- clear focus;
- readable status;
- non-color-only availability;
- responsive desktop/tablet layouts.

Hospital Command is primarily desktop-oriented but must remain usable on tablets.

---

# 76. Security

Hospital operational data may influence emergency decisions.

Protect integrity strongly.

Review:

- tenant isolation;
- hospital access;
- stale update overwrite;
- IDOR;
- mass assignment;
- status tampering;
- realtime authorization;
- sensitive incoming case exposure;
- audit integrity.

---

# 77. Data Integrity

Database constraints should protect important invariants.

Potential examples:

- unique hospital code per organization;
- capability uniqueness;
- valid relationships;
- idempotency keys;
- version values;
- notification relationships.

Do not rely only on frontend validation.

---

# 78. Automated Tests

M3 requires comprehensive tests.

## Hospitals

- create/view authorized;
- cross-org hidden;
- inactive behavior;
- code uniqueness.

## Departments

- organization/hospital relationships;
- permissions;
- cross-org denial.

## Capabilities

- add capability;
- duplicate prevented;
- disable capability;
- availability separate from capability;
- cross-org denial.

## Receiving Status

- OPEN update;
- LIMITED update;
- NOT_RECEIVING update;
- history preserved;
- freshness calculated;
- expiry handled;
- stale state represented.

## Capability Availability

- AVAILABLE;
- LIMITED;
- UNAVAILABLE;
- UNKNOWN;
- history preserved;
- freshness;
- expired status.

## Resources

- numeric capacity;
- UNKNOWN capacity;
- zero distinct from unknown;
- history preserved;
- cross-org denial.

## Snapshot

- deterministic result;
- capability and availability separation;
- stale/expired behavior;
- unknown handling.

## Concurrency

- stale version rejected;
- simultaneous update safety;
- no silent overwrite.

## Incoming Cases

- explicit hospital notification;
- authorized hospital visibility;
- unrelated hospital denied;
- acknowledgement;
- duplicate acknowledgement idempotent;
- notification does not imply clinical acceptance.

## Realtime

- hospital channel authorized;
- cross-org rejected;
- minimal payloads.

## Security

- IDOR;
- tenant isolation;
- hospital context isolation;
- permission checks.

---

# 79. Browser / Visual QA

Verify:

- Hospital Command desktop;
- laptop;
- tablet;
- OPEN;
- LIMITED;
- NOT_RECEIVING;
- UNKNOWN;
- stale status;
- capability unavailable;
- resource unknown;
- incoming case;
- acknowledgement;
- conflict error.

Do not claim QA not actually performed.

---

# 80. Migration Safety

M3 must migrate normally from:

`v0.3.0`

No normal implementation may require `migrate:fresh`.

Verify forward migration against the existing M2 database.

Separately verify fresh migrations on a confirmed development/test database.

---

# 81. Backward Compatibility

M3 must not break:

- M0 authentication/RBAC;
- M1 Dispatch;
- M1 assignment/realtime;
- M2 ambulance workflow;
- M2 patient encounters;
- M2 vitals;
- M2 assessments;
- M2 offline sync.

All existing tests remain green.

---

# 82. Documentation

Create/update implementation documentation.

Consider ADRs for:

- capability vs availability;
- hospital operational history;
- freshness/staleness;
- snapshot calculation;
- concurrency/versioning.

Document only actual decisions.

---

# 83. Quality Verification

Before completion run:

```text
php artisan test
npm run build
Laravel Pint
composer audit
npm audit
git diff --check
```

Report exact results.

---

# 84. M3 Acceptance Criteria

M3 is complete only when:

## Hospital Network

- multiple hospitals supported;
- hospital identity explicit;
- departments supported;
- organization isolation enforced.

## Capabilities

- capability catalog exists;
- hospital capabilities configurable;
- capability and availability are separate.

## Availability

- dynamic availability history preserved;
- UNKNOWN supported;
- freshness tracked;
- stale data visible;
- expiry handled safely.

## Receiving

- hospital receiving status exists;
- history preserved;
- OPEN/LIMITED/NOT_RECEIVING/UNKNOWN supported.

## Resources

- resource state supported;
- zero and unknown are distinct;
- history preserved.

## Snapshot

- current operational snapshot deterministic;
- stale/unknown state represented truthfully.

## Hospital Command

- operational interface exists;
- realtime state visible;
- receiving/capabilities/resources/freshness visible;
- updates available to authorized staff.

## Incoming Cases

- explicit hospital notification exists;
- hospital can view incoming case;
- acknowledgement exists;
- acknowledgement semantics are precise.

## Concurrency

- stale updates cannot silently overwrite newer state;
- conflicts return explicit operational error.

## Realtime

- hospital protected channels work;
- cross-org authorization fails;
- reconnect reloads authoritative state.

## Security

- tenant isolation works;
- hospital context authorization works;
- incoming clinical exposure minimized;
- audit works.

## Quality

- M0 tests pass;
- M1 tests pass;
- M2 tests pass;
- M3 tests pass;
- forward migrations pass;
- fresh migrations pass;
- build passes;
- Pint passes;
- dependency audits pass.

---

# 85. Forbidden Shortcuts

Do NOT:

- merge capability and availability;
- treat missing status as AVAILABLE;
- treat unknown capacity as zero;
- overwrite status history;
- hide stale data age;
- silently accept stale updates;
- rank hospitals;
- recommend hospitals;
- choose destinations;
- fabricate ETA;
- fabricate hospital capacity;
- claim fake external integrations;
- expose complete patient records hospital-wide;
- implement M4;
- add AI hospital selection.

---

# 86. Final Verification Scenario

Verify a synthetic scenario:

```text
Hospital Central configured
        ↓
Capabilities:
CT
Neurosurgery
ICU
        ↓
Receiving status OPEN
        ↓
CT AVAILABLE
Neurosurgery AVAILABLE
ICU LIMITED
        ↓
Resource state updated
        ↓
Hospital Command updates realtime
        ↓
Neurosurgery becomes UNAVAILABLE
        ↓
Old AVAILABLE history remains
        ↓
Snapshot shows UNAVAILABLE
        ↓
Status ages beyond freshness threshold
        ↓
UI shows STALE
```

Then:

```text
Emergency case exists
        ↓
Explicit HospitalCaseNotification created
        ↓
Authorized Hospital Central operator sees incoming case
        ↓
Unrelated hospital cannot see it
        ↓
Hospital operator acknowledges notification
        ↓
Case timeline records acknowledgement
```

Acknowledgement must not imply clinical acceptance or handover.

Then concurrency:

```text
Operator A loads version 8
Operator B loads version 8

Operator A updates status
        ↓
Server becomes version 9

Operator B submits version 8
        ↓
409 HOSPITAL_STATE_CONFLICT
```

No silent overwrite.

---

# 87. Completion Report

At completion provide:

## Baseline

Exact v0.3.0 baseline.

## Implemented

## Hospital Domain

## Departments

## Capability Architecture

## Availability Architecture

## Receiving Status

## Resource Model

## Freshness / Staleness

## Operational Snapshot

## Hospital Command UI

## Incoming Case Notifications

## Acknowledgement Semantics

## Concurrency Strategy

## Realtime

Separate authorization, server, transport and browser verification.

## Authorization

## Audit / History

## Database / Migrations

## API

## Dispatch / Ambulance Impact

## Performance Review

## Observability

## Security Review

## UI / Visual QA

## Tests

Exact tests/assertions/failures.

## Build / Quality

Exact results.

## Known Limitations

## Git Status

## M3 Acceptance

Review every acceptance criterion.

Then STOP.

Do NOT begin M4.

Do NOT commit.

Do NOT tag.

Do NOT push.

---

# 88. Final Rule

Hospital information in MEDGRID may influence time-critical decisions.

Therefore M3 must prioritize:

**truth → freshness → provenance → history → authorization → concurrency → realtime → usability**

A hospital having a capability does not mean that capability is currently available.

An old availability report is not the same as a current availability report.

Unknown is not available.

Unknown capacity is not zero capacity.

Acknowledgement is not clinical acceptance.

MEDGRID must preserve these distinctions everywhere: database, API, realtime and UI.