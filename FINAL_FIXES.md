# ReportCo Final Backend Fixes

This package is based on the latest ReportCo backend and preserves the accumulated backend development, including the Claude fixes, map/image work, notifications, analytics, Mankilam location support, emergency workflow, and connection/CORS configuration.

Final verification fixes in this package:
- Emergency urgency is persisted as `emergency` and sets `emergency_override`, `priority`, reason, and timestamp.
- Reports may omit `street_id` when the selected Purok has no registered streets.
- Added feature coverage for no-street reports and resident-selected emergency reports.
- Updated existing location-flow test payloads to include the required resident urgency.
- `.env`, vendor packages, generated logs/cache, and local Git metadata are excluded from the clean deliverable.
