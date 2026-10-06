# Gmail Approver Replies

This workflow lets approvers respond to email while Laravel remains the system of record. Approvers must have a Laravel user record and email address for assignment, but they do not need to sign in to the portal to make decisions.

## Mailbox setup

Use a monitored Gmail or Google Workspace mailbox (or alias) for approval replies. Configure approver notification emails in n8n with this mailbox as the `Reply-To` address. Approvers reply from their own assigned address, which Laravel checks against the request's current approver.

Use a stable HTTPS URL for Laravel. The temporary Codespaces forwarded URL is not suitable for a production n8n connection.

Configure these values on the Laravel host:

```env
N8N_BASE_URL=https://<n8n-host>/webhook/procurement-events
N8N_WEBHOOK_SECRET=<long-random-shared-secret>
PROCUREMENT_REPLY_TO=procurement@example.com
```

`N8N_BASE_URL` is the active n8n production webhook that receives Laravel lifecycle events. Laravel adds `PROCUREMENT_REPLY_TO` to each event as `reply_to`; n8n uses it for the email's Reply-To header. Store the same secret in n8n credentials; do not put real credentials in workflow JSON or source control.

## Notification workflow

1. Import [`n8n/procurement-notifications.workflow.json`](../n8n/procurement-notifications.workflow.json).
2. Create an n8n Header Auth credential with header name `X-N8N-Webhook-Secret` and the same value as Laravel's `N8N_WEBHOOK_SECRET`; assign it to the Webhook trigger. Laravel also sends `X-N8N-Signature`, but this export authenticates with the shared-secret header.
3. Assign a Gmail OAuth2 credential to the send node and replace the `REPLACE_WITH_MONITORED_GMAIL_ADDRESS` reply-to placeholder.
4. Set Laravel's `N8N_BASE_URL` to the active production webhook URL shown in the imported n8n Webhook node.
5. Activate the workflow only after the webhook credential, Gmail credential, and reply-to address are configured.
6. Send to the event's `approver.email` or `requester.email` as appropriate. The request number and thread type appear in the subject so replies can be correlated.

When manually testing the n8n webhook with curl, include a `reply_to` value in the JSON payload. Laravel supplies it automatically from `PROCUREMENT_REPLY_TO` during normal operation.

## Reply workflow

1. Import [`n8n/workflow.json`](../n8n/workflow.json).
2. Assign Gmail OAuth2 credentials to the Gmail Trigger and manual-review email node. Keep the full Gmail payload, including the `Authentication-Results` header and message ID; the classifier requires `dmarc=pass` before it allows a callback.
3. Create/assign an n8n Header Auth credential with header name `X-N8N-Webhook-Secret` and value matching Laravel's `N8N_WEBHOOK_SECRET` to both HTTP Request nodes.
4. Replace `REPLACE_WITH_LARAVEL_HOST` on both nodes with the stable HTTPS Laravel host and replace `REPLACE_WITH_MANUAL_REVIEW_ADDRESS` with an operational mailbox.
5. Route `action = needs_review` to manual follow-up. Do not call Laravel for those messages. Cancellation is not currently supported.
6. Route `approve`, `reject`, and `clarify` to the approver HTTP Request node. Route `clarification_response` to the requester HTTP Request node.
7. Activate only after credentials, placeholders, callback URL, and Laravel migration are configured.

Map the HTTP Request JSON body from the Code node output:

```json
{
  "message_id": "={{$json.message_id}}",
  "request_number": "={{$json.request_number}}",
  "sender_email": "={{$json.sender_email}}",
  "sender_authenticated": "={{$json.sender_authenticated}}",
  "action": "={{$json.action}}",
  "comment": "={{$json.comment}}"
}
```

For `clarification_response`, POST to `https://<laravel-host>/api/v1/requester/email-clarification-response` with `message_id`, `request_number`, `sender_email`, `sender_authenticated`, and `body` (map `body` from `comment`). Laravel verifies the sender is the requester and that the request has a pending clarification before storing the reply and emitting `procurement.request.resubmitted`.

The classifier only accepts a single recognizable decision. Clear replies such as `Approved.`, `Reject. The quote is over budget.`, and `Please clarify why this model is required.` are recognized. Missing request IDs, conflicting intent, cancellations, failed/missing DMARC authentication, or unclear replies are sent to manual follow-up instead of changing procurement state.

Laravel then verifies the shared secret, verifies that `sender_email` belongs to the currently assigned approver, applies the decision through the workflow service, records the message ID to prevent duplicate processing, and emits the normal lifecycle event. n8n can use that event to notify the requester.

## Smoke test

1. Submit a test request and confirm the approver notification contains its request number.
2. Reply from the assigned approver's Gmail address with `Please clarify why this item is needed.` Confirm Laravel returns `clarification_required` and the requester receives the clarification email.
3. Submit the clarification in the requester portal and confirm the approver receives a resubmitted notification.
4. Reply `Approved.` from the assigned approver mailbox. Confirm the request becomes approved and the requester is notified.
5. Replay the same Gmail message through n8n. Laravel should acknowledge it as a duplicate without applying the decision twice.