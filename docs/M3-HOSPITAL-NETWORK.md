# MEDGRID M3 Hospital Network

## Scope

M3 adds explicit hospitals, departments, capability and resource catalogs, append-oriented operational reports, restrictions, Hospital Command, incoming notifications, acknowledgement, private realtime and hospital-scoped authorization. It does not rank facilities, recommend destinations, fabricate ETA or implement handover.

## Truth Model

Capability configuration and current availability are independent. Receiving status, capability availability and resource capacity preserve every report with effective time, server receive time, provenance and optional expiry. Missing and expired information projects as UNKNOWN. Configurable freshness labels are MEDGRID operational policy, not medical standards.

HospitalOperationalSnapshotService deterministically projects the latest report effective at a supplied reference time. PostgreSQL histories remain authoritative.

## Authorization

Hospital access requires the active organization, the granular permission and an explicit row in hospital_user_access. Knowing a hospital ULID or sharing its organization is insufficient. Cross-tenant and unassigned-hospital access is hidden with 404. The hospital.{hospitalId} channel uses the same policy.

## Concurrency and Idempotency

Every operational report submits expected_version. The service locks the hospital row and returns 409 HOSPITAL_STATE_CONFLICT for stale state. Successful changes increment the hospital version. Unique organization-scoped idempotency keys make retries safe.

## Incoming Cases

A HospitalCaseNotification is created only by an explicit workflow action. Hospital responses expose a minimized case summary. ACKNOWLEDGED means receipt was seen, not clinical acceptance, bed reservation, destination approval or handover. Notification and acknowledgement create case timeline facts while operational hospital history remains separate.

## UI and Recovery

/hospital is a desktop-first operational workspace with an authorized hospital selector, receiving truth, freshness, capability state, resources, restrictions, incoming cases and recent history. Realtime events contain identifiers and status summaries only. Reconnect reloads authoritative HTTP state.