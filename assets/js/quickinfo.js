// /schedule-ng/assets/js/quickInfo.js
(function () {
  const esc = (s) =>
    String(s ?? "").replace(/[&<>"']/g, (m) => (
      { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m]
    ));

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

    // Wire buttons (close + edit)
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

    return host;
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
