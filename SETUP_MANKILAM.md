# ReportCo local setup

1. Make sure PostgreSQL is running and create a database named `reportco_1`
   (or change `DB_DATABASE` in `.env`).
2. Update `DB_USERNAME` and `DB_PASSWORD` in `.env` if your PostgreSQL credentials differ.
3. Run:

```bash
composer install
php artisan migrate:fresh --seed
php artisan serve
```

4. Login with the demo accounts documented in `MANKILAM_DEMO_DATA.md`.

The `.env` file is included in this development ZIP for convenience. Do not commit
`.env` or reuse these demo passwords in production.


## PostgreSQL / Map configuration

This development package is configured for the PostgreSQL instance used during setup:
- Host: 127.0.0.1
- Port: 1024
- Database: reportco_1
- Username: postgres
- Password: set this in `.env`

The frontend uses Leaflet with OpenStreetMap tiles. OpenStreetMap attribution is displayed in the map UI. The backend's reverse-geocoding endpoint is user-triggered and uses Nominatim with an identifying User-Agent; do not use it for autocomplete or bulk geocoding.
