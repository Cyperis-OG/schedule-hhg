# Entry Points

## index.php
- Includes: `includes/syncfusion_cdn.php`
- Stylesheets:
  - `./assets/app.css`
- Scripts (in load order):
  - `./assets/js/core.js`
  - `./assets/js/apptemplate.js`
  - `./assets/js/dnd.js`
  - `./assets/js/persist-moves.js`
  - `./assets/js/quickinfo.js`
  - `./assets/js/quickadd.js`
  - `./assets/js/editjob.js`

## admin/add_job.php
- Inline styles and scripts only (no external files).
- Fetches API endpoints: `/095/schedule-ng/api/contractors_list.php`, `/095/schedule-ng/api/customers_search.php`, `/095/schedule-ng/api/job_save.php`.

## admin/contractors.php
- Inline styles and scripts only (no external files).
- Fetches API endpoints: `/095/schedule-ng/api/contractors_list.php`, `/095/schedule-ng/api/contractors_mutate.php`.

## map.php
- Stylesheets:
  - `https://unpkg.com/leaflet@1.9.4/dist/leaflet.css`
- Scripts:
  - `https://unpkg.com/leaflet@1.9.4/dist/leaflet.js`
- Fetches API endpoint: `/095/schedule-ng/api/jobs_by_date_geo.php`