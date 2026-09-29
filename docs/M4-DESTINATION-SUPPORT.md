# MEDGRID v0.5.0 — M4 Destination Support

## Baseline

M4 extends the verified M0-M3 foundation at tag `v0.4.0`. PostgreSQL/PostGIS, Redis, queues, Reverb, and the existing M3 hospital operational snapshot remain authoritative.

## Implemented

- Explicit encounter-level destination requirements with provenance and lifecycle.
- Stable requirement sets captured for each evaluation.
- Versioned declarative rule sets with draft, active, and retired states.
- Deterministic candidate evaluation with `ELIGIBLE`, `INELIGIBLE`, and `UNKNOWN`.
- Structured evidence and immutable hospital fact snapshots.
- Human destination selection, explicit override, manual path, and append-only change history.
- Transactional, idempotent hospital notification.
- Medical Coordinator workspace at `/medical`.
- Read-only destination projections in Dispatch and Ambulance.
- Versioned API and private realtime synchronization.

## Destination Requirements

Requirements are operational inputs, not diagnoses. M4 supports:

- `CAPABILITY_REQUIRED`
- `CAPABILITY_PREFERRED`
- `RESOURCE_REQUIRED`
- `RECEIVING_REQUIRED`

The primary M4 source is `MANUAL`. Replacing a requirement supersedes the old row; cancellation is explicit. Evaluations reference exact requirement rows through a stable requirement set.

## Requirement Provenance

Each requirement records organization, case, patient encounter, type, target code, importance, source, author, optional confirmer, notes, status, and optional superseded requirement.

## Rule Architecture

Rules are validated declarative JSONB. No PHP expressions, scripts, dynamic class names, or external decision sources are accepted. Configuration controls receiving, freshness, capability, resource, and active restriction semantics.

## Rule Versioning

Only an explicitly active version may evaluate. Activation is separate from draft creation. Activating a new version retires the prior active version for that rule set. Active and retired definitions are immutable.

## Evaluation Engine

The synchronous M4 engine:

1. locks the encounter;
2. captures active requirements;
3. validates the active organization-owned rule version;
4. loads only explicitly shared hospitals;
5. takes M3 operational snapshots at one UTC reference time;
6. evaluates structured checks;
7. persists all candidate evidence;
8. marks the evaluation complete atomically.

Hard result precedence is `FAIL -> INELIGIBLE`, otherwise `UNKNOWN -> UNKNOWN`, otherwise `ELIGIBLE`. Preferred requirements never turn a candidate ineligible.

## Determinism

A production evaluation uses a PostgreSQL `REPEATABLE READ` transaction and one reference time. The test suite already owns an outer transaction, so it uses that transaction snapshot instead of attempting an invalid nested isolation change.

## Candidate Outcomes

Candidate display order is neutral and deterministic. M4 does not calculate a medical score, rank hospitals, or identify a clinical winner.

## Evidence / Explainability

Each check stores a stable rule ID, rule version, observed fact, result, reason, requirement identifier, target code, and whether the check is hard or preferred.

## Evaluation Snapshots

Completed candidates retain the receiving state, capabilities, availability/freshness, resources, restrictions, timestamps, and hospital identity used at evaluation time. Later M3 updates do not change historical evidence.

## Medical Coordinator UI

The desktop-first responsive workspace provides case and patient selection, explicit requirement history, active rule execution, three-state candidates, expandable evidence, human selection, override/manual decisions, and destination history.

## Human Destination Selection

Evaluation never selects a hospital. Selection is a separate authenticated human action. Eligible selection, unknown override, ineligible override, and manual selection without evaluation are preserved as distinct facts.

## Override Workflow

Unknown, ineligible, and manual paths require `destination_selection.override` plus an explicit reason. Destination changes also require `destination_selection.change` and a reason.

## Destination History

Selections are append-oriented. A change supersedes the prior row and preserves both decisions. `patient_encounters.destination_version` provides optimistic concurrency protection.

## Multi-Patient Behavior

Requirements, evaluations, concurrency versions, current destinations, and selection history are encounter-specific. One emergency case can therefore route different patients independently.

## Hospital Notification Integration

Selection and notification occur within the same database transaction. Cross-organization notification is permitted only when an active `destination_hospital_access` agreement connects the case organization to the hospital. Notification idempotency is derived from the selection ID.

## Realtime

`destination.state.changed` uses private `case.{caseId}` and `destination.{encounterId}` channels. Payloads contain identifiers and state only. Clients reload authoritative HTTP state after events and reconnects.

## Authorization

M4 adds granular requirement, evaluation, selection, override/change, and rule-management permissions. The coordinator demo role receives M4 permissions explicitly. They are not added to the broad legacy `Permissions::All` grant set.

## Audit / Timeline

Requirement changes, completed evaluations, destination selections/changes, and hospital notification facts produce append-oriented case timeline entries and separate audit logs with correlation IDs.

## Concurrency

Encounter and case rows are locked for destination decisions. Stale destination versions return `409 DESTINATION_CONFLICT`. Partial candidate results are not exposed as completed.

## Database / Migrations

Migration `2026_09_29_040000_create_destination_support_tables.php` adds explicit hospital network access, requirements and stable sets, versioned rules, evaluations/candidates, append-only selections, and the `DESTINATION_SELECTED` case state. Migration `2026_09_29_040100_register_destination_permissions.php` registers the granular M4 grants for existing installations.

## API

See `docs/API.md`. All endpoints remain under `/api/v1`, use the authenticated web session, active/current organization middleware, validation, rate limiting, and structured resources.

## Performance

Candidate hospitals and M3 operational facts are eager loaded in bounded queries. Medical lists cap active cases and recent evaluations. Historical snapshots avoid live joins during later review.

## Observability

Audit/timeline metadata includes case, encounter, evaluation, rule version, hospital, candidate count, outcome context, and correlation identifiers without duplicating patient details.

## Security Review

Organization ownership is checked server-side. Rule versions and encounters must share an organization. Hospitals require explicit network access. The destination realtime channel is separate from the more sensitive clinical encounter channel.

## UI / Visual QA

The workspace is responsive for desktop, laptop, and tablet breakpoints. Status is expressed with text plus color, evidence is keyboard-accessible through native details controls, and actions use labeled controls.

## Tests

M4 adds 11 tests with 89 assertions covering requirements, organization isolation, all three outcomes, immutable snapshots, preference semantics, idempotency, selection/notification, override permission/reason, stale conflicts, destination history, multi-patient independence, rules, manual selection, draft rejection, workspace rendering, and realtime payloads.

Full regression result: 92 tests, 461 assertions.

## Build / Quality

- Pint applied to changed PHP files.
- Blade compilation passes.
- Vite production build passes.
- PostgreSQL migrations and local synthetic seeders pass.
- Composer and npm audits report no known vulnerabilities.

## Known Limitations

- No route, distance, traffic, or ETA computation in M4.
- No medical ranking or autonomous clinical recommendation.
- No hospital arrival, transport, handover, or M5 Live Operations behavior.
- Rule administration is API-based; the operational coordinator workspace consumes active versions.

## Git Status

No commit, tag, push, or release operation is performed by this completion pass.

## M4 Acceptance

The implemented M4 boundary is deterministic, explainable, auditable, organization-isolated, human-controlled, and independently testable.