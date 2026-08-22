# ReportCo fixes applied

Applied using the staged fix plan:

- Added dedicated admin report detail API: `GET /api/admin/reports/{report}`.
- Kept resident `GET /api/reports/{report}` ownership protection unchanged.
- Added admin report-detail test coverage.
- Added admin report search/filter query support for q, Purok, Street, priority and status.
- Added frontend admin report detail view with status update, emergency override and LGU escalation controls.
- Separated admin navigation from resident navigation.
- Added admin routes for Reports, Map, Analytics and IoT Monitoring.
- Improved admin map popup and linked it to admin report detail.
- Connected existing IoT and alert APIs to the admin UI.
- Added LGU escalation frontend integration using the existing backend endpoint.
- Added responsive admin filter styling.

## Verification

- All backend PHP files pass `php -l` syntax checks.
- All frontend JS files pass `node --check` syntax checks.
- Full Laravel tests could not be executed in this package because `vendor/` is not included.
- Frontend Vite build could not be executed because dependencies were not installed in this environment.

## Remaining dependency issue

The supplied `composer.lock` contains Symfony 8.1.x packages requiring PHP >= 8.4.1 while `composer.json` declares PHP ^8.3. Composer was not available in the build environment, so the lock file was not rewritten blindly. On the development machine, resolve this with Composer using the project's intended PHP version before final deployment.

Create `.env` locally from `.env.example`; the distributable package intentionally does not include `.env`.
