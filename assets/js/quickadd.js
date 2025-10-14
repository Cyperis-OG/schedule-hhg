// assets/js/quickadd.js
// Quick-Add dialog (centered). Only depends on window.sch and window.loadDay

(function () {
  // ---- Local config & helpers (no external globals required) ----
  const MAX_DAYS = 5;
  const apiCfg = (window.SCH_CFG && window.SCH_CFG.API) || {};
  const API = {
    saveJob: apiCfg.saveJob || './api/job_save.php',
    salesSearch: apiCfg.salesSearch || apiCfg.salesmenSearch || './api/salesmen_search.php',
    customersSearch: apiCfg.customersSearch || './api/customers_search.php'
  };

  const pad2  = (n) => (n < 10 ? '0' : '') + n;
  const toYMD = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;

  const DEFAULT_FIELDS = [
    { key: 'tractors',         label: 'TTrailers' },
    { key: 'bobtails',         label: 'Bobtails' },
    { key: 'movers',           label: 'Movers' },
    { key: 'drivers',          label: 'Drivers' },
    { key: 'installers',       label: 'Installers' },
    { key: 'pctechs',          label: 'PC Techs' },
    { key: 'supervisors',      label: 'Supervisors' },
    { key: 'project_managers', label: 'Project Managers' },
    { key: 'crew_transport',   label: 'Crew Transport' },
    { key: 'electricians',     label: 'Electricians' }
  ];
  const DAY_FIELDS = (window.SCH_CFG?.DAY_FIELDS || DEFAULT_FIELDS).filter(f => f.enabled !== false);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));

  const addHoursClamp = (hhmm, add) => {
    const [h, m] = hhmm.split(':').map(Number);
    let eh = h + add, em = m;
    if (eh > 23 || (eh === 23 && em > 59)) { eh = 23; em = 59; }
    return `${pad2(eh)}:${pad2(em)}`;
  };

  const tripleTo24h = (hStr, mStr, ap) => {
    let h = Math.max(1, Math.min(12, parseInt(hStr || '0', 10) || 12));
    let m = Math.max(0, Math.min(59, parseInt(mStr || '0', 10) || 0));
    ap = (ap || 'AM').toUpperCase();
    if (ap === 'PM' && h !== 12) h += 12;
    if (ap === 'AM' && h === 12) h = 0;
    return `${pad2(h)}:${pad2(m)}`;
  };

  const h24ToTriple = (hhmm) => {
    const [H, M] = hhmm.split(':').map(Number);
    const ap = H >= 12 ? 'PM' : 'AM';
    let h = H % 12; if (h === 0) h = 12;
    return { h: String(h), m: pad2(M), ap };
  };

  const contractorOptionsHtml = () => {
    const ds = window.sch?.resources?.[0]?.dataSource || [];
    return ds.map(c => `<option value="${String(c.id)}">${c.name}</option>`).join('');
  };

  // ---- Quick-Add ----
  let quickDlg = null;

  function openQuickAddDialog({ startTime, endTime, groupIndex }) {
    let contractorId = null;
    try {
      const res = window.sch.getResourcesByIndex(groupIndex);
      contractorId = res?.resourceData?.id ?? res?.resourceData?.ContractorId ?? null;
    } catch (_) {}

    const dateYMD = toYMD(startTime);
    const start24 = `${pad2(startTime.getHours())}:${pad2(startTime.getMinutes())}`;
    const end24   = addHoursClamp(start24, 4);

    const host = document.createElement('div');
    host.className = 'qa';
    host.innerHTML = `
      <form id="qaForm" autocomplete="off">
        <div class="qa-grid">
          <div class="qa-row">
            <label>Customer <span style="color:#ef4444">*</span></label>
            <div class="ac">
              <input name="job.customer_name" type="text" placeholder="e.g., Brightstar" required autocomplete="off" />
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
            <input name="job.salesman" type="text" placeholder="Optional" autocomplete="off" />
            <div class="ac-list"></div>
          </div>
        </div>
        <div class="qa-row">
          <label>Service Type</label>
          <input name="job.service_type" type="text" placeholder="Optional" />
        </div>
          <div class="qa-row">
            <label>Job Number</label>
            <input name="job.job_number" type="text" placeholder="Optional (e.g., DA-14855-5)" />
          </div>

          <div class="qa-row status-row">
            <label>Status</label>
            <select name="job.status">
              <option value="placeholder">Placeholder</option>␊
              <option value="needs_paperwork" selected>Scheduled - Needs Paperwork</option>
              <option value="scheduled">Scheduled</option>
              <option value="dispatched">Dispatched</option>
              <option value="canceled">Canceled</option>
              <option value="completed">Completed</option>
              <option value="paid">Paid</option>
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
          const r=await fetch(`${API.salesSearch}?q=${encodeURIComponent(q)}`);
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
          const r=await fetch(`${API.customersSearch}?q=${encodeURIComponent(q)}`);
          const j=await r.json(); custList.innerHTML='';
          (j.results||[]).forEach(item=>{
            const it=document.createElement('div'); it.className='ac-item';
            it.innerHTML=`<div><strong>${esc(item.name)}</strong></div><div class="help">Prefers: ${esc(item.preferred_contractor_name||'—')} · Requester: ${esc(item.default_salesman||'—')}</div>`;
            it.addEventListener('click',()=>{
              custInput.value=item.name; custList.style.display='none';
              const loc=host.querySelector('input[name="day.0.location"]');
              if(loc && item.default_location) loc.value=item.default_location;
              const sal=host.querySelector('input[name="job.salesman"]');
              if(sal && item.default_salesman) sal.value=item.default_salesman;
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

    // ---- per-day card ----
    function createDayCard(index, initial) {
      const start = h24ToTriple(initial?.start24 || start24);
      const end   = h24ToTriple(initial?.end24   || end24);
      const date  = initial?.date || dateYMD;
      const loc   = initial?.location || '';

      const card = document.createElement('div');
      card.className = 'day-card';
      card.dataset.index = String(index);
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
                <input name="day.${index}.date" type="date" value="${date}" required />
              </div>
              <div class="qa-row">
                <label>Location (per-day)</label>
                <input name="day.${index}.location" type="text" value="${loc}" placeholder="e.g., Origin or Destination" />
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
                <label>End (auto +4h, capped @ 11:59 PM)</label>
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
            ${DAY_FIELDS.map(f=>`
              <div class="qa-row"><label>${esc(f.label)}</label>
                <input name="day.${index}.${f.key}" type="number" min="0" step="1" value="${Number(initial?.[f.key] ?? 0)}" />
              </div>`).join('')}
          </div>

          <div class="day-notes qa-row">
            <label>Day Notes</label>
            <textarea name="day.${index}.notes" placeholder="Notes for this day">${initial?.notes ?? ''}</textarea>
          </div>

          <div class="files-row">
            <div class="qa-row">
              <label>BOL / CSO (PDF)</label>
              <input name="day.${index}.bol_files" type="file" accept="application/pdf" />
              <div class="file-hint">Attach a PDF (bill of lading, CSO, etc.). Uploading a new file replaces the existing one.</div>
            </div>
            <div class="qa-row">
              <label>Additional files (any)</label>
              <input name="day.${index}.extra_files" type="file" multiple />
              <div class="file-hint">Attach images, Excel, etc.</div>
            </div>
          </div>
        </div>
      `;

      // collapse control
      const btnToggle = card.querySelector('[data-act="toggle"]');
      btnToggle.addEventListener('click', () => {
        const open = btnToggle.getAttribute('aria-expanded') === 'true';
        card.classList.toggle('collapsed', open);
        btnToggle.setAttribute('aria-expanded', open ? 'false':'true');
        btnToggle.textContent = open ? '▸' : '▾';
        btnToggle.title = open ? 'Expand' : 'Collapse';
      });

      // auto-calc end (+4h) until user touches end fields
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

      card.querySelector('[data-act="dup"]').addEventListener('click',()=>duplicateDay(index));
      card.querySelector('[data-act="del"]').addEventListener('click',()=>removeDay(index));

      return card;
    }

    const daysWrap = host.querySelector('#daysWrap');
    let dayCount = 0;

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
      const g=(n)=>card.querySelector(`[name="day.${idx}.${n}"]`)?.value ?? '';
      const d=new Date(g('date')||dateYMD); d.setDate(d.getDate()+1);
      const counts={};
      DAY_FIELDS.forEach(f=>{ counts[f.key]=g(f.key); });
      addDay({
        date: toYMD(d),
        start24: tripleTo24h(g('start_h'), g('start_m'), g('start_ap')),
        end24:   tripleTo24h(g('end_h'),   g('end_m'),   g('end_ap')),
        location: g('location'),
        ...counts,
        notes: card.querySelector(`[name="day.${idx}.notes"]`)?.value ?? ''
      });
    }

    function removeDay(idx){
      const card=daysWrap.children[idx]; if(!card) return;
      if(dayCount<=1){ alert('At least one day is required.'); return; }
      card.remove(); renumber();
    }

    const addAnotherBtn=document.createElement('button');
    addAnotherBtn.type='button';
    addAnotherBtn.className='btn ghost';
    addAnotherBtn.textContent='Add another day';
    addAnotherBtn.addEventListener('click',()=>{
      const lastIdx=daysWrap.children.length-1;
      const last=daysWrap.children[lastIdx];
      let nextDate=dateYMD,startS=start24,endS=end24;
      if(last){
        const g=(n)=>last.querySelector(`[name="day.${lastIdx}.${n}"]`)?.value ?? '';
        const d=new Date(g('date')||dateYMD); d.setDate(d.getDate()+1);
        nextDate=toYMD(d);
        startS=tripleTo24h(g('start_h'), g('start_m'), g('start_ap'));
        endS=tripleTo24h(g('end_h'),   g('end_m'),   g('end_ap'));
      }
      const defaults={}; DAY_FIELDS.forEach(f=>defaults[f.key]=0);
      addDay({
        date: nextDate, start24: startS, end24: endS,
        location: '', ...defaults, notes:''
      });
    });

    // ---- Dialog ----
    quickDlg = new ej.popups.Dialog({
      cssClass: 'qa-dialog',
      header: `Add Job — ${dateYMD}`,
      isModal: true,
      showCloseIcon: true,
      width: '1040px',
      target: document.body,
      position: { X: 'center', Y: 'center' },  // centered
      overlayClick: () => quickDlg.hide(),     // click-outside closes
      animationSettings: { effect:'Zoom' },
      content: host,
      buttons: [
        { buttonModel:{content:'Cancel'}, click:()=>quickDlg.hide() },
        { buttonModel:{content:'Save', isPrimary:true}, click:()=>saveQuickAdd() }
      ],
      open:()=>{
        const daysWrapEl = host.querySelector('#daysWrap');
        daysWrapEl.innerHTML=''; dayCount=0;
        const topCtr=host.querySelector('select[name="job.contractor_id"]');
        if(topCtr && contractorId!=null) topCtr.value=String(contractorId);
        addDay({ date:dateYMD, start24, end24 });
        const footer=quickDlg.element.querySelector('.e-footer-content');
        footer?.prepend(addAnotherBtn);
      },
      close:()=>{
        try { quickDlg.destroy(); quickDlg.element.remove(); } catch(_){}
        quickDlg=null;
      }
    });

    const dlgHost=document.createElement('div'); document.body.appendChild(dlgHost);
    quickDlg.appendTo(dlgHost); quickDlg.show();

    // ---- Save ----
    async function saveQuickAdd(){
      const get=(n)=>host.querySelector(`[name="${n}"]`)?.value ?? '';
      const customer=get('job.customer_name').trim();
      if(!customer){ alert('Customer is required.'); return; }
      const contractorIdAll=get('job.contractor_id') ? Number(get('job.contractor_id')) : null;

      const job={
        title: customer,
        customer_name: customer,
        job_number: get('job.job_number').trim() || null,
        salesman: get('job.salesman').trim() || null,
        status: get('job.status') || 'needs_paperwork'
      };

      const days=[], filesMap=[];
      [...host.querySelectorAll('.day-card')].forEach((card, idx)=>{
        const g=(n)=>card.querySelector(`[name="day.${idx}.${n}"]`)?.value ?? '';
        const start24h=tripleTo24h(g('start_h'), g('start_m'), g('start_ap'));
        const end24h  =tripleTo24h(g('end_h'),   g('end_m'),   g('end_ap'));

        const dayObj={
          work_date:g('date'),
          start_time:`${start24h}:00`,
          end_time:`${end24h}:00`,
          contractor_id: contractorIdAll,
          location:(g('location')||'').trim() || null,
          day_notes:(card.querySelector(`[name="day.${idx}.notes"]`)?.value||'').trim() || null,
          status: job.status,
          meta:{},
        };
        DAY_FIELDS.forEach(f=>{ dayObj[f.key]=+(g(f.key)||0); });
        days.push(dayObj);
        const bol=card.querySelector(`[name="day.${idx}.bol_files"]`)?.files;
        const ext=card.querySelector(`[name="day.${idx}.extra_files"]`)?.files;
        filesMap.push({ idx, bolFiles:bol, extraFiles:ext });
      });

      const fd=new FormData();
      fd.append('payload', JSON.stringify({ job, days }));
      filesMap.forEach(({idx,bolFiles,extraFiles})=>{
        if(bolFiles && bolFiles.length) for(const f of bolFiles) fd.append(`files[${idx}][bol][]`, f, f.name);
        if(extraFiles && extraFiles.length) for(const f of extraFiles) fd.append(`files[${idx}][extra][]`, f, f.name);
      });

      try{
        const r=await fetch(API.saveJob, { method:'POST', body: fd });
        const ct=r.headers.get('content-type')||'';
        let j;
        if(ct.includes('application/json')){
          j=await r.json();
          if(!r.ok || !j.ok) throw new Error(j.error || `Save failed (${r.status})`);
        }else{
          const text=await r.text();
          throw new Error(text || `Unexpected response (status ${r.status})`);
        }
        await window.loadDay(days[0].work_date);
        quickDlg.hide();
      }catch(e){
        console.error(e);
        alert(e.message || 'Failed to save job.');
      }
    }
  }

  // expose
  window.quickAdd = { open: openQuickAddDialog };
})();