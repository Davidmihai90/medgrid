# MEDGRID API

## Conventions

All application endpoints are under `/api/v1` and require the authenticated web session, active account, current organization, CSRF protection, and API rate limiting. ULIDs identify domain resources. Errors follow Laravel JSON validation/HTTP semantics; authorization and tenant boundaries are enforced server-side.

## Dispatch

- `GET /cases`
- `POST /cases`
- `GET /cases/{case}`
- `POST /cases/{case}/triage`
- `POST /cases/{case}/assignments`
- `PUT /assignments/{assignment}`
- `DELETE /assignments/{assignment}`
- `POST /assignments/{assignment}/delivery`
- `POST /assignments/{assignment}/acceptance`

## Ambulance Workflow

- `POST /cases/{case}/workflow` with the exact next state.
- `GET /cases/{case}/encounters`
- `POST /cases/{case}/encounters`
- `GET /encounters/{encounter}`
- `PUT /encounters/{encounter}/patients/{patient}`
- `PUT /encounters/{encounter}/condition`

Mutable patient/encounter updates include `entity_version`.

## Vitals and Notes

- `GET /encounters/{encounter}/vitals`
- `POST /encounters/{encounter}/vitals`
- `POST /vitals/{vital}/correct`
- `POST /encounters/{encounter}/notes`

Vital creation preserves `measured_at` separately from server `recorded_at`. Blood pressure uses primary and secondary numeric values. GCS accepts a direct total or all three components. Glucose requires an explicit supported unit.

## Assessments

- `POST /encounters/{encounter}/assessments/{templateVersion}`
- `PUT /assessments/{assessment}/responses`
- `POST /assessments/{assessment}/complete`

An assessment references an exact template version. Required fields are checked at completion and completed responses are immutable in M2.

## Offline Sync

`POST /sync/operations`

Supported M2 operations:

- `VITAL_CREATE`
- `CONDITION_UPDATE` at the server layer; the browser UI queues only `VITAL_CREATE`.

Each operation carries `operation_id`, `operation_type`, `target_id`, optional `entity_version`, `payload`, `captured_at`, and optional device/session metadata. Results are returned per operation as `ACCEPTED`, `DUPLICATE`, `CONFLICT`, or `REJECTED`.


## Hospital Network

- `GET /hospitals`
- `POST /hospitals`
- `GET /hospitals/{hospital}`
- `GET /hospitals/{hospital}/snapshot`
- `GET /hospitals/{hospital}/departments`
- `POST /hospitals/{hospital}/departments`
- `GET /hospitals/{hospital}/capabilities`
- `POST /hospitals/{hospital}/capabilities`
- `PUT /hospital-capabilities/{capability}`
- `GET /hospitals/{hospital}/availability`
- `GET /hospitals/{hospital}/resources`
- `GET /hospitals/{hospital}/incoming`
- `POST /hospitals/{hospital}/receiving-status`
- `POST /hospital-capabilities/{capability}/availability`
- `POST /hospitals/{hospital}/resources/{resource}/state`
- `POST /hospitals/{hospital}/restrictions`
- `POST /hospital-notifications/{notification}/acknowledge`

Operational writes require an idempotency key and expected hospital version. Stale versions return `409 HOSPITAL_STATE_CONFLICT`. Expired or absent dynamic facts project as `UNKNOWN`.


## Destination Support

- `GET /encounters/{encounter}/destination`
- `POST /encounters/{encounter}/destination-requirements`
- `PUT /encounters/{encounter}/destination-requirements/{requirement}`
- `DELETE /encounters/{encounter}/destination-requirements/{requirement}`
- `POST /encounters/{encounter}/destination-evaluations`
- `POST /encounters/{encounter}/destination-selections`
- `GET /destination-rule-sets`
- `POST /destination-rule-sets`
- `POST /destination-rule-sets/{ruleSet}/versions`
- `POST /destination-rule-versions/{version}/activate`

Evaluations require an active organization-owned rule version and an idempotency key. Selections require `expected_destination_version` and an idempotency key. Unknown, ineligible, manual, and changed selections require the corresponding granular permission and an explicit reason. Stale versions and domain conflicts return `409 DESTINATION_CONFLICT`. Candidate responses include structured evidence and the immutable operational snapshot used by the engine.