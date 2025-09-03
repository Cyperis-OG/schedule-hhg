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
- Fetches API endpoints under `{BASE_PATH}/api/` such as `contractors_list.php`, `customers_search.php`, and `job_save.php`.

## admin/contractors.php
- Inline styles and scripts only (no external files).
- Fetches API endpoints under `{BASE_PATH}/api/` such as `contractors_list.php` and `contractors_mutate.php`.

## map.php
- Stylesheets:
  - `https://unpkg.com/leaflet@1.9.4/dist/leaflet.css`
- Scripts:
  - `https://unpkg.com/leaflet@1.9.4/dist/leaflet.js`
- Fetches API endpoint: `{BASE_PATH}/api/jobs_by_date_geo.php`