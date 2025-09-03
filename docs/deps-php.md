# PHP Dependency Map

| PHP File | Referenced By |
|----------|---------------|
| index.php | entry point; includes `config.php` and `includes/syncfusion_cdn.php` |
| admin/add_job.php | entry point; includes `config.php`; JS fetches `api/contractors_list.php`, `api/customers_search.php`, `api/job_save.php` |
| admin/contractors.php | entry point; includes `config.php`; JS fetches `api/contractors_list.php`, `api/contractors_mutate.php` |
| map.php | entry point; includes `config.php`; JS fetches `api/jobs_by_date_geo.php` |
| api/accept_invite.php | referenced by `rg` only in itself; no known callers |
| api/contractors_list.php | fetched by `admin/add_job.php` and `admin/contractors.php` |
| api/contractors_mutate.php | fetched by `admin/contractors.php` |
| api/create_invite.php | no references found |
| api/customers_search.php | fetched by `admin/add_job.php` |
| api/job_full_get.php | referenced in `index.php` config and `assets/js/editjob.js` |
| api/job_full_save.php | referenced in `index.php` config and `assets/js/editjob.js` |
| api/job_save.php | referenced in `index.php`, `admin/add_job.php`, and `assets/js/quickadd.js` |
| api/job_update_timeslot.php | referenced in `index.php` and `assets/js/persist-moves.js` |
| api/jobs_by_date_geo.php | fetched by `map.php` |
| api/jobs_fetch.php | referenced in `index.php` and `assets/js/core.js` |
| api/popup_render.php | referenced in `index.php` and `assets/js/core.js` |
| lib/ids.php | included by `api/contractors_mutate.php`, `api/create_invite.php`, `api/accept_invite.php` |
| scripts/send_tomorrow_schedule.php | includes `config.php`; no references found |