# MEDGRID M2 Ambulance Architecture

## Scope

M2 extends the accepted dispatch mission into an ambulance workflow:

`UNIT_ACCEPTED -> EN_ROUTE_TO_SCENE -> ON_SCENE -> PATIENT_CONTACT -> ASSESSMENT -> DESTINATION_PENDING`.

Every transition is server-authorized against the accepted assignment, active crew membership, organization, and a granular permission. `DESTINATION_PENDING` only signals readiness for a later milestone and requires a completed assessment. M2 does not select a destination.

## Clinical Data Model

- `patients` stores minimized, evolvable identity with optimistic `version`.
- `patient_encounters` separates an emergency interaction from identity and supports multiple patients per case.
- Encounter numbers are generated while the case row is locked and protected by a unique case/index constraint.
- `vital_observations` is append-only. Measurement time and server record time are separate.
- Corrections create a replacement observation and mark the original as superseded.
- `assessment_templates` and immutable numbered `assessment_template_versions` define organization-owned forms.
- `assessments` retain the exact version used; completed responses cannot be rewritten.
- `clinical_notes` is an append-oriented encounter record.
- Unknown allergy, medication, and history states remain explicit and are never inferred as negative findings.

## Authorization

Clinical writes require all of:

1. active authenticated user;
2. active organization context;
3. matching resource organization;
4. accepted case assignment;
5. active membership in the assigned vehicle crew;
6. operation-specific permission.

Cross-organization resources are hidden with 404 at the HTTP boundary where applicable. A private `encounter.{id}` channel applies the same crew relationship through `PatientEncounterPolicy`.

## API

M2 extends `/api/v1` with explicit workflow, encounter, patient identity, vital, correction, condition, assessment, note, and synchronization operations. Responses avoid the national identifier and realtime payloads carry identifiers/state only.

## Realtime

Operational workflow transitions publish the existing minimal `dispatch.state.changed` envelope to dispatch, case, and active vehicle channels. Clinical changes publish `clinical.state.changed` to private case and encounter channels after persistence. Clients reload authoritative HTTP state after an event or reconnect.

## Offline Groundwork

Only vital creation is queued by the browser client in M2. Each operation includes a ULID, type, encounter target, payload, capture time, and device label. The server reauthenticates, reauthorizes, validates, and records only a payload hash plus processing metadata.

Outcomes are `ACCEPTED`, `DUPLICATE`, `CONFLICT`, or `REJECTED`. Accepted/duplicate operations leave the local queue. Conflict/rejected operations remain blocked and the UI displays `SYNC NEEDS REVIEW`. Mutable condition sync uses encounter versions and never applies last-write-wins.

## PWA Safety

The service worker caches only Vite build assets, the manifest, and the application icon. It explicitly bypasses navigation, login, broadcasting authorization, and all API requests.

The IndexedDB queue stores the minimum vital operation required for retry. It does not store passwords, CSRF tokens, session cookies, names, or national identifiers. IndexedDB is not application-level encrypted; security depends on browser profile and device controls. Production deployment requires managed-device policy, storage lifecycle/remote response decisions, and a deliberate conflict-resolution workflow.

## UI

The ambulance workspace is tablet-first and exposes mission progression, patient tabs, condition, latest vitals, observation history, quick entry, assessment, identity, and append-only notes. Critical Mode removes secondary identity/history/note controls while retaining patient, mission, condition, latest vitals, quick vital entry, assessment state, realtime state, and sync truth.

## Known M2 Limits

- Offline queue support is intentionally limited to additive vital creation.
- Blocked conflicts are surfaced but require an administrative reconciliation workflow in a later approved scope.
- No clinical thresholds, scoring, diagnosis, treatment, medication, routing, hospital, or destination decision logic exists.
- The seeded template is synthetic and non-authoritative.
