# MEDGRID — Security Architecture

**Document:** Security Specification  
**Version:** 1.0  
**Status:** Initial Security Architecture  
**Related:** `MASTER_SPEC.md`, `ARCHITECTURE.md`, `AGENTS.md`

---

# 1. Purpose

MEDGRID processes highly sensitive medical, personal and operational information.

Security is therefore a core architectural property, not an optional feature added before production.

This document defines the security principles and boundaries that MEDGRID development must preserve from the first milestone.

The security architecture follows the principles of:

- least privilege;
- deny by default;
- defense in depth;
- explicit authorization;
- data minimization;
- strong authentication;
- traceability;
- secure failure;
- separation of duties;
- limited trust;
- auditable emergency access;
- minimum necessary disclosure.

---

# 2. Security Objectives

MEDGRID must protect:

## Confidentiality

Sensitive information must only be accessible to authorized users and systems.

## Integrity

Medical and operational information must not be silently altered, corrupted or overwritten.

## Availability

Critical functionality should remain available or fail gracefully during infrastructure failures.

## Accountability

Sensitive actions must be attributable to an authenticated actor, device/system or integration.

## Traceability

Important actions must be reconstructable through audit and operational records.

---

# 3. Security Is Server-Side

Frontend restrictions are usability features, not security controls.

Never rely on:

- hidden menu items;
- disabled buttons;
- frontend roles;
- JavaScript checks;
- route names;
- client-provided organization IDs

as authorization boundaries.

Every protected operation must be authorized server-side.

---

# 4. Trust Boundaries

Major MEDGRID trust boundaries include:

```text
User / Browser
      │
      ▼
MEDGRID Application
      │
      ▼
Database / Redis

Ambulance Device
      │
      ▼
Local Offline Storage

MEDGRID
      │
      ▼
External Integrations

MEDGRID Application
      │
      ▼
Realtime Infrastructure

MEDGRID
      │
      ▼
Future WebRTC Infrastructure
```

Data crossing a trust boundary must be validated.

---

# 5. Authentication

All operational users require authenticated accounts.

Anonymous access must not expose medical or operational information.

Authentication architecture must support future:

- MFA;
- SSO;
- enterprise identity providers;
- device/session management;
- institutional identity providers.

Authentication implementation must remain replaceable without rewriting MEDGRID domain logic.

---

# 6. Multi-Factor Authentication

Architecture must be MFA-ready from M0.

Production policy may require MFA for privileged and/or clinical accounts.

Potential factors:

- authenticator application;
- passkeys/WebAuthn;
- hardware security keys;
- institution-approved identity providers.

SMS should not be assumed to be the preferred high-assurance factor.

Exact MFA requirements depend on deployment policy.

---

# 7. Password Security

If MEDGRID manages passwords directly:

- use Laravel-supported secure password hashing;
- never store plaintext passwords;
- never log passwords;
- never email existing passwords;
- support secure password reset;
- rate-limit authentication attempts;
- invalidate reset tokens appropriately.

Password policy must be configurable according to deployment requirements.

---

# 8. Session Security

Sessions must be protected against:

- theft;
- fixation;
- replay;
- indefinite persistence;
- unauthorized device reuse.

Requirements include:

- secure cookies in production;
- HttpOnly cookies;
- appropriate SameSite configuration;
- HTTPS-only transport in production;
- session regeneration after authentication;
- logout invalidation;
- inactivity policy;
- maximum lifetime policy where required.

Exact timeout values should be deployment-configurable.

---

# 9. Device Sessions

MEDGRID should eventually maintain identifiable device/session records.

Potential fields:

- user;
- session identifier;
- device label;
- platform;
- first seen;
- last activity;
- approximate network metadata where appropriate;
- revoked timestamp.

Users or administrators with permission should be able to revoke sessions.

---

# 10. Ambulance Devices

Ambulance tablets are high-risk endpoints because they may contain temporarily cached sensitive information.

Future controls should support:

- registered devices;
- session binding;
- remote session revocation;
- automatic lock;
- minimal offline data;
- encrypted local storage where technically appropriate;
- local data expiration;
- mission-completion cleanup.

MEDGRID must assume that a physical device can be lost or stolen.

---

# 11. Lost or Stolen Device

Architecture must allow an administrator to revoke access for a device/session.

Conceptual flow:

```text
DEVICE REPORTED LOST
        │
        ▼
Session revoked
        │
        ▼
Refresh/auth tokens invalidated
        │
        ▼
Realtime access rejected
        │
        ▼
Future API requests rejected
```

Where technically possible, locally stored mission information should have a limited lifetime and be cleared after session invalidation when the application next runs/connects.

MEDGRID must not claim to remotely erase a device unless the underlying device-management infrastructure actually supports it.

---

# 12. Authorization Model

Authorization combines:

- role;
- permission;
- organization membership;
- resource relationship;
- operational context.

Example:

Having:

`patient.view`

does NOT mean the user may view every patient.

The policy must also determine whether the user has a legitimate relationship to the case/patient.

---

# 13. Roles and Permissions

Roles provide convenient permission groups.

Permissions remain the actual capabilities.

Examples:

```text
cases.view
cases.create
cases.update
cases.assign
cases.close

patient.view
patient.update

vitals.create

assessments.create

destinations.evaluate
destinations.select
destinations.override

hospital.capacity.view
hospital.capacity.update

hospital.case.acknowledge

communications.message
communications.ptt

audit.view

users.manage
roles.manage
integrations.manage
```

Permissions evolve by milestone.

---

# 14. Least Privilege

Users should receive only the permissions necessary for their responsibilities.

Examples:

An ambulance driver does not automatically require access to all medical history.

A hospital resource manager does not automatically require access to full patient clinical information.

An auditor may require read-only audit access without operational modification permissions.

---

# 15. Organization Isolation

MEDGRID is multi-organization.

Organization isolation is mandatory.

Queries involving protected resources must consider organization scope.

Do not use:

```php
Model::find($id);
```

followed by an assumption that the authenticated user may access it.

Authorization must verify resource context.

---

# 16. Cross-Organization Cases

Emergency coordination inherently requires controlled cross-organization data sharing.

Example:

Ambulance Organization A

→ transports patient

→ Hospital Organization B

Hospital B requires access to selected case information.

This does NOT imply that Hospital B gains general access to Organization A data.

Access should be case-scoped and purpose-scoped.

---

# 17. Case Participation

A case may define authorized participants.

Examples:

- dispatch organization;
- assigned ambulance organization;
- receiving hospital;
- medical coordinator;
- authorized clinicians.

Case participation can be used as part of authorization decisions.

Participation alone may not grant every permission.

---

# 18. Minimum Necessary Information

Different roles should receive different levels of information.

Example:

Control Center may need:

- case status;
- location;
- assigned ambulance;
- destination;
- operational priority.

It may not need:

- complete medical history.

API Resources/DTOs should support role/context-specific data exposure.

---

# 19. Patient Access

Patient information is highly sensitive.

Patient access should require:

1. authenticated user;
2. appropriate permission;
3. valid organizational/operational relationship;
4. legitimate active context where required.

Sensitive access may generate audit records.

---

# 20. Patient Search

Patient search must not become a mechanism for browsing unrelated people.

Future controls may include:

- permission restrictions;
- minimum search specificity;
- rate limits;
- access auditing;
- organization/context filters.

Do not expose unrestricted global patient lists to ordinary operational users.

---

# 21. Break Glass / Emergency Access

MEDGRID may eventually require emergency override access.

This is known as Break Glass.

Concept:

```text
USER REQUESTS EMERGENCY ACCESS
          │
          ▼
Strong confirmation
          │
          ▼
Reason required
          │
          ▼
Temporary scoped access
          │
          ▼
High-severity audit event
          │
          ▼
Security review capability
```

Break Glass must not become a convenient bypass for ordinary authorization.

M0 should prepare architecture but does not need to implement this feature.

---

# 22. Privileged Accounts

Administrative accounts require stronger controls.

Potential requirements:

- mandatory MFA;
- shorter session lifetime;
- separate permissions;
- sensitive-action confirmation;
- stronger auditing.

Super Administrator must not be used as a normal operational account.

---

# 23. Service Accounts

External integrations and backend services should use dedicated service identities.

Do not share human user credentials with integrations.

Service identities require:

- scoped permissions;
- credential rotation;
- auditability;
- revocation.

---

# 24. API Authentication

Internal browser/PWA authentication and external integration authentication may use different mechanisms.

Do not expose privileged external API access using ordinary browser session assumptions.

Potential future external mechanisms:

- OAuth2/OIDC;
- signed service credentials;
- mTLS;
- institution-approved mechanisms.

Exact implementation depends on integration requirements.

---

# 25. API Authorization

Every protected API endpoint requires authorization.

Do not assume API secrecy because endpoints are undocumented.

Object-level authorization is mandatory.

Prevent IDOR/BOLA vulnerabilities.

Example attack to prevent:

```text
/api/v1/cases/01ABC...
```

User changes identifier to another valid case.

Server must reject access unless authorized.

---

# 26. Rate Limiting

Apply rate limiting based on endpoint risk.

Examples:

- authentication;
- password reset;
- patient search;
- external APIs;
- expensive routing requests;
- sensitive administrative operations.

Operational emergency functionality must use carefully designed limits so security controls do not unnecessarily block legitimate emergency activity.

---

# 27. Input Validation

All external input is untrusted.

Validate:

- types;
- lengths;
- formats;
- enum values;
- relationships;
- ownership;
- file metadata;
- identifiers;
- state transitions.

Clinical ranges must not be invented without approved requirements.

---

# 28. Mass Assignment

Do not allow uncontrolled model mass assignment.

Sensitive fields must never be writable simply because they appear in request payload.

Examples:

```text
organization_id
role
permissions
is_admin
case_status
destination_id
```

require explicit handling and authorization.

---

# 29. Output Encoding

Prevent injection and XSS.

Use framework escaping by default.

Do not render untrusted HTML without explicit sanitization and justification.

Operational messages should normally be treated as text.

---

# 30. CSRF

Browser session-based state-changing requests require CSRF protection.

Do not disable CSRF globally to make development easier.

API authentication strategies must use appropriate protections for their authentication model.

---

# 31. SQL Injection

Use Eloquent/query builder parameterization.

Do not construct raw SQL using untrusted input.

Raw SQL requires explicit parameter binding and review.

---

# 32. File Upload Security

Future attachments require strict controls.

Validate:

- file size;
- MIME type;
- extension where relevant;
- authorization;
- storage destination.

Sensitive files must be private by default.

Do not trust client-supplied filenames.

Generate server-side storage names.

Future production architecture should consider malware scanning where appropriate.

---

# 33. URLs

Sensitive patient information must not be embedded in URLs.

Bad:

```text
/case?patientName=...
```

Prefer opaque identifiers.

URLs may appear in:

- logs;
- browser history;
- proxy logs;
- analytics.

---

# 34. Logging

Application logs must avoid sensitive medical content unless specifically required and protected.

Prefer:

```text
case_id
user_id
organization_id
correlation_id
action
```

instead of:

```text
patient_name
diagnosis
full clinical payload
```

Never log:

- passwords;
- authentication tokens;
- API secrets;
- session cookies;
- encryption keys.

---

# 35. Audit Logs

Security audit events may include:

- successful login;
- failed login;
- logout;
- session revocation;
- patient access;
- patient modification;
- role change;
- permission change;
- destination override;
- sensitive export;
- administrative action;
- integration credential change;
- emergency access.

Audit logs should be append-oriented.

Normal application users must not be able to edit/delete audit history.

---

# 36. Audit Content

Audit entry may contain:

```text
event_id
timestamp
actor_type
actor_id
organization_id
action
resource_type
resource_id
result
reason
session_id
correlation_id
ip_metadata
user_agent/device metadata
changes
```

Do not store unnecessary sensitive values inside audit records.

For modifications, consider recording controlled field-level changes rather than entire sensitive payloads.

---

# 37. Audit Integrity

Future production environments should strengthen audit integrity.

Potential approaches:

- restricted database permissions;
- separate append-only storage;
- export to security monitoring;
- tamper-evident mechanisms.

M0 establishes application-level audit foundations.

---

# 38. Encryption in Transit

Production communication must use TLS.

Includes:

- browser ↔ MEDGRID;
- ambulance ↔ MEDGRID;
- WebSocket connections;
- service ↔ service;
- MEDGRID ↔ external provider.

Do not deploy sensitive production traffic over plaintext HTTP.

---

# 39. Encryption at Rest

Production storage should use appropriate infrastructure-level encryption.

Selected highly sensitive fields may additionally require application-level encryption depending on threat model and regulatory requirements.

Do not encrypt fields blindly if doing so destroys required querying functionality without architectural planning.

Encryption design must include key management.

---

# 40. Encryption Keys

Never commit encryption keys.

Keys must be managed outside source control.

Production architecture should support:

- secure key storage;
- rotation;
- restricted access;
- recovery procedures.

Key management is part of security architecture, not merely an environment variable decision.

---

# 41. Database Security

Production database requirements include:

- private network exposure where possible;
- strong credentials;
- restricted DB users;
- backups;
- encryption;
- monitoring;
- patch management.

Application DB credentials should not automatically possess infrastructure administration privileges.

---

# 42. Redis Security

Redis may contain operationally sensitive ephemeral information.

Production Redis should:

- not be publicly exposed;
- require appropriate authentication/access controls;
- use network isolation;
- use encryption in transit where deployment requires;
- use appropriate persistence configuration.

Do not treat Redis as harmless because data is temporary.

---

# 43. Queue Security

Queued jobs may contain sensitive identifiers or payloads.

Avoid unnecessarily serializing entire patient records into queue payloads.

Prefer identifiers and fetch authorized/required state during execution where appropriate.

Failed job storage must be considered sensitive.

---

# 44. WebSocket Security

Realtime channels carrying protected information must be private or presence channels.

Channel authorization occurs server-side.

Example:

User requests:

```text
case.01ABC
```

Server checks:

- authentication;
- permission;
- case relationship;
- organization/context.

Never authorize based only on knowing the channel name.

---

# 45. Realtime Payload Security

Broadcast the minimum necessary payload.

Bad:

```text
entire Patient model
```

Better:

```text
case_id
observation_id
observation_type
captured_at
authorized display data
```

Clients can request additional information through authorized API endpoints.

---

# 46. Presence Information

Presence itself may be sensitive.

Knowing that a specific doctor is involved in a case may expose operational information.

Presence channels require the same authorization discipline as clinical data.

---

# 47. Push Notifications

Push notifications may appear on locked devices.

Therefore avoid placing unnecessary patient-identifying or clinical information directly in notification text.

Prefer:

`Critical update for case MG-2026-000184`

rather than exposing detailed medical information on the lock screen.

Exact notification policy should be configurable.

---

# 48. Offline Storage

Offline storage is a major security boundary.

Store only data necessary for the active operational workflow.

Do not create a permanent offline patient database on ambulance tablets.

Data lifecycle should eventually include:

```text
mission assigned
      ↓
minimum case data cached
      ↓
mission active
      ↓
handover completed
      ↓
retention window
      ↓
local cleanup
```

Exact retention windows must be defined by policy.

---

# 49. Offline Operation Queue

Offline operations should use opaque operation IDs.

Do not trust offline operations simply because they originated from a previously authenticated client.

When synchronizing:

- reauthenticate;
- reauthorize;
- validate;
- verify case relationship;
- apply idempotency checks;
- detect conflicts.

---

# 50. Service Worker Security

PWA service workers must not indiscriminately cache API responses containing sensitive data.

Caching strategies must explicitly distinguish:

- static assets;
- public reference data;
- sensitive operational data.

Never use a generic "cache everything" strategy.

---

# 51. Browser Storage

Do not place long-lived secrets in LocalStorage.

Authentication architecture should prefer secure mechanisms appropriate to the selected frontend architecture.

Sensitive offline records require dedicated lifecycle/security design.

---

# 52. Data Minimization

Collect only information required for legitimate workflows.

Do not collect data merely because it might become useful someday.

Every sensitive field increases:

- privacy risk;
- security impact;
- retention complexity;
- breach impact.

---

# 53. Data Retention

Different data classes may require different retention periods.

Examples:

- clinical records;
- operational case records;
- telemetry;
- audit logs;
- realtime presence;
- notifications;
- integration logs.

Do not implement arbitrary deletion periods without approved policy.

Architecture must support configurable retention.

---

# 54. Data Deletion

Medical/operational data may have legal retention requirements.

Do not implement unrestricted user deletion of case history.

Deletion/anonymization must follow defined policy and applicable legal requirements.

Normal CRUD delete buttons are inappropriate for many MEDGRID entities.

---

# 55. Backups

Production architecture requires protected backups.

Backup strategy must consider:

- encryption;
- retention;
- access control;
- restoration testing;
- geographic/infrastructure redundancy where required.

A backup that has never been tested for restoration is not sufficient operational assurance.

---

# 56. Disaster Recovery

Future production deployment requires documented:

- recovery procedures;
- RPO;
- RTO;
- responsible personnel;
- failover procedures.

Exact targets depend on deployment requirements.

Do not invent production guarantees during development.

---

# 57. Availability Attacks

MEDGRID must consider denial-of-service and resource exhaustion.

Controls may include:

- rate limiting;
- request size limits;
- queue separation;
- timeouts;
- circuit breakers;
- infrastructure-level protection.

Critical operational endpoints may require different protection profiles from public endpoints.

---

# 58. External Integrations

Every external integration is a trust boundary.

Validate all inbound data.

Do not assume trusted organizations always send valid data.

Outbound integration should use:

- timeouts;
- TLS;
- credential isolation;
- controlled retries;
- structured logging.

---

# 59. Webhooks

Future webhooks require:

- signature verification;
- timestamp/replay protection where supported;
- idempotency;
- source validation;
- rate limiting.

Never trust a webhook solely because its URL is difficult to guess.

---

# 60. SSRF

External URL fetching must be tightly controlled.

Do not allow users to supply arbitrary URLs that backend services fetch.

Integration endpoints should come from controlled configuration.

---

# 61. Dependency Security

Dependencies introduce supply-chain risk.

Before adding packages:

- verify maintenance;
- minimize dependency count;
- review security history where appropriate;
- use lock files;
- monitor known vulnerabilities.

Production CI should eventually include dependency vulnerability scanning.

---

# 62. Development Environment

Development convenience must not weaken production architecture.

Never commit:

```text
.env
```

Never use production patient data for ordinary local development.

Use synthetic datasets.

Debug mode must be disabled in production.

---

# 63. Staging

Staging should use synthetic/anonymized data unless explicitly authorized otherwise.

Staging must not casually become a copy of production medical records.

---

# 64. Simulation / Training

Simulation and training environments must be visibly distinct.

Example UI banner:

```text
TRAINING ENVIRONMENT
NO LIVE EMERGENCY DATA
```

Simulation events must not trigger production integrations.

---

# 65. Production Environment

Production must be unmistakably identifiable.

Deployment configuration must prevent accidental use of:

- mock emergency providers;
- development mail;
- debug routes;
- synthetic event generators

unless intentionally enabled under controlled conditions.

---

# 66. Security Headers

Production web responses should use appropriate security headers.

Potential controls:

- Content-Security-Policy;
- X-Content-Type-Options;
- Referrer-Policy;
- frame restrictions;
- HSTS.

Exact configuration depends on frontend requirements.

Do not blindly copy restrictive policies that break required functionality.

---

# 67. CORS

CORS must be explicit.

Do not use unrestricted:

```text
*
```

for credentialed production APIs.

Allowed origins depend on deployment architecture.

---

# 68. Secrets

Secrets include:

- DB passwords;
- Redis credentials;
- API keys;
- signing keys;
- OAuth secrets;
- WebRTC credentials;
- push credentials;
- certificates.

Secrets must not appear in:

- repository;
- screenshots;
- documentation examples using real values;
- logs.

---

# 69. Secret Rotation

Architecture must allow credential rotation without redesign.

Integrations should not assume credentials remain permanent.

---

# 70. Administrative Actions

High-impact actions may require additional confirmation.

Examples:

- disabling organization;
- revoking all sessions;
- changing integration credentials;
- changing roles;
- enabling simulation in controlled environments;
- sensitive exports.

Future deployment may require step-up authentication for selected actions.

---

# 71. Export Security

Exports containing patient information are high-risk.

Future export functionality requires:

- explicit permission;
- audit;
- purpose;
- scoped dataset;
- secure file handling;
- expiry where appropriate.

Do not implement unrestricted "Export All Patients".

---

# 72. Impersonation

Administrator impersonation of users should not be implemented casually.

If future support requires it:

- explicit permission;
- visible UI state;
- reason;
- audit;
- limited duration.

Prefer troubleshooting without impersonation where possible.

---

# 73. Account Lifecycle

Support:

```text
INVITED
ACTIVE
SUSPENDED
DISABLED
```

Disabling a user should prevent future authentication and allow active sessions to be revoked.

Do not hard-delete user identities when audit history depends on them.

---

# 74. Organization Lifecycle

Organization deactivation must not destroy historical cases.

Historical references remain intact.

Deactivation primarily prevents new operational activity according to policy.

---

# 75. Soft Deletes

Do not automatically use soft deletes everywhere.

For each entity decide whether it should:

- never be deleted;
- be deactivated;
- be archived;
- use soft deletion;
- be physically deleted.

Clinical and audit records require special care.

---

# 76. Sensitive Error Messages

Avoid errors such as:

`Patient John Smith does not exist.`

when the user is not authorized to know whether that patient exists.

Use context-appropriate responses that do not leak protected resource existence.

---

# 77. Enumeration

Public identifiers should make large-scale enumeration harder.

ULIDs provide non-sequential public identifiers.

Authorization remains mandatory even with opaque IDs.

Security through difficult identifiers alone is insufficient.

---

# 78. Security Events

Potential security events include:

- repeated authentication failure;
- suspicious patient searches;
- unauthorized resource access attempts;
- unusual administrative changes;
- repeated Break Glass usage;
- integration authentication failures;
- session anomalies.

Future monitoring can surface these to security personnel.

---

# 79. Incident Response

Production deployment requires an incident response process covering:

- detection;
- containment;
- investigation;
- credential revocation;
- recovery;
- documentation;
- required notifications.

Application architecture should preserve evidence necessary for investigation without unnecessarily collecting sensitive data.

---

# 80. Privacy by Design

New features involving sensitive data must answer:

1. Why is this data required?
2. Who can access it?
3. How long is it retained?
4. Where is it stored?
5. Is it transmitted externally?
6. Is access audited?
7. What happens offline?
8. What happens after the case closes?

If these questions cannot be answered, the feature is not ready for production.

---

# 81. AI Security Boundary

Future AI functionality must not automatically receive unrestricted patient data.

AI integrations require explicit review of:

- provider;
- data sent;
- legal basis;
- retention;
- training policies;
- location of processing;
- authentication;
- audit;
- output reliability.

Never send patient data to an external AI provider merely because an API is available.

---

# 82. Security Testing

Automated tests should cover security-critical behavior.

Examples:

- unauthorized case access rejected;
- cross-organization access rejected;
- unauthorized patient access rejected;
- unauthorized channel subscription rejected;
- permission escalation rejected;
- invalid state transitions rejected;
- service credentials scoped;
- disabled accounts rejected.

Every discovered security bug should receive a regression test where practical.

---

# 83. Static and Dependency Analysis

Production CI should eventually include:

- dependency vulnerability scanning;
- static analysis;
- secret detection;
- test execution.

Exact tooling is selected during development.

---

# 84. Penetration Testing

Before real clinical/operational deployment, MEDGRID should undergo professional security assessment appropriate to its deployment context.

Automated scanners alone are insufficient.

Potential assessment areas:

- authentication;
- authorization;
- multi-tenancy;
- APIs;
- WebSockets;
- PWA/offline data;
- integrations;
- infrastructure;
- mobile/tablet exposure.

---

# 85. Threat Modeling

Security-sensitive milestones should update the MEDGRID threat model.

Threat categories include:

- unauthorized patient access;
- account compromise;
- stolen ambulance device;
- malicious insider;
- compromised integration;
- realtime channel leakage;
- data tampering;
- service outage;
- ransomware/infrastructure compromise;
- offline data extraction.

Threat modeling is an ongoing activity.

---

# 86. Compliance Boundary

MEDGRID architecture should support applicable requirements such as:

- GDPR;
- medical confidentiality obligations;
- healthcare cybersecurity requirements;
- NIS2-related obligations where applicable;
- medical software/device requirements where functionality falls within their scope.

Development documentation must not claim certification, approval or compliance that has not been formally established.

---

# 87. M0 Security Requirements

M0 Foundation must establish at minimum:

- authentication;
- account status;
- organizations;
- organization memberships;
- roles;
- permissions;
- policies;
- server-side authorization;
- base audit infrastructure;
- session security configuration;
- secure environment handling;
- rate limiting groundwork;
- protected realtime channel authorization;
- synthetic development data;
- security-focused tests.

M0 does not need to implement every security capability in this document.

---

# 88. M0 Security Tests

At minimum, M0 should demonstrate:

### Authentication

Unauthenticated users cannot access protected application areas.

### Account status

Disabled/suspended users cannot continue normal access according to implemented policy.

### Permissions

Users without required permission receive denial.

### Organization isolation

A user from Organization A cannot access protected Organization B resources without explicit authorization.

### Administration

Ordinary users cannot manage roles/permissions.

### Realtime

Unauthorized users cannot subscribe to protected test channels.

### Audit

Selected security-sensitive administrative actions generate audit records.

---

# 89. Security Definition of Done

A feature handling protected information is not complete until:

- authentication requirements are defined;
- authorization is enforced server-side;
- input is validated;
- output exposure is minimized;
- audit requirements are considered;
- realtime exposure is considered;
- offline implications are considered;
- tests cover unauthorized access;
- sensitive errors do not leak unnecessary information.

---

# 90. Security Invariants

The following must remain true.

## Invariant 1

No authenticated user automatically has access to all patient information.

## Invariant 2

Knowing a resource ID never grants access.

## Invariant 3

Organization boundaries are enforced server-side.

## Invariant 4

Sensitive WebSocket channels require authorization.

## Invariant 5

Patient data is not unnecessarily logged.

## Invariant 6

Secrets never enter source control.

## Invariant 7

Offline clients are reauthorized during synchronization.

## Invariant 8

Historical audit information cannot be casually edited by ordinary users.

## Invariant 9

External integrations are treated as untrusted boundaries.

## Invariant 10

MEDGRID never claims a security/compliance property that has not actually been implemented and validated.

---

# 91. Final Security Principle

When choosing between convenience and unnecessary exposure of sensitive information, choose the safer architecture.

When security requirements are uncertain, do not silently guess.

Document the uncertainty and require an explicit decision before introducing a high-risk behavior.
## M2 Clinical and Offline Controls

Clinical mutation requires organization ownership, accepted assignment, active crew relationship, and a granular operation permission. Patient identity responses omit national identifiers and cross-tenant resources are hidden where applicable. Clinical corrections, identity changes, encounter creation, assessment completion, condition changes, and notes produce audit records without duplicating full sensitive payloads.

The M2 PWA service worker caches only static build assets, manifest, and icon. It never caches navigation, login, broadcasting authorization, or API responses. The IndexedDB queue is restricted to additive vital operations and stores no credential or patient identity. Browser storage is not application-level encrypted; production use requires managed-device controls and an approved retention/reconciliation policy.
