# Backend Handoff: Enterprise Access-Control Final Alignment

## Final Naming Decision (Current Release)
- Backend response standard: camelCase only.
- Backend request parsing: accept both camelCase and snake_case during transition.
- Frontend remains unchanged (it already tolerates both).
- No more naming convention changes until release is stable.

One-line summary:
Keep camelCase as backend wire-format output, support dual-input parsing for compatibility, and avoid naming drift until release stabilization.

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

## Request Parsing Compatibility (Input)
Backend must accept both styles for incoming payloads:
- camelCase (preferred)
- snake_case (compatibility)

Examples:
- userId or user_id
- teamIds or team_ids
- ticketVisibility or ticket_visibility
- isActive or is_active

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
- Request parser accepts camelCase and snake_case where applicable.

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

### Request Compatibility

Dual input accepted for transition examples:
- userId or user_id
- currentPassword or current_password
- newPassword or new_password
- forceChangeOnNextLogin or force_change_on_next_login
- mustChangePassword or must_change_password
- passwordNeverExpires or password_never_expires

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
