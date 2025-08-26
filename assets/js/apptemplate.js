// /095/schedule-ng/assets/js/apptemplate.js
(function () {
  if (!window.sch) return;

  const esc = (s) =>
    String(s ?? '').replace(/[&<>"']/g, (m) => (
      { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[m]
    ));

  let filePopup = null;
  const closePopup = () => {
    if (filePopup) {
      filePopup.remove();
      filePopup = null;
      document.removeEventListener('pointerdown', outsideListener, true);
    }
  };
  const outsideListener = (ev) => {
    if (filePopup && !filePopup.contains(ev.target)) closePopup();
  };
  const showAttachments = (anchor, files) => {
    closePopup();

    const list = [];
    (files?.bol || []).forEach(u =>
      list.push(`<li>BOL/CSO: <a href="${u}" target="_blank">${u.split('/').pop()}</a></li>`)
    );
    (files?.extra || []).forEach(u =>
      list.push(`<li>Additional File: <a href="${u}" target="_blank">${u.split('/').pop()}</a></li>`)
    );
    if (!list.length) return;

    const popup = document.createElement('div');
    popup.className = 'file-popup';
    popup.innerHTML = `<ul>${list.join('')}</ul>`;
    document.body.appendChild(popup);

    const r = anchor.getBoundingClientRect();
    popup.style.left = `${r.left + window.scrollX}px`;
    popup.style.top  = `${r.bottom + window.scrollY + 4}px`;

    filePopup = popup;
    document.addEventListener('pointerdown', outsideListener, true);
  };

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
      const wrap = args.element.querySelector('.appt');
      const f = d.files;
      if (wrap && f && ((f.bol && f.bol.length) || (f.extra && f.extra.length))) {
        const icon = document.createElement('i');
        icon.className = 'file-clip fa-solid fa-paperclip';
        icon.title = 'View attachments';
        icon.addEventListener('click', (ev) => {
          ev.stopPropagation();
          showAttachments(icon, f);
        });
        wrap.appendChild(icon);
      }
    }
  };

  // Attach after scheduler is created
  window.sch.eventRendered = renderTile;
  if (window.sch.dataBind) window.sch.dataBind();
  if (window.sch.refreshEvents) window.sch.refreshEvents();
})();
