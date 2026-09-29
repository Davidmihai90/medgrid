# MEDGRID — Realtime Architecture

**Document:** Realtime Architecture Specification  
**Version:** 1.0  
**Status:** Initial Realtime Architecture  
**Related:** `MASTER_SPEC.md`, `ARCHITECTURE.md`, `SECURITY.md`, `AGENTS.md`

---

# 1. Purpose

Realtime communication is a core capability of MEDGRID.

The system must allow authorized dispatchers, ambulance crews, hospitals, medical coordinators and control centers to share operational information with minimal delay.

Realtime functionality includes:

- emergency case updates;
- ambulance assignments;
- ambulance operational status;
- patient observation notifications;
- hospital availability changes;
- destination workflow updates;
- ETA updates;
- vehicle positions;
- Incident Room activity;
- presence;
- operational alerts;
- connection state;
- future Push-to-Talk signaling.

Realtime transport must never become the sole source of truth.

Persistent domain state remains authoritative in PostgreSQL.

---

# 2. Core Principle

MEDGRID follows:

```text id="bml7qo"
WRITE
  │
  ▼
SERVER VALIDATION
  │
  ▼
DATABASE
  │
  ▼
DOMAIN EVENT
  │
  ▼
REALTIME BROADCAST
  │
  ▼
AUTHORIZED CLIENTS
```

Not:

```text id="9gm1ya"
CLIENT
  │
  ▼
WEBSOCKET
  │
  ▼
OTHER CLIENT
```

for authoritative medical or operational state.

The server validates and persists important state before other clients treat it as authoritative.

---

# 3. Initial Technology

Initial realtime infrastructure:

**Laravel Reverb**

with Laravel Broadcasting.

Redis may support:

- queues;
- presence-related infrastructure;
- locks;
- caching;
- selected ephemeral state.

Laravel Echo or the selected compatible client library may be used on the frontend.

---

# 4. Realtime Components

Conceptual architecture:

```text id="ylh8c8"
                 PostgreSQL
                     ▲
                     │
                 Laravel Core
                     │
           ┌─────────┼─────────┐
           │         │         │
           ▼         ▼         ▼
        Events     Queues    Reverb
                               │
                               ▼
                     Authorized Channels
                               │
              ┌────────────────┼────────────────┐
              ▼                ▼                ▼
           Dispatch         Ambulance        Hospital
              │                │                │
              └────────────────┼────────────────┘
                               ▼
                         Control Center
```

---

# 5. Realtime Responsibilities

Realtime transport is responsible for:

- notifying clients of changes;
- delivering operational events;
- presence;
- low-latency UI synchronization;
- operational alerts.

Realtime transport is NOT responsible for:

- permanent case storage;
- permanent clinical storage;
- authorization source of truth;
- historical reconstruction;
- reliable offline storage;
- replacing APIs.

---

# 6. Channel Types

Use:

## Private Channels

For protected operational information.

## Presence Channels

Where authorized participant presence is useful.

Avoid public channels for protected MEDGRID data.

---

# 7. Channel Naming

Initial naming convention:

```text id="39ws5a"
organization.{organizationId}

dispatch.{organizationId}

vehicle.{vehicleId}

hospital.{hospitalId}

case.{caseId}

incident-room.{roomId}

user.{userId}
```

Potential future channels:

```text id="1d86sc"
region.{regionId}

control-center.{organizationId}

integration-health.{organizationId}
```

Identifiers should use public opaque identifiers where practical.

---

# 8. Channel Authorization

Every private/presence channel requires server-side authorization.

Example:

User requests subscription:

```text id="77sdkw"
private-case.01JXYZ...
```

Server evaluates:

```text id="nx6rxp"
Authenticated?
      │
      ▼
Account active?
      │
      ▼
Permission?
      │
      ▼
Authorized relationship to case?
      │
      ▼
YES → Subscribe
NO  → Reject
```

Knowing the channel identifier never grants access.

---

# 9. Organization Channel

`organization.{organizationId}`

Used for broad organization-level operational events.

Potential events:

- organization configuration changed;
- vehicle availability changed;
- operational announcement;
- integration state changed.

Do not broadcast patient details to broad organization channels unless explicitly justified.

---

# 10. Dispatch Channel

`dispatch.{organizationId}`

Subscribers:

Authorized dispatch personnel.

Potential events:

- new case;
- case priority changed;
- vehicle status changed;
- assignment accepted;
- case escalation;
- hospital operational change relevant to dispatch.

---

# 11. Vehicle Channel

`vehicle.{vehicleId}`

Subscribers may include:

- currently assigned crew;
- authorized dispatch;
- selected operational supervisors.

Potential events:

- mission assigned;
- mission changed;
- mission cancelled;
- destination selected;
- route update;
- dispatch message.

Do not use this channel as a permanent history store.

---

# 12. Hospital Channel

`hospital.{hospitalId}`

Subscribers:

Authorized hospital personnel.

Potential events:

- incoming patient;
- ETA update;
- transport started;
- patient condition update according to disclosure policy;
- case cancelled/rerouted;
- resource status changed.

---

# 13. Case Channel

`case.{caseId}`

This is the primary realtime channel for a specific emergency.

Potential subscribers:

- assigned dispatchers;
- assigned ambulance crew;
- medical coordinator;
- authorized receiving hospital personnel;
- authorized supervisors.

Potential events:

- case status;
- assignment;
- patient encounter changes;
- vital observation notification;
- destination workflow;
- transport;
- arrival;
- handover.

---

# 14. Incident Room Channel

`incident-room.{roomId}`

Usually a Presence Channel.

Used for:

- participant presence;
- operational messages;
- quick messages;
- communication state;
- PTT signaling metadata.

Incident Room access is case-scoped and permission-controlled.

---

# 15. User Channel

`user.{userId}`

Used for user-specific notifications.

Examples:

- assignment;
- permission-sensitive notification;
- session/device warning;
- acknowledgement request.

A user may subscribe only to their own user channel unless explicitly authorized otherwise.

---

# 16. Event Naming

Realtime event names should represent domain facts.

Examples:

```text id="7cxf13"
case.created
case.status.changed
case.priority.changed

vehicle.assigned
vehicle.status.changed

patient.contact.established
patient.vitals.recorded
patient.condition.changed

destination.evaluation.completed
destination.selected

hospital.incoming.created
hospital.incoming.acknowledged
hospital.capacity.changed

transport.started
transport.eta.updated
transport.arrived

handover.started
handover.completed

incident.message.created

system.connection.warning
```

Internal PHP event class names may use PascalCase.

---

# 17. Event Envelope

Realtime events should use a predictable envelope.

Example:

```json id="wm11xu"
{
  "event_id": "01J...",
  "event": "case.status.changed",
  "occurred_at": "2026-09-27T17:52:31Z",
  "case_id": "01J...",
  "correlation_id": "01J...",
  "version": 1,
  "data": {}
}
```

Not every event requires `case_id`.

---

# 18. Event ID

Every significant realtime event should have a unique event identifier.

Preferred:

ULID.

This assists:

- debugging;
- deduplication;
- tracing;
- client event processing.

---

# 19. Event Version

Realtime payloads should include a schema version where useful.

Example:

```json id="ixnnzs"
"version": 1
```

If payload structure changes incompatibly later, event versioning allows controlled evolution.

---

# 20. Correlation ID

Related operations should share a correlation ID where useful.

Example:

```text id="ob3d5z"
DestinationSelected
      │
      ├── Hospital notification
      ├── Incident Room event
      ├── Audit entry
      └── Push notification
```

These can be traced using the same correlation identifier.

---

# 21. Minimal Payload Principle

Broadcast only what the receiving client needs.

Avoid:

```text id="oj1j2l"
serialize complete EmergencyCase
serialize complete Patient
serialize complete User
```

Prefer explicit payloads.

Example:

```json id="qf1tfq"
{
  "event": "patient.vitals.recorded",
  "case_id": "01J...",
  "observation_id": "01J...",
  "captured_at": "2026-09-27T17:55:02Z"
}
```

Authorized clients can fetch detailed state if necessary.

---

# 22. Sensitive Payloads

Realtime payloads must follow the same authorization/data-minimization requirements as HTTP responses.

WebSocket transport does not make information less sensitive.

Do not expose unnecessary:

- patient identity;
- medical history;
- national identifiers;
- contact details.

---

# 23. Persist Before Broadcast

For authoritative state:

```text id="2nxhkj"
Validate
   ↓
Authorize
   ↓
Persist
   ↓
Commit
   ↓
Broadcast
```

Clients should not receive successful authoritative updates for transactions that later roll back.

Use after-commit behavior where appropriate.

---

# 24. Optimistic UI

Optimistic UI may be used for low-risk interactions.

It must not create false certainty for critical actions.

For example, destination selection should not display:

`DESTINATION CONFIRMED`

until server confirmation exists.

Possible temporary state:

```text id="rfm9j5"
CONFIRMING...
```

then:

```text id="rcxq31"
CONFIRMED
```

or:

```text id="p5b90n"
FAILED — RETRY
```

---

# 25. Delivery Semantics

WebSocket delivery should be treated as potentially:

- delayed;
- duplicated;
- missed;
- reordered during reconnection.

Clients must not assume exactly-once delivery.

Domain/API architecture must remain correct under these conditions.

---

# 26. Idempotent Event Processing

Clients should be capable of ignoring duplicate events.

Use:

`event_id`

as a deduplication aid where appropriate.

Example:

```text id="x2zgdu"
received event 01JABC
processed

received event 01JABC again
ignored
```

---

# 27. Ordering

Global event ordering is not assumed.

Ordering within a case may be reconstructed using:

- authoritative timestamps;
- entity versions;
- timeline sequence;
- server state.

Do not rely exclusively on arrival order.

---

# 28. Entity Version

Concurrency-sensitive entities may expose a version.

Example:

```json id="6irkrz"
{
  "case_id": "01J...",
  "entity_version": 14
}
```

If client has version 12 and receives version 14 without 13, it may request current state.

---

# 29. Missed Events

Clients must recover from missed events.

Flow:

```text id="f16ykf"
Connection lost
      ↓
Events happen
      ↓
Client reconnects
      ↓
Re-authenticate
      ↓
Rejoin channels
      ↓
Fetch authoritative current state
      ↓
Reconcile UI
```

MEDGRID does not initially require indefinite WebSocket event replay.

---

# 30. Connection States

Frontend should explicitly represent connection health.

Initial states:

```text id="ey8e6r"
LIVE
RECONNECTING
DEGRADED
OFFLINE
```

Potential UI:

```text id="ayzqv7"
● LIVE
```

or:

```text id="03iyfn"
⚠ RECONNECTING
```

or:

```text id="wq5jjv"
OFFLINE MODE
```

Never make users assume data is live when realtime connectivity is unavailable.

---

# 31. LIVE

Means:

- network connectivity exists;
- authenticated realtime connection exists;
- expected channels are subscribed;
- API is reachable.

It does not necessarily mean every external integration is healthy.

---

# 32. RECONNECTING

Used when realtime connection is temporarily interrupted.

During reconnect:

- preserve current screen;
- avoid unnecessary interruption;
- queue appropriate local actions according to feature rules;
- attempt controlled reconnect.

---

# 33. DEGRADED

Used when core application is reachable but one or more realtime/operational dependencies are impaired.

Examples:

- Reverb unavailable;
- routing unavailable;
- hospital integration stale.

UI should identify relevant degradation rather than showing a generic error where possible.

---

# 34. OFFLINE

Ambulance clients may enter OFFLINE mode when server connectivity is unavailable.

Offline mode must be visually unmistakable.

Example:

```text id="1pnlm1"
OFFLINE
Last synchronized: 18:52:11
```

Do not show stale hospital availability as live.

---

# 35. Reconnection Strategy

Use controlled exponential backoff with jitter where supported.

Conceptually:

```text id="rmyfb2"
disconnect
   ↓
short retry
   ↓
retry
   ↓
increasing delay
   ↓
connection restored
```

Do not create aggressive reconnect loops that overload infrastructure.

---

# 36. Reconnection Recovery

After connection restoration:

1. verify authentication/session;
2. restore authorized subscriptions;
3. fetch authoritative active-case state;
4. reconcile client state;
5. synchronize permitted offline operations;
6. report conflicts/failures;
7. return to LIVE only after critical synchronization succeeds.

---

# 37. Heartbeat

Realtime infrastructure should support heartbeat/connection health mechanisms.

The application may additionally track operational client heartbeat where useful.

Potential use:

```text id="hwqggq"
Ambulance tablet
last_seen_at
```

Do not interpret missing application heartbeat as proof of physical emergency.

It indicates connectivity/application state only.

---

# 38. Presence

Presence is useful in Incident Rooms.

Example:

```text id="2w3g45"
Incident Room

Dispatcher 14      ONLINE
Ambulance B-142    ONLINE
Hospital Operator  ONLINE
Medical Coordinator OFFLINE
```

Presence information is ephemeral.

Do not use presence as permanent case history.

---

# 39. Presence Privacy

Only authorized participants may view Incident Room presence.

Presence payload should expose minimum necessary identity information.

---

# 40. Acknowledgements

Some operational events require explicit acknowledgement.

Example:

```text id="5dhfhr"
Hospital incoming patient notification
```

Realtime delivery alone does not mean the hospital has acknowledged the case.

Separate:

```text id="sck5rc"
DELIVERED
```

from:

```text id="y7n7cq"
ACKNOWLEDGED
```

Acknowledgement is an explicit domain action persisted in the database.

---

# 41. Critical Notifications

Critical notifications may require acknowledgement.

Flow:

```text id="ng24q8"
Critical event
     ↓
Persist
     ↓
Broadcast
     ↓
Recipient receives
     ↓
User acknowledges
     ↓
Acknowledgement persisted
     ↓
Sender/participants notified
```

Future escalation rules may apply if acknowledgement is not received.

Do not invent escalation times without approved operational requirements.

---

# 42. Hospital Incoming Case

Example realtime flow:

```text id="n8dt8x"
Destination Selected
       │
       ▼
Persist Selection
       │
       ▼
Hospital Incoming Record
       │
       ▼
Broadcast
       │
       ▼
Hospital Dashboard

INCOMING PATIENT
ETA 08:42

       │
       ▼
Hospital clicks ACKNOWLEDGE
       │
       ▼
Persist acknowledgement
       │
       ▼
Broadcast HospitalAcknowledged
       │
       ▼
Ambulance + Dispatch
```

---

# 43. Vital Signs

When vital signs are recorded:

```text id="41e3e4"
Tablet
   │
   ▼
POST /vitals
   │
   ▼
Authorize
   │
   ▼
Validate
   │
   ▼
Persist
   │
   ▼
VitalSignsRecorded
   │
   ├── Timeline
   └── Broadcast
          │
          ▼
Authorized participants
```

Do not use WebSocket messages themselves to create authoritative vital-sign records.

---

# 44. Patient Condition Change

Potential future condition-change detection must rely only on approved configured rules.

Codex must not invent clinical thresholds.

If a configured rule triggers:

```text id="hhj8br"
PatientConditionChanged
```

the realtime layer distributes the already-determined domain fact.

Realtime infrastructure itself does not interpret medicine.

---

# 45. Hospital Capacity Updates

Flow:

```text id="o9s1gn"
Authorized Hospital User
        │
        ▼
Update Resource
        │
        ▼
Persist + timestamp
        │
        ▼
HospitalCapacityChanged
        │
        ├── Hospital UI
        ├── Relevant Dispatch
        └── Destination data invalidation
```

Destination evaluations already completed must retain their historical snapshots.

---

# 46. Capacity Freshness

Operational resource data requires timestamps.

Example event:

```json id="gqq3h4"
{
  "event": "hospital.capacity.changed",
  "hospital_id": "01J...",
  "resource_id": "01J...",
  "status": "LIMITED",
  "updated_at": "2026-09-27T17:58:10Z"
}
```

Clients determine freshness using server-defined policy/configuration, not arbitrary frontend thresholds.

---

# 47. Vehicle Position

Vehicle position updates have different characteristics from normal domain events.

They may be:

- frequent;
- ephemeral;
- high-volume.

Concept:

```text id="4fr3zf"
Vehicle Device
      │
      ▼
Position Endpoint
      │
      ▼
Validate device/session
      │
      ▼
Update Current Position
      │
      ├── Optional historical persistence
      └── Realtime broadcast
```

Do not automatically create a permanent case timeline event for every GPS point.

---

# 48. Position Throttling

Frontend rendering and server broadcasting should support throttling/coalescing where appropriate.

Do not broadcast meaningless GPS noise at excessive frequency.

Exact intervals are deployment/configuration decisions.

---

# 49. Position Accuracy

Position data should support:

```text id="1ezdcl"
latitude
longitude
accuracy
heading
speed
captured_at
received_at
```

Clients should be able to identify old or inaccurate positions.

---

# 50. ETA Updates

ETA is derived data.

Store/communicate:

- provider;
- calculated_at;
- ETA/duration;
- route reference where appropriate.

Do not present old ETA as live.

If routing provider fails:

```text id="6yjd0b"
LIVE ETA UNAVAILABLE
Last successful estimate: 8 min
Calculated 6 min ago
```

where such fallback display is permitted.

---

# 51. Incident Room Messages

Message flow:

```text id="klwhmy"
User
 │
 ▼
POST message
 │
 ▼
Authorize room membership
 │
 ▼
Validate
 │
 ▼
Persist
 │
 ▼
MessageCreated
 │
 ▼
Broadcast
```

Messages must not exist only in WebSocket memory.

---

# 52. Structured Quick Messages

Operational quick messages may include:

```text id="5ahj9l"
TRANSPORT STARTED
ETA UPDATED
REQUEST MEDICAL COORDINATOR
PATIENT CONDITION CHANGED
ARRIVING NOW
```

Actual available quick messages are configurable and must follow approved workflows.

---

# 53. System Messages

Incident Room can display system-generated events.

Example:

```text id="j5z3e9"
18:54 Hospital acknowledged incoming case.
```

System messages should be visually distinct from human messages.

---

# 54. Typing Indicators

Typing indicators are low priority.

Do not spend M0/M1 development effort on chat polish while critical operational functionality is incomplete.

---

# 55. Read Receipts

Read receipts may be useful for selected messages.

Do not confuse:

- delivered;
- displayed;
- read;
- acknowledged.

Critical operational acknowledgement must remain explicit.

---

# 56. Push Notifications

WebSockets work while client is connected.

Push notifications may later support:

- backgrounded PWA;
- mobile device alerts;
- assignment alerts;
- critical acknowledgement requests.

Push is complementary to realtime WebSockets.

---

# 57. Push Privacy

Lock-screen notifications must minimize sensitive information.

Example:

```text id="zv9aq5"
MEDGRID
Critical update for case MG-2026-000184
```

rather than detailed clinical information.

---

# 58. Realtime Authorization Changes

Permissions can change while a user is connected.

Architecture must not assume channel authorization lasts forever.

Examples:

- user disabled;
- user removed from organization;
- case participation ended;
- session revoked.

Clients should be disconnected/restricted as soon as reasonably possible.

Server-side API authorization remains authoritative regardless of stale WebSocket subscriptions.

---

# 59. Case Participant Changes

When a participant loses access to a case:

- future API access is denied;
- future channel authorization is denied;
- client should unsubscribe;
- sensitive local state should be cleared according to policy.

---

# 60. Realtime and Transactions

Avoid broadcasting before database commit.

Correct:

```text id="u3p40n"
DB transaction
     ↓
COMMIT
     ↓
Broadcast
```

Incorrect:

```text id="3otq88"
Broadcast success
     ↓
DB transaction fails
```

Laravel after-commit mechanisms should be used where appropriate.

---

# 61. Realtime and Queues

Non-critical broadcasts may be queued.

Critical operational events require carefully selected delivery strategy.

Do not assume queueing everything is always correct.

Likewise, do not make every broadcast synchronous.

Milestone requirements should define critical paths.

---

# 62. Queue Separation

Future deployment may use queues such as:

```text id="d6mbn5"
critical
realtime
default
notifications
integrations
analytics
```

This prevents large background workloads from starving critical operational jobs.

M0 may start simpler but should not create architectural barriers.

---

# 63. Realtime Failure

If Reverb fails but Laravel/API/database remain healthy:

MEDGRID enters degraded realtime state.

Clients should:

- indicate degraded status;
- continue safe persistent operations where possible;
- periodically reconcile authoritative state;
- attempt reconnect.

Do not discard user-entered clinical information because WebSockets are unavailable.

---

# 64. Redis Failure

Behavior depends on Redis responsibilities.

Potential impacts:

- queues;
- cache;
- Reverb-related infrastructure;
- locks.

Health system must detect Redis failure.

Critical persistent data must remain in PostgreSQL.

Operations requiring unavailable distributed locks may need safe failure rather than unsafe execution.

---

# 65. Database Failure

If PostgreSQL is unavailable, authoritative writes cannot safely continue normally.

Do not report clinical/operational writes as successful.

Ambulance offline-capable workflows may queue supported operations locally.

Other interfaces should clearly indicate service disruption.

---

# 66. Split-Brain Avoidance

Clients must not independently decide authoritative state during server isolation.

Example:

Two clients should not independently "confirm" different destinations and later merge them automatically.

Concurrency-sensitive actions require server arbitration.

Offline functionality must distinguish append-safe actions from authoritative decisions.

---

# 67. Offline-Safe Operations

Likely candidates:

- locally record new vital observations;
- draft assessment information;
- append selected notes.

Potentially unsafe without server:

- assigning ambulance;
- confirming destination;
- claiming realtime hospital capacity;
- hospital acknowledgement;
- changing permissions.

Exact list is defined by future milestones.

---

# 68. Offline Queue Synchronization

Each offline operation should contain:

```json id="6o9rmc"
{
  "operation_id": "01J...",
  "operation_type": "vital_signs.record",
  "captured_at": "...",
  "entity_version": 12,
  "payload": {}
}
```

Server responds per operation:

```text id="admrwe"
ACCEPTED
DUPLICATE
CONFLICT
REJECTED
```

---

# 69. Offline Deduplication

If client retries the same operation:

```text id="dte7c1"
operation_id = 01JABC
```

server must avoid creating duplicate data.

This is especially important for clinical observations.

---

# 70. Offline Conflict

Conflict must be explicit.

Do not silently overwrite.

Example:

```text id="iz93e8"
CONFLICT

Case state changed while device was offline.
```

Append-only observations may avoid many conflicts naturally.

---

# 71. Offline Sync Order

Operations may have dependencies.

Example:

```text id="vcsu4w"
Create temporary encounter
      ↓
Record vitals against encounter
```

Synchronization must preserve dependencies or map local temporary identifiers to server identifiers.

Detailed design belongs to Ambulance milestone.

---

# 72. PTT Boundary

Push-to-Talk is realtime communication but not ordinary WebSocket messaging.

WebSockets may coordinate:

- channel membership;
- speaking state;
- signaling;
- session metadata.

Actual audio transport should use WebRTC/media infrastructure.

---

# 73. PTT Authorization

Before joining a PTT channel:

```text id="q7lzgo"
Authenticate
     ↓
Verify active session
     ↓
Verify incident membership
     ↓
Verify communications.ptt
     ↓
Authorize media session
```

Media infrastructure must not accept arbitrary room joins.

---

# 74. PTT Speaking State

Future PTT may use events such as:

```text id="q5w0x7"
ptt.requested
ptt.granted
ptt.started
ptt.ended
```

Actual arbitration strategy will be defined during M6.

Do not implement PTT during M0.

---

# 75. PTT Recording

PTT is not recorded by default.

Any recording capability requires separate explicit requirements covering:

- legal basis;
- participant notice;
- retention;
- access;
- encryption;
- audit.

---

# 76. Control Center

Control Center subscribes only to data the user is authorized to see.

It may aggregate:

- active cases;
- vehicles;
- hospital status;
- operational alerts.

Avoid subscribing Control Center clients individually to thousands of channels if an aggregate authorized channel is more appropriate.

Scale architecture should evolve based on measured requirements.

---

# 77. Realtime Scalability

Initial:

```text id="sdqv8a"
Laravel
  │
Reverb
  │
Clients
```

Future:

```text id="5x85zl"
Load Balancer
      │
 ┌────┴────┐
 ▼         ▼
App       App
      │
    Redis
      │
 ┌────┴────┐
 ▼         ▼
Reverb    Reverb
```

Architecture must allow horizontal scaling.

Do not prematurely implement distributed complexity in M0.

---

# 78. Channel Subscription Efficiency

Clients should subscribe only to relevant channels.

Ambulance tablet does not need:

- every hospital channel;
- every ambulance;
- every case.

Typical ambulance subscriptions:

```text id="i6c1le"
user.{userId}
vehicle.{vehicleId}
case.{activeCaseId}
incident-room.{activeRoomId}
```

---

# 79. Dispatch Subscription Efficiency

Dispatch may use:

```text id="xpn9qi"
user.{userId}
dispatch.{organizationId}
```

plus specific case channels for actively opened cases where required.

---

# 80. Hospital Subscription Efficiency

Hospital UI may use:

```text id="42cvzm"
user.{userId}
hospital.{hospitalId}
```

plus selected case/Incident Room channels.

---

# 81. Realtime Metrics

Future observability should include:

- active connections;
- subscriptions;
- reconnect rate;
- broadcast latency;
- failed authorization;
- queue delay;
- event processing failures;
- connection duration.

Do not include unnecessary patient data in metrics.

---

# 82. Realtime Logging

Useful logging context:

```text id="4rhczb"
event_id
event_type
case_id
channel type
correlation_id
delivery path
```

Avoid logging full sensitive payloads.

---

# 83. Testing Realtime

Tests should cover:

- event dispatched;
- correct channel;
- unauthorized subscription rejected;
- authorized subscription accepted;
- payload does not expose unnecessary fields;
- broadcast occurs after successful persistence where required.

---

# 84. Integration Tests

Important realtime workflows should eventually have integration tests.

Example:

```text id="xfu8js"
Create case
Assign vehicle
Vehicle receives assignment
Accept mission
Dispatch receives acceptance
```

Do not rely solely on manual browser testing.

---

# 85. Simulation

Realtime simulation is useful for development.

Possible simulator:

```text id="52vntk"
Generate emergency
      ↓
Assign synthetic ambulance
      ↓
Move vehicle
      ↓
Generate synthetic vitals
      ↓
Change hospital capacity
```

Simulation must use the same domain/realtime architecture where practical.

Do not build a separate fake frontend-only realtime system.

---

# 86. M0 Realtime Scope

M0 establishes realtime infrastructure only.

M0 should include:

- Laravel Reverb configuration;
- broadcasting configuration;
- authenticated private channels;
- authenticated presence-channel groundwork;
- Echo/client connection;
- connection-state UI indicator;
- one or more safe demonstration events;
- realtime health visibility;
- tests for channel authorization.

M0 must NOT implement:

- live ambulance GPS;
- patient vitals workflow;
- hospital capacity workflow;
- destination workflow;
- Incident Room chat;
- PTT;
- offline sync.

---

# 87. M0 Demonstration Event

M0 may implement a non-clinical demonstration event.

Example:

```text id="l2dqxr"
SystemPulse
```

or:

```text id="23d0le"
OrganizationOperationalNotice
```

Purpose:

Verify:

```text id="8x90s1"
Laravel
→ Broadcast
→ Reverb
→ Authorized Client
```

Do not create fake clinical workflows merely to test Reverb.

---

# 88. Connection Indicator

M0 frontend should provide reusable connection state.

Example:

```text id="llx6f6"
● LIVE
```

Possible states:

```text id="jrhkyg"
LIVE
RECONNECTING
DEGRADED
OFFLINE
```

This component will later be reused in Ambulance, Dispatch and Hospital interfaces.

---

# 89. Realtime Security Requirements

All protected channels require authentication.

Channel authorization must check resource context.

No patient data on public channels.

No full-model broadcasts by default.

No client-controlled channel authorization.

No secret credentials embedded in frontend source.

---

# 90. Realtime Definition of Done

A realtime feature is complete when:

- authoritative state is persisted appropriately;
- domain event exists where appropriate;
- authorized subscribers receive required notification;
- unauthorized subscribers cannot access the channel;
- payload is minimized;
- duplicate/missed delivery does not corrupt domain state;
- reconnection behavior is considered;
- failure state is visible where relevant;
- tests exist.

---

# 91. Realtime Invariants

## Invariant 1

WebSockets are never the sole source of truth.

## Invariant 2

Protected events are never broadcast on public channels.

## Invariant 3

A successful broadcast never substitutes for database persistence.

## Invariant 4

Realtime delivery does not equal human acknowledgement.

## Invariant 5

Clients may miss or duplicate events without corrupting authoritative state.

## Invariant 6

Connection state is visible to operational users.

## Invariant 7

Stale data is never silently presented as live.

## Invariant 8

Realtime payloads expose minimum necessary information.

## Invariant 9

Offline actions are revalidated by the server.

## Invariant 10

Audio communication remains separate from ordinary application event transport.

---

# 92. Example End-to-End Future Flow

```text id="vlx05k"
112 / Emergency Source
        │
        ▼
Emergency Case Created
        │
        ▼
Dispatch receives realtime case
        │
        ▼
Ambulance assigned
        │
        ▼
Tablet receives assignment
        │
        ▼
Crew accepts
        │
        ▼
Dispatch sees acceptance
        │
        ▼
Crew reaches patient
        │
        ▼
Clinical observations recorded
        │
        ▼
Authorized participants updated
        │
        ▼
Destination evaluation
        │
        ▼
Hospital selected
        │
        ▼
Hospital receives incoming patient
        │
        ▼
Hospital acknowledges
        │
        ▼
Ambulance sees acknowledgement
        │
        ▼
Transport begins
        │
        ▼
Vehicle + ETA update
        │
        ▼
Hospital prepares
        │
        ▼
Patient arrives
        │
        ▼
Handover
        │
        ▼
Case completed
```

The purpose of the realtime architecture is to make this entire chain feel like one coordinated system rather than separate applications exchanging delayed information.

---

# 93. Final Realtime Principle

MEDGRID should make important information appear where it is needed as soon as safely and reliably possible.

Speed must never bypass:

- authorization;
- persistence;
- validation;
- auditability;
- data minimization;
- human acknowledgement where required.

Realtime means synchronized operational awareness.

It does not mean sacrificing correctness.
## M2 Implementation Note

M2 adds the private `encounter.{encounterId}` channel. Authorization requires the current organization, `encounters.view`, an accepted assignment, and active crew membership on the assigned vehicle.

`clinical.state.changed` uses a versioned minimal envelope on case and encounter channels. It carries resource/state identifiers only; patient names, national identifiers, note bodies, and assessment responses are not broadcast. Clients refetch authoritative HTTP state after an event and after reconnect.


## M3 Hospital Channel

M3 adds private hospital.{hospitalId}. Authorization requires current organization, hospitals.view, and explicit hospital assignment. hospital.state.changed carries identifiers, state and hospital version only. Hospital Command reloads authoritative HTTP state after events and reconnect.