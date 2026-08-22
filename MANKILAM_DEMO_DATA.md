# ReportCo Mankilam Demo Data

This package seeds a development/reference geography for Barangay Mankilam, Tagum City. Mankilam is PSA PSGC code **1102319011** and is classified as urban. PSA reports a 2024 POPCEN population of 42,540.

## Important customer-data note

The public web sources I could verify do **not** expose one authoritative, complete barangay master list mapping every Purok to every Street. Therefore this demo seeds the Puroks and street relationships that could be corroborated publicly, while avoiding invented Purok→Street assignments. Before production use, replace/complete this reference data with the official master list supplied by Barangay Mankilam.

## Publicly corroborated Puroks currently seeded

- Purok Abaca
- Purok Caimito
- Purok Capitol
- Purok Cogon
- Purok Countryhomes
- Purok Dela Cruz
- Purok Durian
- Purok Garcia
- Purok Garciaville
- Purok Galingan
- Purok Gulayan
- Purok Ilocandia
- Purok Kalubiran
- Purok Lemonsito
- Purok Magsanoc
- Purok Magkidong
- Purok Magtalisay
- Purok Magtaya
- Purok Papaya
- Purok Union
- Purok Uraya

The public sources supporting these names include barangay directories, government procurement/administrative records, official institutional addresses, and OpenStreetMap-derived locality data.

## Street relationships currently seeded

Only relationships with sufficient public evidence are attached to a Purok:

- **Purok Galingan** — Aala Road; R. Aala Road
- **Purok Garcia** — Garcia Street
- **Purok Gulayan** — Gulayan Avenue
- **Purok Ilocandia** — Barangay Road; Capitol Circumferential Road; Durian Street; Ipil-Ipil Street; Mahogany Street; Yakal Street
- **Purok Lemonsito** — Virginia Street
- **Purok Magsanoc** — Capitol Avenue
- **Purok Union** — Tadena 3 St.

There are additional named streets publicly associated with Mankilam (for example A.F. Ferido Street, Aala Street, Ernesto Punzalan Street, Estrella Street, H.A. Misa Street, Maginhawa Street, Santa Cruz Avenue, and V.C. Boiser Avenue), but the sources reviewed did not provide a reliable Purok relationship for each one. They should not be attached to an arbitrary Purok.

## Demo accounts

System Admin:
- Email: system@reportco.local
- Password: change-me-before-production

Barangay Admin:
- Email: admin@mankilam.reportco.local
- Password: Admin@12345
- Role: barangay_admin
- Demo Purok: Purok Galingan

Resident:
- Email: resident@mankilam.reportco.local
- Password: Resident@12345
- Role: resident
- Demo Purok: Purok Garcia

Change these credentials before any non-demo deployment.

## Seeding

```bash
php artisan migrate:fresh --seed
```

For an existing development database:

```bash
php artisan db:seed
```

The seeder is idempotent for the demo records.

## Research/reference sources

- PSA PSGC — Mankilam, code 1102319011: https://psa.gov.ph/classification/psgc/brgydetail/1102319011
- PhilAtlas — Mankilam profile and adjacent barangays: https://www.philatlas.com/mindanao/r11/davao-del-norte/tagum/mankilam.html
- OpenAlfa/OSM — Mankilam street guide: https://philippines-streets.openalfa.com/mankilam
- OpenAlfa/OSM — Purok Ilocandia street guide: https://philippines-streets.openalfa.com/ilocandia
- Tagum City official procurement/notice records referencing Mankilam locations: https://tagumcity.gov.ph/?page_id=3286
- CCRO Tagum official news referencing Purok Garcia/Garciaville and Purok Uraya: https://www.ccrotagum.com/news-updates
- Philippine Department of Energy records referencing Mankilam Purok/street addresses: https://prod-cms.doe.gov.ph/documents/d/guest/list-of-lpg-lto-in-mindanao-area-as-of-march-2026-dealers-pdf
- DPWH Region XI records referencing Purok Papaya, Mankilam: https://www.dpwh.gov.ph/dpwh/sites/default/files/GAA/APP/RegionXI_IAPP%20FY2019_Civil%20Works.pdf
- Barangay Mankilam directory listing Purok Magsanoc, Magkidong, Magtaya and Kalubiran: https://phshirt.com/places-regions/asia/philippines/barangay/barangay-mankilam/barangay-mankilam-officials-tagum-city/
