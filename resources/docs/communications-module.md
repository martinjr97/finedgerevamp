<!-- @guards:admin @any-permissions:communications.view,communications.create,communications.send,customers.send-message -->
# Communications Module

This module is used by admins to send notifications to customers using email, SMS, or both.

## Where to Find It

Menu path:

- `Communications` -> `View Communications`
- `Communications` -> `Send Communication`

URLs:

- List: [`/admin/communications`](/admin/communications)
- Create/send: [`/admin/communications/create`](/admin/communications/create)
- Detail template: `/admin/communications/{communication}`

## Permissions

Typical permissions used in this module:

- `communications.view` to view communication history
- `communications.create` to open compose page
- `communications.send` to send bulk communication
- `customers.send-message` to send a direct message to a specific customer

## Bulk Notification Flow

1. Open [`/admin/communications/create`](/admin/communications/create).
2. Choose type: `sms`, `email`, or `both`.
3. Enter subject (required for email/both) and message.
4. Apply customer filters (product, province, age group, active-loan state, gender).
5. Send communication.
6. Review delivery summary in communication details page.

## Direct Message to One Customer

Use customer-level action from customer management pages.

Route used by the system:

- dynamic endpoint: `/admin/customers/{customer}/send-message`
- starting page: [`/admin/customers`](/admin/customers)

This is useful for targeted follow-up on a single account.

## Operational Notes

- If no customers match filters, sending is blocked.
- Communication records keep sent/failed counters and status for traceability.
