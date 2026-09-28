# ADR 0007: Offline operation idempotency

## Status

Accepted for M2.

## Context

Ambulance connectivity is intermittent and retries must not duplicate clinical records or overwrite newer shared state.

## Decision

Offline operations use organization-scoped ULIDs. The server hashes canonical semantic operation content, stores processing metadata without the clinical payload, and enforces unique operation identifiers. Same-content accepted retries return `DUPLICATE`; changed content under the same ID returns `CONFLICT`. Mutable operations require entity versions.

## Consequences

Additive vitals can reconcile safely. Conflicts remain explicit and no last-write-wins policy is used.
