// /095/schedule-ng/assets/js/apptemplate.js
(function () {
  if (!window.sch) return;

  const esc = (s) =>
    String(s ?? '').replace(/[&<>"']/g, (m) => (
      { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[m]
    ));

  const renderTile = (args) => {
    const d = args?.data || {};
    const jobNo = d.JobNumber && d.JobNumber !== 'null' ? String(d.JobNumber) : '';
    const loc   = d.Location ? String(d.Location) : '';
    const cust  = d.Customer || (d.Subject ? String(d.Subject).replace(/\s*\(.*?\)\s*$/,'') : '');
    const top   = (jobNo && loc) ? `${jobNo} - ${loc}` : (jobNo || loc || '');

    if (args.element) {
      args.element.innerHTML = `
        <div class="appt">
          <div class="appt-top">${esc(top)}</div>
          <div class="appt-bot">${esc(cust)}</div>
        </div>
      `;
    }
  };

  // Attach after scheduler is created
  window.sch.eventRendered = renderTile;
  if (window.sch.dataBind) window.sch.dataBind();
  if (window.sch.refreshEvents) window.sch.refreshEvents();
})();
