# ADR 0004: Dispatch concurrency and delivery truth

## Status

Accepted for M1.

## Context

Dispatchers and crews can act on the same case concurrently. A realtime broadcast confirms transport to Reverb, but does not prove that an assigned crew client displayed the mission.

## Decision

Case assignment runs in a PostgreSQL transaction with row locks on the case and vehicle. Partial unique indexes permit only one active assignment per case and per vehicle. Retried creation and assignment requests may carry organization-scoped idempotency keys.

Assignments progress from `PENDING` to `DELIVERED` only after an explicit authenticated crew acknowledgement, then to `ACCEPTED` after an active member of the assigned vehicle crew accepts. Acceptance of a pending mission also establishes delivery because that request proves the client observed the mission.

Every operational change appends a case timeline event, writes a separate audit record, increments the case version where case state changes, and broadcasts a minimal versioned envelope after the database transaction commits. WebSockets remain a synchronization aid; API and PostgreSQL state remain authoritative.

## Consequences

PostgreSQL constraints remain the final race-condition guard even if application locking regresses. Sequence gaps in human case numbers are expected after rolled-back transactions. Reassignment requires a reason and locks both vehicles in stable identifier order to reduce deadlock risk.
