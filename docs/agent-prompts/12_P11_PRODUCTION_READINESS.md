# 12: Production readiness (the parts of T-P11-03/04 that can be done now)

> Read `docs/agent-prompts/00_COMMON.md` first. Do not enter real secrets and do not change any external service. Everything here is configuration, documentation and local checks.

## Scope
1. **Production config.** Review `config/*` and `.env.example`, then write `docs/ops/PRODUCTION_CONFIG.md`: every required variable, where its value comes from, and a safe default. Cover:
   - `APP_DEBUG=false`
   - secure cookies and `SESSION_SECURE_COOKIE`
   - trusted proxies and HSTS
   - a CSP, after checking that Livewire and Filament still work with it
   - `config:cache`, `route:cache`, `view:cache` and `event:cache` all run without errors.
2. **Queues and scheduler.** Horizon or queue workers, and `schedule:run` with every scheduled command (reminders, `privacy:prune`, reconciliation). Document them and check them against `schedule:list`.
3. **Backup and restore.** Write a backup script for the database and private storage. Then run a **local restore drill** on a scratch database, with a written integrity check (counts and a sample of files). Document it in `docs/ops/BACKUP_RESTORE.md`.
4. **Monitoring.** Wire up Sentry (an approved decision) without a real DSN, with PII scrubbing and release tagging. Add a health endpoint. Make sure logs contain no secrets or personal data.
5. **Email (SES, approved).** An SPF/DKIM/DMARC checklist, bounce and complaint handling, and a test of every template with the log mailer in Arabic and English.
6. **Storage and CDN (Bunny Stream, approved).** Check the adapter without keys, and check what happens when the service is down.
7. **Production boot.** Run `composer install --no-dev` and boot in production mode. This guards against the earlier Telescope regression coming back.

## Output
- The `docs/ops/*` files, commits for any config fixes, and an updated `docs/ENVIRONMENT_INVENTORY.md`.
- A checklist of what the owner must provide before launch, with each item linked to its O-xxx.
- A summary in Arabic.
