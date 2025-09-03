// Centralized config + shared state
const BASE_PATH = window.SCH_CFG?.BASE_PATH || '.';
export const API = {
  fetchDay:        `${BASE_PATH}/api/jobs_fetch.php`,
  popup:           `${BASE_PATH}/api/popup_render.php`,
  persistTimeslot: `${BASE_PATH}/api/job_update_timeslot.php`,
  saveJob:         `${BASE_PATH}/api/job_save.php`,
  deleteJob:       `${BASE_PATH}/api/job_delete.php`
};

export const MAX_DAYS = 5;

export const state = {
  sch: null,
  dialogs: {
    quick: null,
    info: null,
    infoOutsideAbort: null
  }
};

// Ensure required modules are injected
if (window.ej?.schedule?.Schedule?.Inject) {
  ej.schedule.Schedule.Inject(
    ej.schedule.TimelineViews,
    ej.schedule.Resize,
    ej.schedule.DragAndDrop
  );
}
