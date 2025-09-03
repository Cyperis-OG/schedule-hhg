import { state } from './state.js';
import { pad2 } from './utils.js';

export function getViewYMD() {
  const dates = state.sch?.getCurrentViewDates ? state.sch.getCurrentViewDates() : null;
  const first = (dates && dates.length) ? dates[0] : state.sch.selectedDate;
  return `${first.getFullYear()}-${pad2(first.getMonth()+1)}-${pad2(first.getDate())}`;
}

export async function loadDay(dateStr) {
  try {
    const BASE_PATH = window.SCH_CFG?.BASE_PATH || '.';
    const r = await fetch(`${BASE_PATH}/api/jobs_fetch.php?date=${encodeURIComponent(dateStr)}`);
    const j = await r.json();

    const rawEvents = (j.events || []).map(ev => ({
      ...ev,
      StartTime: (ev.StartTime instanceof Date)
        ? ev.StartTime
        : new Date(typeof ev.StartTime === 'string' ? ev.StartTime.replace(' ', 'T') : ev.StartTime),
      EndTime: (ev.EndTime instanceof Date)
        ? ev.EndTime
        : new Date(typeof ev.EndTime === 'string' ? ev.EndTime.replace(' ', 'T') : ev.EndTime),
      ContractorId: ev.ContractorId == null ? null : Number(ev.ContractorId)
    }));
    const resources = (j.resources || []).map(r => ({ ...r, id: Number(r.id) }));
    const resIds = new Set(resources.map(r => Number(r.id)));
    const events = rawEvents.filter(e => e.ContractorId == null || resIds.has(Number(e.ContractorId)));
    const orphans = rawEvents.filter(e => e.ContractorId != null && !resIds.has(Number(e.ContractorId)));

    // Bind
    state.sch.resources[0].dataSource = resources;
    state.sch.eventSettings.dataSource = events;

    state.sch.dataBind();
    if (orphans.length) {
      console.warn('[loadDay] events with unknown ContractorId:', orphans.map(o => ({Id:o.Id, ContractorId:o.ContractorId})));
    }
  } catch (e) {
    console.error('Failed to load schedule data:', e);
    state.sch.eventSettings.dataSource = []; state.sch.dataBind();
  }
}