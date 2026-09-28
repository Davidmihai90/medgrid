# ADR 0006: Organization-owned versioned assessments

## Status

Accepted for M2.

## Context

Assessment structures may differ by organization and must remain reproducible after use.

## Decision

Templates have immutable numbered JSONB definitions. Every assessment references the exact template version used. Responses are keyed records under the assessment. Completion validates required fields and prevents normal response rewriting.

## Consequences

Template changes require a new version. JSONB is limited to the form definition while core clinical observations remain relational.
