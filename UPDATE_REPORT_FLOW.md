# ReportCo Report Flow Update

This update addresses the resident/admin flow gaps:

- Residents choose an initial urgency: Normal, Important, Urgent, or Emergency.
- Emergency selection activates the Emergency Override path.
- Final priority remains subject to SVM classification and official review.
- Residents can open/view the full report after submission.
- Residents receive in-app notifications when a report is received, in progress, escalated, or resolved.
- Notifications can be marked read individually or all at once.
- Admins can open reports from the report queue and see resident urgency alongside system priority.

## PostgreSQL setup

Keep your existing PostgreSQL settings in `.env`, including the port configured by your PostgreSQL installation (for this development setup it may be `1024`).

After replacing the backend files:

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed
```

For a fresh development database:

```powershell
php artisan migrate:fresh --seed
```

Do not run `migrate:fresh` against a database containing important data.
