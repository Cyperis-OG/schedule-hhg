// Centralized config + shared state
export const API = {
  fetchDay:        '/095/schedule-ng/api/jobs_fetch.php',
  popup:           '/095/schedule-ng/api/popup_render.php',
  persistTimeslot: '/095/schedule-ng/api/job_update_timeslot.php',
  saveJob:         '/095/schedule-ng/api/job_save.php',
  deleteJob:       '/095/schedule-ng/api/job_delete.php'
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
