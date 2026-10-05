# Gmail Approver Replies

This workflow lets approvers respond to email while Laravel remains the system of record. Approvers must have a Laravel user record and email address for assignment, but they do not need to sign in to the portal to make decisions.

## Mailbox setup

Use a monitored Gmail or Google Workspace mailbox (or alias) for approval replies. Configure approver notification emails in n8n with this mailbox as the `Reply-To` address. Approvers reply from their own assigned address, which Laravel checks against the request's current approver.

Use a stable HTTPS URL for Laravel. The temporary Codespaces forwarded URL is not suitable for a production n8n connection.

Configure these values on the Laravel host:

```env
N8N_BASE_URL=https://<n8n-host>/webhook/procurement-events
N8N_WEBHOOK_SECRET=<long-random-shared-secret>
```

`N8N_BASE_URL` is the active n8n production webhook that receives Laravel lifecycle events. Store the same secret in n8n credentials; do not put real credentials in workflow JSON or source control.

## Notification workflow

1. Add an n8n Webhook trigger for Laravel's submitted, clarification-requested, resubmitted, approved, and rejected events.
2. Verify Laravel's `X-N8N-Signature` using the shared secret before sending email.
3. Send to the event's `approver.email` or `requester.email` as appropriate.
4. Include the request number and thread type in the subject: `[PR-2026-0042] Approval needed` for approvers, `[PR-2026-0042] Clarification requested` for requesters.
5. Set the email's `Reply-To` to the monitored mailbox. Keep the request number and thread type in the subject when the recipient replies.

## Reply workflow

1. Add a Gmail Trigger for new messages in the monitored inbox. Configure Gmail OAuth2 credentials in n8n and retain the full Gmail payload, including the `Authentication-Results` header and message ID. The classifier requires `dmarc=pass` before allowing an action.
2. Add a Code node and paste the contents of [`n8n/approver-reply-classifier.js`](../n8n/approver-reply-classifier.js).
3. Route `action = needs_review` to a manual follow-up path. Do not call Laravel for those messages. Cancellation is not currently supported.
4. Route `approve`, `reject`, and `clarify` to the approver HTTP Request node. Route `clarification_response` to the requester HTTP Request node.
5. POST approver JSON to `https://<laravel-host>/api/v1/approver/email-decision` with an n8n Header Auth credential named `X-N8N-Webhook-Secret`.

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