# ADR 0009: Hospital operational truth

## Status

Accepted for M3.

## Context

Destination support will depend on hospital facts that change at different rates. Structural capability, current availability, resource capacity, receiving state and freshness cannot be represented by one mutable flag without losing history and uncertainty.

## Decision

Hospital is an explicit organization-owned entity with explicit user access assignments. Structural capabilities are catalog-backed configuration. Receiving, capability availability and resource reports are separate append-oriented histories with effective, received and optional expiry timestamps.

The current view is produced by a deterministic snapshot service using persisted history, a reference time and configurable MEDGRID operational freshness thresholds. Missing or expired reports project as UNKNOWN; stale reports retain their last value but are labelled STALE. Unknown numeric capacity remains null and is never converted to zero.

Operational writes lock the hospital row and compare expected_version. A mismatch returns 409 HOSPITAL_STATE_CONFLICT. Idempotency keys prevent duplicate history rows on network retry.

Incoming notification acknowledgement means only that an authorized operator acknowledged receipt. It does not mean clinical acceptance, destination approval, reservation or handover.

## Alternatives

A mutable current-state row was rejected because it destroys history. Combining capability and availability was rejected because it creates false operational certainty. Frontend-only freshness and conflict checks were rejected because clients are not authoritative.

## Consequences

Snapshots are reconstructable and M4 can capture explainable inputs. History tables grow over time and require indexed current-state queries. Administrative capability configuration remains distinct from frequent operational reporting.