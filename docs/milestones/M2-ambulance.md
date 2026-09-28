# MEDGRID v0.3.0 — M2 Ambulance

## 1. Purpose

M2 introduces the ambulance-side operational and clinical data capture foundation.

The primary workflow becomes:

```text
Mission accepted
    ↓
En route to scene
    ↓
Arrival on scene
    ↓
Patient contact
    ↓
Patient encounter
    ↓
Initial assessment
    ↓
Vital observations
    ↓
Ongoing reassessment
    ↓
Patient condition updates
```

M2 must allow authorized ambulance personnel to capture reliable structured information while operating under time pressure.

MEDGRID remains clinician-supporting software.

It must not autonomously diagnose, prescribe treatment, recommend medication, determine destination, or replace medical judgment.

---

# 2. Milestone Boundary

M2 implements:

- ambulance operational workflow;
- mission progression;
- arrival on scene;
- patient contact;
- patient identity foundation;
- unidentified patients;
- patient encounters;
- multiple patients per emergency case where required;
- structured vital observations;
- repeated vital observations;
- clinical assessment framework;
- versioned assessment templates;
- assessment responses;
- patient condition classification;
- clinical notes;
- Critical Mode;
- ambulance-oriented realtime updates;
- offline architecture groundwork;
- local operation queue design;
- synchronization/idempotency foundation;
- conflict awareness;
- audit;
- timeline integration;
- synthetic clinical demonstration data;
- automated tests.

M2 does NOT implement:

- hospital capacity;
- hospital destination recommendation;
- autonomous clinical decision support;
- diagnosis generation;
- treatment recommendations;
- medication recommendations;
- AI clinical reasoning;
- route optimization;
- traffic;
- full live GPS tracking;
- hospital handover;
- Incident Room;
- PTT;
- WebRTC;
- real 112 integration.

---

# 3. Safety Principle

Clinical information must never be fabricated by MEDGRID.

The system must clearly distinguish:

- known;
- unknown;
- not assessed;
- unavailable;
- pending;
- explicitly negative.

Never turn missing information into a clinical conclusion.

Example:

No recorded allergy information does NOT mean:

`No allergies`

It means:

`Allergy status unknown`

unless explicitly documented otherwise.

---

# 4. Ambulance Workflow

After mission acceptance, authorized crew may progress operational state.

M2 extends the case lifecycle with:

```text
UNIT_ACCEPTED
EN_ROUTE_TO_SCENE
ON_SCENE
PATIENT_CONTACT
ASSESSMENT
DESTINATION_PENDING
```

M2 should not implement destination selection itself.

`DESTINATION_PENDING` may represent readiness for the later Destination Support workflow.

Exact state representation may adapt to the existing M1 state machine.

---

# 5. Operational Transitions

Use explicit domain actions.

Examples:

```text
StartResponseToScene
ArriveOnScene
EstablishPatientContact
CreatePatientEncounter
RecordVitalObservation
CompleteAssessment
UpdatePatientCondition
```

Do not expose arbitrary case state editing.

Each transition must validate:

- current state;
- active assignment;
- actor;
- organization;
- crew membership;
- permissions;
- required information.

---

# 6. Ambulance Authorization

Only appropriately authorized crew associated with the active vehicle/mission may perform ambulance operational actions.

Do not assume that any user with `cases.update` may edit clinical information.

Clinical permissions must be explicit.

---

# 7. Patient vs PatientEncounter

Patient identity and emergency encounter are separate concepts.

Use:

```text
Patient
    ↓
PatientEncounter
```

A Patient represents the person where identity is known or partially known.

A PatientEncounter represents the specific clinical interaction during an emergency case.

Clinical observations belong primarily to the encounter.

Do not place current emergency vital signs directly on the Patient record.

---

# 8. Patient

Suggested fields:

```text
id
public_id

organization_id

identity_status

first_name nullable
last_name nullable

date_of_birth nullable
estimated_age nullable

sex nullable

national_identifier nullable

phone nullable

notes nullable

created_at
updated_at
```

Exact fields must follow data minimization.

Do not collect identity fields simply because they might be useful someday.

---

# 9. Patient Identity Status

Support explicit identity state.

Suggested:

```text
UNIDENTIFIED
PARTIALLY_IDENTIFIED
IDENTIFIED
```

A patient may enter the system as:

`UNIDENTIFIED`

and later become identified.

Do not require name/CNP/date of birth to begin emergency care documentation.

---

# 10. National Identifier

If a national identifier is supported:

- treat it as highly sensitive;
- validate format appropriately where applicable;
- restrict access;
- never expose it unnecessarily;
- never include it in URLs;
- never include it in realtime payloads unless strictly required;
- never log it.

Do not make CNP mandatory.

---

# 11. Unknown Patient

MEDGRID must support workflows such as:

```text
Unknown Patient #1
Adult male, estimated 35–45
Identity pending
```

The display identifier is operational.

Do not fabricate a legal identity.

---

# 12. Multiple Patients

One EmergencyCase may involve multiple patients.

Examples:

- road traffic collision;
- public incident;
- multiple casualties.

Therefore:

```text
EmergencyCase
    has many
PatientEncounter
```

Do not enforce one case = one patient.

---

# 13. PatientEncounter

Suggested fields:

```text
id
public_id

organization_id
emergency_case_id
patient_id nullable

encounter_number

status

contact_at nullable
assessment_started_at nullable
assessment_completed_at nullable

condition_level

created_by
created_at
updated_at
```

Patient may initially be null if identity has not yet been represented separately, depending on the final implementation strategy.

However, avoid duplicate identity records without reason.

---

# 14. Encounter Number

Each encounter should have a stable operational identifier.

Example:

`MG-2026-000184-P01`

For a second patient:

`MG-2026-000184-P02`

Generation must be concurrency-safe.

Do not use naive `count() + 1` without locking/constraint protection.

---

# 15. Encounter Status

Suggested states:

```text
ACTIVE
STABILIZED
TRANSFER_PENDING
TRANSFERRED
CANCELLED
```

Only implement states actually required by M2.

Do not prematurely implement hospital handover.

---

# 16. Patient Condition Level

M2 may support a generic operational condition level such as:

```text
CRITICAL
SERIOUS
MODERATE
STABLE
UNKNOWN
```

This is not an autonomous diagnosis.

The value must be entered/confirmed by authorized personnel.

MEDGRID must not calculate it from vitals unless a future validated clinical rule explicitly permits that behavior.

---

# 17. Vital Observations

Vitals are observations.

They must be append-oriented.

Do NOT store:

```text
patient.current_heart_rate
patient.current_spo2
patient.current_blood_pressure
```

as the authoritative clinical history.

Each measurement must be preserved.

---

# 18. VitalObservation

Use a model such as:

`VitalObservation`

or another name consistent with the codebase.

Suggested fields:

```text
id
public_id

organization_id
patient_encounter_id

type

value_numeric nullable
value_text nullable

unit nullable

secondary_value_numeric nullable

measured_at
recorded_at

recorded_by

source
device_identifier nullable

operation_id nullable

created_at
```

The exact model may use specialized fields where clinically/technically justified.

---

# 19. Vital Types

M2 should support at minimum:

```text
HEART_RATE
BLOOD_PRESSURE
SPO2
RESPIRATORY_RATE
TEMPERATURE
GLUCOSE
GCS
PAIN_SCORE
```

The architecture should allow future types without dangerous schema hacks.

---

# 20. Blood Pressure

Blood pressure is a paired observation.

Example:

```text
120 / 80 mmHg
```

Do not store the whole value only as an arbitrary string if structured representation is practical.

Preserve:

- systolic;
- diastolic;
- measured_at;
- actor/source.

---

# 21. Glasgow Coma Scale

If GCS is recorded, support structured components where appropriate:

```text
Eye
Verbal
Motor
Total
```

Do not silently derive or override clinician-entered values incorrectly.

If a total is calculated from components, clearly distinguish derived value from directly entered value.

---

# 22. Measurement Time

Distinguish:

`measured_at`

from:

`recorded_at`

Example:

A measurement may occur at 14:31 but be entered into MEDGRID at 14:34.

Both facts matter.

Offline synchronization makes this distinction mandatory.

---

# 23. Observation Source

Suggested values:

```text
MANUAL
DEVICE
IMPORTED
OFFLINE_SYNC
```

M2 primarily uses:

`MANUAL`

and offline synchronization groundwork.

Do not claim device integration unless it actually exists.

---

# 24. Vital History

The ambulance interface must show:

- latest value;
- measurement time;
- previous observations;
- trend/history.

Never replace old measurements when new values arrive.

---

# 25. Vital Corrections

Clinical history must not be silently rewritten.

If a mistaken observation needs correction:

prefer explicit correction/supersession metadata.

Example:

```text
Original observation
    ↓
Marked corrected
    ↓
Replacement observation
```

Preserve who corrected it, when, and why.

Do not simply overwrite the original measurement.

---

# 26. Assessment Framework

M2 introduces structured assessments.

Do not hard-code one giant assessment form directly into Blade.

Use:

```text
AssessmentTemplate
AssessmentTemplateVersion
Assessment
AssessmentResponse
```

or an equivalent versioned architecture.

---

# 27. AssessmentTemplate

Represents a reusable assessment definition.

Examples:

```text
GENERAL_EMERGENCY
TRAUMA
NEUROLOGICAL
RESPIRATORY
CARDIAC
BURNS
PEDIATRIC
OBSTETRIC
```

M2 does NOT need to implement validated official medical protocols for all these categories.

The framework should support them.

Only include synthetic/general demo templates that are clearly non-authoritative unless a validated source exists.

---

# 28. Assessment Template Ownership

Templates must be organization-aware/configurable where appropriate.

Future institutions may use different approved forms.

Do not assume one universal form is legally or clinically correct for every organization.

---

# 29. Template Versioning

Once used clinically, assessment structure must be reproducible.

Therefore templates require versions.

Example:

```text
General Emergency
v1
v2
v3
```

An assessment must reference the exact template version used at the time.

Editing a template must not retroactively alter completed assessments.

---

# 30. Assessment Definition

A template version may define fields such as:

```text
key
label
type
required
options
validation
section
display_order
```

JSON/JSONB may be appropriate for template definitions if justified.

Clinical observations themselves should remain structured and queryable where required.

---

# 31. Assessment

Suggested fields:

```text
id
public_id

organization_id
patient_encounter_id
template_version_id

status

started_at
completed_at nullable

created_by
completed_by nullable

created_at
updated_at
```

---

# 32. Assessment Status

Suggested:

```text
IN_PROGRESS
COMPLETED
AMENDED
CANCELLED
```

Do not silently rewrite completed assessments.

---

# 33. Assessment Responses

Responses must preserve:

- field key;
- value;
- actor;
- timestamp;
- template version context.

Use appropriate structured storage.

Avoid unsafe arbitrary serialization.

---

# 34. Clinical Notes

M2 may support encounter notes.

Notes are sensitive.

Requirements:

- organization scoped;
- encounter scoped;
- authorized;
- escaped safely;
- not exposed in URLs;
- not logged;
- not broadcast beyond required audiences.

Consider append-oriented notes rather than one continuously overwritten textarea if consistent with the architecture.

---

# 35. Allergies / History / Medication

M2 may include minimum structured fields required to capture known information, but do not build a complete longitudinal EHR.

If supported, distinguish explicitly:

```text
UNKNOWN
NONE_KNOWN
KNOWN
```

For example:

`Allergies unknown`

is different from:

`No known allergies`.

The same principle applies to relevant medical history and medications.

---

# 36. Critical Mode

The ambulance interface requires a dedicated:

# Critical Mode

Purpose:

reduce interaction complexity during high-pressure patient care.

Critical Mode should prioritize:

- patient identifier;
- case identifier;
- condition;
- latest vitals;
- quick vital entry;
- assessment status;
- operational mission state;
- connection state;
- critical actions.

---

# 37. Critical Mode UX

Critical Mode should:

- use large touch targets;
- minimize navigation;
- avoid decorative UI;
- avoid unnecessary modal chains;
- show connection state;
- show whether data is saved/syncing/offline;
- remain usable on ambulance tablets;
- avoid relying only on color.

Do not overload Critical Mode with administrative information.

---

# 38. Standard Ambulance View

The normal ambulance workspace may contain:

```text
MISSION
PATIENTS
VITALS
ASSESSMENT
TIMELINE
```

Destination and hospital workflow remain future milestones.

---

# 39. Realtime Clinical Updates

M2 may broadcast relevant encounter updates to authorized operational users.

Potential events:

```text
PatientEncounterCreated
PatientIdentityUpdated
VitalObservationRecorded
VitalObservationCorrected
AssessmentStarted
AssessmentCompleted
PatientConditionChanged
```

Use minimal payloads.

Do not broadcast unnecessary personal identifiers.

---

# 40. Case Timeline Integration

Important ambulance facts should appear in the operational case timeline.

Examples:

```text
Ambulance arrived on scene
Patient contact established
Patient encounter created
Initial assessment started
Patient condition changed to CRITICAL
```

Do not dump every individual assessment response into the operational timeline.

Vitals should normally remain clinical observations rather than creating noisy timeline entries for every measurement.

---

# 41. Audit

Audit sensitive actions including:

- patient identity access/change where appropriate;
- encounter creation;
- vital correction;
- assessment completion/amendment;
- condition changes;
- clinical note changes;
- offline conflict resolution.

Do not duplicate all clinical data into audit metadata.

---

# 42. Offline Requirement

Ambulance connectivity may be intermittent.

M2 must establish offline architecture groundwork.

The system must distinguish:

```text
ONLINE
SYNCING
OFFLINE
CONFLICT
```

from realtime WebSocket state where necessary.

---

# 43. Offline Local Operation Queue

Client operations intended for offline support must receive a stable:

`operation_id`

preferably UUID/ULID.

An offline operation should conceptually include:

```text
operation_id
operation_type
entity_type
entity_id
entity_version
payload
captured_at
queued_at
device_id
session_id
```

Never depend only on local timestamps for uniqueness.

---

# 44. Offline-Safe Operations

Potentially offline-safe M2 operations include:

- vital observation creation;
- certain assessment responses;
- clinical note creation;
- limited encounter updates.

Exact operations must be deliberately selected.

Do not automatically make every API endpoint offline-capable.

---

# 45. Offline-Unsafe Operations

Operations requiring current shared state should remain online-only unless specifically designed otherwise.

Examples:

- hospital availability;
- destination evaluation;
- live acknowledgement;
- realtime communications.

These belong primarily to future milestones.

---

# 46. Offline UI Truth

If data exists only locally and has not been accepted by the server, the UI must NOT present it as fully synchronized authoritative state.

Clearly show:

```text
Saved locally
Waiting to sync
Syncing
Synced
Conflict
Rejected
```

Do not fake successful server persistence.

---

# 47. Synchronization Endpoint

M2 may introduce an API synchronization endpoint or equivalent architecture.

Example concept:

`POST /api/v1/sync/operations`

The server must process operations idempotently.

Duplicate `operation_id` must not create duplicate clinical records.

---

# 48. Sync Results

Possible server results:

```text
ACCEPTED
DUPLICATE
CONFLICT
REJECTED
```

Each result should include enough information for the client to reconcile safely.

---

# 49. Conflict Handling

Do NOT use blind last-write-wins for clinical data.

For append-only vital creation, conflicts should usually be minimal because observations are additive.

For mutable encounter information, detect version conflicts.

Where automatic resolution is unsafe:

mark:

`CONFLICT`

and require deliberate reconciliation.

---

# 50. Entity Versioning

Mutable clinical/operational entities should support optimistic version awareness where appropriate.

The client may submit:

`entity_version`

The server compares it against current authoritative state.

Do not silently overwrite newer server information.

---

# 51. Device Identity

Offline operations should carry a device identifier/session context.

Do not treat device identity as sufficient authorization.

Server authentication and authorization remain mandatory when synchronization occurs.

---

# 52. Local Data Security

Clinical data stored locally for offline use is sensitive.

Architecture must minimize:

- amount stored;
- retention duration;
- unnecessary identifiers.

Do not store credentials or long-lived secrets in unsafe browser storage.

Document the security limitations of browser/PWA offline storage.

---

# 53. PWA Groundwork

M2 should strengthen the existing application toward ambulance PWA use.

Potential areas:

- installable manifest;
- appropriate icons/metadata;
- service worker foundation;
- safe static asset caching;
- update strategy.

Do NOT blindly cache authenticated clinical API responses.

Do NOT create a generic cache-all service worker.

---

# 54. Service Worker Safety

Never cache:

- login pages containing sensitive state;
- arbitrary authenticated responses;
- patient API responses

using broad cache-first rules.

Offline clinical storage must use a deliberate controlled data strategy.

---

# 55. API

Extend `/api/v1`.

Potential resources:

```text
GET    /cases/{case}/encounters
POST   /cases/{case}/encounters

GET    /encounters/{encounter}
PATCH  /encounters/{encounter}

GET    /encounters/{encounter}/vitals
POST   /encounters/{encounter}/vitals

POST   /vitals/{vital}/correct

GET    /encounters/{encounter}/assessments
POST   /encounters/{encounter}/assessments

GET    /assessments/{assessment}
PATCH  /assessments/{assessment}
POST   /assessments/{assessment}/complete

POST   /sync/operations
```

Exact routes should follow existing MEDGRID API conventions.

---

# 56. Validation

Clinical input requires strict validation.

Examples:

- numeric type;
- allowed units;
- timestamps;
- paired blood pressure values;
- valid GCS component ranges;
- pain score range;
- required assessment fields.

Do not silently clamp invalid values into apparently valid clinical observations.

Reject invalid input clearly.

---

# 57. Plausibility vs Validity

Technical validation and clinical plausibility are different.

M2 may validate that:

`SpO2` is numeric and within the accepted technical representation.

Do NOT automatically decide that a value represents a diagnosis or treatment requirement.

Future validated clinical rules belong elsewhere.

---

# 58. Units

Store/represent units explicitly.

Examples:

```text
bpm
mmHg
%
breaths/min
°C
mg/dL
mmol/L
```

Where multiple units are supported, normalize carefully while preserving original measurement context if necessary.

Do not silently mix glucose units.

---

# 59. Time Integrity

Clinical observation ordering must use measurement time appropriately.

Preserve server receipt time separately.

Offline observations may arrive out of order.

Do not reorder clinical truth based solely on database insertion ID.

---

# 60. Permissions

Add granular M2 permissions.

Suggested:

```text
patients.view
patients.create
patients.update

encounters.view
encounters.create
encounters.update

vitals.view
vitals.create
vitals.correct

assessments.view
assessments.create
assessments.update
assessments.complete
assessments.amend

clinical_notes.view
clinical_notes.create

ambulance.workflow.update

sync.submit
```

Adapt naming to existing conventions where necessary.

---

# 61. Authorization

Authorization must consider:

- organization;
- active case;
- active assignment;
- active vehicle;
- crew membership;
- permission;
- resource relationship.

Do not authorize clinical modification merely because a user knows an encounter ULID.

---

# 62. Cross-Organization Isolation

Mandatory tests must prove Organization A cannot:

- read Organization B patients;
- read Organization B encounters;
- create vitals for Organization B;
- read assessments from Organization B;
- submit sync operations against Organization B resources;
- subscribe to protected Organization B clinical channels.

Use 404 where consistent with existing tenant-hiding behavior.

---

# 63. Clinical Data Exposure

Realtime and API responses should minimize unnecessary patient identity exposure.

A dispatcher may need:

- patient count;
- condition level;
- major operational status.

A dispatcher does not automatically need every clinical assessment response.

Respect least privilege.

---

# 64. Database

Use PostgreSQL relational modeling for core clinical data.

JSONB may be justified for:

- versioned assessment template definitions;
- flexible assessment responses where appropriate;
- sync payload snapshots.

Do not place the entire clinical record in one JSON document.

---

# 65. Database Constraints

Use constraints/indexes appropriate for:

- organization ownership;
- encounter numbering;
- observation ordering;
- operation idempotency;
- template versions;
- assessment relationships;
- active encounter queries.

Do not depend solely on application validation.

---

# 66. Synthetic Clinical Data

All development data must be fictional.

Use clearly synthetic:

- names;
- dates;
- telephone numbers;
- case information;
- clinical observations.

Never use real patient information.

---

# 67. Demo Assessments

Any seeded assessment template must be labelled as synthetic/demo/non-authoritative where appropriate.

Do not represent it as an official Romanian EMS medical protocol.

---

# 68. Ambulance UI

The M2 ambulance workspace should be optimized for tablets first.

Suggested structure:

```text
┌────────────────────────────────────────────┐
│ MEDGRID AMBULANCE     LIVE / SYNCED        │
├────────────────────────────────────────────┤
│ CASE MG-2026-000184                        │
│ P1 CRITICAL                                │
│                                            │
│ PATIENT 01                                 │
│ Condition: CRITICAL                        │
│                                            │
│ HR      BP       SpO2      RR              │
│ 112     95/60    91%       24              │
│                                            │
│ [ RECORD VITALS ]                          │
│ [ ASSESSMENT ]                             │
│                                            │
│ [ CRITICAL MODE ]                          │
└────────────────────────────────────────────┘
```

Conceptual only.

Do not sacrifice usability to match the diagram literally.

---

# 69. Latest Vitals

Latest vitals are a projection of the observation history.

They are not the authoritative storage model.

Queries/projections may optimize display, but the underlying observations remain preserved.

---

# 70. Clinical Trend View

Provide a simple history/trend presentation.

M2 does not require sophisticated predictive charts.

Allow personnel to see how observations changed over time.

---

# 71. Accessibility

Critical Mode and ambulance screens must support:

- touch;
- keyboard where practical;
- readable typography;
- high contrast;
- non-color-only status;
- clear focus states;
- adequate touch target size.

---

# 72. Realtime Failure

If Reverb fails:

clinical records may still be persisted through HTTP/API if available.

Show realtime state truthfully.

Do not block safe clinical recording merely because WebSocket delivery failed.

---

# 73. Network Failure

If HTTP/server connectivity fails:

only deliberately supported offline operations may be stored locally.

The UI must clearly indicate local-only state.

Do not queue unsafe operations and pretend they succeeded remotely.

---

# 74. Database Failure

If server persistence fails:

do not show:

`Synced`

or:

`Saved to MEDGRID`

unless it actually occurred.

If local offline storage succeeds, say:

`Saved locally — waiting to sync`

---

# 75. Idempotency

Vital creation through offline sync must be idempotent.

Submitting the same operation twice must not create duplicate observations.

Use server-enforced uniqueness on operation identifiers where appropriate.

---

# 76. Corrections

Corrections must also be idempotent where practical.

Repeated sync of a correction operation must not generate multiple correction chains.

---

# 77. Observability

Add observability for:

- clinical validation failures;
- sync failures;
- conflicts;
- rejected offline operations;
- assessment completion failures;
- unauthorized clinical access.

Do not log the complete clinical payload unnecessarily.

---

# 78. Performance

Review:

- encounter list queries;
- latest vitals queries;
- vital history;
- assessment loading;
- template loading.

Avoid N+1 behavior.

Do not prematurely introduce external analytics infrastructure.

---

# 79. Automated Tests

M2 requires extensive automated tests.

At minimum:

## Patient

- unidentified patient creation;
- partial identification;
- authorized identity update;
- cross-organization denial.

## Encounter

- encounter creation;
- multiple patients per case;
- concurrency-safe encounter numbering;
- invalid case relationship denied;
- unauthorized crew denied.

## Vitals

- valid observation accepted;
- invalid observation rejected;
- blood pressure structured correctly;
- repeated observations preserved;
- latest projection correct;
- measured_at preserved;
- recorded_at preserved;
- correction preserves original;
- unauthorized correction denied.

## Assessments

- template version preserved;
- assessment creation;
- responses validated;
- completion;
- completed assessment not silently rewritten;
- cross-organization isolation.

## Workflow

- UNIT_ACCEPTED → EN_ROUTE_TO_SCENE;
- EN_ROUTE_TO_SCENE → ON_SCENE;
- ON_SCENE → PATIENT_CONTACT;
- invalid transitions rejected.

## Critical Mode

- authorized crew access;
- unauthorized user denied;
- correct patient/encounter context.

## Offline / Sync

- operation accepted;
- duplicate operation idempotent;
- invalid operation rejected;
- cross-organization operation rejected;
- version conflict detected;
- additive vital operation safely syncs.

## Realtime

- clinical channel authorization;
- cross-org channel rejected;
- minimal payload verification where practical.

---

# 80. Security Tests

Explicitly test:

- IDOR against encounters;
- IDOR against vitals;
- IDOR against assessments;
- cross-organization sync;
- unauthorized crew clinical modification;
- sensitive identifier leakage where testable;
- protected realtime channels.

---

# 81. Browser / Visual QA

Verify:

- ambulance tablet;
- smaller tablet;
- desktop ambulance view;
- Critical Mode;
- vital entry;
- assessment form;
- offline/sync indicators;
- validation errors.

Do not claim browser verification if it was not performed.

---

# 82. Migration Safety

M2 must migrate normally from:

`v0.2.0`

Do not require database reset.

Verify:

existing v0.2.0 database → M2 migrations.

Also verify fresh database installation separately in a confirmed development/test database.

---

# 83. Backward Compatibility

Existing Dispatch workflows must continue to work.

M2 must not break:

- case creation;
- assignment;
- reassignment;
- cancellation;
- mission acceptance;
- realtime Dispatch board.

Keep M0/M1 tests passing.

---

# 84. Documentation

Document actual M2 architecture.

Consider ADRs for:

- clinical observation immutability;
- assessment template versioning;
- offline sync/idempotency;
- local clinical data security.

Update README only where actual setup/runtime behavior changes.

---

# 85. Quality Verification

Before completion run:

```text
php artisan test
npm run build
Laravel Pint
composer audit
npm audit
git diff --check
```

Report actual results.

---

# 86. M2 Acceptance Criteria

M2 is complete only when:

## Workflow

- accepted mission progresses to en route;
- ambulance can mark arrival;
- patient contact can be established;
- invalid transitions are rejected.

## Patients

- unidentified patients supported;
- multiple patients per case supported;
- identity can evolve safely;
- sensitive identity remains protected.

## Encounters

- patient encounters exist separately from patient identity;
- encounter numbering is concurrency-safe;
- encounter remains linked to case.

## Vitals

- observations are append-oriented;
- repeated measurements are preserved;
- measurement and record times are distinct;
- corrections preserve original observations;
- latest vitals can be displayed reliably.

## Assessments

- templates exist;
- templates are versioned;
- assessments reference exact template versions;
- completed assessments are not silently rewritten.

## Ambulance UX

- tablet workspace operational;
- Critical Mode operational;
- quick vital recording works;
- status/sync/realtime state visible.

## Offline Groundwork

- supported operation queue exists;
- operation IDs provide idempotency;
- duplicate sync does not duplicate records;
- conflicts are represented explicitly;
- local-only data is visibly local;
- unsafe operations are not falsely queued.

## Realtime

- authorized clinical updates work;
- organization isolation works;
- reconnect restores authoritative state.

## Security

- clinical permissions work;
- crew authorization works;
- cross-org access fails;
- sensitive identifiers are protected;
- audit works.

## Quality

- M0 tests pass;
- M1 tests pass;
- M2 tests pass;
- migrations pass;
- frontend build passes;
- formatting passes;
- security audits pass.

---

# 87. Forbidden Shortcuts

Do not:

- store only latest vitals;
- overwrite old observations;
- treat missing data as negative findings;
- make CNP mandatory;
- assume one case equals one patient;
- create one giant clinical JSON blob;
- hard-code one universal medical assessment;
- modify completed assessments silently;
- use last-write-wins for clinical conflicts;
- cache authenticated patient APIs indiscriminately;
- display local data as server-synced;
- invent clinical recommendations;
- automatically diagnose;
- automatically recommend medication;
- automatically choose hospital destination;
- implement M3 functionality;
- introduce AI clinical reasoning.

---

# 88. Final Verification Scenario

Before M2 completion, verify a synthetic workflow:

```text
Dispatcher creates case
        ↓
Vehicle assigned
        ↓
Crew accepts mission
        ↓
Crew marks EN_ROUTE_TO_SCENE
        ↓
Crew marks ON_SCENE
        ↓
Patient contact established
        ↓
Unknown Patient #1 created
        ↓
PatientEncounter created
        ↓
Initial vital observations recorded
        ↓
Second vital set recorded
        ↓
Previous observations remain preserved
        ↓
Assessment started
        ↓
Assessment completed
        ↓
Patient condition updated
        ↓
Dispatch receives appropriate operational update
```

Then simulate supported offline behavior:

```text
Connection unavailable
        ↓
Vital recorded locally
        ↓
UI says SAVED LOCALLY
        ↓
Connection restored
        ↓
Operation submitted
        ↓
Server accepts operation once
        ↓
Duplicate submission returns DUPLICATE
        ↓
UI reconciles to SYNCED
```

No step may falsely claim persistence or synchronization.

---

# 89. Completion Report

At completion provide:

## Baseline

v0.2.0 test/build state.

## Implemented

## Ambulance Workflow

## Patient Model

## Patient Encounter Model

## Vital Observation Architecture

## Vital Corrections

## Assessment Architecture

## Template Versioning

## Critical Mode

## Offline Architecture

## Sync / Idempotency

## Conflict Handling

## Realtime

## Authorization

## Audit

## Database / Migrations

## API

## UI / Visual QA

## Performance Review

## Security Review

## Tests

Exact test/assertion/failure counts.

## Build / Quality

Exact results.

## Known Limitations

## Git Status

## M2 Acceptance

State whether every criterion is satisfied.

Then STOP.

Do NOT begin M3.

Do NOT commit.

Do NOT tag.

Do NOT push.

---

# 90. Final Rule

M2 handles information that may later influence emergency medical care.

Therefore MEDGRID must prioritize:

**clinical truth → provenance → chronology → authorization → persistence → synchronization → usability**

over convenience.

When MEDGRID does not know something, it must say that it does not know.

When data exists only locally, it must say that it exists only locally.

When a measurement was corrected, the original must remain traceable.

When information conflicts, MEDGRID must expose the conflict rather than silently inventing certainty.