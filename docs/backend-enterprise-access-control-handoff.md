# Backend Handoff: Enterprise Access-Control Final Alignment

## Final Naming Decision (Current Release)
- Backend response standard: camelCase only.
- Backend request standard: camelCase only.
- Frontend contract: camelCase only.
- No more naming convention changes until release is stable.

One-line summary:
Keep camelCase as backend wire-format output and input, and avoid naming drift until release stabilization.

## Base URL
http://localhost/tecsa-desk-backend/public/api

## Auth Endpoints
- POST /auth/login
- GET /auth/me
- POST /auth/logout

## Setup Endpoints (Canonical, Do Not Rename)
- GET/POST/PUT/DELETE /setup/permission-roles
- GET/POST/PUT/DELETE /setup/customer-user-access
- GET/POST/PUT/DELETE /setup/support-user-scope
- GET /setup/users
- GET /setup/customers
- GET /setup/teams
- GET /setup/queues

## Response Shape Rules Frontend Supports
List endpoints may return any one of:
- array
- { items: [...] }
- { data: [...] }
- { results: [...] }
- { rows: [...] }
- { list: [...] }
- nested { data: { items|data|results|rows|list } }

Single record may return a direct object.

## Required Setup Payload Contract (Canonical Output Keys)

### permissionRoles
Create/Update keys:
- id
- name
- userType: Internal | Customer
- ticketVisibility: All | Team | Assigned | TeamAndAssigned | Customer | Own
- permissions: string[]
- userIds: string[]
- teamIds: string[]
- customerIds: string[]
- isActive: boolean

### customerUserAccess
Create/Update keys:
- id
- userId
- userName
- userEmail
- customerId
- customerName
- accessLevel: OwnTickets | CompanyTickets | Admin
- canCreateTicket: boolean
- canViewAttachments: boolean
- canReply: boolean
- isActive: boolean

### supportUserScope
Create/Update keys:
- id
- userId
- userName
- visibilityMode: All | Team | Assigned | TeamAndAssigned | Customer | Own
- teamIds: string[]
- queueIds: string[]
- customerIds: string[]
- isActive: boolean

## Auth Me Payload Contract
GET /auth/me returns either:
- { user: { ... } }
- or direct { ... }

Required user fields (camelCase output):
- id
- name
- email
- userType
- role
- permissions: string[]
- teamIds: string[]
- queueIds: string[]
- customerIds: string[]
- ticketVisibility
- isAdmin

If customer access is returned, use object array form:
- customerAccess: [{ customerId, customerName, accessLevel, canCreateTicket, canViewAttachments, canReply }]

## Request Input Rules
Backend request payloads must use camelCase.

Examples:
- userId
- teamIds
- ticketVisibility
- isActive

## Stability Rules
- Arrays must always be arrays, never null.
- Booleans must always be true/false.
- Do not omit relation keys after create/update responses.

Critical keys that must remain in save responses:
- permissionRoles: permissions, userIds, teamIds, customerIds
- customerUserAccess: userId, customerId, accessLevel
- supportUserScope: userId, visibilityMode, teamIds, queueIds, customerIds

## Implementation Status (2026-05-17)

Completed:
- Created and wired enterprise upsert requests:
	- `UpsertPermissionRoleRequest`
	- `UpsertCustomerUserAccessRequest`
	- `UpsertSupportUserScopeRequest`
- Setup controllers now use these upsert requests for both create and update.
- Models updated with required fillables/casts so arrays and booleans are stable.
- Enterprise migrations created and applied successfully.

Migrate output snapshot:
- `2026_05_17_041259_add_enterprise_indexes_to_customer_user_access_table ... DONE`
- `2026_05_17_041301_add_enterprise_scope_columns_to_support_user_scope_table ... DONE`

Cache clear snapshot:
- `php artisan optimize:clear` completed successfully.

Route smoke check snapshot:
- Setup routes available for `permission-roles`, `customer-user-access`, `support-user-scope`.
- Auth route `GET api/auth/me` available.

## Verified Runtime Sample Responses

permissionRoles sample:
```json
{
	"id": "3",
	"name": "super-admin",
	"role": "super-admin",
	"userId": "1",
	"userEmail": "amit@example.com",
	"userType": "Internal",
	"ticketVisibility": "All",
	"permissions": ["support.ticket.view", "..."],
	"userIds": [],
	"teamIds": [],
	"customerIds": [],
	"isActive": true,
	"isAdmin": true
}
```

customerUserAccess sample:
```json
{
	"id": "2",
	"userId": "4",
	"userName": null,
	"userEmail": "sarah@example.com",
	"customerId": "hlib",
	"customerName": null,
	"accessLevel": "CompanyTickets",
	"canCreateTicket": false,
	"canViewAttachments": false,
	"canReply": false,
	"isActive": true
}
```

supportUserScope sample:
```json
{
	"id": "2",
	"userId": "1",
	"userName": null,
	"userEmail": "amit@example.com",
	"visibilityMode": "Own",
	"teamIds": [],
	"queueIds": [],
	"customerIds": [],
	"isActive": true
}
```

auth/me sample:
```json
{
	"user": {
		"id": "1",
		"name": "Amit",
		"email": "amit@example.com",
		"userType": "Internal",
		"role": "super-admin",
		"permissions": ["support.ticket.view", "..."],
		"teamIds": [],
		"queueIds": [],
		"customerIds": [],
		"ticketVisibility": "All",
		"customerAccess": [],
		"isAdmin": true
	}
}
```

## Customer Scope Runtime Troubleshooting (Frontend + Backend)

Symptom in UI:
- "No customer scope mapping available for this account."

Meaning:
- Logged user is customer-context, but usable active customer access rows are not available in runtime payload used by UI guards.

Frontend must read from `GET /api/auth/me`:
- `user.userType` should be `Customer`.
- `user.customerAccess` must be object array (not string array).
- At least one row must satisfy:
	- `isActive === true`
	- `canCreateTicket === true` for create flow
	- `customerId` or `customerName` matches selected ticket customer

Expected `customerAccess` row shape:
```json
{
	"customerId": "ATLAS",
	"customerName": "ATLAS",
	"accessLevel": "OwnTickets",
	"canCreateTicket": true,
	"canViewAttachments": true,
	"canReply": true,
	"isActive": true
}
```

Backend enforcement now aligned:
- `SupportAccessResolver` loads only active customer mappings and returns full `customerAccess` objects in auth payload.
- `POST /api/support/tickets` now checks customer create eligibility server-side and returns `403` when mapping does not allow create.

## Enterprise Password Lifecycle (Implemented Phase-1)

Objective

Support enterprise password lifecycle for Internal and Customer users without hardcoded frontend behavior.

Implementation status

- Implemented and live in backend.
- Canonical response keys are camelCase.
- Request payload contract is camelCase.

### Endpoints

- GET /api/auth/password-policy
- PATCH /api/auth/password-policy
- POST /api/auth/password/generate
- POST /api/auth/password/set-by-admin
- POST /api/auth/password/change
- POST /api/auth/password/forgot
- POST /api/auth/password/reset
- GET /api/users/{id}/security-settings
- PATCH /api/users/{id}/security-settings

### Canonical Response Fields

Password policy response:
- minLength
- requireUppercase
- requireLowercase
- requireNumber
- requireSymbol
- disallowCommonPasswords
- historyCount
- maxAgeDays
- lockoutThreshold
- lockoutDurationMinutes
- allowPasswordGenerate
- allowManualPasswordSet
- forceChangeOnFirstLoginDefault
- updatedAt
- updatedBy

Login and auth/me additions:
- mustChangePassword
- passwordExpired
- lockedUntil
- mfaRequired
- nextAction (none | change_password | setup_mfa | contact_admin)

Security settings response:
- userId
- mustChangePassword
- passwordNeverExpires
- mfaRequired
- failedLoginAttempts
- lockedUntil
- passwordLastChangedAt
- passwordExpiresAt
- securityVersion
- version

### Request Naming

Accepted request keys are camelCase only:
- userId
- currentPassword
- newPassword
- forceChangeOnNextLogin
- mustChangePassword
- passwordNeverExpires

### Frontend Quick Contract

POST /api/auth/password/set-by-admin request:
```json
{
  "userId": "15",
  "mode": "generated",
  "forceChangeOnNextLogin": true,
  "notifyUser": false,
  "reason": "admin reset"
}
```

POST /api/auth/password/set-by-admin response:
```json
{
  "userId": "15",
  "mustChangePassword": true,
  "passwordLastChangedAt": "2026-05-17T10:10:56Z",
  "temporaryPassword": "...",
  "auditId": "1"
}
```

POST /api/auth/password/change request:
```json
{
  "currentPassword": "temp_password",
  "newPassword": "Qw#2026SecurePass!"
}
```

POST /api/auth/password/change response:
```json
{
  "changedAt": "2026-05-17T10:15:00Z",
  "mustChangePassword": false,
  "sessionRefreshToken": null
}
```

PATCH /api/users/{id}/security-settings request:
```json
{
  "mustChangePassword": true,
  "accountLocked": false,
  "version": 2
}
```

PATCH /api/users/{id}/security-settings response:
```json
{
  "userId": "15",
  "mustChangePassword": true,
  "passwordNeverExpires": false,
  "mfaRequired": false,
  "failedLoginAttempts": 0,
  "lockedUntil": null,
  "passwordLastChangedAt": "2026-05-17T10:10:56Z",
  "passwordExpiresAt": null,
  "securityVersion": 3,
  "version": 3
}
```

### Security and Compliance Notes

- Password hashes and reset token raw values are never returned in read endpoints.
- Forgot/reset endpoints are rate-limited.
- Password and security updates are audit logged.
- Password history reuse is enforced in policy validation paths.

## Enterprise Conversation APIs (Implemented)

Objective

Support a full enterprise ticket conversation detail screen with email send, threaded activities, attachment delivery URLs, and notification dispatch queueing.

### Endpoints

- POST /api/support/tickets/{id}/email-send
- POST /api/support/tickets/{id}/reply
- GET /api/support/tickets/{id}/activities
- GET /api/support/tickets/{id}/attachments
- POST /api/support/tickets/{id}/attachments/download-all
- POST /api/support/tickets/{id}/notifications/dispatch

### Request/Response Contract

POST /api/support/tickets/{id}/email-send request:
```json
{
	"to": ["customer@example.com"],
	"cc": ["manager@example.com"],
	"bcc": [],
	"subject": "Ticket update",
	"htmlBody": "<p>Update</p>",
	"textBody": "Update",
	"attachmentIds": ["ATT-1001"],
	"parentActivityId": "ACT-9001"
}
```

POST /api/support/tickets/{id}/email-send response:
```json
{
	"activityId": "ACT-10010",
	"providerMessageId": "queued-...",
	"deliveryStatus": "queued",
	"queuedAt": "2026-05-17T12:30:00Z"
}
```

POST /api/support/tickets/{id}/reply response (current):
```json
{
	"success": true,
	"activityId": "ACT-10011",
	"createdAt": "2026-05-17T12:31:10Z"
}
```

Reply body format note (important):
- `POST /api/support/tickets/{id}/reply` now supports both `message` (plain fallback) and `htmlBody`.
- At least one body input must be present: `message` or `htmlBody`.
- Inline resized images in normal reply body can be preserved via `htmlBody`.

Reply request (current, supported):
```json
{
	"message": "plain text fallback",
	"htmlBody": "<p>...</p><img style='width:640px'>",
	"isInternalNote": false,
	"attachmentIds": []
}
```

GET /api/support/tickets/{id}/activities response item fields:
- id
- title
- type
- body
- htmlBody
- authorId
- authorName
- visibility
- isInternal
- parentActivityId
- createdAt
- attachments[]
- mentions[]
- providerMessageId
- deliveryStatus (queued | sent | failed)
- deliveredAt
- failedReason
- isUnread
- readAt
- mentionedCurrentUser
- mentionedNames

GET /api/support/tickets/{id}/attachments response item fields:
- id
- fileName
- size
- mimeType
- uploadedBy
- uploadedAt
- visibility
- ticketId
- ticketSubject
- activityId
- previewUrl (signed)
- downloadUrl (signed)

POST /api/support/tickets/{id}/attachments/download-all request:
```json
{
	"attachmentIds": ["ATT-1001", "ATT-1002"]
}
```

POST /api/support/tickets/{id}/attachments/download-all response:
```json
{
	"downloadUrl": "http://localhost/.../api/support/attachments/bundles/{bundleId}/download?...signature=..."
}
```

POST /api/support/tickets/{id}/notifications/dispatch request:
```json
{
	"eventTypes": ["reply", "email", "forward", "internal_mention"],
	"activityId": "ACT-10011",
	"channels": ["email", "in_app"]
}
```

POST /api/support/tickets/{id}/notifications/dispatch response:
```json
{
	"queuedJobIds": ["uuid-1", "uuid-2", "uuid-3"]
}
```

### Runtime Notes

- Existing auth flow remains unchanged.
- Ticket access control remains policy/resolver-based (view/reply/forward/internal note checks are reused).
- Email send and notification dispatch are queued with retry/backoff.
- Activity stream now includes delivery metadata and linked attachment references via activity-attachment pivot.

## Conversation Read-State (Implemented)

Goal

Allow frontend to highlight only unread activities and apply stronger mention styling when current user is mentioned.

### Endpoints

- GET /api/support/tickets/{ticketId}/activities
- POST /api/support/tickets/{ticketId}/activities/mark-read
- POST /api/support/tickets/{ticketId}/activities/mark-read-all

### Activities Payload Additions

Each activity now includes:
- isUnread: boolean
- readAt: string | null (ISO datetime)
- mentionedCurrentUser: boolean
- mentionedNames: string[]

### Mark Read Requests

POST /api/support/tickets/{ticketId}/activities/mark-read request:
```json
{
	"activityIds": ["ACT-1", "ACT-2", "ACT-3"]
}
```

POST /api/support/tickets/{ticketId}/activities/mark-read response:
```json
{
	"updated": 3
}
```

POST /api/support/tickets/{ticketId}/activities/mark-read-all response:
```json
{
	"updated": 24
}
```

### Data Model

Table: support_activity_reads
- id
- activity_id
- user_id
- read_at
- created_at
- updated_at

Unique index:
- (activity_id, user_id)

### Access Notes

- `mark-read` and `mark-read-all` require bearer token auth (`api.token`).
- Ticket visibility policy is enforced before updating read-state.

### Frontend Alignment Confirmation

Aligned with current frontend implementation:
- Popup composer should call `POST /api/support/tickets/{id}/email-send` with `to/cc/bcc/subject/htmlBody/textBody/attachmentIds/parentActivityId`.
- Normal reply composer can call `POST /api/support/tickets/{id}/reply` with `message` and/or `htmlBody` (at least one required) plus `attachmentIds`.
- Ticket detail and popup refresh should call `GET /api/support/tickets/{id}/activities` and `GET /api/support/tickets/{id}/attachments`.
- Download all should call `POST /api/support/tickets/{id}/attachments/download-all` and use returned signed `downloadUrl`.
- Notification trigger should call `POST /api/support/tickets/{id}/notifications/dispatch`.

Backend status for frontend notes:
- Delivery status transitions are implemented: email rows move `queued -> sent` on job success and `queued -> failed` on job failure.
- Activities endpoint already returns per-activity `attachments[]`, `htmlBody`, read-state fields, and email delivery metadata (`providerMessageId`, `deliveryStatus`, `deliveredAt`, `failedReason`).

Environment prerequisite (ops/config):
- Configure mail provider credentials and sender identity in environment so queued email jobs can deliver through SMTP/provider.
