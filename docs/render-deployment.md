# Render Staging Deployment

This deploys the existing Laravel app as a Docker Web Service backed by Render PostgreSQL. It is a single app instance; uploaded documents remain on ephemeral container storage in this first staging setup.

## Create PostgreSQL

1. Create a PostgreSQL database in Render.
2. Use its **Internal Database URL** for the Laravel service's `DB_URL` environment variable.
3. Keep this database dedicated to the procurement app; do not put the n8n database in the Laravel schema.

## Create the Web Service

1. Connect the repository in Render and create a **Web Service** with **Docker** as the runtime. Render will build the root `Dockerfile`.
2. Set the health check path to `/up`.
3. Use one instance. The entrypoint listens on Render's `PORT`, runs `php artisan migrate --force`, then starts Apache.
4. Wait for the first deploy and copy the stable HTTPS service URL into `APP_URL`.

## Environment Variables

Configure these in the Render service settings; do not add real secrets to the Dockerfile or repository.

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generate-a-new-key-for-this-environment>
APP_URL=https://<render-laravel-host>

DB_CONNECTION=pgsql
DB_URL=<render-postgres-internal-database-url>
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync

N8N_BASE_URL=https://<reachable-n8n-host>/webhook/procurement-events
N8N_WEBHOOK_SECRET=<same-secret-configured-in-n8n>
PROCUREMENT_REPLY_TO=<monitored-procurement-mailbox>
```

Generate a unique `APP_KEY` for staging (for example with `php artisan key:generate --show`) and keep it stable across redeploys. Use the active n8n production webhook URL, not `/webhook-test/`; the test URL only works while the workflow is listening for a test event.

## First Deploy Checks

1. Confirm Render reports `/up` as healthy and the app can connect to PostgreSQL.
2. Seed the demo data once from the Render Shell with `php artisan db:seed --force` if needed. Do not put demo seeding in the container startup command.
3. Activate the n8n workflow and confirm its public webhook is reachable from Render.
4. Log in with the seeded requester, submit one test request, and inspect the n8n execution and approver email.

## Current Staging Limitations

- Keep one Laravel instance; SQLite is not used in this deployment.
- Uploaded supporting documents use local container storage and can disappear during a deploy or restart. Add persistent/object storage before relying on attachments.
- The current Laravel-to-n8n event dispatch is synchronous. If n8n is unavailable, the request submission can still be affected; queue/outbox delivery is a later reliability improvement.
- Keep n8n on an always-on service so its Gmail Trigger can poll continuously. A sleeping service is not appropriate for incoming mail monitoring.