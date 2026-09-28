# ADR 0008: Browser offline clinical data boundary

## Status

Accepted for M2 groundwork.

## Context

A PWA needs limited continuity during network loss, but browser storage can expose sensitive clinical data on an unmanaged or compromised device.

## Decision

The M2 browser queues only additive vital operations in IndexedDB. It stores no credential, token, patient identity, or API response. The service worker caches static assets only and never caches authenticated navigation or APIs. Accepted operations are deleted after reconciliation; rejected/conflicting items remain visibly blocked.

## Consequences

The current browser store is not application-level encrypted and has no policy-defined timed expiry. Production requires managed-device controls, an approved retention lifecycle, and an explicit reconciliation interface before broader offline scope.
