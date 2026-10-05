# n8n Workflow Exports

These exports are templates, not live workflows. Import each file into n8n and configure credentials and deployment-specific values in the n8n UI before activation.

- `procurement-notifications.workflow.json`: Laravel lifecycle events enter through an authenticated Webhook node and notifications are sent through Gmail.
- `workflow.json`: Gmail replies are classified and routed to the approver-decision or requester-clarification callback in Laravel. Unclear, unauthenticated, or unsupported messages go to manual review.

## Before activation

1. Configure Laravel with `N8N_BASE_URL` set to the production Webhook URL from the notification workflow, and a strong `N8N_WEBHOOK_SECRET`.
2. Create an n8n Header Auth credential using header `X-N8N-Webhook-Secret` and the same shared secret. Assign it to the notification Webhook trigger and both Laravel HTTP Request nodes.
3. Create a Google OAuth client and configure the Gmail OAuth2 credential in n8n:
	- Enable the Gmail API in the Google Cloud project.
	- Create a Google OAuth client of type **Web application**.
	- Copy the exact **OAuth Redirect URL** displayed by the n8n credential into the Google client's **Authorized redirect URIs**. For local n8n, use the URL shown by n8n; it commonly looks like `http://localhost:5678/rest/oauth2-credential/callback`.
	- In n8n, enter the matching Client ID and Client Secret, save the credential, then click **Sign in with Google** and complete Google's consent flow. Entering the ID and secret alone does not create an access token. Verify n8n shows the credential as **Connected**.
	- Select that connected Gmail credential on the Gmail Trigger, Gmail send node, and manual-review notification node. Reconnect it if a Gmail node reports an invalid grant or missing access token.
	- If the Google OAuth consent screen is in Testing mode, add the Gmail account as a test user.
4. Replace the Laravel host, monitored reply-to address, and manual-review recipient placeholders in the workflow nodes.
5. Run Laravel migrations before enabling the workflows. Keep both workflows inactive until credentials and URLs are verified.
6. Test a submitted request, an approver clarification reply, a requester email reply, and a final approver decision end to end.

Credentials and real URLs are intentionally excluded from these files.
