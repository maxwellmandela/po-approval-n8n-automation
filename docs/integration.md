# Laravel to n8n Integration

## Architecture

```text
Laravel (source of truth)
  -> Procurement workflow state and audit records
  -> IntegrationEventService
  -> HTTPS webhook/API endpoint
  -> n8n automation layer
  -> Email, Google Sheets, and downstream downstream systems
```

Laravel remains the system of record for approval state, budgets, purchase orders, clarifications, and audit logs. n8n is responsible for delivering notifications and integrations, but it must not mutate procurement decisions directly.

Approvers can make decisions by replying to email. n8n classifies the reply and sends the proposed action to Laravel; Laravel verifies the sender and applies the workflow transition.

## Available events

- procurement.request.submitted
- procurement.request.clarification_requested
- procurement.request.resubmitted
- procurement.request.approved
- procurement.request.rejected

## Payload example

```json
{
  "event": "procurement.request.submitted",
  "request_id": 42,
  "request_number": "PR-2026-0042",
  "amount": 85000,
  "department": "Operations",
  "status": "under_review",
  "requester": {
    "name": "Jane Requester",
    "email": "jane@example.com"
  },
  "approver": {
    "name": "Procurement Manager",
    "email": "procurement@example.com"
  }
}
```

## Laravel to n8n events

- Laravel posts lifecycle events to the n8n production webhook configured in `N8N_BASE_URL`.
- Events are signed using `N8N_WEBHOOK_SECRET` in the `X-N8N-Signature` header.
- The n8n workflow uses these events to send notifications and other downstream communications.

## n8n to Laravel email decisions

- Method: `POST`
- URL: `/api/v1/approver/email-decision`
- Authentication: `X-N8N-Webhook-Secret` must match Laravel's `N8N_WEBHOOK_SECRET`.
- Idempotency: `message_id` is the Gmail message ID; duplicate delivery is acknowledged without applying the action twice.
- Supported actions: `approve`, `reject`, `clarify`.
- Required identity: `sender_email` must match the request's currently assigned approver.

Example JSON body:

```json
{
  "message_id": "gmail-message-id",
  "request_number": "PR-2026-0042",
  "sender_email": "approver@example.com",
  "action": "clarify",
  "comment": "Please explain why this model is required."
}
```

Use HTTPS and store the shared secret in an n8n Header Auth credential, not in workflow text. Configure the Laravel URL and secret as n8n credentials/variables.

## Security expectations

- Keep the Laravel app behind TLS.
- Laravel requires the shared secret and verifies that the email sender is the assigned approver.
- Laravel applies status transitions and writes audit records; n8n does not directly update procurement state.
- The message ID is recorded for idempotent processing when Gmail retries delivery.

## n8n responsibilities

n8n may:

- send email notifications,
- push updates to Google Sheets,
- create operational reports,
- enqueue downstream actions.

n8n should not:

- decide an ambiguous email or apply an approval itself,
- create purchase orders,
- mutate budget data,
- act as the source of truth for workflow state.

Replies with conflicting intent, missing request numbers, or cancellation language should be routed for manual follow-up. Cancellation is not currently an approver action.

## Failure and retry

- Treat webhook delivery as best-effort and idempotent.
- Use retry and dead-letter handling in n8n for delivery failures.
- Laravel logs the received payload and can be extended to store outbound dispatch results later.

## Environment configuration

```env
N8N_WEBHOOK_SECRET=replace-with-a-strong-secret
N8N_BASE_URL=https://n8n.example.com/webhook/procurement
```

This keeps the integration contract clean while ensuring that Google Sheets remains reporting output and not the database authority.
