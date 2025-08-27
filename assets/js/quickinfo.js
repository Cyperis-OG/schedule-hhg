// /095/schedule-ng/assets/js/quickInfo.js
(function () {
  const esc = (s) =>
    String(s ?? "").replace(/[&<>"']/g, (m) => (
      { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m]
    ));

  const CFG = window.SCH_CFG || {};
  const API = CFG.API || {};

  const pad2 = (n) => (n < 10 ? "0" : "") + n;
  const fmtTimeRange = (start, end) => {
    try {
      const s = new Date(start);
      const e = new Date(end);
      const opts = { hour: "numeric", minute: "2-digit", hour12: true };
      return `${s.toLocaleTimeString([], opts)} - ${e.toLocaleTimeString([], opts)}`;
    } catch { return ""; }
  };

  const contractorNameById = (id) => {
    const ds = (window.sch?.resources?.[0]?.dataSource) || [];
    const f = ds.find(x => Number(x.id) === Number(id));
    return f?.name || "—";
  };

  let infoDlg = null;

  function closeInfo() {
    try { infoDlg?.hide(); infoDlg?.destroy(); infoDlg?.element?.remove(); } catch(_) {}
    infoDlg = null;
  }

  function buildCard(ev) {
    const start = ev.StartTime instanceof Date ? ev.StartTime : new Date(ev.StartTime);
    const end   = ev.EndTime   instanceof Date ? ev.EndTime   : new Date(ev.EndTime);

    const title = ev.Customer || (ev.Subject ? String(ev.Subject).replace(/\s*\(.*?\)\s*$/,'') : 'Job');
    const jobNo = (ev.JobNumber && ev.JobNumber !== 'null') ? String(ev.JobNumber) : '';
    const loc   = ev.Location || '';
    const status= ev.Status || 'scheduled';
    const contractor = contractorNameById(ev.ContractorId);

    // vehicles & labor (show zeros)
    const tractors = Number(ev.tractors ?? ev.NumTractorTrailers ?? 0);
    const bobtails = Number(ev.bobtails ?? ev.NumBobtails ?? 0);

    const drivers  = Number(ev.drivers  ?? ev.NumDrivers ?? 0);
    const movers   = Number(ev.movers   ?? ev.NumMovers ?? 0);
    const installers = Number(ev.installers ?? ev.NumInstallers ?? 0);
    const pctechs    = Number(ev.pctechs    ?? ev.NumPCTechs ?? 0);
    const supervisors= Number(ev.supervisors?? ev.NumSupervisors ?? 0);
    const projectManagers = Number(ev.project_managers ?? ev.NumProjectManagers ?? 0);
    const crewTransport   = Number(ev.crew_transport   ?? ev.NumCrewTransport   ?? 0);
    const electricians    = Number(ev.electricians     ?? ev.NumElectricians    ?? 0);

    const host = document.createElement('div');
    host.className = 'qi-card';
    host.innerHTML = `
      <div class="qi-title">${esc(title)}</div>
      <div class="qi-sheet">
        <div class="qi-row"><div class="qi-label">Time:</div>
          <div class="qi-value">${esc(fmtTimeRange(start, end))}</div></div>

        <div class="qi-row"><div class="qi-label">Location:</div>
          <div class="qi-value">${esc(loc || '—')}</div></div>

        <div class="qi-row"><div class="qi-label">Contractor:</div>
          <div class="qi-value">${esc(contractor)}</div></div>

        <div class="qi-row"><div class="qi-label">Vehicles:</div>
          <div class="qi-value">${esc(`${tractors} - TTrailers\n${bobtails} - Bobtails`)}</div></div>

        <div class="qi-row"><div class="qi-label">Labor:</div>
          <div class="qi-value">${esc(
            `${drivers} - Drivers\n${movers} - Movers\n${installers} - Installers\n${pctechs} - PC Techs\n` +
            `${supervisors} - Supervisors\n${projectManagers} - Project Managers\n${crewTransport} - Crew Transport\n${electricians} - Electricians`
          )}</div></div>

        <div class="qi-row"><div class="qi-label">Status:</div>
          <div class="qi-value">${esc(status)}</div></div>

        ${jobNo ? `<div class="qi-row"><div class="qi-label">Job #:</div>
          <div class="qi-value">${esc(jobNo)}</div></div>` : ''}

        ${ev.day_notes ? `<div class="qi-row"><div class="qi-label">Notes:</div>
          <div class="qi-value">${esc(ev.day_notes)}</div></div>` : ''}
      </div>

      <div class="qi-footer">
        <button class="qi-btn"        data-act="copy">Copy</button>
        <button class="qi-btn"        data-act="edit">Edit/View Details</button>
        <button class="qi-btn danger" data-act="delete">Delete</button>
        <button class="qi-btn primary"data-act="close">Close</button>
      </div>
    `;

    // Wire buttons (close, edit, delete, copy)
    host.querySelector('[data-act="close"]')?.addEventListener('click', () => closeInfo());
    host.querySelector('[data-act="edit"]')?.addEventListener('click', () => {
      // Close first (this was causing your ReferenceError)
      closeInfo();
      // Then open the full editor if available
      const dayUid = ev.Id || ev.day_uid || ev.DayUID;
      if (window.editJob?.openByDayId && dayUid) {
        window.editJob.openByDayId(dayUid);
      } else if (window.editJob?.openByJobId && (ev.JobId || ev.job_id)) {
        window.editJob.openByJobId(ev.JobId || ev.job_id);
      } else {
        alert('Edit module is not loaded.');
      }
    });
    host.querySelector('[data-act="copy"]')?.addEventListener('click', () => handleCopy(ev));
    host.querySelector('[data-act="delete"]')?.addEventListener('click', () => handleDelete(ev));

    return host;
  }

  async function handleCopy(ev) {
    if (!API.saveJob) {
      alert('Copy API not configured.');
      return;
    }
    const start = ev.StartTime instanceof Date ? ev.StartTime : new Date(ev.StartTime);
    const end   = ev.EndTime   instanceof Date ? ev.EndTime   : new Date(ev.EndTime);
    const ymd    = `${start.getFullYear()}-${pad2(start.getMonth()+1)}-${pad2(start.getDate())}`;
    const start24= `${pad2(start.getHours())}:${pad2(start.getMinutes())}`;
    const end24  = `${pad2(end.getHours())}:${pad2(end.getMinutes())}`;

    const host = document.createElement('div');
    host.className = 'qi-copy-form';
    host.innerHTML = `
      <div class="qi-row"><label>Date: <input type="date" name="date" value="${ymd}"></label></div>
      <div class="qi-row"><label>Start: <input type="time" name="start" value="${start24}"></label></div>
      <div class="qi-row"><label>End: <input type="time" name="end" value="${end24}"></label></div>
    `;

    let copyDlg = new ej.popups.Dialog({
      cssClass: 'qi-dialog',
      header: 'Copy Job Day',
      content: host,
      isModal: true,
      showCloseIcon: true,
      target: document.body,
      width: '360px',
      buttons: [
        { buttonModel:{ content:'Cancel' }, click: () => copyDlg.hide() },
        { buttonModel:{ content:'Save', isPrimary:true }, click: () => saveCopy() }
      ],
      close: () => { try { copyDlg.destroy(); copyDlg.element.remove(); } catch (_) {} }
    });

    async function saveCopy() {
      const date  = host.querySelector('[name="date"]')?.value;
      const startS= host.querySelector('[name="start"]')?.value;
      const endS  = host.querySelector('[name="end"]')?.value;
      if (!date || !startS || !endS) {
        alert('Date and time are required.');
        return;
      }
      const job = {
        title: ev.Customer || ev.Subject || 'Job',
        customer_name: ev.Customer || ev.Subject || 'Job',
        job_number: ev.JobNumber || null,
        salesman: ev.salesman || ev.Salesman || null,
        status: ev.Status || 'scheduled'
      };
      const day = {
        work_date: date,
        start_time: `${startS}:00`,
        end_time: `${endS}:00`,
        contractor_id: ev.ContractorId ?? null,
        location: ev.Location || null,
        tractors: Number(ev.tractors ?? 0),
        bobtails: Number(ev.bobtails ?? 0),
        drivers: Number(ev.drivers ?? 0),
        movers: Number(ev.movers ?? 0),
        installers: Number(ev.installers ?? 0),
        pctechs: Number(ev.pctechs ?? 0),
        supervisors: Number(ev.supervisors ?? 0),
        project_managers: Number(ev.project_managers ?? 0),
        crew_transport: Number(ev.crew_transport ?? 0),
        electricians: Number(ev.electricians ?? 0),
        day_notes: ev.day_notes || null,
        status: job.status,
        meta: {}
      };
      try {
        const res = await fetch(API.saveJob, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ job, days: [day] })
        });
        const text = await res.text();
        let j; try { j = JSON.parse(text); } catch { j = null; }
        if (!res.ok || !j || j.ok === false) {
          const msg = (j && j.error) ? j.error : `API ${res.status}`;
          throw new Error(msg);
        }
        if (Array.isArray(j.events)) {
          j.events.forEach(e => window.sch?.addEvent(e));
        } else if (window.loadDay) {
          await window.loadDay(date);
        }
        copyDlg.hide();
        closeInfo();
      } catch (e) {
        console.error(e);
        alert(e.message || 'Failed to copy job.');
      }
    }

    const mount = document.createElement('div');
    document.body.appendChild(mount);
    copyDlg.appendTo(mount);
    copyDlg.show();
  }

  async function handleDelete(ev) {
    const dayUid = ev.Id || ev.day_uid || ev.DayUID;
    if (!dayUid || !API.deleteJob || !API.editRead) {
      alert('Delete API not configured.');
      return;
    }
    try {
      const r = await fetch(`${API.editRead}?from_day_uid=${encodeURIComponent(dayUid)}`);
      const j = await r.json();
      if (!j.ok) {
        alert(j.error || 'Failed to fetch job details.');
        return;
      }
      const days = j.days || [];
      const jobUid = j.job?.JobUID || j.job?.uid || j.job_uid;
      if (!jobUid) {
        alert('Missing job identifier.');
        return;
      }
      let deleteEntire = false;
      if (days.length <= 1) {
        if (!confirm('Delete this job?')) return;
        deleteEntire = true;
      } else {
        const others = days
          .filter(d => (d.Id || d.uid) !== dayUid)
          .map(d => d.work_date)
          .join('\n');
        deleteEntire = confirm(`This job has other days:\n${others}\n\nOK to delete entire job?`);
        if (!deleteEntire && !confirm('Delete only this day?')) return;
      }
      const payload = deleteEntire ? { job_uid: jobUid } : { job_day_uid: dayUid };
      await fetch(API.deleteJob, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      if (deleteEntire) {
        days.forEach(d => { const id = d.Id || d.uid; if (id) window.sch?.deleteEvent(id); });
      } else {
        window.sch?.deleteEvent(dayUid);
      }
      closeInfo();
    } catch (e) {
      console.error(e);
      alert('Failed to delete job.');
    }
  }

  function onEventClick(args) {
    const ev = args?.event || args?.data || {};
    closeInfo(); // ensure only one at a time

    infoDlg = new ej.popups.Dialog({
      cssClass: 'qi-dialog',
      header: '',
      content: buildCard(ev),
      isModal: true,
      showCloseIcon: true,
      target: document.body,
      width: '640px',
      position: { X: 'center', Y: 'center' },
      overlayClick: () => closeInfo(),
      close: () => closeInfo()
    });

    const mount = document.createElement('div');
    document.body.appendChild(mount);
    infoDlg.appendTo(mount);
    infoDlg.show();
  }

  // Attach handler to the existing scheduler instance
  if (window.sch) {
    window.sch.eventClick = onEventClick;
    if (window.sch.dataBind) window.sch.dataBind();
  }

  // Optional export for debugging
  window.quickInfo = { close: closeInfo };
})();
