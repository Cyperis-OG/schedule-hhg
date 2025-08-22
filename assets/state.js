// Centralized config + shared state
export const API = {
  fetchDay:        '/schedule-ng/api/jobs_fetch.php',
  popup:           '/schedule-ng/api/popup_render.php',
  persistTimeslot: '/schedule-ng/api/job_update_timeslot.php',
  saveJob:         '/schedule-ng/api/job_save.php'
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
