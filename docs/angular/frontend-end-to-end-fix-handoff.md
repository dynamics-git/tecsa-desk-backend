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

## Backend References
- `app/Http/Requests/Setup/UpsertPermissionRoleRequest.php`
- `app/Support/Auth/SupportAccessResolver.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Support/Auth/CurrentUserResolver.php`
