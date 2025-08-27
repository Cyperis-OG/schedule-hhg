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

    const resources = normalizeResources(j.resources || []);
    const rawEvents = normalizeEvents(j.events || []);
    const resIds = new Set(resources.map(r => Number(r.id)));
    const events = rawEvents.filter(e => e.ContractorId == null || resIds.has(Number(e.ContractorId)));
    const orphans = rawEvents.filter(e => e.ContractorId != null && !resIds.has(Number(e.ContractorId)));

    window.sch.resources[0].dataSource   = resources;
    window.sch.eventSettings.dataSource  = events;
    window.sch.dataBind();

    if (orphans.length) {
      console.warn('[loadDay] events with unknown ContractorId:', orphans.map(o => ({Id:o.Id, ContractorId:o.ContractorId})));
    }

    console.log('[loadDay]', dateStr, {
      events: events.length,
      resources: resources.length,
      sampleEvent: events[0],
      sampleResource: resources[0]
    });
  }catch(e){
    console.error('Failed to load schedule data:', e);
    window.sch.eventSettings.dataSource = [];
    window.sch.dataBind();
  }
}

window.loadDay = loadDay;
