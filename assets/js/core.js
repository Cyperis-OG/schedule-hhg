// /095/schedule-ng/assets/js/core.js

// --- Inject required modules (Timeline, DnD, Resize) ---
if (window.ej?.schedule?.Schedule?.Inject) {
  ej.schedule.Schedule.Inject(
    ej.schedule.TimelineViews,
    ej.schedule.DragAndDrop,
    ej.schedule.Resize
  );
}

// --- Config / helpers (read from global config with fallbacks) ---
const CFG = window.SCH_CFG || {};
const API = (CFG.API) ? CFG.API : {
  fetchDay:        './api/jobs_fetch.php',
  popup:           './api/popup_render.php',
  persistTimeslot: './api/job_update_timeslot.php',
  saveJob:         './api/job_save.php'
};
const DEFAULT_TZ = CFG.DEFAULT_TZ || 'America/Chicago';
const pad2  = (n)=>(n<10?'0':'')+n;
const toYMD = (d)=>`${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;


function parseDateMaybeSpace(v){
  if (v instanceof Date) return v;
  if (typeof v === 'string') return new Date(v.replace(' ', 'T'));
  return new Date(v);
}
function ymdToLocalDate(s){
  const [y,m,d] = String(s).split('-').map(Number);
  return new Date(y, m-1, d); // local midnight
}

// Read the date the view is actually showing (avoids UTC wobble)
function getViewYMD() {
  try {
    const dates = sch?.getCurrentViewDates ? sch.getCurrentViewDates() : null;
    const first = (dates && dates.length) ? dates[0] : sch.selectedDate;
    return `${first.getFullYear()}-${pad2(first.getMonth()+1)}-${pad2(first.getDate())}`;
  } catch {
    const d = new Date();
    return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
  }
}

// --- Build the Scheduler ---
window.sch = new ej.schedule.Schedule({
  height:'100%', width:'100%',
  views:[{ option:'TimelineDay', isSelected:true }],
  currentView:'TimelineDay',
  timeZone: DEFAULT_TZ,
  startHour:'00:00', endHour:'24:00',
  resourceHeaderWidth:170,
  selectedDate:new Date(),
  showQuickInfo:false,
  rowAutoHeight:true,

  // group by Contractors (vertical)
  group:{ resources:['Contractors'], orientation:'Vertical', allowGroupEdit:false },

  // resource config
  resources:[{
    field:'ContractorId', title:'Contractor', name:'Contractors',
    dataSource:[], textField:'name', idField:'id', colorField:'color_hex'
  }],

  // IMPORTANT: map event fields, especially resourceId -> ContractorId
  eventSettings:{
    dataSource:[],
    fields:{
      id:        'Id',
      subject:   { name:'Subject' },
      startTime: { name:'StartTime' },
      endTime:   { name:'EndTime' },
      resourceId:'ContractorId'
    }
  },

  // initial load AFTER the widget computes its dates/timezone
  created: () => {
    loadDay(getViewYMD());
  },

  cellDoubleClick: (args) => {
    if (!args?.startTime || !args?.endTime) return;
    if (!args.element?.classList?.contains('e-work-cells')) return; // only blank cells
    window.quickAdd?.open(args);
    args.cancel = true;
  },

  // keep data in sync when navigating prev/next/today
  actionComplete: (args) => {
    if (args.requestType === 'dateNavigate') {
      loadDay(getViewYMD());
    }
  }
});

sch.appendTo('#Schedule');

// --- Data loader (safe-order bind + explicit refresh) ---
async function loadDay(dateStr) {
  try {
    const r = await fetch(`${API.fetchDay}?date=${encodeURIComponent(dateStr)}`);
    const j = await r.json();

    const resources = (j.resources || []).map(x => ({ ...x, id: Number(x.id) }));
    const rawEvents = (j.events || []).map(ev => ({
      ...ev,
      StartTime: parseDateMaybeSpace(ev.StartTime),
      EndTime:   parseDateMaybeSpace(ev.EndTime),
      ContractorId: ev.ContractorId == null ? null : Number(ev.ContractorId)
    }));
    const resIds = new Set(resources.map(r => Number(r.id)));
    const events = rawEvents.filter(e => e.ContractorId == null || resIds.has(Number(e.ContractorId)));
    const orphans = rawEvents.filter(e => e.ContractorId != null && !resIds.has(Number(e.ContractorId)));

    // --- safe bind order ---
    // 0) clear events
    sch.eventSettings.dataSource = [];
    sch.dataBind();

    // 1) resources
    if (Array.isArray(sch.resources) && sch.resources[0]) {
      sch.resources[0].dataSource = resources;
    }

    // 2) anchor the view on the requested day
    sch.selectedDate = ymdToLocalDate(dateStr);

    // 3) events
    sch.eventSettings.dataSource = events;

    // 4) apply + force event repaint
    sch.dataBind();
    if (sch.refreshEvents) sch.refreshEvents();

    // helpful debug: log any events whose ContractorId isn't in resources
    if (orphans.length) {
      console.warn('[loadDay] events with unknown ContractorId:', orphans.map(o => ({Id:o.Id, ContractorId:o.ContractorId})));
    }

    console.log('[loadDay]', dateStr, {
      events: events.length,
      resources: resources.length,
      sampleEvent: events[0],
      sampleResource: resources[0]
    });
  } catch (e) {
    console.error('Failed to load schedule data:', e);
    try {
      sch.eventSettings.dataSource = [];
      sch.dataBind();
    } catch(_) {}
  }
}

// Expose loader if other modules need it
window.loadDay = loadDay;
window.getViewYMD = getViewYMD;
