# MEDGRID v0.5.0 — M4 Destination Support

## 1. Purpose

M4 introduces the MEDGRID Destination Support Engine.

Its purpose is to combine authorized case/encounter requirements with current hospital operational facts and produce an explainable set of destination candidates.

The engine supports human decision-making.

It does NOT make autonomous medical decisions.

Core flow:

```text
Patient Encounter
        ↓
Authorized destination requirements
        ↓
Destination Evaluation
        ↓
Hospital operational snapshots
        ↓
Deterministic rules
        ↓
Eligible / Ineligible / Unknown candidates
        ↓
Explainable evidence
        ↓
Authorized human destination selection
```

---

# 2. Fundamental Safety Rule

MEDGRID must never silently transform:

```text
clinical data
```

into:

```text
medical destination requirements
```

unless that transformation is based on an explicitly configured, validated and authorized rule set.

For M4, destination requirements should primarily be explicitly entered or confirmed by authorized personnel.

Example:

```text
Requires: NEUROSURGERY
Requires: CT
```

is acceptable when explicitly selected/confirmed.

Automatically inferring:

```text
Patient has symptom X
therefore requires neurosurgery
```

is outside M4 unless a formally validated future rule explicitly authorizes it.

---

# 3. Human Authority

The Destination Support Engine produces decision support.

It does NOT produce an autonomous destination order.

Final destination selection requires an authorized human action.

MEDGRID must record:

- who selected the destination;
- when;
- which evaluation was visible;
- which rule-set version was used;
- which hospital snapshot facts were considered;
- whether the selected hospital was eligible, unknown or overridden;
- override reason where required.

---

# 4. Scope

M4 implements:

- destination requirements;
- requirement sets;
- deterministic destination evaluation;
- versioned evaluation rules;
- hospital candidate evaluation;
- capability checks;
- availability checks;
- receiving-status checks;
- freshness checks;
- restriction checks;
- selected resource checks where explicitly configured;
- eligible candidates;
- ineligible candidates;
- unknown/insufficient-data candidates;
- explanation/evidence;
- immutable evaluation snapshots;
- human destination selection;
- deliberate override;
- destination change history;
- audit;
- case timeline;
- realtime;
- Dispatch integration;
- Ambulance integration where authorized;
- Medical Coordinator workflow;
- API;
- UI;
- tests.

---

# 5. Explicit Exclusions

M4 does NOT implement:

- diagnosis;
- treatment recommendations;
- medication recommendations;
- autonomous triage;
- AI clinical reasoning;
- AI destination ranking;
- opaque ML scoring;
- route optimization;
- live traffic;
- fabricated ETA;
- hospital bed reservation;
- clinical acceptance;
- handover;
- Incident Room;
- PTT;
- real 112 integration;
- real HIS integration.

---

# 6. Core Architecture

Use a pipeline conceptually similar to:

```text
DestinationEvaluationRequest
        ↓
DestinationRequirementSet
        ↓
HospitalSnapshotProvider
        ↓
DestinationRuleSet
        ↓
DestinationEvaluator
        ↓
DestinationEvaluation
        ↓
DestinationCandidateEvaluation[]
        ↓
Human DestinationSelection
```

Names may adapt to existing MEDGRID conventions.

---

# 7. Determinism

The same:

- destination requirements;
- hospital facts;
- rule-set version;
- configuration;
- reference time;

must produce the same evaluation result.

No randomness.

No LLM.

No hidden external decision source.

No mutable "current state" may alter a historical evaluation after it has completed.

---

# 8. Destination Requirement

Introduce an explicit destination requirement concept.

Examples:

```text
CAPABILITY_REQUIRED
CAPABILITY_PREFERRED
RESOURCE_REQUIRED
RECEIVING_REQUIRED
```

M4 should begin conservatively.

Do not create a universal medical protocol.

---

# 9. Requirement Provenance

Each requirement must preserve why it exists.

Suggested fields:

```text
id
public_id

organization_id
emergency_case_id
patient_encounter_id

type
target_code
importance

source

entered_by
confirmed_by nullable

created_at
```

Possible sources:

```text
MANUAL
PROTOCOL
INTEGRATION
```

For M4, primarily:

`MANUAL`.

Do not use `PROTOCOL` unless an actual configured/validated rule exists.

---

# 10. Requirement Status

Support explicit lifecycle if necessary:

```text
ACTIVE
SUPERSEDED
CANCELLED
```

Do not silently rewrite historical requirements used by an evaluation.

---

# 11. Requirement Set

An evaluation must operate against a stable requirement set.

Conceptually:

```text
DestinationRequirementSet
```

It should preserve exactly which requirements participated in an evaluation.

---

# 12. Requirements Are Not Diagnoses

Do not model destination requirements as diagnoses.

Example:

```text
CAPABILITY_REQUIRED: CT
```

is an operational requirement.

It does not mean MEDGRID diagnosed a condition requiring CT.

---

# 13. Rule Set

Introduce:

```text
DestinationRuleSet
DestinationRuleSetVersion
```

or equivalent.

A rule set must be versioned.

Completed evaluations must reference an immutable version.

---

# 14. Rule Versioning

Example:

```text
MEDGRID Destination Rules
v1
v2
v3
```

Changing v2 must not alter evaluations performed using v1.

Historical evaluations must remain reproducible.

---

# 15. Rule Ownership

Rule sets should support organization-aware configuration where appropriate.

Do not assume every organization uses identical operational policies.

---

# 16. Rule Activation

Rule versions may have states such as:

```text
DRAFT
ACTIVE
RETIRED
```

Only an explicitly ACTIVE version may be used for production-style evaluation.

Do not silently activate modified rules.

---

# 17. Rule Safety

M4 rules may evaluate operational facts.

Examples:

```text
required capability exists
required capability availability
hospital receiving status
availability freshness
active restriction
required resource status
```

M4 rules must NOT encode unvalidated diagnosis/treatment logic.

---

# 18. Evaluation Outcome

A hospital candidate should resolve to one of:

```text
ELIGIBLE
INELIGIBLE
UNKNOWN
```

Avoid reducing everything to yes/no.

UNKNOWN is mandatory.

---

# 19. Meaning of UNKNOWN

UNKNOWN means MEDGRID lacks sufficient reliable information to establish eligibility.

Examples:

- availability missing;
- availability expired;
- receiving status unknown;
- required resource state unknown;
- snapshot unavailable.

UNKNOWN must never silently become ELIGIBLE.

---

# 20. Eligibility vs Preference

Separate hard eligibility from soft preference.

A hospital may be:

`ELIGIBLE`

without MEDGRID claiming it is the "best" hospital.

M4 must not produce a clinical winner.

---

# 21. No Ranking by Default

M4 must not assign:

```text
#1
#2
#3
```

based on medical suitability.

Do not implement an opaque score.

Candidate presentation may use neutral deterministic sorting for usability, such as:

- eligibility state;
- hospital name;

but this must not be presented as a medical recommendation.

---

# 22. Capability Evaluation

For each required capability:

check:

1. capability exists;
2. capability enabled;
3. current availability;
4. freshness;
5. expiration;
6. relevant restriction.

Preserve the evidence.

---

# 23. Example Capability Result

```text
Requirement:
NEUROSURGERY

Hospital:
MEDGRID Central

Capability:
PRESENT

Availability:
AVAILABLE

Freshness:
FRESH

Result:
PASS
```

---

# 24. Example Failure

```text
Requirement:
NEUROSURGERY

Hospital:
MEDGRID North

Capability:
PRESENT

Availability:
UNAVAILABLE

Reason:
STAFFING

Result:
FAIL
```

---

# 25. Example Unknown

```text
Requirement:
CT

Hospital:
MEDGRID Trauma Center

Capability:
PRESENT

Availability:
AVAILABLE

Freshness:
STALE

Result:
UNKNOWN
```

Exact stale handling must be defined by versioned rule configuration.

Do not hide it.

---

# 26. Receiving Evaluation

Hospital receiving state participates explicitly.

Example:

```text
OPEN
→ PASS

NOT_RECEIVING
→ FAIL

UNKNOWN
→ UNKNOWN
```

Treatment of LIMITED must be explicitly defined by rule-set configuration.

Do not guess.

---

# 27. Freshness Evaluation

M4 consumes M3 freshness semantics.

Do not duplicate incompatible freshness logic.

Use the authoritative M3 snapshot/freshness services.

---

# 28. Restrictions

Active operational restrictions must participate where relevant.

The engine must explain when a restriction affected eligibility.

---

# 29. Resource Evaluation

Resource checks are allowed only when explicitly included by the requirement/rule configuration.

Example:

```text
Resource:
ICU_BEDS

State:
AVAILABLE

Available capacity:
2
```

Unknown capacity remains UNKNOWN.

Zero remains zero.

Never convert one into the other.

---

# 30. Evaluation Snapshot

Every completed evaluation must preserve the operational facts it used.

Do NOT rely on reconstructing historical results solely from current hospital state.

Persist an immutable evaluation snapshot.

---

# 31. Snapshot Content

The evaluation snapshot should contain enough information to answer later:

- which hospitals were evaluated;
- what receiving status MEDGRID knew;
- which capabilities existed;
- their availability;
- freshness;
- resources used;
- restrictions used;
- timestamps;
- rule version;
- requirements.

Do not duplicate unnecessary PHI.

---

# 32. Historical Reproducibility

Later hospital updates must not alter the historical evaluation.

Example:

At 14:00:

```text
Hospital A
Neurosurgery AVAILABLE
```

At 14:20:

```text
Neurosurgery UNAVAILABLE
```

An evaluation completed at 14:05 must continue showing the 14:05 evidence.

---

# 33. DestinationEvaluation

Suggested fields:

```text
id
public_id

organization_id
emergency_case_id
patient_encounter_id

requirement_set_id
rule_set_version_id

status

reference_time
started_at
completed_at nullable

requested_by

created_at
```

---

# 34. Evaluation Status

Suggested:

```text
PENDING
RUNNING
COMPLETED
FAILED
CANCELLED
```

Do not expose partially calculated results as complete.

---

# 35. Candidate Evaluation

Conceptually:

```text
DestinationCandidateEvaluation
```

Suggested fields:

```text
id
destination_evaluation_id
hospital_id

outcome

explanation_summary
snapshot_data

created_at
```

Structured evidence should be preferred over explanation text alone.

---

# 36. Candidate Evidence

Use structured evidence such as:

```text
checks:
  - rule: RECEIVING_STATUS
    result: PASS
    observed: OPEN

  - rule: CAPABILITY_NEUROSURGERY
    result: PASS
    observed: AVAILABLE

  - rule: CAPABILITY_CT
    result: UNKNOWN
    observed: STALE
```

Explanation UI should be generated from structured facts.

---

# 37. Rule Execution Trace

Preserve a safe structured rule trace.

The trace should explain:

- rule identifier;
- rule version;
- input fact;
- outcome;
- reason.

Do not expose internal secrets or executable code.

---

# 38. Rule Identifiers

Rules require stable identifiers.

Example:

```text
DS-RECEIVING-001
DS-CAPABILITY-001
DS-FRESHNESS-001
DS-RESOURCE-001
```

These are MEDGRID identifiers.

---

# 39. Evaluation Failure

If evaluation cannot complete safely:

status becomes:

`FAILED`

with an operationally useful reason.

Do not return an incomplete candidate list as if it were authoritative.

---

# 40. Evaluation Concurrency

Avoid duplicate active evaluations for the same encounter/requirement-set where inappropriate.

Use idempotency/locking where justified.

Do not create uncontrolled duplicate evaluations due to retries.

---

# 41. Destination Selection

Introduce an explicit human action:

```text
SelectDestination
```

This occurs AFTER evaluation.

Selection is not evaluation.

---

# 42. DestinationSelection

Suggested fields:

```text
id
public_id

organization_id
emergency_case_id
patient_encounter_id

destination_evaluation_id
hospital_id

selected_by
selected_at

selection_type

override_reason nullable

created_at
```

---

# 43. Selection Type

Suggested:

```text
ELIGIBLE_SELECTION
UNKNOWN_OVERRIDE
INELIGIBLE_OVERRIDE
MANUAL_WITHOUT_EVALUATION
```

Exact naming may adapt.

The important point is preserving context.

---

# 44. Override

MEDGRID must not trap authorized clinicians/operators behind the engine.

An authorized human may select a destination outside the engine's eligible set when operational policy allows.

Such action must be deliberate and auditable.

---

# 45. Override Reason

Selecting an:

`UNKNOWN`

or:

`INELIGIBLE`

candidate should require a reason.

Do not silently permit override.

---

# 46. Manual Selection Without Evaluation

If operational policy permits destination selection when evaluation is unavailable:

support an explicit manual path.

Record:

- evaluation unavailable/not used;
- actor;
- hospital;
- reason;
- timestamp.

Do not fabricate an evaluation.

---

# 47. Destination Change

Destination may change after initial selection.

Preserve history.

Do not overwrite the previous selection.

Conceptually:

```text
DestinationSelection
DestinationSelectionSupersession
```

or append-oriented selection records.

---

# 48. Current Destination

Current destination may be a projection over selection history.

Historical destination selections remain preserved.

---

# 49. Case Lifecycle

M4 may extend case lifecycle from:

`DESTINATION_PENDING`

to:

`DESTINATION_SELECTED`

where consistent with the master lifecycle.

Do not implement hospital arrival/handover yet.

---

# 50. Patient Encounter

Destination evaluation is encounter-specific.

In multi-patient incidents, different patients may have different destination requirements and selections.

Never assume one EmergencyCase has one destination for every patient.

---

# 51. Medical Coordinator

M4 should introduce/strengthen a Medical Coordinator workspace.

Potential route:

`/medical`

Primary responsibilities:

- review encounter summary;
- review destination requirements;
- request evaluation;
- inspect candidate evidence;
- select destination where authorized;
- record override reasons.

---

# 52. Medical Coordinator UI

Conceptual layout:

```text
┌────────────────────────────────────────────────────────────┐
│ MEDGRID DESTINATION SUPPORT                     LIVE      │
├──────────────────────┬─────────────────────────────────────┤
│ CASE / PATIENT       │ REQUIREMENTS                        │
│ MG-2026-000184-P01   │ ✓ CT required                       │
│ Condition: CRITICAL  │ ✓ Neurosurgery required             │
├──────────────────────┴─────────────────────────────────────┤
│ DESTINATION CANDIDATES                                     │
│                                                            │
│ Central Hospital                         ELIGIBLE           │
│ Receiving OPEN                                             │
│ CT AVAILABLE · FRESH                                       │
│ Neurosurgery AVAILABLE · FRESH                             │
│ [View evidence] [Select]                                   │
│                                                            │
│ North Hospital                           INELIGIBLE         │
│ Neurosurgery UNAVAILABLE                                   │
│ [View evidence]                                            │
│                                                            │
│ Trauma Center                            UNKNOWN            │
│ CT availability STALE                                      │
│ [View evidence] [Override & select]                        │
└────────────────────────────────────────────────────────────┘
```

Conceptual only.

---

# 53. UI Language

Avoid:

```text
BEST
OPTIMAL
AI RECOMMENDED
95% MATCH
```

Prefer:

```text
ELIGIBLE
INELIGIBLE
UNKNOWN
```

with factual explanations.

---

# 54. Dispatch Integration

Dispatch may view:

- evaluation status;
- selected destination;
- destination changes;
- factual candidate state where authorized.

Do not allow unauthorized dispatcher roles to alter clinical requirements.

---

# 55. Ambulance Integration

Authorized ambulance personnel may view:

- selected destination;
- evaluation status;
- relevant explanation if permitted.

Do not overload Critical Mode.

Do not implement routing/ETA yet.

---

# 56. Hospital Integration

Once a human destination is selected, M4 may use the M3 HospitalCaseNotification foundation to notify the selected hospital.

Do not equate:

destination selected

with:

hospital acknowledged.

---

# 57. Selection to Notification

Conceptual flow:

```text
Human selects destination
        ↓
DestinationSelection persisted
        ↓
Case/encounter updated
        ↓
Timeline + audit
        ↓
HospitalCaseNotification created
        ↓
Realtime notification
```

All multi-record changes must be transactionally safe.

---

# 58. Hospital Acknowledgement

Hospital acknowledgement remains the M3 meaning:

`Notification received/acknowledged.`

It is not clinical acceptance.

---

# 59. Realtime Events

Potential M4 events:

```text
DestinationRequirementsChanged
DestinationEvaluationRequested
DestinationEvaluationCompleted
DestinationSelected
DestinationSelectionChanged
DestinationOverrideRecorded
```

Use minimal payloads.

---

# 60. Realtime Channels

Reuse protected channels:

```text
case.{caseId}
encounter.{encounterId}
hospital.{hospitalId}
user.{userId}
```

Add new channels only if justified.

---

# 61. Realtime Truth

Persist first.

Broadcast after commit.

Realtime event receipt triggers authoritative reload.

Do not use WebSocket state as the source of truth.

---

# 62. Authorization

Destination operations require granular permissions.

Suggested:

```text
destination_requirements.view
destination_requirements.manage

destination_evaluations.view
destination_evaluations.create

destination_selection.view
destination_selection.select
destination_selection.override
destination_selection.change

destination_rules.view
destination_rules.manage
destination_rules.activate
```

Adapt to existing RBAC conventions.

---

# 63. Clinical Authority

Do not grant destination requirement editing merely because a user can view the case.

Separate operational viewing from clinical/coordination authority.

---

# 64. Cross-Organization Isolation

Organization A must not:

- evaluate Organization B encounter;
- view Organization B evaluation;
- select Organization B destination;
- view Organization B candidate evidence;
- modify Organization B requirements;
- use Organization B private rule sets;
- trigger Organization B hospital notification.

Mandatory tests.

---

# 65. Hospital Access

Candidate evaluation may inspect hospitals made available to the evaluating organization according to MEDGRID network policy.

Do not bypass organization/hospital visibility rules accidentally.

If cross-organization network sharing is required, implement it explicitly rather than disabling tenant isolation.

---

# 66. Audit

Audit:

- requirement creation/change;
- evaluation request;
- rule version used;
- evaluation completion/failure;
- destination selection;
- override;
- destination change;
- manual selection without evaluation.

Do not copy entire patient record into audit metadata.

---

# 67. Timeline

Case timeline may include:

```text
Destination requirements confirmed
Destination evaluation completed
Destination selected
Destination changed
Hospital notified
Hospital acknowledged
```

Keep detailed rule traces outside the general operational timeline.

---

# 68. API

Extend `/api/v1`.

Potential routes:

```text
GET  /encounters/{encounter}/destination-requirements
POST /encounters/{encounter}/destination-requirements

POST /encounters/{encounter}/destination-evaluations
GET  /destination-evaluations/{evaluation}

GET  /destination-evaluations/{evaluation}/candidates
GET  /destination-candidates/{candidate}

POST /destination-evaluations/{evaluation}/select
POST /encounters/{encounter}/destination/manual

GET  /encounters/{encounter}/destination
```

Rule administration endpoints may exist under protected admin/API routes.

Follow actual MEDGRID conventions.

---

# 69. Thin Controllers

Controllers should orchestrate only:

authorization

→ validation

→ domain action/service

→ resource response.

Evaluation logic belongs in domain services.

---

# 70. Transactions

Use transactions for:

- evaluation persistence;
- destination selection;
- destination change;
- hospital notification creation;
- timeline/audit updates.

Use locks/versioning where needed.

---

# 71. Idempotency

Evaluation requests and selection requests should support safe retry where appropriate.

Repeated network submission must not accidentally:

- duplicate evaluation;
- duplicate selection;
- duplicate hospital notification.

---

# 72. Rule Storage

Do not store arbitrary executable PHP supplied through the UI.

Rules must use a controlled declarative structure.

No `eval`.

No dynamic code execution.

---

# 73. Declarative Rules

A safe rule representation may include:

```text
rule_type
target
operator
expected_value
failure_behavior
unknown_behavior
```

Exact schema should remain simple and auditable.

---

# 74. Rule Validation

Invalid or unsupported rule definitions must be rejected before activation.

Active rule versions should be immutable.

Changes create a new version.

---

# 75. Rule Activation

Activation must be explicit and authorized.

Audit:

- actor;
- version;
- timestamp.

Do not automatically activate a newly edited rule.

---

# 76. No Retrospective Mutation

Changing rules tomorrow must not change yesterday's evaluation.

Historical evaluations reference immutable rule versions and snapshots.

---

# 77. Snapshot Size

Persist enough evidence for reproducibility without blindly copying the entire hospital database.

Store only relevant operational facts.

---

# 78. PHI Minimization

Evaluation snapshots should primarily contain:

- requirements;
- hospital operational facts;
- rule evidence.

Do not copy unnecessary:

- patient names;
- CNP;
- clinical notes;
- complete assessments.

---

# 79. Failure Handling

If hospital snapshot generation fails:

do not silently omit that hospital and pretend evaluation is complete.

Represent failure/unknown according to deterministic rules.

---

# 80. Partial Network Failure

If one hospital cannot be evaluated but others can:

the engine may complete only if rule-set semantics explicitly support candidate-level UNKNOWN.

Do not hide missing candidates.

---

# 81. No External ETA Yet

Do not invent travel time.

If no routing provider exists:

display:

`ETA unavailable`

not:

`12 min`

---

# 82. Future Routing Compatibility

Design candidate evidence so M5 or later routing facts may eventually be added without rewriting the entire engine.

Do not implement them now.

---

# 83. Observability

Track operational metrics/logs such as:

- evaluation duration;
- evaluation failure;
- candidate UNKNOWN rate;
- stale hospital data encountered;
- override occurrence;
- rule version used;
- notification creation failure.

Do not log unnecessary PHI.

---

# 84. Security

Review:

- IDOR;
- tenant isolation;
- rule tampering;
- unauthorized rule activation;
- evaluation snapshot exposure;
- override permission;
- mass assignment;
- stale selection;
- notification duplication;
- realtime authorization.

---

# 85. Concurrency

Protect against:

- requirements changing while evaluation starts;
- destination selected twice concurrently;
- destination changed from stale UI;
- rule version changing during evaluation;
- hospital facts changing during evaluation.

The evaluation must operate against captured inputs.

---

# 86. Evaluation Input Capture

At evaluation start, establish the exact:

- requirement set;
- rule-set version;
- reference time.

Hospital facts used must be captured consistently.

Do not allow half of an evaluation to use old state and half to use newly updated state unpredictably.

---

# 87. Selection Concurrency

Use versioning/locking.

Example:

Operator A and B both see no destination.

A selects Hospital X.

B attempts Hospital Y from stale state.

Server must detect conflict rather than silently replacing X.

Return an explicit conflict.

---

# 88. Destination Change

Changing an already selected destination must use an explicit action and permission.

Require reason where appropriate.

Do not reuse initial selection endpoint as a silent overwrite.

---

# 89. Synthetic Rule Set

Seed a clearly synthetic demonstration rule set.

It may implement operational logic such as:

```text
Hospital receiving must not be NOT_RECEIVING.

Every REQUIRED capability must exist.

Every REQUIRED capability must have acceptable availability.

STALE required capability availability produces UNKNOWN.

UNKNOWN required capability availability produces UNKNOWN.

Active blocking restriction produces INELIGIBLE.
```

Clearly label the rule set as:

`MEDGRID DEMO — NOT A CLINICAL PROTOCOL`

---

# 90. Synthetic Evaluation Data

Seed/demo cases may contain explicitly selected requirements.

Example:

```text
CT REQUIRED
NEUROSURGERY REQUIRED
```

Do not imply MEDGRID inferred them clinically.

---

# 91. Tests — Requirements

Test:

- authorized creation;
- unauthorized creation;
- cross-org denial;
- requirement history;
- supersession;
- stable requirement set.

---

# 92. Tests — Rules

Test:

- version creation;
- activation;
- immutable active version;
- invalid rule rejected;
- unauthorized activation denied;
- old evaluations preserve old rule version.

---

# 93. Tests — Evaluation

Test at minimum:

- eligible hospital;
- unavailable capability → INELIGIBLE;
- missing capability → INELIGIBLE;
- unknown availability → UNKNOWN;
- stale availability → configured deterministic outcome;
- expired availability;
- receiving NOT_RECEIVING;
- receiving UNKNOWN;
- restriction;
- resource zero;
- resource unknown;
- deterministic repeat evaluation.

---

# 94. Tests — Snapshot

Test that after evaluation:

hospital operational state changes

but:

historical candidate evidence remains unchanged.

---

# 95. Tests — Selection

Test:

- eligible selection;
- UNKNOWN override requires permission/reason;
- INELIGIBLE override requires permission/reason;
- unauthorized override denied;
- manual selection behavior;
- destination history;
- stale concurrent selection conflict.

---

# 96. Tests — Notification

Test:

destination selection

→ exactly one appropriate HospitalCaseNotification.

Retry must not duplicate notification.

Hospital acknowledgement remains separate.

---

# 97. Tests — Multi-Patient

One EmergencyCase with two encounters must support:

Patient A → Hospital X

Patient B → Hospital Y.

No case-wide one-destination assumption.

---

# 98. Tests — Security

Test:

- evaluation IDOR;
- candidate evidence IDOR;
- requirement IDOR;
- selection IDOR;
- rule administration authorization;
- cross-org access;
- realtime authorization.

---

# 99. Tests — Realtime

Verify appropriate events are emitted only after persistence.

Cross-org subscription must fail.

Payloads should not expose unnecessary PHI.

---

# 100. UI / Visual QA

Verify:

- `/medical` desktop;
- laptop;
- tablet where practical;
- ELIGIBLE;
- INELIGIBLE;
- UNKNOWN;
- stale evidence;
- override flow;
- destination selected;
- destination changed;
- realtime reconnect/degraded state.

Do not claim unperformed QA.

---

# 101. Migration Safety

M4 must migrate forward normally from:

`v0.4.0`.

Do not require `migrate:fresh`.

Verify forward migration.

Verify fresh test database separately.

---

# 102. Backward Compatibility

All M0–M3 functionality must remain operational.

Existing tests must stay green.

Do not weaken M3 freshness semantics for easier M4 evaluation.

---

# 103. Performance

Review:

- hospital snapshot retrieval;
- candidate evaluation;
- rule evaluation;
- evidence loading;
- medical coordinator workspace.

Avoid N+1.

M4 does not need distributed compute infrastructure.

---

# 104. Evaluation Scale

Design for evaluating multiple hospitals efficiently.

Avoid one uncontrolled database query per rule per hospital where possible.

Prefer prepared snapshot data and in-memory deterministic evaluation after authorized data loading.

---

# 105. Documentation

Create/update:

- M4 implementation documentation;
- API documentation;
- ADRs where appropriate.

Strong ADR candidates:

- deterministic destination evaluation;
- immutable evaluation snapshots;
- human destination authority;
- declarative rule versioning.

---

# 106. Quality Verification

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

# 107. M4 Acceptance Criteria

M4 is complete only when:

## Requirements

- explicit destination requirements supported;
- provenance preserved;
- historical requirements not silently rewritten.

## Rules

- declarative rules exist;
- rules are versioned;
- activation explicit;
- historical versions immutable.

## Evaluation

- deterministic evaluation works;
- ELIGIBLE/INELIGIBLE/UNKNOWN supported;
- capability checks work;
- availability checks work;
- receiving checks work;
- freshness works;
- restrictions work;
- selected resource checks work.

## Evidence

- every candidate has structured explanation;
- rule IDs visible internally;
- historical evidence immutable.

## Human Authority

- engine does not autonomously select destination;
- authorized human selection required;
- override deliberate;
- override audited.

## Multi-Patient

- destination is encounter-specific.

## Notification

- selected hospital notification created safely;
- acknowledgement remains separate.

## Realtime

- evaluation/selection updates propagate appropriately;
- server state remains authoritative.

## Security

- tenant isolation;
- permission isolation;
- rule administration protected;
- override protected;
- evidence protected.

## Quality

- M0 tests pass;
- M1 tests pass;
- M2 tests pass;
- M3 tests pass;
- M4 tests pass;
- migrations pass;
- build passes;
- Pint passes;
- audits pass.

---

# 108. Forbidden Shortcuts

Do NOT:

- infer clinical requirements from symptoms without validated rules;
- create opaque scores;
- use AI ranking;
- label one hospital "best";
- automatically select destination;
- hide UNKNOWN;
- convert stale data to AVAILABLE;
- mutate completed evaluation evidence;
- mutate active rule versions;
- overwrite destination history;
- allow silent override;
- fabricate ETA;
- equate destination selection with hospital acknowledgement;
- equate acknowledgement with clinical acceptance;
- implement M5.

---

# 109. Final Verification Scenario

Verify:

```text
Case MG-...
        ↓
Patient Encounter P01
        ↓
Authorized coordinator enters:
CT REQUIRED
NEUROSURGERY REQUIRED
        ↓
Evaluation requested
        ↓
Rule version captured
        ↓
Hospital snapshots captured
        ↓
Central Hospital
CT AVAILABLE/FRESH
Neurosurgery AVAILABLE/FRESH
Receiving OPEN
→ ELIGIBLE
        ↓
North Hospital
Neurosurgery UNAVAILABLE
→ INELIGIBLE
        ↓
Trauma Center
CT STALE
→ UNKNOWN
```

Then:

```text
Coordinator inspects evidence
        ↓
Selects Central Hospital
        ↓
DestinationSelection persisted
        ↓
Case becomes DESTINATION_SELECTED
        ↓
HospitalCaseNotification created
        ↓
Hospital receives notification
```

No autonomous selection occurs.

---

# 110. Historical Verification

After evaluation:

change Central Hospital Neurosurgery to:

`UNAVAILABLE`.

Reload historical evaluation.

It must still show the facts captured when the evaluation occurred.

Then request a NEW evaluation.

The new evaluation must use the new hospital state.

---

# 111. Override Verification

Verify:

```text
Candidate = UNKNOWN
        ↓
User without override permission
        ↓
DENIED
```

Then:

```text
Authorized coordinator
        ↓
provides explicit reason
        ↓
selects UNKNOWN candidate
        ↓
override recorded
        ↓
audit recorded
        ↓
timeline updated
```

---

# 112. Concurrency Verification

Verify:

```text
Coordinator A loads no destination
Coordinator B loads no destination

A selects Hospital X
        ↓
selection version changes

B submits Hospital Y from stale state
        ↓
CONFLICT
```

No silent destination replacement.

---

# 113. Completion Report

At completion provide:

## Baseline

Exact v0.4.0 baseline.

## Implemented

## Destination Requirements

## Requirement Provenance

## Rule Architecture

## Rule Versioning

## Evaluation Engine

## Determinism

## Candidate Outcomes

## Evidence / Explainability

## Evaluation Snapshots

## Medical Coordinator UI

## Human Destination Selection

## Override Workflow

## Destination History

## Multi-Patient Behavior

## Hospital Notification Integration

## Realtime

## Authorization

## Audit / Timeline

## Concurrency

## Database / Migrations

## API

## Performance

## Observability

## Security Review

## UI / Visual QA

## Tests

Exact test/assertion/failure count.

## Build / Quality

Exact results.

## Known Limitations

## Git Status

## M4 Acceptance

Review every acceptance criterion.

Then STOP.

Do NOT begin M5.

Do NOT commit.

Do NOT tag.

Do NOT push.

---

# 114. Final Rule

Destination Support is not autonomous medical decision-making.

MEDGRID must preserve:

**explicit requirements → reliable hospital facts → deterministic rules → structured evidence → authorized human decision**

The system must always be able to answer:

**What did MEDGRID know?**

**Which rule was applied?**

**Why did this hospital receive this outcome?**

**Who made the final destination decision?**

If those questions cannot be answered reliably, the evaluation is not acceptable.