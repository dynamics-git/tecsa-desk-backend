# Frontend Access Control Integration Guide

## Purpose
This document is the long-term frontend contract for auth payload mapping, permission guards, scoped ticket UX, and error handling against the backend access-control implementation.

Use this guide for:
- new frontend onboarding
- regression prevention during backend changes
- release readiness checks

## Release Naming Lock (Current Release)
- Backend response format is camelCase only.
- Backend request format is camelCase only.
- Frontend internal models stay camelCase.
- No naming convention changes are allowed until release stabilization.

## Quick Start (Recommended Order)
1. Integrate `GET /api/auth/me` and normalize payload into one `AuthUser` model.
2. Implement `hasPermission()` using canonical `support.ticket.*` keys only.
3. Add UI guards to list page, detail page, and all action buttons.
4. Ensure 403 is handled as access denied, not as a generic system failure.
5. Run the verification checklist at the end of this document before release.

## Backend Contract (Source of Truth)

### Auth Me Response
Endpoint:
- `GET /api/auth/me`

Expected user payload fields:
- `id: string`
- `name: string`
- `email: string | null`
- `userType: "Internal" | "Customer"`
- `role: string`
- `permissions: string[]`
- `teamIds: string[]`
- `queueIds: string[]`
- `customerIds: string[]`
- `ticketVisibility: "All" | "Team" | "Assigned" | "TeamAndAssigned" | "Customer" | "Own"`
- `customerAccess: ("OwnTickets" | "CompanyTickets" | "Admin")[]`
- `isAdmin: boolean`

Frontend must support both response wrappers:
1. wrapped
```json
{ "user": { "id": "...", "userType": "Internal" } }
```
2. direct
```json
{ "id": "...", "userType": "Internal" }
```

### Reference Type (Frontend)
```ts
export type TicketVisibility =
  | 'All'
  | 'Team'
  | 'Assigned'
  | 'TeamAndAssigned'
  | 'Customer'
  | 'Own';

export type CustomerAccess = 'OwnTickets' | 'CompanyTickets' | 'Admin';

export interface AuthUser {
  id: string;
  name: string;
  email: string | null;
  userType: 'Internal' | 'Customer';
  role: string;
  permissions: string[];
  teamIds: string[];
  queueIds: string[];
  customerIds: string[];
  ticketVisibility: TicketVisibility;
  customerAccess: CustomerAccess[];
  isAdmin: boolean;
}
```

## Permission Key Contract
Use these permission keys for all ticket-action UI guards:
- `support.ticket.view`
- `support.ticket.reply`
- `support.ticket.internalNote`
- `support.ticket.assign`
- `support.ticket.changeStatus`
- `support.ticket.changePriority`
- `support.ticket.forward`
- `support.ticket.uploadAttachment`
- `support.ticket.bulkUpdate`

Do not use old aliases such as `ticket.*` in frontend conditions.

## Setup API Path Contract
These setup endpoint paths are canonical and must stay exactly the same:
- `permission-roles`
- `customer-user-access`
- `support-user-scope`

## Frontend Mapping Checklist

### 1) Auth mapping
- Read `auth/me` payload into a single normalized `AuthUser` model.
- Normalize wrapper shape (`response.user ?? response`).
- Default unknown arrays to `[]` and booleans to `false`.

Example mapper:
```ts
function mapAuthUser(response: any): AuthUser {
  const raw = response?.user ?? response ?? {};

  return {
    id: String(raw.id ?? ''),
    name: String(raw.name ?? ''),
    email: raw.email ?? null,
    userType: raw.userType ?? 'Customer',
    role: String(raw.role ?? ''),
    permissions: Array.isArray(raw.permissions) ? raw.permissions : [],
    teamIds: Array.isArray(raw.teamIds) ? raw.teamIds : [],
    queueIds: Array.isArray(raw.queueIds) ? raw.queueIds : [],
    customerIds: Array.isArray(raw.customerIds) ? raw.customerIds : [],
    ticketVisibility: raw.ticketVisibility ?? 'Own',
    customerAccess: Array.isArray(raw.customerAccess) ? raw.customerAccess : [],
    isAdmin: Boolean(raw.isAdmin ?? false),
  };
}
```

### 2) Permission helpers
Implement helper methods:
- `hasPermission(key: string): boolean`
- `canViewTickets(): boolean`
- `canReply(): boolean`
- `canInternalNote(): boolean`
- `canAssign(): boolean`
- `canBulkUpdate(): boolean`

Each helper should reference the canonical `support.ticket.*` key list.

### 3) Ticket list behavior
- Always call list endpoint normally.
- If list returns zero items, show scoped message: `No tickets in your scope`.
- Do not imply system failure for empty scoped result.

### 4) Ticket detail behavior
When opening detail:
- 200 -> render detail
- 403 -> show access-denied panel/message
- 404 -> show ticket-not-found message

Do not retry aggressively on 403.

### 5) Action endpoint behavior
For reply, internal note, assign, status, priority, forward, upload, bulk update:
- hide or disable action if permission is missing
- if API still returns 403 (server wins), show clear unauthorized message and stop action

## Suggested UI Guard Matrix

### Global guards
- Ticket page visibility requires: `support.ticket.view`

### Detail-pane actions
- Reply button: `support.ticket.reply`
- Internal note button: `support.ticket.internalNote`
- Assign action: `support.ticket.assign`
- Change status action: `support.ticket.changeStatus`
- Change priority action: `support.ticket.changePriority`
- Forward action: `support.ticket.forward`
- Upload attachment action: `support.ticket.uploadAttachment`

### Bulk toolbar
- Bulk actions require: `support.ticket.bulkUpdate`

## Response Handling Matrix
Use this matrix consistently in frontend API interceptors and components.

| Status | Meaning | Frontend behavior |
|---|---|---|
| 200/201 | Success | Render data and continue flow |
| 401 | Unauthenticated | Clear auth state and redirect to login |
| 403 | Authenticated but not allowed | Show access-denied UI, do not auto-retry |
| 404 | Not found or out-of-scope by design | Show not-found state |
| 422 | Validation failure | Show field-level errors |
| 500 | Unexpected server error | Show retry message and keep route stable |

## Error Handling Contract

### Auth errors
- 401 from `auth/me`: clear local auth session and redirect to login.

### Authorization errors
- 403 from ticket endpoints: show permission or scope denial UI, keep app stable.

### Validation errors
- 422 from setup endpoints: show field-level validation messages.

### Server errors
- 500: show generic retry message; keep route functional.

## Release Readiness Checklist

Before release, verify all items:
- Auth mapper supports wrapped and direct payload shape.
- UI checks only `support.ticket.*` keys.
- Setup service paths match backend contract exactly.
- Ticket list empty state is scope-aware.
- Ticket detail handles 403 and 404 distinctly.
- Action endpoints handle 403 gracefully.
- API interceptor does not treat 403 as generic network failure.

## Regression Test Cases (Frontend)

### Auth mapping
- internal user payload maps correctly
- customer user payload maps correctly

### Scope visibility
- internal list displays all allowed tickets
- customer list displays only in-scope customer tickets

### Detail access
- customer allowed ticket detail returns 200 and renders
- customer denied ticket detail returns 403 and shows denial UI

### Action guards
- hidden or disabled actions when permission absent
- forced API 403 still handled gracefully

## API Verification Script (Manual)
Use these calls in order when validating environment behavior:
1. `GET /api/auth/me`
2. `GET /api/support/tickets`
3. `GET /api/support/tickets/{allowedTicketId}`
4. `GET /api/support/tickets/{deniedTicketId}`
5. `POST /api/support/tickets/{id}/reply` with missing permission user

Expected outcomes:
- `auth/me` returns normalized fields listed above
- list returns only scope-allowed tickets
- allowed detail is 200
- denied detail is 403
- unauthorized action is 403

## Copy-Paste Handoff Note
Use this note when handing over to frontend engineers:

"Implement auth normalization using docs/angular/frontend-access-control-integration-guide.md. Enforce support.ticket.* permission guards for all ticket actions, keep setup paths as permission-roles/customer-user-access/support-user-scope, and ensure 403 is treated as access-denied (not system error)."
