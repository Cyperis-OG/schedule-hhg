// Data loader normalizes resources & events and rebinds into the scheduler
const { API } = window.SCH_CFG;
const { pad2 } = window._h;

function normalizeEvents(arr=[]) {
  return arr.map(ev => ({
    ...ev,
    StartTime: (ev.StartTime instanceof Date)
      ? ev.StartTime
      : new Date(typeof ev.StartTime === 'string' ? ev.StartTime.replace(' ', 'T') : ev.StartTime),
    EndTime: (ev.EndTime instanceof Date)
      ? ev.EndTime
      : new Date(typeof ev.EndTime === 'string' ? ev.EndTime.replace(' ', 'T') : ev.EndTime),
    ContractorId: ev.ContractorId == null ? null : Number(ev.ContractorId)
  }));
}
function normalizeResources(arr=[]) {
  return arr.map(r => ({ ...r, id: Number(r.id) }));
}

async function loadDay(dateStr){
  try{
    const r = await fetch(`${API.fetchDay}?date=${encodeURIComponent(dateStr)}`);
    const j = await r.json();

    window.sch.resources[0].dataSource   = normalizeResources(j.resources || []);
    window.sch.eventSettings.dataSource  = normalizeEvents(j.events || []);
    window.sch.dataBind();

    console.log('[loadDay]', dateStr, {
      events: (j.events||[]).length,
      resources: (j.resources||[]).length,
      sampleEvent: (j.events||[])[0],
      sampleResource: (j.resources||[])[0]
    });
  }catch(e){
    console.error('Failed to load schedule data:', e);
    window.sch.eventSettings.dataSource = [];
    window.sch.dataBind();
  }
}

window.loadDay = loadDay;
