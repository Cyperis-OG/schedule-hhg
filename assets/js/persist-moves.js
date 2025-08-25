// Persist new time/contractor after drag or resize.
// Load AFTER core.js (creates window.sch) and dnd.js (the toggle).

(() => {
  if (!window.sch) {
    console.warn('persist-moves.js: window.sch not ready. Load after core.js.');
    return;
  }

  const API = (window.SCH_CFG && window.SCH_CFG.API) || {
    persistTimeslot: '/095/schedule-ng/api/job_update_timeslot.php'
  };

  // use shared helpers if available, else fallbacks
  const pad2  = (window._h && window._h.pad2)  || ((n)=> (n<10?'0':'')+n);
  const toYMD = (window._h && window._h.toYMD) || ((d)=> {
    return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
  });

  const fmtLocal = (d) =>
    `${toYMD(d)} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;

  const dndOn = () => {
    const cb = document.getElementById('dragToggle');
    return !cb || !!cb.checked; // if missing, treat as ON
  };

  async function persist(ev) {
    if (!ev || !ev.Id) return;

    const payload = {
      job_day_uid: ev.Id,
      start: fmtLocal(new Date(ev.StartTime)),
      end:   fmtLocal(new Date(ev.EndTime)),
      contractor_id: ev.ContractorId ?? null
    };

    try {
      const res = await fetch(API.persistTimeslot, {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify(payload)
      });
      const j = await res.json().catch(() => ({}));
      if (!res.ok || j?.ok === false) {
        console.error('[persist] server rejected update', { status: res.status, j, payload });
      } else {
        console.log('[persist] saved', payload);
      }
    } catch (e) {
      console.error('[persist] network/error', e);
    }
  }

  // Save when a drag or resize finishes (only when toggle is ON)
  window.sch.dragStop = (args) => {
    if (!dndOn()) { console.log('[persist] DnD OFF — not saving'); return; }
    persist(args?.data);
  };

  window.sch.resizeStop = (args) => {
    if (!dndOn()) { console.log('[persist] DnD OFF — not saving'); return; }
    persist(args?.data);
  };
})();
