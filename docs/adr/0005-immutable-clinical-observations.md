# ADR 0005: Immutable clinical observations

## Status

Accepted for M2.

## Context

Repeated and corrected measurements must retain chronology, provenance, and the originally entered value.

## Decision

Vital observations are append-only encounter records with separate measurement and server record timestamps. A correction inserts a replacement linked through `supersedes_observation_id` and marks the original with correction actor/time metadata. Latest vitals are a projection that excludes superseded records.

## Consequences

Clinical history remains reconstructable. Storage grows with observation count and callers must deliberately query latest versus historical values.
