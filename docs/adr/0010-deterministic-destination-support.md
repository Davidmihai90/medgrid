# ADR 0010: Deterministic, Snapshot-Based Destination Support

## Status

Accepted for MEDGRID M4.

## Context

Destination support consumes explicit patient encounter requirements and volatile hospital operational facts. Historical decisions must remain explainable after hospital state and organizational rules change. Hospitals can belong to a different organization from the emergency case, but broad cross-tenant access is unacceptable.

## Decision

MEDGRID will:

- store explicit encounter-level requirements and capture exact rows in stable requirement sets;
- use organization-owned, declarative, versioned rule definitions;
- evaluate all candidates at one UTC reference time under a repeatable PostgreSQL snapshot;
- persist structured evidence and immutable M3 hospital fact snapshots per candidate;
- represent outcomes as `ELIGIBLE`, `INELIGIBLE`, or `UNKNOWN`;
- keep evaluation separate from human selection;
- require explicit reasons and permissions for unknown, ineligible, and manual selections;
- preserve selection changes append-only with optimistic concurrency;
- expose cross-organization hospitals only through explicit active destination network access;
- publish minimal events on a dedicated private destination channel.

## Consequences

Historical evaluations remain reproducible without consulting current hospital state. Missing or stale required information cannot silently become eligible. The system does not rank hospitals or make autonomous clinical decisions. Snapshot storage adds database volume, and rule changes require a new version and explicit activation.