// assets/js/editjob.js
(function () {
  const CFG = window.SCH_CFG || {};
  const BASE = CFG.BASE_PATH || '.';
  const API = (window.SCH_CFG && window.SCH_CFG.API) || {};

  // Allow either the new names (editRead/editSave) or your existing jobFull/jobUpdate keys.
  const ENDPOINTS = {
    editRead: API.editRead  || API.jobFull   || `${BASE}/api/job_full_get.php`,
    editSave: API.editSave  || API.jobUpdate || `${BASE}/api/job_full_save.php`,
    salesSearch: API.salesmenSearch || `${BASE}/api/salesmen_search.php`,
    customersSearch: API.customersSearch || `${BASE}/api/customers_search.php`
  };

  console.log('[editJob] module initialized');

  const MAX_DAYS = Number(CFG.MAX_DAYS || 5);

  const DEFAULT_FIELDS = [
    { key: 'tractors',         label: 'TTrailers',        input: 'number' },
    { key: 'bobtails',         label: 'Bobtails',         input: 'number' },
    { key: 'movers',           label: 'Movers',           input: 'number' },
    { key: 'drivers',          label: 'Drivers',          input: 'number' },
    { key: 'installers',       label: 'Installers',       input: 'number' },
    { key: 'pctechs',          label: 'PC Techs',         input: 'number' },
    { key: 'supervisors',      label: 'Supervisors',      input: 'number' },
    { key: 'project_managers', label: 'Project Managers', input: 'number' },
    { key: 'crew_transport',   label: 'Crew Transport',   input: 'number' },
    { key: 'electricians',     label: 'Electricians',     input: 'number' },
    { key: 'equipment',        label: 'Equipment',        input: 'text' },
    { key: 'weight',           label: 'Weight',           input: 'number', step: '0.01', default: '' }
  ];

  function normalizeDayField(f){
    const input = (f.input || f.type || '').toLowerCase() === 'text' ? 'text' : 'number';
    const step = f.step ?? (input === 'number' ? (f.decimals ? '0.01' : '1') : undefined);
    const defaultValue = f.default ?? (input === 'text' ? '' : 0);
    return { ...f, input, step, defaultValue };
  }

  const DAY_FIELDS = (CFG.DAY_FIELDS || DEFAULT_FIELDS)
    .filter(f => f.enabled !== false)
    .map(normalizeDayField);
  const LEGACY_MAP = {
    tractors: 'NumTractorTrailers',
    bobtails: 'NumBobtails',
    movers: 'NumMovers',
    drivers: 'NumDrivers',
    installers: 'NumInstallers',
    pctechs: 'NumPCTechs',
    supervisors: 'NumSupervisors',
    project_managers: 'NumProjectManagers',
    crew_transport: 'NumCrewTransport',
    electricians: 'NumElectricians'
  };

  // ---------- helpers ----------
  const pad2 = (n) => (n < 10 ? "0" : "") + n;
  const esc = (s) =>
    String(s ?? "").replace(/[&<>"']/g, (m) => (
      { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m]
    ));
  const toYMD = (d) => `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;

  const tripleTo24h = (hStr, mStr, ap) => {
    let h = Math.max(1, Math.min(12, parseInt(hStr || "0", 10) || 12));
    let m = Math.max(0, Math.min(59, parseInt(mStr || "0", 10) || 0));
    ap = (ap || "AM").toUpperCase();
    if (ap === "PM" && h !== 12) h += 12;
    if (ap === "AM" && h === 12) h = 0;
    return `${pad2(h)}:${pad2(m)}`;
  };
  const h24ToTriple = (hhmm) => {
    const [H, M] = (hhmm || "00:00").split(":").map(Number);
    const ap = H >= 12 ? "PM" : "AM";
    let h = H % 12; if (h === 0) h = 12;
    return { h: String(h), m: pad2(M || 0), ap };
  };
  const addHoursClamp = (hhmm, add) => {
    const [h, m] = hhmm.split(":").map(Number);
    let eh = h + add, em = m;
    if (eh > 23 || (eh === 23 && em > 59)) { eh = 23; em = 59; }
    return `${pad2(eh)}:${pad2(em)}`;
  };

  const contractorOptionsHtml = () => {
    const ds = (window.sch?.resources?.[0]?.dataSource) || [];
    return ds.map(c => `<option value="${String(c.id)}">${esc(c.name)}</option>`).join("");
  };

  // Robust JSON fetch (handles non-JSON server replies gracefully)
  async function fetchJSON(url, opts) {
    const r = await fetch(url, { headers: { Accept: "application/json" }, ...opts });
    const text = await r.text();
    let data;
    try { data = JSON.parse(text); }
    catch {
      console.error("[editJob] Non-JSON response from", url, "\n\n", text);
      throw new Error(`API ${r.status} @ ${url}`);
    }
    if (!r.ok || data?.ok === false) {
      const msg = data?.error || `API ${r.status} @ ${url}`;
      throw new Error(msg);
    }
    return data;
  }

  // ---------- dialog lifecycle ----------
  let editDlg = null;
  const closeEdit = () => {
    try { editDlg?.hide(); editDlg?.destroy(); editDlg?.element?.remove(); } catch(_) {}
    editDlg = null;
  };

  // Normalize server "day" record to editor card inputs
  function normalizeDay(d) {
    // accept StartTime/EndTime or work_date + start_time/end_time
    let ymd, s24, e24;
    if (d.StartTime) {
      const [dY, dT] = String(d.StartTime).replace("T"," ").split(" ");
      const [eY, eT] = String(d.EndTime || d.StartTime).replace("T"," ").split(" ");
      ymd = dY; s24 = (dT || "00:00:00").slice(0,5); e24 = (eT || "00:00:00").slice(0,5);
    } else {
      ymd = d.work_date ?? d.WorkDate;
      const [sh, sm] = String(d.start_time || "").split(":").map(Number);
      const [eh, em] = String(d.end_time   || "").split(":").map(Number);
      s24 = `${pad2(sh||0)}:${pad2(sm||0)}`;
      e24 = `${pad2(eh||0)}:${pad2(em||0)}`;
    }
    const counts={};
    DAY_FIELDS.forEach(f=>{
      const legacy=LEGACY_MAP[f.key];
      const raw = d[f.key] ?? (legacy ? d[legacy] : undefined);
      if (f.input === 'text') {
        counts[f.key] = raw != null ? String(raw) : '';
      } else {
        const val = raw ?? f.defaultValue;
        if (val === '' || val === null || typeof val === 'undefined') {
          counts[f.key] = '';
        } else {
          const num = Number(val);
          counts[f.key] = Number.isFinite(num) ? num : '';
        }
      }
    });
    return {
      uid: d.Id || d.day_uid || null,
      date: ymd,
      start24: s24 || "08:00",
      end24:   e24 || addHoursClamp(s24 || "08:00", 4),
      location: d.Location ?? d.location ?? "",
      ...counts,
      notes: d.day_notes ?? d.DayNotes ?? d.notes ?? "",
      status: d.Status ?? d.status ?? "scheduled",
      contractor_id: (d.contractor_id != null ? Number(d.contractor_id)
                      : (d.ContractorId != null ? Number(d.ContractorId) : null)),
      bol: Array.isArray(d.files?.bol) ? d.files.bol : [],
      extra: Array.isArray(d.files?.extra) ? d.files.extra : []
    };
  }

  function createEditor(hostTitle, job, daysInit, topContractorId) {
    const host = document.createElement("div");
    host.className = "qa"; // reuse Quick-Add styling
    host.innerHTML = `
      <form id="qeForm" autocomplete="off">
        <div class="qa-grid">
          <div class="qa-row">
            <label>Customer <span style="color:#ef4444">*</span></label>
            <div class="ac">
              <input name="job.customer_name" type="text"
                     value="${esc(job.customer_name || job.Customer || job.title || job.Subject || "")}"
                     required autocomplete="off" />
              <div class="ac-list"></div>
            </div>
          </div>
          <div class="qa-row">
            <label>Contractor (applies to all days)</label>
            <select name="job.contractor_id">
              <option value="">— Unassigned —</option>
              ${contractorOptionsHtml()}
            </select>
          </div>

          <div class="qa-row">
            <label>Requester Name</label>
            <div class="ac">
              <input name="job.salesman" type="text" value="${esc(job.salesman || "")}" placeholder="Optional" autocomplete="off" />
              <div class="ac-list"></div>
            </div>
          </div>
          <div class="qa-row">
            <label>Service Type</label>
            <input name="job.service_type" type="text" value="${esc(job.service_type || "")}" placeholder="Optional" />
          </div>
          <div class="qa-row">
            <label>Job Number</label>
            <input name="job.job_number" type="text" value="${esc(job.job_number || job.JobNumber || "")}" placeholder="Optional (e.g., DA-14855-5)" />
          </div>

          <div class="qa-row status-row">
            <label>Status</label>
            <select name="job.status">
              ${[
                ['placeholder','Placeholder'],
                ['needs_paperwork','Scheduled - Needs Paperwork'],
                ['scheduled','Scheduled'],
                ['dispatched','Dispatched'],
                ['canceled','Canceled'],
                ['completed','Completed'],
                ['paid','Paid']
              ].map(([val,label]) => `<option value="${val}" ${String(job.status || job.Status || "scheduled")===val?"selected":""}>${label}</option>`).join("")}
            </select>
          </div>

          <div class="days-wrap" id="daysWrap"></div>
        </div>
      </form>
    `;

    const salesInput = host.querySelector('input[name="job.salesman"]');
    const salesList = salesInput?.parentElement.querySelector('.ac-list');
    if (salesInput && salesList) {
      let tId=null;
      salesInput.addEventListener('input',()=>{
        const q=salesInput.value.trim();
        if(tId) clearTimeout(tId);
        if(q.length<2){ salesList.style.display='none'; return; }
        tId=setTimeout(async()=>{
          const r=await fetch(`${ENDPOINTS.salesSearch}?q=${encodeURIComponent(q)}`);
          const j=await r.json(); salesList.innerHTML='';
          (j.results||[]).forEach(item=>{
            const it=document.createElement('div'); it.className='ac-item';
            it.innerHTML=`<div><strong>${esc(item.name)}</strong> ${esc(item.phone||'')}</div>`;
            it.addEventListener('click',()=>{ salesInput.value=`${item.name} ${item.phone||''}`.trim(); salesList.style.display='none'; });
            salesList.appendChild(it);
          });
          salesList.style.display = salesList.children.length ? 'block':'none';
        },220);
      });
    }

    const custInput = host.querySelector('input[name="job.customer_name"]');
    const custList = custInput?.parentElement.querySelector('.ac-list');
    if (custInput && custList) {
      let tId=null;
      custInput.addEventListener('input',()=>{
        const q=custInput.value.trim();
        if(tId) clearTimeout(tId);
        if(q.length<2){ custList.style.display='none'; return; }
        tId=setTimeout(async()=>{
          const r=await fetch(`${ENDPOINTS.customersSearch}?q=${encodeURIComponent(q)}`);
          const j=await r.json(); custList.innerHTML='';
          (j.results||[]).forEach(item=>{
            const it=document.createElement('div'); it.className='ac-item';
            it.innerHTML=`<div><strong>${esc(item.name)}</strong></div><div class="help">Prefers: ${esc(item.preferred_contractor_name||'—')} · Requester: ${esc(item.default_salesman||'—')}</div>`;
            it.addEventListener('click',()=>{
              custInput.value=item.name; custList.style.display='none';
              const loc=host.querySelector('input[name="day.0.location"]');
              if(loc && item.default_location) loc.value=item.default_location;
              if(salesInput && item.default_salesman) salesInput.value=item.default_salesman;
              const jobn=host.querySelector('input[name="job.job_number"]');
              if(jobn && item.last_job_number) jobn.value=item.last_job_number;
              const sel=host.querySelector('select[name="job.contractor_id"]');
              if(sel && item.preferred_contractor_id) sel.value=String(item.preferred_contractor_id);
            });
            custList.appendChild(it);
          });
          custList.style.display=custList.children.length?'block':'none';
        },220);
      });
    }

    const daysWrap = host.querySelector("#daysWrap");
    let dayCount = 0;
    const deleteUIDs = new Set();

    function createDayCard(index, initial) {
      const start = h24ToTriple(initial.start24);
      const end   = h24ToTriple(initial.end24);
      const card = document.createElement("div");
      card.className = "day-card";
      card.dataset.index = String(index);
      if (initial.uid) card.dataset.uid = String(initial.uid);

      card.innerHTML = `
        <div class="day-head">
          <div class="left">
            <button type="button" class="btn sm ghost caret-btn" data-act="toggle" aria-expanded="true" title="Collapse">▾</button>
            <div class="tag">Day ${index+1}</div>
          </div>
          <div class="actions">
            <button type="button" class="btn sm" data-act="dup">Duplicate</button>
            <button type="button" class="btn sm danger" data-act="del">Remove</button>
          </div>
        </div>

        <div class="day-body">
          <div class="day-grid">
            <div>
              <div class="qa-row">
                <label>Date</label>
                <input name="day.${index}.date" type="date" value="${initial.date}" required />
              </div>
              <div class="qa-row">
                <label>Location (per-day)</label>
                <input name="day.${index}.location" type="text" value="${esc(initial.location)}" placeholder="e.g., Origin or Destination" />
              </div>
            </div>

            <div class="time-col">
              <div class="qa-row">
                <label>Start</label>
                <div class="time-triple">
                  <input name="day.${index}.start_h" type="number" min="1" max="12" value="${start.h}" />
                  <input name="day.${index}.start_m" type="number" min="0" max="59" value="${start.m}" />
                  <select name="day.${index}.start_ap">
                    <option ${start.ap==='AM'?'selected':''}>AM</option>
                    <option ${start.ap==='PM'?'selected':''}>PM</option>
                  </select>
                </div>
              </div>
              <div class="qa-row">
                <label>End (capped @ 11:59 PM)</label>
                <div class="time-triple">
                  <input name="day.${index}.end_h" type="number" min="1" max="12" value="${end.h}" />
                  <input name="day.${index}.end_m" type="number" min="0" max="59" value="${end.m}" />
                  <select name="day.${index}.end_ap">
                    <option ${end.ap==='AM'?'selected':''}>AM</option>
                    <option ${end.ap==='PM'?'selected':''}>PM</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <div class="counts">
            ${DAY_FIELDS.map(f => `
              <div class="qa-row">
                <label>${esc(f.label)}</label>
                ${f.input === 'text'
                  ? `<input name="day.${index}.${f.key}" type="text" value="${esc(initial[f.key] ?? '')}" placeholder="Optional" />`
                  : `<input name="day.${index}.${f.key}" type="number" min="0" step="${esc(f.step || '1')}" value="${(initial[f.key] === '' || initial[f.key] == null) ? '' : Number(initial[f.key])}" />`
                }
              </div>
            `).join("")}
          </div>

          <div class="day-notes qa-row">
            <label>Day Notes</label>
            <textarea name="day.${index}.notes" placeholder="Notes for this day">${esc(initial.notes || "")}</textarea>
          </div>

          <div class="files-row">
            <div class="qa-row">
              <label>BOL / CSO (PDF)</label>
              <input name="day.${index}.bol_files" type="file" accept="application/pdf" />
              ${initial.bol && initial.bol.length ? `<div class="file-links">${initial.bol.map(u=>`<a href="${esc(u)}" target="_blank">${esc(u.split('/').pop())}</a>`).join('<br>')}</div>` : ''}
              <div class="file-hint">Attach a PDF (bill of lading, CSO, etc.). Uploading a new file replaces the existing one.</div>
            </div>
            <div class="qa-row">
              <label>Additional files (any)</label>
              <input name="day.${index}.extra_files" type="file" multiple />
              ${initial.extra && initial.extra.length ? `<div class="file-links">${initial.extra.map(u=>`<a href="${esc(u)}" target="_blank">${esc(u.split('/').pop())}</a>`).join('<br>')}</div>` : ''}
              <div class="file-hint">Attach images, Excel, etc.</div>
            </div>
          </div>
        </div>
      `;

      // collapse toggle
      const btnToggle = card.querySelector('[data-act="toggle"]');
      btnToggle.addEventListener("click", () => {
        const open = btnToggle.getAttribute("aria-expanded") === "true";
        card.classList.toggle("collapsed", open);
        btnToggle.setAttribute("aria-expanded", open ? "false" : "true");
        btnToggle.textContent = open ? "▸" : "▾";
        btnToggle.title = open ? "Expand" : "Collapse";
      });

      // auto-end (+4h) until end is touched
      const sh=card.querySelector(`[name="day.${index}.start_h"]`);
      const sm=card.querySelector(`[name="day.${index}.start_m"]`);
      const sap=card.querySelector(`[name="day.${index}.start_ap"]`);
      const eh=card.querySelector(`[name="day.${index}.end_h"]`);
      const em=card.querySelector(`[name="day.${index}.end_m"]`);
      const eap=card.querySelector(`[name="day.${index}.end_ap"]`);
      let endTouched=false;
      [eh,em,eap].forEach(el=>el.addEventListener('input',()=>endTouched=true));
      function syncEnd(){
        if(endTouched) return;
        const s24=tripleTo24h(sh.value, sm.value, sap.value);
        const e24=addHoursClamp(s24,4);
        const t=h24ToTriple(e24);
        eh.value=t.h; em.value=t.m; eap.value=t.ap;
      }
      [sh,sm,sap].forEach(el=>el.addEventListener('input',syncEnd));

      const tr=card.querySelector(`[name="day.${index}.tractors"]`);
      const bob=card.querySelector(`[name="day.${index}.bobtails"]`);
      const drv=card.querySelector(`[name="day.${index}.drivers"]`);
      function syncDrv(){ if(drv) drv.value = (Number(tr?.value||0) + Number(bob?.value||0)); }
      [tr,bob].forEach(el=>el&&el.addEventListener('input',syncDrv));
      syncDrv();

      // actions
      card.querySelector('[data-act="dup"]').addEventListener('click', () => duplicateDay(index));
      card.querySelector('[data-act="del"]').addEventListener('click', () => removeDay(index));

      return card;
    }

    function renumber(){
      [...daysWrap.children].forEach((card,i)=>{
        card.dataset.index=String(i);
        card.querySelector('.tag').textContent=`Day ${i+1}`;
        card.querySelectorAll('[name]').forEach(inp=>{
          inp.name = inp.name.replace(/day\.\d+\./, `day.${i}.`);
        });
      });
      dayCount=daysWrap.children.length;
    }
    function addDay(initial){
      if(dayCount >= MAX_DAYS){ alert(`Max ${MAX_DAYS} days.`); return; }
      daysWrap.appendChild(createDayCard(dayCount, initial));
      dayCount++;
    }
    function duplicateDay(idx){
      const card=daysWrap.children[idx]; if(!card) return;
      const g=(n)=>card.querySelector(`[name=\"day.${idx}.${n}\"]`)?.value ?? "";
      const d=new Date(g("date")); d.setDate(d.getDate()+1);
      const counts={};
      DAY_FIELDS.forEach(f=>{
        const raw = g(f.key);
        if (f.input === 'text') {
          counts[f.key] = raw;
        } else if (raw === '' || raw == null) {
          counts[f.key] = f.defaultValue === '' ? '' : 0;
        } else {
          const num = Number(raw);
          counts[f.key] = Number.isFinite(num) ? num : (f.defaultValue === '' ? '' : 0);
        }
      });
      addDay({
        uid: null,
        date: toYMD(d),
        start24: tripleTo24h(g("start_h"), g("start_m"), g("start_ap")),
        end24:   tripleTo24h(g("end_h"),   g("end_m"),   g("end_ap")),
        location: g("location"),
        ...counts,
        notes: card.querySelector(`[name=\"day.${idx}.notes\"]`)?.value ?? "",
        status: card.querySelector(`[name=\"day.${idx}.status\"]`)?.value ?? (job.status || "scheduled"),
      });
    }
    function removeDay(idx){
      const card=daysWrap.children[idx]; if(!card) return;
      if(dayCount<=1){ alert("At least one day is required."); return; }
      const uid = card.dataset.uid;
      if (uid) deleteUIDs.add(uid);
      card.remove(); renumber();
    }

    // seed initial days
    daysWrap.innerHTML=""; dayCount=0;
    daysInit.forEach(d => addDay(d));

    // top contractor select after DOM exists
    const selCtr = host.querySelector('select[name="job.contractor_id"]');
    if (selCtr) selCtr.value = topContractorId != null ? String(topContractorId) : "";

    // footer "Add another day"
    const addAnotherBtn = document.createElement("button");
    addAnotherBtn.type = "button";
    addAnotherBtn.className = "btn ghost";
    addAnotherBtn.textContent = "Add another day";
    addAnotherBtn.addEventListener("click", () => {
      const lastIdx=daysWrap.children.length-1;
      const last=daysWrap.children[lastIdx];
      let nextDate = toYMD(new Date());
      let startS = "08:00", endS="12:00";
      if (last) {
        const g=(n)=>last.querySelector(`[name="day.${lastIdx}.${n}"]`)?.value ?? "";
        const d=new Date(g("date")); d.setDate(d.getDate()+1);
        nextDate = toYMD(d);
        startS = tripleTo24h(g("start_h"), g("start_m"), g("start_ap"));
        endS   = tripleTo24h(g("end_h"),   g("end_m"),   g("end_ap"));
      }
      const defaults={}; DAY_FIELDS.forEach(f=>defaults[f.key]=f.defaultValue);
      addDay({
        uid:null, date: nextDate, start24:startS, end24:endS,
        location:"", ...defaults, notes:""
      });
    });

    // Dialog
    editDlg = new ej.popups.Dialog({
      cssClass: "qa-dialog",
      header: `Edit Job — ${esc(hostTitle || "")}`,
      isModal: true,
      showCloseIcon: true,
      width: "1040px",
      target: document.body,
      position: { X: "center", Y: "center" },
      animationSettings: { effect:"Zoom" },
      content: host,
      buttons: [
        { buttonModel:{content:"Cancel"}, click:()=>closeEdit() },
        { buttonModel:{content:"Save Changes", isPrimary:true}, click:()=>saveAll() }
      ],
      open: () => {
        const footer = editDlg.element.querySelector(".e-footer-content");
        footer.prepend(addAnotherBtn);
      },
      overlayClick: () => closeEdit(),
      close: () => closeEdit()
    });

    const mount=document.createElement("div"); document.body.appendChild(mount);
    editDlg.appendTo(mount); editDlg.show();

    // save handler
    async function saveAll(){
      const get=(n)=>host.querySelector(`[name="${n}"]`)?.value ?? "";
      const customer = get("job.customer_name").trim();
      if(!customer){ alert("Customer is required."); return; }
      const contractorIdAll = get("job.contractor_id") ? Number(get("job.contractor_id")) : null;
      const status = get("job.status") || "scheduled";

      const jobOut = {
        title: customer,
        customer_name: customer,
        job_number: get("job.job_number").trim() || null,
        salesman: get("job.salesman").trim() || null,
        service_type: get("job.service_type").trim() || null,
        status
      };

      const days=[], filesMap=[];
      [...daysWrap.children].forEach((card, idx) => {
        const g=(n)=>card.querySelector(`[name="day.${idx}.${n}"]`)?.value ?? "";
        const start24h=tripleTo24h(g("start_h"), g("start_m"), g("start_ap"));
        const end24h  =tripleTo24h(g("end_h"),   g("end_m"),   g("end_ap"));

        const dayObj={
          day_uid: card.dataset.uid || null, // null => new
          work_date: g("date"),
          start_time: `${start24h}:00`,
          end_time:   `${end24h}:00`,
          contractor_id: contractorIdAll,
          location: (g("location")||"").trim() || null,
          day_notes:(card.querySelector(`[name="day.${idx}.notes"]`)?.value||"").trim() || null,
          status,
          meta:{},
        };
        DAY_FIELDS.forEach(f=>{
          const raw = g(f.key);
          if (f.input === 'text') {
            const trimmed = (raw || '').trim();
            dayObj[f.key] = trimmed === '' ? null : trimmed;
          } else if (raw === '' || raw == null) {
            dayObj[f.key] = f.defaultValue === '' ? null : 0;
          } else {
            const num = Number(raw);
            dayObj[f.key] = Number.isFinite(num) ? num : (f.defaultValue === '' ? null : 0);
          }
        });
        days.push(dayObj);
        const bol=card.querySelector(`[name="day.${idx}.bol_files"]`)?.files;
        const ext=card.querySelector(`[name="day.${idx}.extra_files"]`)?.files;
        filesMap.push({ idx, bolFiles:bol, extraFiles:ext });
      });

      const fd=new FormData();
      fd.append("payload", JSON.stringify({
        job_uid: job.job_uid || job.JobUID || job.id || job.JobId || null,
        job: jobOut,
        days,
        delete_uids: Array.from(deleteUIDs)
      }));
      filesMap.forEach(({idx,bolFiles,extraFiles})=>{
        if(bolFiles && bolFiles.length) for(const f of bolFiles) fd.append(`files[${idx}][bol][]`, f, f.name);
        if(extraFiles && extraFiles.length) for(const f of extraFiles) fd.append(`files[${idx}][extra][]`, f, f.name);
      });

      try{
        // NOTE: server should return JSON { ok:true } or { ok:false, error:"..." }
        const res = await fetch(ENDPOINTS.editSave, { method:"POST", body: fd });
        const txt = await res.text();
        let j; try { j = JSON.parse(txt); } catch { j = null; }
        if (!res.ok || !j || j.ok === false) {
          const msg = (j && j.error) ? j.error : `API ${res.status} @ ${ENDPOINTS.editSave}`;
          throw new Error(msg);
        }

        // refresh the visible day
        if (window.getViewYMD && window.loadDay) {
          await window.loadDay(window.getViewYMD());
        }
        closeEdit();
      }catch(e){
        console.error(e);
        alert(e.message || "Failed to update job.");
      }
    }
  }

  // ---------- public openers ----------
  async function openByDayId(dayUID) {
    if (!dayUID) return;
    try {
      const url = `${ENDPOINTS.editRead}?from_day_uid=${encodeURIComponent(dayUID)}`;
      const { job, days } = await fetchJSON(url);
      const daysNorm = (Array.isArray(days) ? days : []).map(normalizeDay);
      const title = job?.customer_name || job?.Customer || job?.title || job?.Subject || "";
      let topContractorId = null;
      if (job?.contractor_id != null) topContractorId = Number(job.contractor_id);
      else if (daysNorm.length && daysNorm[0].contractor_id != null) topContractorId = Number(daysNorm[0].contractor_id);
      createEditor(title, job || {}, daysNorm, topContractorId);
    } catch (e) {
      console.error(e);
      alert(e.message || "Could not open edit.");
    }
  }

  async function openByJobId(jobId) {
    if (!jobId) return;
    try {
      const url = `${ENDPOINTS.editRead}?job=${encodeURIComponent(jobId)}`;
      const { job, days } = await fetchJSON(url);
      const daysNorm = (Array.isArray(days) ? days : []).map(normalizeDay);
      const title = job?.customer_name || job?.Customer || job?.title || job?.Subject || "";
      let topContractorId = null;
      if (job?.contractor_id != null) topContractorId = Number(job.contractor_id);
      else if (daysNorm.length && daysNorm[0].contractor_id != null) topContractorId = Number(daysNorm[0].contractor_id);
      createEditor(title, job || {}, daysNorm, topContractorId);
    } catch (e) {
      console.error(e);
      alert(e.message || "Could not open edit.");
    }
  }

  window.editJob = { openByDayId, openByJobId };

  // Backwards compatibility: legacy callers may still invoke
  // `open_job_edit_dialog` expecting to pass a scheduler event.
  // Accept a variety of event shapes and delegate to the new API.
  window.open_job_edit_dialog = function(ev) {
    const dayUid = ev?.Id || ev?.day_uid || ev?.uid || ev?.DayUID;
    if (dayUid) return openByDayId(dayUid);

    const jobId = ev?.JobId || ev?.job_id;
    if (jobId) return openByJobId(jobId);

    console.error('open_job_edit_dialog: missing event data', ev);
    alert('Missing job id');
  };
})();