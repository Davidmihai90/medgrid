# M1 Dispatch implementation

## Scope

M1 implements dispatch intake, triage, unit visibility, assignment, truthful delivery acknowledgement, crew acceptance, cancellation/reassignment services, append-only timeline, audit records, private realtime channels, versioned API resources, and the Dispatch/Ambulance operational interfaces.

No patient record, clinical observation, routing, ETA, hospital workflow, destination support, or M2 functionality is included.

## API

All endpoints are under `/api/v1` and require authenticated, active, organization-scoped sessions:

- `GET|POST /cases`
- `GET /cases/{case}`
- `POST /cases/{case}/triage`
- `POST /cases/{case}/assignments`
- `PUT|DELETE /assignments/{assignment}`
- `POST /assignments/{assignment}/delivery`
- `POST /assignments/{assignment}/acceptance`

Cross-organization entity access returns 404. Invalid lifecycle operations return a structured 409 `DISPATCH_CONFLICT` response.

## Dispatch UI

The Dispatch board exposes assignment cancellation and atomic reassignment only when the authenticated dispatcher has the corresponding granular permission. Both actions are contextual to the active assignment, require an operational reason, and use an explicit confirmation step. The web controllers call the same transactional domain service as the API.

Reassignment preserves the cancelled assignment and creates a new pending assignment. Cancellation returns the case to `TRIAGED` and releases the vehicle. If a selected replacement becomes unavailable concurrently, the operation rolls back, the dispatcher receives a callsign-specific conflict message, and the redirected board reloads authoritative database state.
## Realtime

Private channels are `dispatch.{organizationId}`, `case.{caseId}`, and `vehicle.{vehicleId}`. Payloads contain identifiers, status, case version, correlation identifier, and event metadata only. Clients reload authoritative server state after operational events and after reconnect.

## Operations

Run the standard migration and synthetic seeders:

```powershell
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=DispatchDemoSeeder --force
```

The demo data is restricted to local/testing environments by the foundation seeder and uses fictional organizations, users, vehicles, and scenarios.
