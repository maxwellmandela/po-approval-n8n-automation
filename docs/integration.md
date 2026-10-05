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

## Endpoint

- Method: `POST`
- URL: `/api/v1/webhooks/procurement`
- Authentication: configurable secret in `N8N_WEBHOOK_SECRET` and `X-N8N-Signature` header
- Purpose: receive events from Laravel for downstream automation

### Example request

```bash
curl -X POST "https://your-laravel-app.test/api/v1/webhooks/procurement" \
  -H "Content-Type: application/json" \
  -H "X-N8N-Signature: <sha256-hmac>" \
  -d '{
    "event": "procurement.request.approved",
    "request_id": 42,
    "request_number": "PR-2026-0042",
    "amount": 85000,
    "status": "approved"
  }'
```

## Security expectations

- Keep the Laravel app behind normal authentication and TLS.
- Store the shared secret in the environment as `N8N_WEBHOOK_SECRET`.
- Validate the HMAC signature before processing inbound automation messages.
- Keep n8n as an automation consumer, not as the business authority.

## n8n responsibilities

n8n may:

- send email notifications,
- push updates to Google Sheets,
- create operational reports,
- enqueue downstream actions.

n8n should not:

- approve or reject a procurement request,
- create purchase orders,
- mutate budget data,
- act as the source of truth for workflow state.

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
