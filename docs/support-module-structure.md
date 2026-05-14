# Support Module Structure

Recommended long-term structure for the support desk domain:

```text
app/
  Http/
    Controllers/
      Api/
        SupportTicketController.php
    Requests/
      Support/
        BulkAssignTicketsRequest.php
        BulkUpdatePriorityRequest.php
        BulkUpdateStatusRequest.php
        ListTicketsRequest.php
        ReplyToTicketRequest.php
  Support/
    DTOs/
      ActivityDto.php
      PaginatedTicketsDto.php
      RelatedItemDto.php
      TicketDetailDto.php
      TicketListItemDto.php
    Enums/
      TicketPriority.php
      TicketStatus.php
    Repositories/
      SupportTicketRepositoryInterface.php
      EloquentSupportTicketRepository.php
    Services/
      SupportTicketService.php
routes/
  api.php
docs/
  postman/
    support-ticket-api.postman_collection.json
database/
  migrations/
    2026_05_02_000000_create_support_ticket_tables.php
    2026_05_03_000003_create_support_ticket_linked_tasks_table.php
  seeders/
    SupportTicketSeeder.php
```

Notes for production hardening:

- Keep the repository interface as the contract so data access can evolve without changing controllers or Angular-facing responses.
- Keep request validation at the HTTP edge and business orchestration in `SupportTicketService`.
- Keep list and detail DTOs separate so grid responses stay small while the detail pane can include SLA, activity, and related records.
- Add authorization policies once agent/team visibility rules are known.
- Extend OpenAPI annotations when a Swagger package such as `zircote/swagger-php` or `darkaonline/l5-swagger` is installed.
