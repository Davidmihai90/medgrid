# MEDGRID — System Architecture

**Document:** Architecture Specification  
**Version:** 1.0  
**Status:** Initial Architecture  
**Related:** `MASTER_SPEC.md`, `AGENTS.md`

---

# 1. Purpose

This document defines the technical architecture of MEDGRID.

MEDGRID is a real-time emergency medical coordination platform connecting:

- emergency dispatch;
- ambulance crews;
- medical coordinators;
- hospitals;
- authorized medical personnel;
- operational control centers;
- external emergency and healthcare systems.

The architecture must prioritize:

1. reliability;
2. security;
3. traceability;
4. realtime operation;
5. maintainability;
6. offline tolerance;
7. scalability;
8. interoperability;
9. explainability;
10. graceful degradation.

MEDGRID must initially remain practical to develop and operate while preserving clear boundaries that allow future components to be extracted into independent services.

---

# 2. Architectural Strategy

MEDGRID starts as a:

# Modular Monolith

with event-driven internal architecture.

This is intentional.

Do not begin MEDGRID as a large collection of microservices.

A modular monolith provides:

- simpler development;
- easier local setup;
- simpler transactions;
- easier testing;
- lower operational complexity;
- faster early development.

However, modules must maintain clear boundaries so high-load or specialized functionality can later be extracted.

Potential future service extraction includes:

- realtime gateway;
- communications/PTT;
- routing;
- telemetry;
- notifications;
- integration gateway;
- analytics.

---

# 3. High-Level Architecture

```text
                     EXTERNAL SYSTEMS
                           │
       ┌───────────────────┼────────────────────┐
       │                   │                    │
       ▼                   ▼                    ▼
 Emergency Call       Hospital Systems     Maps / Traffic
   Providers              Providers           Providers
       │                   │                    │
       └──────────────┬────┴────────────┬───────┘
                      │                 │
                      ▼                 ▼
                 INTEGRATION LAYER
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                       MEDGRID CORE                          │
│                                                             │
│  Identity        Dispatch          Emergency Cases          │
│  Organizations   Ambulance         Patient Encounters       │
│  Hospitals       Destination       Incident Rooms           │
│  Communications  Notifications     Audit                    │
│                                                             │
└──────────────┬──────────────┬──────────────┬─────────────────┘
               │              │              │
               ▼              ▼              ▼
          PostgreSQL        Redis         Queues
          + PostGIS
               │
               │
               ▼
          Persistent
          Domain State

               MEDGRID CORE
                    │
          ┌─────────┴─────────┐
          │                   │
          ▼                   ▼
       Realtime            HTTP/API
       Reverb
          │                   │
          └─────────┬─────────┘
                    ▼
              CLIENT LAYER
                    │
       ┌────────────┼───────────────┐
       ▼            ▼               ▼
    Dispatch     Ambulance        Hospital
    Desktop      Tablet/PWA       Desktop
       │            │               │
       └────────────┼───────────────┘
                    ▼
              Control Center
```

---

# 4. Technology Stack

## Backend

Laravel.

Use the stable Laravel version selected at project initialization and record the exact version in project documentation.

Do not upgrade major framework versions during a milestone without explicit approval.

---

## Database

PostgreSQL.

Primary persistent source of truth.

---

## Geospatial

PostGIS.

Used for:

- incident coordinates;
- hospital locations;
- vehicle locations where persisted;
- spatial queries;
- geographic filtering.

---

## Cache / Ephemeral State

Redis.

Used for:

- cache;
- queues;
- locks;
- rate limiting;
- presence/realtime support;
- ephemeral operational state.

Redis must not be the sole permanent source of critical clinical or operational information.

---

## Realtime

Laravel Reverb / WebSockets.

Used for:

- case updates;
- unit status;
- hospital updates;
- capacity changes;
- timeline notifications;
- Incident Room;
- presence;
- operational alerts.

---

## Queues

Laravel Queue.

Initial queue backend:

Redis.

Workers must be managed separately from HTTP processes in production.

---

## Frontend

Laravel-integrated frontend with responsive PWA capability.

The exact UI framework may be selected during M0 based on compatibility and maintainability.

The frontend architecture must support:

- reusable components;
- responsive layouts;
- tablet-first ambulance interface;
- desktop-first dispatch/hospital interfaces;
- realtime state updates;
- PWA functionality;
- offline operation groundwork.

Avoid unnecessary separation into a completely independent frontend application during initial development unless a clear architectural need emerges.

---

# 5. Application Areas

MEDGRID exposes several specialized application areas.

```text
/dispatch
/ambulance
/hospital
/medical
/control
/admin
```

These are not separate products.

They are role-oriented views over the same MEDGRID domain.

Authorization must be server-side.

A user must never gain access merely by knowing a route.

---

# 6. Domain Modules

The modular monolith should contain clearly separated domains.

Initial domains:

```text
Identity
Organizations
Dispatch
EmergencyCases
Patients
Ambulance
Hospitals
Destination
Realtime
IncidentRooms
Communications
Notifications
Integrations
Audit
```

Future:

```text
Analytics
Simulation
Intelligence
```

---

# 7. Suggested Application Structure

Do not force this exact structure where Laravel conventions provide a cleaner solution, but preserve domain boundaries.

Example:

```text
app/
│
├── Domain/
│   │
│   ├── EmergencyCases/
│   │   ├── Actions/
│   │   ├── Data/
│   │   ├── Enums/
│   │   ├── Events/
│   │   ├── Exceptions/
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Services/
│   │   └── States/
│   │
│   ├── Ambulance/
│   ├── Hospitals/
│   ├── Patients/
│   ├── Destination/
│   ├── IncidentRooms/
│   └── ...
│
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
│
├── Integrations/
│   ├── Contracts/
│   ├── EmergencyCall/
│   ├── Hospital/
│   ├── Routing/
│   └── Traffic/
│
├── Jobs/
├── Listeners/
├── Notifications/
├── Providers/
└── Support/
```

Do not create architecture ceremony without functional value.

Classes should exist because they represent actual responsibilities.

---

# 8. Request Flow

Standard application request:

```text
HTTP Request
      │
      ▼
Authentication
      │
      ▼
Authorization
      │
      ▼
Validation
      │
      ▼
Controller
      │
      ▼
Action / Domain Service
      │
      ▼
Domain Model / Database
      │
      ▼
Domain Event
      │
      ├─────────────► Timeline
      │
      ├─────────────► Audit
      │
      ├─────────────► Broadcast
      │
      └─────────────► Queue / Integration
      │
      ▼
Response
```

Controllers must remain thin.

---

# 9. Actions

Important domain operations should be explicit.

Examples:

```text
CreateEmergencyCase
AssignVehicleToCase
AcceptMission
MarkVehicleEnRoute
RecordPatientContact
RecordVitalSigns
CompleteAssessment
RequestDestinationEvaluation
EvaluateDestination
SelectDestination
OverrideDestination
AcknowledgeIncomingPatient
StartTransport
CompleteHandover
CloseEmergencyCase
```

Actions provide:

- clear business boundaries;
- easier authorization;
- easier testing;
- transaction boundaries;
- easier future service extraction.

---

# 10. Transactions

Operations affecting multiple records must use database transactions where atomicity is required.

Example:

```text
AssignVehicleToCase

BEGIN

Lock emergency case
Lock vehicle

Validate case state
Validate vehicle availability

Create assignment
Change vehicle state
Change case state
Create case timeline event

COMMIT

Dispatch domain event
```

Care must be taken when dispatching events from transactions.

Events that trigger external side effects should execute only after successful commit where appropriate.

---

# 11. Concurrency

MEDGRID must assume concurrent users.

Example:

Two dispatchers attempt to assign the same ambulance simultaneously.

This must not produce:

```text
Case A → Ambulance 142
Case B → Ambulance 142
```

unless explicitly supported.

Use:

- database constraints;
- transactions;
- row locking;
- optimistic concurrency;
- Redis locks

depending on workflow.

Database correctness must not depend solely on frontend state.

---

# 12. Domain Events

Events describe facts that already happened.

Correct:

`VehicleAssigned`

Incorrect:

`PleaseAssignVehicle`

Commands/actions request behavior.

Events describe completed behavior.

Events should contain identifiers and explicit payloads rather than unnecessary serialized model graphs.

---

# 13. Event Delivery

Different event consumers have different reliability requirements.

Example:

```text
Domain Event
      │
      ├── Persist timeline
      ├── Audit
      ├── Broadcast
      ├── Notification
      └── Integration
```

Persistence-critical effects should happen reliably within or directly around the domain transaction.

External calls should generally be asynchronous.

Future versions may introduce an outbox pattern where reliability requirements justify it.

---

# 14. Database as Source of Truth

PostgreSQL is authoritative for persistent MEDGRID state.

Realtime events notify clients that state changed.

They do not replace persistence.

Example:

Client misses:

`HospitalCapacityChanged`

After reconnecting, the client fetches current hospital state through API.

The system must recover without requiring every WebSocket message to have been received.

---

# 15. Core Data Relationships

Conceptual model:

```text
Organization
   │
   ├── Users
   ├── Vehicles
   └── Hospitals

EmergencyCase
   │
   ├── CaseEvents
   ├── VehicleAssignments
   ├── PatientEncounters
   ├── DestinationEvaluations
   ├── DestinationSelection
   └── IncidentRoom

Patient
   │
   └── PatientEncounters
          │
          ├── VitalSigns
          ├── Assessments
          └── Interventions

Hospital
   │
   ├── Departments
   ├── Capabilities
   ├── Resources
   └── CapacitySnapshots
```

Detailed database design evolves by milestone.

---

# 16. Identifiers

MEDGRID should distinguish:

## Internal identifier

Database primary key.

ULID is preferred for major domain entities unless a documented reason requires another strategy.

## Public/domain identifier

Human-facing identifier.

Example:

`MG-2026-000184`

Never rely on a human-readable identifier as the only database relationship key.

---

# 17. Case Number Generation

Human-facing case numbers require concurrency-safe generation.

Do not implement:

```text
last case + 1
```

without locking/sequence protection.

PostgreSQL sequences or another safe mechanism should generate numeric components.

Format example:

```text
MG-{YEAR}-{NUMBER}
```

Exact implementation is defined by the relevant milestone.

---

# 18. Time Architecture

Persist system timestamps in UTC.

Display timestamps using the appropriate operational timezone.

For Romania this will commonly be Europe/Bucharest, but timezone must not be hard-coded into domain assumptions if future deployment may cover other regions.

For client-captured events, preserve where needed:

```text
captured_at
received_at
```

Example:

Tablet records vitals offline at 18:42.

Server receives them at 18:49.

Both timestamps matter.

---

# 19. Geospatial Architecture

Use PostGIS types/functions for spatial operations.

Examples:

- incident point;
- hospital point;
- ambulance point;
- nearby facilities;
- operational regions.

Geographic proximity is not equivalent to road travel time.

PostGIS answers questions such as:

> Which hospitals are geographically nearby?

Routing provider answers:

> How long will the ambulance take to reach them?

---

# 20. Vehicle Telemetry

Vehicle location updates may become high-volume.

Do not design all telemetry as if it were ordinary administrative CRUD.

Initial architecture may persist selected position records in PostgreSQL.

Future scale may introduce:

- reduced persistence frequency;
- Redis current-position cache;
- telemetry stream;
- time-series storage.

Always distinguish:

`current operational position`

from:

`historical position archive`.

---

# 21. Destination Architecture

Destination support should be isolated in its own domain.

Conceptual flow:

```text
Emergency Case
      │
      ▼
Patient / Encounter
      │
      ▼
Configured Requirements
      │
      ▼
Destination Evaluation
      │
      ├── Hospital capabilities
      ├── Current availability
      ├── Capacity freshness
      ├── Location
      ├── Routing ETA
      └── Configured rules
      │
      ▼
Compatible Candidates
      │
      ▼
Human / Authorized Workflow
      │
      ▼
Destination Selection
```

Evaluation and selection are separate concepts.

---

# 22. Destination Snapshots

Destination evaluation must remain explainable after operational data changes.

Suppose at 18:52:

Hospital A:

```text
CT AVAILABLE
ICU LIMITED
ETA 8 min
```

At 19:10:

```text
CT UNAVAILABLE
ICU AVAILABLE
```

Historical evaluation must still explain what information existed at 18:52.

Therefore evaluation should store relevant snapshots rather than depending solely on current hospital records.

JSONB may be appropriate for immutable evaluation snapshots.

---

# 23. Integration Architecture

External integrations use contracts.

Example:

```text
RoutingProviderInterface
```

Methods may conceptually include:

```text
calculateRoute()
calculateEta()
getRouteAlternatives()
```

Implementation:

```text
MockRoutingProvider
FutureProviderA
FutureProviderB
```

Core domain code depends on the interface, not vendor SDK.

---

# 24. Integration Gateway

All external provider communication should eventually pass through a controlled integration layer.

Responsibilities:

- authentication;
- request mapping;
- response mapping;
- timeout;
- retry;
- rate limiting;
- circuit/degraded state;
- logging;
- health monitoring.

Vendor response formats must not leak deeply into MEDGRID domain models.

---

# 25. External IDs

Store external identifiers separately.

Example:

```text
integration_source
external_id
```

Do not replace MEDGRID internal IDs with third-party IDs.

---

# 26. Idempotency

External inbound operations must support idempotency.

Example:

112 adapter sends the same incident twice due to retry.

MEDGRID should not create two emergencies.

Possible approach:

```text
source = mock_112
external_id = ABC123
```

with uniqueness constraints.

---

# 27. Realtime Architecture

Realtime is separated into:

1. persistent domain changes;
2. realtime notification delivery.

Example:

```text
RecordVitalSigns
      │
      ▼
PostgreSQL
      │
      ▼
VitalSignsRecorded
      │
      ├── Timeline
      └── Broadcast
             │
             ▼
      Authorized clients
```

Broadcast payloads should be explicit and minimal.

Detailed realtime rules live in:

`docs/REALTIME.md`

---

# 28. Client State

Clients maintain a local representation of relevant server state.

But server state remains authoritative.

After reconnect:

```text
Reconnect
   │
   ▼
Authenticate
   │
   ▼
Rejoin authorized channels
   │
   ▼
Fetch current state
   │
   ▼
Reconcile local state
```

Do not assume replay of every missed WebSocket event.

---

# 29. Offline Architecture

Offline functionality is primarily required for ambulance clients.

Concept:

```text
USER ACTION
    │
    ▼
Local Operation
    │
    ▼
Offline Queue
    │
    ├── operation_uuid
    ├── operation_type
    ├── payload
    ├── captured_at
    └── entity_version
          │
          ▼
    NETWORK RETURNS
          │
          ▼
    Sync Endpoint
          │
          ▼
    Idempotency Check
          │
          ▼
    Validation
          │
          ▼
    Persist / Conflict
```

Offline support will be introduced incrementally.

M0 must not attempt full offline implementation.

Architecture must merely avoid blocking future support.

---

# 30. Offline Security

Offline data is sensitive.

Do not store an unlimited patient cache.

Offline storage should eventually support:

- minimum necessary dataset;
- expiration;
- logout cleanup;
- mission completion cleanup;
- encryption where technically appropriate;
- device/session binding.

Detailed requirements belong to security/offline milestone specifications.

---

# 31. Authentication Architecture

Authentication is centralized through Laravel authentication infrastructure.

Architecture must support future:

- MFA;
- enterprise identity provider;
- SSO;
- device/session management.

Do not tightly couple domain logic to one authentication UI package.

---

# 32. Authorization Architecture

Authorization consists of:

```text
Role
   │
   ▼
Permissions
   │
   ▼
Policy
   │
   ▼
Resource Context
```

Example:

A user possessing:

`cases.view`

does not automatically mean they may view every case in MEDGRID.

Policy also considers:

- organization;
- assignment;
- hospital relationship;
- incident participation;
- operational context.

---

# 33. Emergency Access / Break Glass

Future versions may require emergency-access mechanisms.

Example:

A doctor who normally lacks access to a case may require emergency access.

If implemented, it must be explicit:

```text
BREAK GLASS
      │
      ▼
Reason required
      │
      ▼
Elevated temporary access
      │
      ▼
High-priority audit record
      │
      ▼
Security review capability
```

Do not implement Break Glass during M0 unless specifically required.

Architecture must leave room for it.

---

# 34. Audit Architecture

Audit logging is independent of operational case timeline.

Case timeline answers:

> What happened during this emergency?

Audit answers:

> Who accessed or changed protected information and what did they do?

Audit should be append-oriented and protected.

---

# 35. Notifications Architecture

Notification flow:

```text
Domain Event
    │
    ▼
Notification Policy
    │
    ├── Realtime
    ├── Push
    ├── In-App
    └── External channel
```

Do not send every domain event to every user.

Recipients depend on:

- organization;
- role;
- assignment;
- case participation;
- severity;
- notification preference where appropriate.

---

# 36. Communications Architecture

Incident communication contains:

- text messages;
- structured quick messages;
- system events;
- PTT.

Text and structured messages may use Laravel persistence/realtime infrastructure.

Audio should use specialized realtime media infrastructure.

Do not route continuous audio through Laravel controllers.

---

# 37. PTT Conceptual Architecture

Future architecture:

```text
Ambulance Tablet
       │
       ▼
WebRTC Media Layer
       │
       ▼
Authorized Incident Channel
       │
       ├── Dispatch
       ├── Hospital
       └── Medical Coordinator

Laravel
   │
   ├── Authentication
   ├── Authorization
   ├── Channel membership
   ├── Session metadata
   └── Audit metadata
```

Media transport and business authorization remain separate concerns.

---

# 38. PWA Architecture

PWA components may include:

```text
Service Worker
Local Cache
Offline Operation Store
Sync Manager
Realtime Client
API Client
Authentication State
```

Service-worker updates must not unexpectedly interrupt an active emergency workflow.

Application update strategy must eventually account for active missions.

---

# 39. API Architecture

Version API:

```text
/api/v1
```

Potential future:

```text
/api/v2
```

Never silently introduce breaking API behavior within the same version where external clients depend on it.

API controllers should use:

- authentication;
- policies;
- Form Requests;
- Resources/DTOs;
- domain actions.

---

# 40. API Response Design

Use predictable responses.

Example:

```json
{
  "data": {},
  "meta": {}
}
```

Errors should have stable machine-readable codes where useful.

Example:

```json
{
  "error": {
    "code": "INVALID_CASE_TRANSITION",
    "message": "The requested case transition is not allowed."
  }
}
```

Do not expose internal exception details.

---

# 41. Health Architecture

Provide health information for infrastructure components.

Potential checks:

```text
Application
Database
Redis
Queue
Realtime
External integrations
```

Do not expose sensitive infrastructure details publicly.

Separate:

`liveness`

from:

`readiness`

where deployment architecture benefits from it.

---

# 42. Graceful Degradation

External dependency failure must not automatically make all MEDGRID unusable.

Example:

## Routing unavailable

Continue:

- case documentation;
- patient assessment;
- hospital communication where available.

Display:

`LIVE ROUTING UNAVAILABLE`

Do not fabricate ETA.

## Realtime unavailable

Allow persistent operations where safe.

Display connection state.

Attempt controlled reconnect.

## Hospital integration unavailable

Display information as unavailable/stale.

Allow authorized manual workflows where defined.

---

# 43. Background Jobs

Jobs should be:

- focused;
- retry-safe;
- idempotent where possible;
- observable.

Examples:

```text
SendHospitalNotification
SyncExternalHospitalState
RefreshRouteETA
ProcessIntegrationWebhook
SendCriticalPushNotification
```

Do not place long-running external operations inside synchronous HTTP requests unless required.

---

# 44. Scheduler

Laravel scheduler may perform:

- stale availability detection;
- integration health checks;
- cleanup of temporary data;
- notification escalation;
- maintenance operations.

Scheduler tasks must not silently modify medical records without explicit domain behavior.

---

# 45. Cache Strategy

Cache only when useful.

Potential cache:

- reference data;
- permissions;
- selected hospital metadata;
- current vehicle positions;
- configuration.

Do not cache sensitive patient information without a clear need and lifecycle.

Cache invalidation must be explicit for operational data.

---

# 46. File Storage

Future cases may include attachments.

Use abstracted Laravel filesystem.

Possible storage:

- local development;
- S3-compatible object storage in production.

Files require:

- authorization;
- MIME validation;
- size limits;
- malware/security considerations;
- retention rules;
- non-public storage by default.

Never store sensitive medical attachments directly in a public web directory.

---

# 47. Search

Initial search should use PostgreSQL where sufficient.

Do not introduce Elasticsearch/OpenSearch during Foundation without demonstrated need.

Future search infrastructure may be introduced for:

- large patient datasets;
- operational search;
- audit search.

---

# 48. Reporting and Analytics

Operational analytics must not burden critical transactional paths.

Future architecture may introduce:

```text
Primary DB
    │
    ▼
Event / ETL pipeline
    │
    ▼
Analytics Store
```

Do not execute heavy historical reporting queries on critical operational tables during emergencies where avoidable.

---

# 49. Simulation Architecture

Simulation should eventually behave like external reality through the same contracts.

Example:

```text
EmergencyCallProviderInterface
       ▲
       │
MockEmergencyCallProvider
```

This allows realistic testing without special-case application code.

Simulation must remain clearly distinguishable from production operation.

---

# 50. Environment Architecture

Expected environments:

```text
local
testing
staging
production
```

Potential future:

```text
simulation
training
```

Production and training data must never be casually mixed.

---

# 51. Configuration

Use environment variables for deployment-specific configuration.

Use application configuration for stable behavior.

Use database-backed configuration only when administrators genuinely need runtime control.

Do not put business rules directly in `.env`.

---

# 52. Feature Flags

Feature flags may be introduced for high-risk or incremental functionality.

Examples:

```text
ptt_enabled
offline_sync_enabled
destination_engine_enabled
simulation_enabled
```

Feature flags must not be used as a substitute for proper authorization.

---

# 53. Security Boundaries

Primary trust boundaries:

```text
Internet / Client
      │
      ▼
MEDGRID Application
      │
      ▼
Domain / Database

MEDGRID
      │
      ▼
External Provider

Ambulance Device
      │
      ▼
Offline Storage
```

Every boundary requires explicit validation and authorization.

Detailed controls live in:

`docs/SECURITY.md`

---

# 54. Secrets Architecture

Secrets remain outside source control.

Use environment/secret-management mechanisms.

Never place:

- API keys;
- DB passwords;
- signing keys;
- private certificates;
- production credentials

inside repository files.

---

# 55. Observability

Application must support:

- structured logging;
- error tracking;
- queue monitoring;
- integration monitoring;
- realtime monitoring;
- health checks;
- performance metrics.

Use correlation IDs where appropriate.

Example:

```text
request_id
correlation_id
case_id
```

Do not include sensitive patient details in general logs.

---

# 56. Deployment Evolution

Initial deployment may be simple:

```text
Reverse Proxy
      │
      ▼
Laravel App
      │
      ├── PostgreSQL/PostGIS
      ├── Redis
      ├── Queue Worker
      └── Reverb
```

Future:

```text
Load Balancer
      │
 ┌────┴────┐
 ▼         ▼
App       App
 │         │
 └────┬────┘
      │
 PostgreSQL
 Redis Cluster
 Queue Workers
 Reverb Nodes
 Media Infrastructure
```

Do not optimize M0 for hypothetical national scale at the cost of maintainability.

Do avoid architectural decisions that obviously prevent horizontal scaling.

---

# 57. Service Extraction Strategy

A module may later become a service when:

- load profile differs substantially;
- deployment lifecycle differs;
- reliability boundary requires isolation;
- technology requirements differ;
- team ownership requires separation.

Likely candidates:

```text
Telemetry Service
Realtime Gateway
PTT Media Service
Routing Service
Integration Gateway
Notification Service
Analytics Service
```

Extraction should happen based on evidence, not fashion.

---

# 58. Failure Philosophy

MEDGRID must prefer explicit uncertainty over fabricated certainty.

If data is unknown:

Display:

`UNKNOWN`

If availability is stale:

Display:

`STALE — last confirmed 18 minutes ago`

If route provider fails:

Display:

`LIVE ETA UNAVAILABLE`

Never silently substitute guessed operational information.

---

# 59. Data Freshness

Realtime operational information must include freshness.

Example:

```text
CT
AVAILABLE

Last confirmed:
42 seconds ago
```

If freshness exceeds configured thresholds:

```text
CT
STATUS STALE
```

Thresholds must be configurable and must not be invented as medical rules.

---

# 60. Architectural Decision Records

Significant decisions should be documented in:

```text
docs/adr/
```

Format:

```text
0001-title.md
```

Each ADR contains:

- Context
- Decision
- Alternatives
- Consequences
- Status

Potential initial ADRs:

```text
0001-modular-monolith.md
0002-postgresql-postgis.md
0003-redis-queues-cache.md
0004-laravel-reverb.md
0005-ulid-identifiers.md
```

Do not create dozens of trivial ADRs.

---

# 61. M0 Architectural Boundary

M0 Foundation establishes infrastructure.

M0 should NOT implement:

- real emergency dispatch;
- patient clinical workflows;
- destination engine;
- hospital capacity workflows;
- maps;
- live vehicle tracking;
- offline synchronization;
- PTT;
- external 112 integration.

M0 prepares the architecture required for them.

---

# 62. M0 Expected Foundation

M0 should establish:

```text
Laravel
PostgreSQL
PostGIS
Redis
Authentication
Organizations
Users
Roles
Permissions
Policies
Application layouts
Domain structure
Queue infrastructure
Reverb infrastructure
Base audit infrastructure
Health checks
Synthetic seed data
Testing infrastructure
```

Exact implementation is defined in:

`docs/milestones/M0-foundation.md`

---

# 63. Architectural Invariants

The following should remain true throughout MEDGRID development.

### Invariant 1

Persistent medical/operational state is not stored exclusively in Redis.

### Invariant 2

Frontend authorization is never trusted as the security boundary.

### Invariant 3

Clinical history is not silently overwritten.

### Invariant 4

Destination evaluation remains explainable.

### Invariant 5

External vendors remain behind interfaces/adapters.

### Invariant 6

Realtime delivery is not the source of truth.

### Invariant 7

Organization isolation is enforced server-side.

### Invariant 8

Missing realtime information is never fabricated.

### Invariant 9

Important actions are traceable.

### Invariant 10

Human medical authority remains explicit.

---

# 64. Architectural North Star

The architecture should make this flow possible:

```text
Emergency Call
      │
      ▼
Emergency Case
      │
      ▼
Dispatch
      │
      ▼
Ambulance
      │
      ▼
Patient Encounter
      │
      ▼
Realtime Clinical Information
      │
      ▼
Destination Evaluation
      │
      ▼
Authorized Destination Selection
      │
      ▼
Hospital Preparation
      │
      ▼
Live Transport
      │
      ▼
Patient Handover
      │
      ▼
Completed Auditable Case
```

Every major architectural decision should support this flow while maintaining security, reliability, traceability and human medical authority.