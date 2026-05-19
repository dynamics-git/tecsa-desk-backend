# Frontend End-to-End Fix Handoff

## Purpose
Use this file as the implementation checklist for frontend fixes related to:
- super admin assignment
- role persistence/reopen mismatches
- inactive user login behavior
- access mismatch between selected users and runtime permissions

This contract is aligned with the current backend behavior.

## Core Rules
1. Login identity is email + password.
2. Super admin is role-based only (`isAdmin=true` in permission role).
3. User type values supported by backend are only:
- `Internal`
- `Customer`
4. UI label `Internal (Agent/Staff)` must submit `Internal`.
5. User page should not own super-admin toggle.

## Where Access Comes From
Runtime access is resolved from three setup entities:
1. `users` (lifecycle / active state)
2. `permission-roles` (permissions, `isAdmin`, visibility)
3. `support-user-scope` (team/queue/customer scope)

## Critical Payload Contract
### 1) Create/Update Permission Role
Always send these fields (do not omit on edit):
```json
{
  "name": "super-admin",
  "userType": "Internal",
  "ticketVisibility": "All",
  "permissions": [
    "support.ticket.view",
    "support.ticket.reply",
    "support.ticket.internalNote",
    "support.ticket.assign",
    "support.ticket.changeStatus",
    "support.ticket.changePriority",
    "support.ticket.forward",
    "support.ticket.uploadAttachment",
    "support.ticket.bulkUpdate"
  ],
  "userIds": ["17"],
  "teamIds": [],
  "customerIds": [],
  "isAdmin": true,
  "isActive": true
}
```

Notes:
- `userType` and `ticketVisibility` are required.
- `userIds` is used for assigned users.
- Backend also supports primary `userId`/`userEmail`, but assigned users in `userIds` are now valid for access resolution.

### 2) Create/Update Support User Scope
```json
{
  "userId": "17",
  "userName": "ayan",
  "userEmail": "ayan@example.com",
  "visibilityMode": "All",
  "teamIds": [],
  "queueIds": [],
  "customerIds": [],
  "isActive": true
}
```

### 3) Create/Update User
```json
{
  "name": "ayan",
  "email": "ayan@example.com",
  "isActive": true
}
```

If password is being set or changed, include:
```json
{
  "password": "StrongPass@123",
  "passwordConfirmation": "StrongPass@123"
}
```

Backend setup user requests map camelCase password confirmation aliases to Laravel confirmation validation.

## API Error Handling (Mandatory)
1. If role save returns `422`, show field errors and block success state.
2. Do not silently continue after failed save.
3. If login returns `403` + `ACCOUNT_INACTIVE`, show "Account inactive. Contact admin.".

### Unified Error Response Contract
All API errors now follow one shape so frontend can use a single dialog renderer:

```json
{
  "success": false,
  "code": "VALIDATION_ERROR",
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  },
  "details": {
    "nextAction": "contact_admin"
  },
  "error": {
    "id": "err_01jv...",
    "status": 422,
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "errors": {
      "email": ["The email field is required."]
    },
    "details": {
      "nextAction": "contact_admin"
    }
  },
  "meta": {
    "requestId": "2f5b2d5e-...",
    "timestamp": "2026-05-18T09:21:30Z",
    "path": "/api/auth/login",
    "method": "POST"
  }
}
```

Frontend handling notes:
- Use `message` for primary dialog text.
- Use `code` for deterministic UX branching.
- Use `errors` for field-level validation mapping.
- Show `error.id` and `meta.requestId` in dialog/support copy for quick debugging.

### Quick Error Code -> Dialog Mapping
Use these defaults in a single global dialog component:

| Code | Dialog Title | Primary Message | Action |
| --- | --- | --- | --- |
| `VALIDATION_ERROR` | Validation Failed | Please correct highlighted fields. | Focus first invalid field |
| `AUTH_UNAUTHENTICATED` | Session Expired | Please sign in again to continue. | Redirect to login |
| `AUTH_FORBIDDEN` | Access Denied | You do not have permission for this action. | Contact admin |
| `ACCOUNT_INACTIVE` | Account Inactive | Account inactive. Contact admin. | Contact admin |
| `ACCOUNT_LOCKED` | Account Locked | Account is locked. Try later or contact admin. | Contact admin |
| `RESOURCE_NOT_FOUND` | Not Found | Requested resource was not found. | Refresh list/state |
| `SECURITY_VERSION_CONFLICT` | Outdated Data | Record changed by another request/session. | Reload + retry |
| `TOO_MANY_REQUESTS` | Too Many Requests | Please wait and retry. | Retry after delay |
| `DATABASE_ERROR` | System Error | Data layer failed while processing request. | Retry |
| `SERVER_ERROR` | Unexpected Error | Server error occurred. | Retry |

Dialog footer support text format:
- `Ref: {error.id}`
- `Request: {meta.requestId}`

## UI State Rules
1. After saving role, re-fetch role from API and rebind form.
2. On open/reopen role drawer, render from API response only.
3. Never derive persisted values from stale local state.
4. Ensure saved/reopened values are consistent for:
- `userType`
- `ticketVisibility`
- `isAdmin`
- `isActive`
- `userIds`

### Rebind Safety (Required)
When creating a role, frontend can use a temporary generated ID, while backend may persist a different ID.

Use this order for role rebind after save:
1. Match by save-response ID (`id` or `roleId`) first.
2. Fallback to requested role ID.
3. Fallback to role name match.
4. If none match, show warning and refresh list.

## End-to-End QA Checklist
1. Create user Ayan as Internal (Agent/Staff in UI).
2. Ensure user status is Active.
3. Assign super-admin role with `isAdmin=true`, `userType=Internal`, `ticketVisibility=All`, and include Ayan in `userIds`.
4. Save role and reopen role drawer.
5. Verify `userType`, `ticketVisibility`, and `isAdmin` persist.
6. Login as Ayan.
7. Call `GET /api/auth/me` and verify:
- `user.userType = Internal`
- `user.isAdmin = true`
- `user.ticketVisibility = All`
8. Open ticket list and verify admin-level access.
9. Set Ayan inactive (`isActive=false`).
10. Retry login and verify `403 ACCOUNT_INACTIVE`.
11. Reactivate Ayan and verify login works again.
12. Change Ayan password using `password` + `passwordConfirmation` and verify save passes.
13. Create a new role and verify post-save reopen binds persisted API values (no field drift).

## Fast Debug Matrix
If behavior is wrong, check these in order:
1. Network payload sent by frontend (missing required fields?).
2. Save response status (422 ignored?).
3. Reopen response payload (values persisted?).
4. `/api/auth/me` runtime payload (isAdmin/userType/ticketVisibility).
5. User active status (`isActive`).

## Frontend Implementation Notes
- Keep internal model camelCase.
- Send camelCase request keys.
- Do not send `Agent` as userType value.
- Use explicit enums in UI to avoid invalid payloads.

## Ticket Origin + Sync Contract (Implemented)

### Canonical createdByType
Backend now persists canonical ticket origin as `createdByType`.

Allowed values:
- `Customer`
- `Agent`
- `Admin`
- `System`

Rules:
- Backend computes canonical value from authenticated actor context during ticket creation.
- Frontend may send `createdByType` on create request for schema alignment, but backend remains authoritative.
- Unknown value is rejected with `422 VALIDATION_ERROR` on field `createdByType`.

### Create Ticket Response (Stable IDs)
Create response now always includes both stable IDs and full ticket snapshot:

```json
{
  "success": true,
  "id": "TK-1049",
  "ticketId": "TK-1049",
  "ticket": {
    "id": "TK-1049",
    "ticketId": "TK-1049",
    "status": "Open",
    "priority": "High",
    "createdByType": "Agent",
    "updatedAt": "2026-05-19T12:30:00Z",
    "waitingOn": null,
    "slaState": "on_track"
  }
}
```

Frontend rule:
- Prefer `id`, fallback `ticketId` only if needed for older code paths.

### List + Detail Ticket Fields (Now Present)
Every ticket item used by list/detail now includes:
- `id`
- `ticketId`
- `createdByType`
- `updatedAt`
- `slaState`

### Mutation Sync Snapshot Contract
For assignment/status/priority mutations, backend now returns `tickets[]` sync snapshots.
For reply/forward mutations, backend now returns single `ticket` sync snapshot.

Snapshot shape:

```json
{
  "id": "TK-1048",
  "ticketId": "TK-1048",
  "status": "In Progress",
  "priority": "High",
  "createdByType": "Customer",
  "updatedAt": "2026-05-19T12:35:00Z",
  "waitingOn": "team",
  "slaState": "at_risk"
}
```

Frontend merge rule:
- After any mutation, merge returned snapshot(s) into local ticket store immediately, then optional background refetch.

## SMTP Setup Page Contract (Implemented)

Use this for frontend SMTP setup page.

Setup endpoints (requires `api.token`):
- `GET /api/setup/mail-config`
- `PUT /api/setup/mail-config`
- `POST /api/setup/mail-config/test-connection`

### Save SMTP Config
Request body:
```json
{
  "mailer": "smtp",
  "host": "smtp.example.com",
  "port": 587,
  "encryption": "tls",
  "username": "smtp-user",
  "password": "smtp-secret",
  "fromAddress": "support@example.com",
  "fromName": "Support Desk",
  "replyToAddress": "noreply@example.com",
  "timeout": 30,
  "isActive": true
}
```

Read/save response:
```json
{
  "success": true,
  "config": {
    "mailer": "smtp",
    "host": "smtp.example.com",
    "port": 587,
    "encryption": "tls",
    "username": "smtp-user",
    "hasPassword": true,
    "fromAddress": "support@example.com",
    "fromName": "Support Desk",
    "replyToAddress": "noreply@example.com",
    "timeout": 30,
    "isActive": true,
    "source": "database"
  }
}
```

Security behavior:
- SMTP password is encrypted at rest in DB.
- API never returns plaintext password; frontend must use `hasPassword` flag.

### Test Connection
Request body:
```json
{
  "to": "qa@example.com",
  "subject": "SMTP test",
  "body": "hello",
  "mailer": "smtp",
  "host": "smtp.example.com",
  "port": 587,
  "encryption": "tls",
  "username": "smtp-user",
  "password": "smtp-secret",
  "fromAddress": "support@example.com",
  "fromName": "Support Desk",
  "isActive": true
}
```

Success response:
```json
{
  "success": true,
  "message": "Test email sent successfully."
}
```

### No Duplicate Logic Rule
- Frontend SMTP page only manages config via `/api/setup/mail-config*` endpoints.
- Frontend ticket email send remains `/api/support/tickets/{ticketId}/email-send`.
- Backend provider automatically applies saved setup config when sending ticket emails.

### Recipient Resolution Contract (Important)
Backend notification recipient calculation uses resolved emails from requester/assignee/team/activity context and then applies strict normalization.

Normalization rules:
- Lowercase + trim
- Must pass RFC email validation
- Sender email is excluded from recipients
- Case-insensitive dedupe

Impact:
- Values like `amitdas@tecsa`, `ayan.das@tecsa`, `abc@tecs` are treated as invalid emails and dropped.
- Use fully qualified emails (example: `amitdas@tecsa.com.my`).

Current staging behavior (temporary to unblock frontend integration):
- Backend now accepts and returns email-like identifiers that contain `@` even if they are not RFC-complete domains.
- This prevents frontend recipient lists from appearing "half missing" during early integration.
- Final production hardening should switch back to strict RFC validation and clean seed/setup data to full domains.

Atlas example:
- Ticket requester `abc` resolves through customer access mapping to `abc@tecsa.com.my`.
- Agent `Amit` resolves through users/scope mapping to `amit@tecsa.com.my`.

Frontend rule:
- Treat dispatch response `recipients[event]` as final backend truth.
- Do not reconstruct recipients from display names on frontend.

### Frontend Pseudocode (Use As-Is)
```ts
// 1) Conversation row binding (never infer recipients from requester/agent labels)
function toConversationRow(activity: SupportTicketActivity) {
  return {
    id: activity.id,
    senderName: activity.authorName ?? '',
    senderType: activity.senderType ?? 'System',
    body: activity.body ?? '',
    htmlBody: activity.htmlBody ?? null,
    recipients: {
      to: activity.recipients?.to ?? [],
      cc: activity.recipients?.cc ?? [],
      bcc: activity.recipients?.bcc ?? []
    },
    deliveryStatus: activity.deliveryStatus ?? null,
    failedReason: activity.failedReason ?? null,
    sentAt: activity.deliveredAt ?? activity.createdAt ?? activity.time
  };
}

// 2) Dispatch submit + recipient update
async function dispatchNotifications(ticketId: string, event: NotificationDispatchEvent, activityId: string) {
  const res = await api.post(`/api/support/tickets/${ticketId}/notifications/dispatch`, {
    event,
    activityId
  });

  // backend truth: resolved, deduped, sender-excluded recipients
  const resolved = res.recipients?.[event] ?? [];

  state.notificationRecipients[event] = resolved;
  state.lastDispatchJobIds = res.queuedJobIds ?? [];
}

// 3) Safe UI fallback when recipients missing in payload
function recipientsForDisplay(row: ReturnType<typeof toConversationRow>): string[] {
  const all = [...row.recipients.to, ...row.recipients.cc, ...row.recipients.bcc];
  return all;
  // If empty, render empty recipient chips/list.
  // Do NOT derive emails from requester/agent/customer labels.
}
```

## Conversation Rendering Contract (Strict)
Render conversation rows from backend activity payload only.

Do not generate fallback activity text or fake sender labels.
Forbidden frontend fallbacks:
- "Amit replied"
- "Agent replied"
- "Customer replied"
- dummy sender names
- generated fake message text

Required row fields from API activity:
- sender name: `authorName`
- sender type: `senderType` (`Requester` / `Agent` / `Admin` / `System`)
- message body: `body` and/or `htmlBody`
- sent timestamp: `createdAt` or `time`
- recipient info (if present): `recipients.to`, `recipients.cc`, `recipients.bcc`

Empty state rule:
- If activities array is empty, show exactly: `No conversation yet.`

Behavior rules:
- Do not infer sender from hardcoded text.
- Do not infer sender from arbitrary fallback strings.
- If a field is null/missing, leave that sub-field blank in UI (or hide that sub-section), but keep row bound to real API data.

## Notification Dispatch API (Copy/Paste)
Frontend should call notifications dispatch with current contract only:

Endpoint:
- `POST /api/support/tickets/{ticketId}/notifications/dispatch`

Request body:
```json
{
  "event": "reply",
  "activityId": "ACT-10001"
}
```

Optional fields:
```json
{
  "channels": ["email", "in_app"]
}
```

- If `channels` is sent, it must be sent together with `event` and `activityId` in the same request body.
- If `channels` is omitted, backend default channel must be explicitly defined (example: `email` only).

Response body:
```json
{
  "queuedJobIds": ["2a4f0a3d-..."],
  "activityId": "ACT-10001",
  "recipients": {
    "reply": ["requester@example.com", "agent@example.com"]
  }
}
```

Frontend parsing rules:
- Read dispatch event from request `event` only.
- Read target activity from request `activityId` only.
- Treat `recipients[event]` as computed backend truth.
- Do not send or depend on `includeRequester`/`includeAssignee` flags.
- Do not send or depend on legacy `eventTypes` unless needed for backward compatibility.

### Production Clarifications To Request From Backend (Must Confirm)
To prevent routing confusion and "queued but unclear" delivery states, frontend requires confirmation on these 8 items:

1. Delivery status contract per recipient
- Confirm per-recipient status model supports: `queued`, `sent`, `failed`, `bounced`.
- Confirm per-recipient fields include `failedReason`, `queuedAt`, `sentAt`, `failedAt`, `bouncedAt` (nullable when not applicable).

2. Recipient policy rules
- Confirm backend recipient builder always:
  - includes requester, assignee, and team watchers/participants (by event rules),
  - excludes sender self by default,
  - dedupes emails case-insensitively.

3. Visibility rules
- Confirm internal note/internal mention events never notify customer recipients.
- Confirm public reply/email events notify customer-side participants according to ticket visibility and recipient policy.

4. Idempotency
- Confirm support for `Idempotency-Key` header on dispatch endpoint.
- Confirm repeated dispatch with same key does not enqueue duplicate send jobs.

5. Retry behavior
- Confirm max retries, retry intervals/backoff strategy, and final failure handling (dead-letter/final-failed state).
- Confirm where frontend can read final failure state.

6. Response schema stability
- Confirm response guarantees:
  - `recipients` always keyed by event: `recipients[event]`,
  - `queuedJobIds` always an array (including empty),
  - `activityId` always echoed.

7. Authorization and audit
- Confirm event-level authorization matrix (who can dispatch `reply`, `email`, `forward`, `internal_mention`).
- Confirm audit fields captured per dispatch: `actorId`, `ticketId`, `activityId`, `channels`, `recipientCount`, `event`.

8. Error contract
- Confirm stable error codes for:
  - invalid `activityId`,
  - forbidden event dispatch,
  - resolved recipient list empty,
  - provider outage / downstream delivery provider unavailable.

Recommended backend response shape for frontend stability:
```json
{
  "queuedJobIds": ["uuid"],
  "activityId": "ACT-10001",
  "recipients": {
    "reply": ["requester@example.com", "agent@example.com"]
  },
  "recipientStatuses": {
    "reply": [
      {
        "email": "requester@example.com",
        "status": "queued",
        "failedReason": null,
        "queuedAt": "2026-05-19T09:30:00Z",
        "sentAt": null,
        "failedAt": null,
        "bouncedAt": null
      }
    ]
  }
}
```

## Backend Coverage Status (Single Source)
Use this section as the final source of truth for what is already implemented on backend vs optional hardening.

Implemented now:
- Reply endpoint contract is active (`message`/`htmlBody`/`isInternalNote`/`attachmentIds`/`parentActivityId`/`mentions`).
- Email-send endpoint contract is active (`to`/`cc`/`bcc`/`subject`/`htmlBody`/`textBody`/`attachmentIds`/`parentActivityId`).
- Notifications dispatch supports frontend contract (`event` + `activityId`; optional `channels`).
- Conversation payload includes real sender identity fields (`authorName`, `authorEmail`, `senderType`) and recipient metadata (`recipients`).
- Recipient resolution is backend-driven with sender exclusion and case-insensitive dedupe.
- Internal note/internal mention dispatch path excludes customer recipients.
- Unified API error envelope is active for API failures.
- Ticket origin field `createdByType` is active and persisted (`Customer`/`Agent`/`Admin`/`System`).
- Create ticket response includes stable `id` + `ticketId` + `ticket` object.
- Ticket list/detail payload now includes `ticketId`, `createdByType`, `updatedAt`, and `slaState`.
- Mutation responses now include ticket sync snapshot payload (`ticket` or `tickets[]`) for immediate frontend state updates.
- SMTP setup page API is active (`GET/PUT /api/setup/mail-config`, `POST /api/setup/mail-config/test-connection`).
- Ticket email send uses saved SMTP setup automatically without additional frontend flags.

Optional hardening (not required for current frontend integration):
- Per-recipient delivery status lifecycle (`queued`/`sent`/`failed`/`bounced`) exposed in stable API response.
- Explicit `Idempotency-Key` support for dispatch endpoint.
- Expanded audit surface in dispatch persistence (`actorId`, recipient count, idempotency reference).
- Dedicated error codes for provider outage and empty-resolved-recipient scenarios.

Frontend blocker status:
- No blocker. Frontend can implement against this document as-is.

## Backend References
- `app/Http/Requests/Setup/UpsertPermissionRoleRequest.php`
- `app/Support/Auth/SupportAccessResolver.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Support/Auth/CurrentUserResolver.php`
