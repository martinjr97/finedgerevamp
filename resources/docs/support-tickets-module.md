<!-- @guards:admin -->
# Support Tickets Module

This module is used to manage tickets submitted by customers.

## Where to Find It

Menu path:

- `Support Tickets`

URLs:

- Ticket list: [`/admin/support-tickets`](/admin/support-tickets)
- Ticket detail template: `/admin/support-tickets/{supportTicket}`
- Ticket update endpoint template: `PATCH /admin/support-tickets/{supportTicket}`

## What Admins Do Here

1. Review newly submitted customer tickets.
2. Open a ticket to see customer issue details.
3. Update status as work progresses.
4. Capture resolution note when closing/resolving.

## Status Handling

- Opening a new ticket marks it as viewed.
- New tickets move to in-progress when first viewed.
- Resolved tickets are locked from further updates.
- Resolution note is required when marking as resolved.

## Recommended Handling Workflow

1. Sort queue by newest or priority.
2. Open and triage the ticket.
3. Assign/handle action internally.
4. Update status and resolution note clearly.
5. Keep notes short, factual, and audit-friendly.
