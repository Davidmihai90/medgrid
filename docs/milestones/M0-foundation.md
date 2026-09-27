# MEDGRID — Milestone M0: Foundation

**Milestone:** M0  
**Release Target:** MEDGRID v0.1.0 — Foundation  
**Status:** Ready for Implementation  
**Depends On:** `AGENTS.md`, `MASTER_SPEC.md`, `ARCHITECTURE.md`, `SECURITY.md`, `REALTIME.md`

---

# 1. Purpose

M0 establishes the technical and security foundation of MEDGRID.

This milestone does NOT implement emergency medical workflows.

The objective is to create a production-oriented Laravel foundation upon which future MEDGRID milestones can safely build:

- Dispatch;
- Ambulance;
- Hospitals;
- Destination Support;
- Live Operations;
- Incident Rooms;
- Handover;
- Integrations.

At the end of M0, MEDGRID should have a functioning authenticated multi-organization application with permissions, auditing, realtime infrastructure, queues, health monitoring and role-oriented application shells.

---

# 2. Mandatory Reading

Before modifying the repository, Codex must read:

```text
AGENTS.md

docs/MASTER_SPEC.md
docs/ARCHITECTURE.md
docs/SECURITY.md
docs/REALTIME.md
docs/milestones/M0-foundation.md
```

These documents are architectural requirements.

If they conflict, stop and report the conflict before making a major architectural decision.

---

# 3. Before Coding

Codex must first:

1. inspect the repository;
2. inspect Git status;
3. determine whether Laravel already exists;
4. inspect installed PHP/Composer/Node versions where relevant;
5. inspect available PostgreSQL/PostGIS/Redis environment;
6. inspect existing files before replacing anything;
7. identify implementation risks;
8. produce a concise implementation plan.

Do not blindly recreate an existing project.

Do not delete existing user work.

---

# 4. M0 Scope

M0 implements:

- Laravel application foundation;
- PostgreSQL;
- PostGIS readiness;
- Redis;
- Laravel queues;
- Laravel Reverb;
- authentication;
- user accounts;
- account states;
- organizations;
- organization membership;
- roles;
- permissions;
- authorization policies;
- initial application areas;
- base audit infrastructure;
- health checks;
- realtime connection infrastructure;
- reusable realtime connection indicator;
- synthetic development seed data;
- automated tests;
- documentation updates;
- secure environment templates.

---

# 5. Explicit Non-Goals

M0 must NOT implement:

- real 112 integration;
- emergency case workflow;
- dispatch assignment workflow;
- patient records;
- patient encounters;
- vital signs;
- clinical assessments;
- hospital capacity workflow;
- destination evaluation;
- destination selection;
- ambulance GPS tracking;
- route calculation;
- traffic;
- offline clinical synchronization;
- Incident Room messaging;
- Push-to-Talk;
- WebRTC;
- AI;
- clinical decision support.

Do not start M1 functionality.

---

# 6. Target Technology

Use:

- Laravel 13;
- PHP version supported/recommended by Laravel 13;
- PostgreSQL;
- PostGIS;
- Redis;
- Laravel Reverb;
- Laravel Queue;
- Vite;
- modern responsive frontend stack compatible with Laravel;
- Pest or PHPUnit according to the initialized project standard.

Prefer Laravel first-party capabilities where practical.

Do not add unnecessary dependencies.

---

# 7. Frontend Direction

Use a Laravel-integrated frontend.

Preferred direction:

```text
Laravel
+
Blade
+
Livewire where interaction benefits from it
+
Alpine.js where small client interactions are appropriate
+
Tailwind CSS
+
Vite
```

If the initialized Laravel stack provides an equivalent modern first-party approach, Codex may use it if it does not conflict with project requirements.

Do not create a separate SPA repository.

Do not introduce React/Vue merely because they are available unless there is a documented architectural reason.

Future realtime and PWA behavior must remain possible.

---

# 8. Project Structure

Preserve normal Laravel structure while adding clear domain organization.

Expected direction:

```text
app/
├── Domain/
│   ├── Identity/
│   ├── Organizations/
│   └── Audit/
│
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
│
├── Jobs/
├── Listeners/
├── Providers/
└── Support/
```

Do not create empty directories/classes merely to satisfy this diagram.

Create domain structure as functionality requires it.

---

# 9. Environment Configuration

Provide safe `.env.example` configuration for:

```text
APP
DATABASE
REDIS
QUEUE
BROADCAST
REVERB
MAIL development defaults where required
```

Never commit `.env`.

Verify `.gitignore`.

No real secrets may appear in repository files.

---

# 10. Database

Use PostgreSQL as the primary development database.

Do not silently substitute SQLite as the application's intended database.

Testing may use an alternate database only where it does not invalidate PostgreSQL-specific behavior.

PostgreSQL-specific/PostGIS behavior must be tested against PostgreSQL when introduced.

---

# 11. PostGIS

M0 must verify PostGIS readiness.

The application should include a safe mechanism/migration/setup requirement for:

```sql
CREATE EXTENSION IF NOT EXISTS postgis;
```

if permitted by the local database environment.

If the database user cannot create extensions, document the required administrator setup instead of failing unpredictably.

M0 does not yet need geospatial domain models.

---

# 12. Redis

Configure Redis for initial use with:

- queues;
- cache where appropriate;
- Reverb/broadcast infrastructure where required.

Application architecture must not depend on Redis as permanent storage.

---

# 13. Queues

Queue infrastructure must work.

Initial backend:

```text
Redis
```

Create at least one safe development/test job or another verifiable mechanism proving:

```text
Laravel → Queue → Worker
```

Do not create fake medical jobs.

---

# 14. Reverb

Install/configure Laravel Reverb according to the selected Laravel version.

M0 must demonstrate:

```text
Laravel
→ Broadcast Event
→ Reverb
→ Authorized Client
```

Use a non-clinical demonstration event.

---

# 15. Demonstration Realtime Event

Preferred event:

```text
OrganizationOperationalNotice
```

or equivalent.

It may contain:

```text
id
organization_id
message
occurred_at
```

Use synthetic/non-sensitive content.

Broadcast only to an authorized organization channel.

---

# 16. Realtime Channels

M0 should establish groundwork for:

```text
organization.{organizationId}
user.{userId}
```

Do not implement future case/hospital/vehicle functionality merely to create their channels.

Channel authorization must be server-side.

---

# 17. Connection State Component

Create a reusable frontend realtime status component.

States:

```text
LIVE
RECONNECTING
DEGRADED
OFFLINE
```

It should be suitable for later reuse across:

- Dispatch;
- Ambulance;
- Hospital;
- Medical Coordinator;
- Control Center.

Do not pretend LIVE if realtime connection has not actually been established.

---

# 18. Authentication

M0 requires:

- login;
- logout;
- secure session handling;
- password reset capability where supported by selected authentication stack;
- protected application routes.

Registration should NOT be publicly open by default.

MEDGRID operational users are institution-managed accounts.

If framework scaffolding creates public registration, disable/remove public registration unless explicitly needed for development.

---

# 19. User Model

Initial User should support at least:

```text
id
name
email
password
status
email_verified_at
last_login_at
created_at
updated_at
```

Use ULID for public/domain-oriented identity if consistent with selected implementation.

Exact primary-key implementation must remain consistent throughout the project.

---

# 20. User Status

Create controlled account status.

Initial values:

```text
INVITED
ACTIVE
SUSPENDED
DISABLED
```

Use enum or equivalent controlled domain representation.

Authorization/authentication behavior must account for inactive states.

At minimum:

`SUSPENDED` and `DISABLED`

must not have ordinary operational access.

---

# 21. Organizations

Create Organization.

Suggested initial fields:

```text
id
public_id if separate identifier strategy is used
name
slug
type
status
timezone
created_at
updated_at
```

Do not over-model organization types during M0.

Use a controlled enum/string strategy that can evolve.

---

# 22. Organization Status

Initial statuses may include:

```text
ACTIVE
INACTIVE
```

Inactive organizations should not perform normal new operational activity.

Historical data must remain intact.

---

# 23. Organization Membership

Users may belong to one or more organizations.

Create explicit membership rather than placing a single `organization_id` on User as the only relationship.

Concept:

```text
Organization
      │
      ▼
OrganizationMembership
      ▲
      │
     User
```

Suggested membership fields:

```text
id
organization_id
user_id
status
created_at
updated_at
```

Potential future metadata:

```text
title
department
employee_identifier
```

Do not add unnecessary HR data in M0.

---

# 24. Membership Status

Initial membership states:

```text
ACTIVE
SUSPENDED
```

Authorization must consider both user status and membership status.

---

# 25. Current Organization Context

Because users may belong to multiple organizations, establish an explicit current organization context.

Do not trust organization IDs directly from requests.

Changing active organization must verify membership.

The selected organization may be stored in the authenticated session or another safe application mechanism.

---

# 26. Roles

Initial roles:

```text
Super Administrator
Organization Administrator
Dispatcher
Medical Coordinator
Ambulance Physician
Paramedic
Nurse
Ambulance Driver
Hospital Operator
Hospital Resource Manager
Doctor
Auditor
```

These roles prepare future milestones.

M0 does not need to implement each role's complete future functionality.

---

# 27. Permission Architecture

Permissions should be granular.

M0 should establish permission infrastructure with an initial useful subset.

Recommended M0 permissions:

```text
dashboard.view

organizations.view
organizations.manage

users.view
users.manage

roles.view
roles.manage

audit.view

system.health.view
```

Future domain permissions are introduced with their milestones.

Do not add dozens of unused permissions merely because they appear in MASTER_SPEC.

---

# 28. RBAC Implementation

Use a mature, well-maintained approach.

If adding a package for permissions, verify compatibility with Laravel 13 and document the dependency.

Alternatively implement a clean native RBAC architecture if justified.

Do not create an insecure simplistic string field such as:

```text
users.role = "admin"
```

as the entire authorization system.

---

# 29. Organization-Aware Roles

Roles/permissions must support organization context.

A user may potentially be:

```text
Organization Administrator
```

in Organization A

and:

```text
Auditor
```

in Organization B.

Do not design M0 in a way that makes this impossible.

---

# 30. Super Administrator

Super Administrator is platform-level.

This role must be clearly distinguished from Organization Administrator.

Do not automatically attach Super Administrator privileges to every organization.

Super Administrator usage should be limited and auditable.

---

# 31. Policies

Create Policies/Gates for protected resources.

At minimum demonstrate authorization for:

- Organization;
- User management;
- role/permission management;
- audit access;
- health/system information where protected.

Controllers must not rely solely on UI visibility.

---

# 32. Application Areas

Create initial route/layout shells:

```text
/dispatch
/ambulance
/hospital
/medical
/control
/admin
```

These are placeholders for future milestones.

They should not contain fake operational functionality.

---

# 33. Application Shell Behavior

Each area should display:

- MEDGRID branding;
- current user;
- current organization;
- application area;
- realtime connection indicator;
- navigation appropriate to current permissions;
- environment indicator in non-production environments.

Example:

```text
MEDGRID

CONTROL CENTER

Organization:
MEDGRID Demo Emergency Service

User:
Demo Administrator

Realtime:
● LIVE
```

---

# 34. Route Authorization

Application area access must be permission/role aware.

M0 may define coarse access rules that future milestones refine.

Example:

- `/admin` → administrative permissions;
- `/dispatch` → dispatcher/admin development access;
- `/ambulance` → ambulance role/admin development access;
- `/hospital` → hospital roles/admin development access;
- `/medical` → medical coordinator/admin;
- `/control` → authorized supervisory/admin roles.

Do not rely solely on sidebar visibility.

---

# 35. UI Design Direction

MEDGRID visual direction:

- professional;
- operational;
- high clarity;
- modern;
- medical/emergency appropriate;
- low visual noise.

Avoid:

- playful SaaS styling;
- excessive gradients;
- unnecessary animation;
- tiny controls;
- decorative dashboards.

---

# 36. Layout

Create reusable application layout components.

Potential structure:

```text
Sidebar
Topbar
Main Content
Connection Status
User Menu
Organization Switcher
Environment Indicator
```

Mobile/tablet navigation must remain usable.

---

# 37. Ambulance Shell

Even though ambulance workflow is M2, M0 shell should already be tablet-friendly.

Requirements:

- large touch targets;
- responsive navigation;
- no desktop-only hover dependency;
- connection state clearly visible.

Do not implement patient functionality.

---

# 38. Environment Indicator

Non-production environments must be visibly identifiable.

Examples:

```text
LOCAL
STAGING
TRAINING
```

Production should not accidentally display itself as training.

Environment label must come from controlled configuration.

---

# 39. Audit Infrastructure

Create base AuditLog architecture.

Suggested fields:

```text
id
occurred_at

actor_type
actor_id

organization_id

action

resource_type
resource_id

result

reason

correlation_id

session_identifier

ip_address
user_agent

metadata

created_at
```

Do not include `updated_at` if audit records are intentionally immutable.

Exact field design may be refined if justified.

---

# 40. Audit Immutability

Audit logs must not have ordinary CRUD update/delete routes.

Do not create:

```text
Edit Audit Log
Delete Audit Log
```

in administration UI.

---

# 41. M0 Audited Events

At minimum consider auditing:

- login;
- logout where practical;
- failed login where practical;
- user created;
- user status changed;
- organization created/updated;
- organization membership changed;
- role assignment;
- permission-sensitive administration.

Avoid logging passwords/tokens.

---

# 42. Audit Viewer

Create a basic read-only admin audit view if practical within M0.

Minimum functionality:

- pagination;
- timestamp;
- actor;
- action;
- resource;
- organization;
- result.

Do not build advanced analytics/search.

Only users with `audit.view` may access it.

---

# 43. Correlation IDs

Introduce request correlation ID infrastructure.

Each relevant HTTP request should have a correlation identifier.

Where appropriate include it in:

- logs;
- audit records;
- domain events.

Expose correlation ID in response headers if useful for support/debugging.

Do not expose sensitive internals.

---

# 44. Logging

Use structured contextual logging where practical.

Useful context:

```text
correlation_id
user_id
organization_id
```

Do not log sensitive medical information.

M0 has no patient data anyway.

---

# 45. Health Checks

Create protected operational health capability.

At minimum report:

```text
Application
Database
PostGIS
Redis
Queue
Realtime configuration
```

Differentiate where practical:

```text
HEALTHY
DEGRADED
UNAVAILABLE
```

Do not expose credentials or detailed infrastructure secrets.

---

# 46. Public Health Endpoint

If a public liveness endpoint is created, keep it minimal.

Example:

```json
{
  "status": "ok"
}
```

Detailed health information should require authorization.

---

# 47. Admin Health Dashboard

Create a simple admin health page accessible only with:

```text
system.health.view
```

Possible display:

```text
Application       HEALTHY
PostgreSQL        HEALTHY
PostGIS           HEALTHY
Redis             HEALTHY
Queue             HEALTHY
Realtime          HEALTHY
```

Do not fabricate healthy status.

---

# 48. Queue Health

Queue health should verify meaningful configuration/connectivity where feasible.

Do not mark queue healthy merely because:

```text
QUEUE_CONNECTION=redis
```

exists.

---

# 49. Realtime Health

Realtime health should distinguish configuration from actual client connection where possible.

Admin infrastructure health and frontend connection status are different concepts.

---

# 50. Error Pages

Provide professional error handling for:

```text
403
404
419
429
500
503
```

Do not expose stack traces in production.

403 should clearly communicate lack of authorization without leaking protected resource details.

---

# 51. API Foundation

Create `/api/v1` namespace.

M0 may expose only minimal endpoints.

Potential:

```text
GET /api/v1/me
GET /api/v1/system/health
```

depending on frontend architecture.

Do not create future domain endpoints yet.

---

# 52. API Response Convention

Establish consistent resource/error formatting.

Success example:

```json
{
  "data": {}
}
```

Error example:

```json
{
  "error": {
    "code": "FORBIDDEN",
    "message": "You are not authorized to perform this action."
  }
}
```

Avoid exposing internal exception information.

---

# 53. Security Headers

Configure reasonable security headers compatible with the selected stack.

At minimum evaluate:

```text
X-Content-Type-Options
Referrer-Policy
frame protection
Content-Security-Policy readiness
HSTS production readiness
```

Do not enable HSTS incorrectly in local development.

---

# 54. Rate Limiting

Establish named rate limiters for security-sensitive areas.

At minimum:

```text
login
api
```

Future milestones will add:

```text
patient-search
routing
integrations
```

Do not choose limits that make development unusable.

Production limits must be configurable where appropriate.

---

# 55. Seed Data

Provide synthetic development data.

Create at least:

```text
1 platform Super Administrator

2 demo organizations

Organization A:
- Organization Administrator
- Dispatcher
- Medical Coordinator
- Paramedic
- Ambulance Driver

Organization B:
- Organization Administrator
- Hospital Operator
- Hospital Resource Manager
- Doctor
- Auditor
```

Names and emails must clearly be fictional/demo data.

---

# 56. Seed Credentials

Do not place insecure fixed production credentials into code.

For local development, seed credentials may be documented through safe development conventions.

Clearly mark:

```text
DEVELOPMENT ONLY
```

Never use real personal accounts.

---

# 57. Factories

Create factories for:

- User;
- Organization;
- OrganizationMembership;
- other M0 entities where useful.

Factories should support automated authorization and isolation testing.

---

# 58. Database Constraints

Use foreign keys and appropriate uniqueness constraints.

Examples:

A user should not accidentally have duplicate active membership records for the same organization unless future design explicitly requires it.

Organization slug should be appropriately unique.

Email uniqueness strategy must be explicit.

---

# 59. Indexes

Add indexes for common M0 queries.

Potential:

```text
users.email
users.status

organizations.slug
organizations.status

organization_memberships.organization_id
organization_memberships.user_id
organization_memberships.status

audit_logs.organization_id
audit_logs.actor_id
audit_logs.resource_id
audit_logs.occurred_at
```

Do not index every column blindly.

---

# 60. ULIDs

Prefer ULIDs for major domain entity identifiers.

Potential entities:

```text
users
organizations
organization_memberships
audit_logs
```

If Laravel/package constraints make another strategy significantly cleaner, document the decision before diverging.

Do not mix identifier strategies arbitrarily.

---

# 61. Time

Persist canonical timestamps consistently.

Application should use UTC internally.

Default display timezone for demo organization may be:

```text
Europe/Bucharest
```

but organization timezone must be configurable.

Do not hard-code Romanian local time throughout the application.

---

# 62. Testing Strategy

M0 requires automated tests.

Tests must not merely verify pages render.

They must verify security and domain boundaries.

---

# 63. Authentication Tests

Test:

```text
guest cannot access protected application
active user can login
disabled user cannot obtain normal operational access
suspended user cannot obtain normal operational access
logout terminates session appropriately
```

---

# 64. Organization Tests

Test:

```text
user can access own organization
user cannot access unrelated organization
user cannot switch to organization without membership
inactive membership prevents normal organization access
```

---

# 65. Permission Tests

Test:

```text
ordinary user cannot access admin
organization admin can access permitted administration
auditor can view audit if permission exists
user without audit.view cannot view audit
```

---

# 66. Super Administrator Tests

Test platform-level behavior separately.

Do not make tests pass by giving all users global permissions.

---

# 67. Realtime Authorization Tests

Test:

```text
unauthenticated user cannot authorize private organization channel

user from Organization A cannot subscribe to Organization B channel

authorized Organization A user can subscribe to Organization A channel
```

---

# 68. Audit Tests

Test at least selected actions:

```text
user creation generates audit
user status change generates audit
role assignment generates audit
```

Audit records should contain expected actor/resource/context without storing secrets.

---

# 69. Health Tests

Test:

```text
public liveness does not leak infrastructure details

unauthorized user cannot access detailed health

authorized user can access detailed health
```

Where infrastructure health is difficult to test deterministically, isolate health check services so they can be tested.

---

# 70. API Tests

If `/api/v1/me` exists:

Test:

```text
guest rejected
authenticated user receives only expected information
organization context represented safely
```

---

# 71. Cross-Organization Security Test

This is mandatory.

Create:

```text
Organization A
Organization B

User A → Organization A
Resource B → Organization B
```

Verify User A cannot access or mutate Resource B.

M0 resources can use organization/member administration for this proof.

---

# 72. Test Naming

Tests should describe behavior.

Good:

```text
it_rejects_users_accessing_an_unrelated_organization
```

or Pest equivalent.

Bad:

```text
test1
test_admin
```

---

# 73. Code Quality

Before declaring M0 complete:

- remove debug code;
- remove `dd()`;
- remove `dump()`;
- remove temporary routes;
- remove unused scaffolding;
- format code;
- run static checks available in repository;
- run full automated tests.

---

# 74. README

Create/update root `README.md`.

Include:

- MEDGRID overview;
- development status;
- prerequisites;
- installation;
- environment setup;
- PostgreSQL setup;
- PostGIS setup;
- Redis setup;
- migrations;
- seeders;
- frontend build;
- queue worker;
- Reverb;
- test commands.

Do not claim production readiness.

---

# 75. Local Development Commands

README should make local startup clear.

Conceptual example:

```text
composer install
npm install

php artisan migrate --seed

php artisan serve
php artisan queue:work
php artisan reverb:start

npm run dev
```

Use actual commands appropriate to implementation.

---

# 76. Development Experience

Local MEDGRID should be reasonably easy to start.

Do not require unnecessary infrastructure beyond:

- PHP;
- Composer;
- Node;
- PostgreSQL/PostGIS;
- Redis.

Optional future services must not be required for M0.

---

# 77. Windows / Laragon

Primary current development environment is Windows with Laragon.

Avoid architecture that unnecessarily assumes Linux-only local development.

Production may later use Linux/containerized infrastructure.

Document platform-specific setup where necessary.

---

# 78. No Docker Requirement

Do not make Docker mandatory for M0 unless explicitly requested.

Docker support may be added later.

The application should run directly under the current Laragon development environment.

---

# 79. No Production Claims

At the end of M0, MEDGRID is:

```text
FOUNDATION COMPLETE
```

It is NOT:

```text
production ready
clinically validated
112 integrated
GDPR certified
medical-device certified
hospital certified
```

Do not make these claims in UI or documentation.

---

# 80. M0 UI Pages

Expected minimum pages:

```text
/login

/dashboard or application landing

/dispatch
/ambulance
/hospital
/medical
/control

/admin
/admin/organizations
/admin/users
/admin/roles
/admin/audit
/admin/system/health
```

Exact route organization may differ if implementation provides a cleaner structure.

---

# 81. Landing After Login

After authentication, route user to an appropriate application landing page.

If user has multiple application areas, provide a clear application selector/dashboard.

Do not send every user to `/admin`.

---

# 82. Application Selector

Potential initial cards:

```text
DISPATCH

AMBULANCE

HOSPITAL

MEDICAL COORDINATION

CONTROL CENTER

ADMINISTRATION
```

Only show areas user can access.

Server authorization must still protect routes.

---

# 83. Organization Switcher

If user belongs to multiple organizations:

Display current organization and allow authorized switching.

Example:

```text
MEDGRID

Organization:
[ Bucharest Demo Emergency Service ▼ ]
```

Switching organization must re-evaluate available roles/permissions.

---

# 84. Permission Cache

If permission caching is used, invalidation must occur correctly when:

- role changes;
- permission changes;
- membership changes;
- user status changes.

Do not leave privileged access active because of stale authorization cache.

---

# 85. Account Suspension

Suspending/disabling a user should invalidate future access.

Where practical in M0, active sessions should be revoked or prevented from continuing normal requests after status changes.

Do not only block the next login while existing sessions remain indefinitely privileged.

---

# 86. Session Management Groundwork

Full device-management UI is not required.

Architecture should make future session revocation possible.

Do not tightly couple security to a single permanent session implementation.

---

# 87. Admin User Management

M0 admin functionality should support:

- list users;
- create user;
- view user;
- update safe profile/account fields;
- change status;
- manage organization membership;
- assign organization-context roles where implemented.

Do not expose password hashes or security secrets.

---

# 88. Admin Organization Management

M0 should support:

- list organizations;
- create organization;
- view organization;
- update organization;
- activate/deactivate organization.

Do not hard-delete organizations through ordinary UI.

---

# 89. Role Management

Role management should be safe.

If M0 exposes editing:

- only authorized users;
- organization scope respected;
- platform roles protected;
- privilege escalation prevented.

Do not allow an Organization Administrator to grant platform Super Administrator unless explicitly authorized by platform-level policy.

---

# 90. Audit Viewer Security

Audit viewer must itself be auditable where practical.

At minimum, access requires explicit permission.

Do not allow organization-level auditors to see unrelated organizations unless policy explicitly permits it.

---

# 91. Health Dashboard Security

Detailed infrastructure errors can reveal useful attacker information.

Display enough information for administrators without exposing:

- credentials;
- connection strings;
- filesystem secrets;
- stack traces.

---

# 92. M0 Acceptance Criteria

M0 is complete only when ALL required criteria below pass.

### Application

- Laravel application runs successfully.
- Frontend assets build successfully.
- Protected application layout works.

### Database

- PostgreSQL works.
- migrations run from clean database.
- PostGIS availability is verified/documented.
- seeders run successfully.

### Redis

- Redis connectivity works.

### Queue

- Redis queue infrastructure works.
- a test/demo job can be processed.

### Realtime

- Reverb starts successfully.
- authenticated client connects.
- authorized organization event can be received.
- unauthorized channel access is rejected.
- connection state is visible.

### Authentication

- login works.
- logout works.
- public registration is disabled unless explicitly justified.
- disabled/suspended accounts are blocked appropriately.

### Organizations

- organizations exist.
- users may have memberships.
- organization switching is authorized.
- organization isolation works.

### Authorization

- roles exist.
- permissions exist.
- policies enforce server-side access.
- Super Administrator and Organization Administrator are distinct.

### Administration

- organizations can be managed.
- users can be managed.
- role/permission infrastructure is available.
- audit viewer works.
- system health page works.

### Audit

- audit records are generated for selected sensitive actions.
- audit records cannot be edited through normal application UI.

### Security

- `.env` is not tracked.
- no secrets are committed.
- sensitive admin routes require authorization.
- cross-organization test passes.

### Testing

- full M0 test suite passes.
- security tests pass.
- authorization tests pass.
- realtime channel authorization tests pass.

### Documentation

- README reflects actual setup.
- architecture docs remain consistent with implementation.

---

# 93. M0 Final Verification

Before reporting completion, Codex must run all applicable commands.

Examples:

```text
composer test
```

or:

```text
php artisan test
```

and:

```text
npm run build
```

plus formatting/static checks configured by the repository.

Also verify:

```text
php artisan migrate:fresh --seed
```

against the development/test database where safe.

Never run destructive migration commands against an unknown production database.

---

# 94. Git Review

Before milestone completion:

```text
git status
git diff
```

Review for:

- accidental secrets;
- temporary files;
- generated junk;
- debug statements;
- unintended deletions.

Do not automatically push.

---

# 95. Suggested Release Commit

Only if explicitly requested to commit:

```text
feat: MEDGRID v0.1.0 Foundation
```

Optional tag after verification:

```text
v0.1.0
```

Do not tag failing/incomplete work.

---

# 96. Completion Report

When M0 is complete, Codex must report:

## Implemented

List completed functionality.

## Architecture

Important decisions made.

## Database

Tables/migrations introduced.

## Authentication & Authorization

What is enforced.

## Realtime

What was configured and verified.

## Security

Security controls implemented.

## Tests

Exact test results.

Example:

```text
48 tests passed
0 failed
```

Do not invent results.

## Build

Report frontend build result.

## Known Limitations

Explicitly list intentionally deferred items.

## Next Milestone

State:

```text
M1 — Dispatch
```

but do NOT begin M1 automatically.

---

# 97. Stop Conditions

Codex must stop and report instead of guessing if:

- required environment dependency is unavailable;
- PostgreSQL cannot be configured;
- PostGIS cannot be enabled and affects implementation;
- Redis is unavailable;
- Laravel/Reverb version incompatibility exists;
- architectural documents conflict;
- an implementation choice would materially weaken security;
- existing user code would need destructive replacement;
- required package is incompatible/unmaintained;
- an unknown production-like database could be affected.

Small ordinary implementation decisions do not require stopping.

---

# 98. Forbidden Shortcuts

Do NOT:

```text
give every user admin
```

Do NOT:

```text
use frontend-only authorization
```

Do NOT:

```text
fake health checks
```

Do NOT:

```text
mark realtime LIVE without connection
```

Do NOT:

```text
use SQLite because PostgreSQL setup is inconvenient
```

Do NOT:

```text
disable CSRF globally
```

Do NOT:

```text
disable authorization temporarily and forget it
```

Do NOT:

```text
hardcode production secrets
```

Do NOT:

```text
create fake emergency/clinical workflows in M0
```

Do NOT:

```text
start M1 early
```

---

# 99. M0 Deliverable

The final result should feel like the secure operating shell of a serious emergency coordination platform.

A developer should be able to:

```text
start MEDGRID
      ↓
login
      ↓
select authorized organization
      ↓
see permitted application areas
      ↓
observe realtime connection health
      ↓
use authorized administration
      ↓
inspect system health
      ↓
inspect audit records
```

while automated tests prove that unauthorized users and unrelated organizations cannot cross security boundaries.

---

# 100. Final M0 Rule

Do not optimize M0 for the number of features implemented.

Optimize it for the quality of the foundation.

Every future MEDGRID milestone depends on the correctness of:

- identity;
- organization isolation;
- authorization;
- persistence;
- realtime infrastructure;
- queues;
- audit;
- testing;
- security.

M0 is complete only when that foundation is trustworthy enough to build M1 on top of it.