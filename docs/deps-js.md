# JS Module Dependencies (`assets/js`)

| Module | Depends on | Notes |
|--------|------------|-------|
| core.js | external Syncfusion `ej` globals | Sets up `window.sch`, provides `loadDay` and `getViewYMD`. Reachable from `index.php`. |
| apptemplate.js | `window.sch` | Renders appointments; requires `core.js` to run. Reachable from `index.php`. |
| dnd.js | `window.sch`, DOM `#dragToggle` | Drag/drop toggle; requires `core.js`. Reachable from `index.php`. |
| persist-moves.js | `window.sch`, `#dragToggle` | Persists moves via fetch to API; requires `core.js` and `dnd.js`. Reachable from `index.php`. |
| quickinfo.js | `window.sch`, optional `editJob` | Custom event popover; requires `core.js`. Reachable from `index.php`. |
| quickadd.js | `window.sch`, `loadDay` | Quick add dialog; requires `core.js`. Reachable from `index.php`. |
| editjob.js | `window.sch` | Full job editor dialog; requires `core.js`. Reachable from `index.php`. |
| data.js | `window.SCH_CFG`, `window._h`, `window.sch` | Legacy loader; **not referenced** by `index.php` or other modules. |