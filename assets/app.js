// ----------------------- Syncfusion modules -----------------------
if (window.ej?.schedule?.Schedule?.Inject) {
  ej.schedule.Schedule.Inject(
    ej.schedule.TimelineViews,
    ej.schedule.Resize,
    ej.schedule.DragAndDrop
  );
}

// ----------------------- Config / helpers -----------------------
const BASE_PATH = '/095/schedule-ng';
const API = {
  fetchDay:        `${BASE_PATH}/api/jobs_fetch.php`,
  popup:           `${BASE_PATH}/api/popup_render.php`,
  persistTimeslot: `${BASE_PATH}/api/job_update_timeslot.php`,
  saveJob:         `${BASE_PATH}/api/job_save.php`
};

const MAX_DAYS = 5;

const pad2 = (n)=>(n<10?'0':'')+n;
const toYMD = (d)=>`${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
const fmtLocal = (d)=>`${toYMD(d)} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;

function tripleTo24h(hStr,mStr,ap){
  let h=Math.max(1,Math.min(12,parseInt(hStr||'0',10)||12));
  let m=Math.max(0,Math.min(59,parseInt(mStr||'0',10)||0));
  ap=(ap||'AM').toUpperCase();
  if(ap==='PM'&&h!==12)h+=12;
  if(ap==='AM'&&h===12)h=0;
  return `${pad2(h)}:${pad2(m)}`;
}
function addHoursClamp(hhmm,add){
  const [h,m]=hhmm.split(':').map(Number);
  let eh=h+add, em=m;
  if(eh>23 || (eh===23&&em>59)){ eh=23; em=59; }
  return `${pad2(eh)}:${pad2(em)}`;
}
function h24ToTriple(hhmm){
  const [H,M]=hhmm.split(':').map(Number);
  const ap=H>=12?'PM':'AM';
  let h=H%12; if(h===0)h=12;
  return {h:String(h), m:pad2(M), ap};
}

// Access to the scheduler instance & DnD toggle element
let sch = null;
const dndCheckbox = document.getElementById('dragToggle');

// Get the YMD for whatever the component is actually rendering
function getViewYMD() {
  const dates = sch?.getCurrentViewDates ? sch.getCurrentViewDates() : null;
  const first = (dates && dates.length) ? dates[0] : sch.selectedDate;
  return `${first.getFullYear()}-${pad2(first.getMonth()+1)}-${pad2(first.getDate())}`;
}

// For quick-add contractor select options
function contractorOptionsHtml(){
  const ds = sch?.resources?.[0]?.dataSource || [];
  return ds.map(c=>`<option value="${String(c.id)}">${c.name}</option>`).join('');
}

function showAttachments(files){
  const links=[];
  (files?.bol||[]).forEach(u=>links.push(`<li><a href="${u}" target="_blank">${u.split('/').pop()}</a></li>`));
  (files?.extra||[]).forEach(u=>links.push(`<li><a href="${u}" target="_blank">${u.split('/').pop()}</a></li>`));
  const mount=document.createElement('div');
  document.body.appendChild(mount);
  const dlg=new ej.popups.Dialog({
    header:'Attachments',
    content:`<ul class="file-list">${links.join('')}</ul>`,
    showCloseIcon:true,
    target:document.body,
    width:'360px',
    buttons:[{buttonModel:{content:'Close',isPrimary:true},click:()=>dlg.hide()}]
  });
  dlg.appendTo(mount);
  dlg.close=()=>{dlg.destroy(); mount.remove();};
  dlg.show();
}

// ----------------------- Quick Add Modal -----------------------
let quickDlg = null;

function openQuickAddDialog({startTime, endTime, groupIndex}){
  let contractorId = null;
  try {
    const res=sch.getResourcesByIndex(groupIndex);
    contractorId=res?.resourceData?.id ?? res?.resourceData?.ContractorId ?? null;
  } catch(_){}

  const dateYMD=toYMD(startTime);
  const start24=`${pad2(startTime.getHours())}:${pad2(startTime.getMinutes())}`;
  const end24=addHoursClamp(start24,4);

  const host=document.createElement('div');
  host.className='qa';
  host.innerHTML=`
    <form id="qaForm" autocomplete="off">
      <div class="qa-grid">
        <div class="qa-row">
          <label>Customer <span style="color:#ef4444">*</span></label>
          <input name="job.customer_name" type="text" placeholder="e.g., Brightstar" required />
        </div>
        <div class="qa-row">
          <label>Contractor (applies to all days)</label>
          <select name="job.contractor_id">
            <option value="">— Unassigned —</option>
            ${contractorOptionsHtml()}
          </select>
        </div>

        <div class="qa-row">
          <label>Salesman / Primary Contact</label>
          <input name="job.salesman" type="text" placeholder="Optional" />
        </div>
        <div class="qa-row">
          <label>Job Number</label>
          <input name="job.job_number" type="text" placeholder="Optional (e.g., DA-14855-5)" />
        </div>

        <div class="qa-row status-row">
          <label>Status</label>
          <select name="job.status">
            <option value="placeholder">Placeholder</option>
            <option value="needs_paperwork">Scheduled - Needs Paperwork</option>
            <option value="scheduled" selected>Scheduled</option>
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

  function createDayCard(index, initial){
    const date  = initial?.date   || dateYMD;
    const start = h24ToTriple(initial?.start24 || start24);
    const end   = h24ToTriple(initial?.end24   || end24);
    const loc   = initial?.location || '';

    const card=document.createElement('div');
    card.className='day-card';
    card.dataset.index=String(index);
    card.innerHTML=`
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
          <div class="qa-row"><label>TTrailers</label>       <input name="day.${index}.tractors"         type="number" min="0" step="1" value="${0}" /></div>
          <div class="qa-row"><label>Bobtails</label>        <input name="day.${index}.bobtails"         type="number" min="0" step="1" value="${0}" /></div>
          <div class="qa-row"><label>Movers</label>          <input name="day.${index}.movers"           type="number" min="0" step="1" value="${0}" /></div>
          <div class="qa-row"><label>Drivers</label>         <input name="day.${index}.drivers"          type="number" min="0" step="1" value="${0}" /></div>
          <div class="qa-row"><label>Installers</label>      <input name="day.${index}.installers"       type="number" min="0" step="1" value="${0}" /></div>
          <div class="qa-row"><label>PC Techs</label>        <input name="day.${index}.pctechs"          type="number" min="0" step="1" value="${0}" /></div>
          <div class="qa-row"><label>Supervisors</label>     <input name="day.${index}.supervisors"      type="number" min="0" step="1" value="${0}" /></div>
          <div class="qa-row"><label>Project Managers</label><input name="day.${index}.project_managers" type="number" min="0" step="1" value="${0}" /></div>
          <div class="qa-row"><label>Electricians</label>    <input name="day.${index}.electricians"     type="number" min="0" step="1" value="${0}" /></div>
        </div>

        <div class="day-notes qa-row">
          <label>Day Notes</label>
          <textarea name="day.${index}.notes" placeholder="Notes for this day"></textarea>
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

    const btnToggle=card.querySelector('[data-act="toggle"]');
    btnToggle.addEventListener('click',()=>{
      const open = btnToggle.getAttribute('aria-expanded')==='true';
      card.classList.toggle('collapsed', open);
      btnToggle.setAttribute('aria-expanded', open ? 'false':'true');
      btnToggle.textContent = open ? '▸' : '▾';
      btnToggle.title = open ? 'Expand' : 'Collapse';
    });

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

    card.querySelector('[data-act="dup"]').addEventListener('click',()=>duplicateDay(index));
    card.querySelector('[data-act="del"]').addEventListener('click',()=>removeDay(index));

    return card;
  }

  const daysWrap=host.querySelector('#daysWrap');
  let dayCount=0;

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
    if(dayCount>=MAX_DAYS){ alert(`Max ${MAX_DAYS} days.`); return; }
    daysWrap.appendChild(createDayCard(dayCount, initial));
    dayCount++;
  }
  function duplicateDay(idx){
    const card=daysWrap.children[idx]; if(!card) return;
    const g=(n)=>card.querySelector(`[name="day.${idx}.${n}"]`)?.value ?? '';
    const d=new Date(g('date')||dateYMD); d.setDate(d.getDate()+1);
    addDay({
      date: toYMD(d),
      start24: tripleTo24h(g('start_h'), g('start_m'), g('start_ap')),
      end24:   tripleTo24h(g('end_h'),   g('end_m'),   g('end_ap')),
      location: g('location'),
      tractors: g('tractors'), bobtails: g('bobtails'),
      movers: g('movers'), drivers: g('drivers'),
      installers: g('installers'), pctechs: g('pctechs'),
      supervisors: g('supervisors'),
      project_managers: g('project_managers'),
      electricians: g('electricians'),
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
    addDay({
      date: nextDate, start24: startS, end24: endS,
      location: '', tractors:0, bobtails:0, movers:0, drivers:0, installers:0,
      pctechs:0, supervisors:0, project_managers:0, electricians:0, notes:''
    });
  });

  quickDlg=new ej.popups.Dialog({
    cssClass:'qa-dialog qi-dialog',
    header:`Add Job — ${dateYMD}`,
    isModal:true, showCloseIcon:true, width:'1040px', target:document.body,
    animationSettings:{effect:'Zoom'},
    content:host,
    buttons:[
      { buttonModel:{content:'Cancel'}, click:()=>quickDlg.hide() },
      { buttonModel:{content:'Save', isPrimary:true}, click:()=>saveQuickAdd() }
    ],
    open:()=>{
      daysWrap.innerHTML=''; dayCount=0;
      const topCtr=host.querySelector('select[name="job.contractor_id"]');
      if(topCtr && contractorId!=null) topCtr.value=String(contractorId);
      addDay({ date:dateYMD, start24, end24 });
      const footer=quickDlg.element.querySelector('.e-footer-content');
      footer.prepend(addAnotherBtn);
    },
    close:()=>{ quickDlg.destroy(); quickDlg.element.remove(); quickDlg=null; }
  });
  const dlgHost=document.createElement('div'); document.body.appendChild(dlgHost);
  quickDlg.appendTo(dlgHost); quickDlg.show();

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
      status: get('job.status') || 'scheduled'
    };

    const days=[], filesMap=[];
    [...host.querySelectorAll('.day-card')].forEach((card, idx)=>{
      const g=(n)=>card.querySelector(`[name="day.${idx}.${n}"]`)?.value ?? '';
      const start24h=tripleTo24h(g('start_h'), g('start_m'), g('start_ap'));
      const end24h  =tripleTo24h(g('end_h'),   g('end_m'),   g('end_ap'));

      days.push({
        work_date:g('date'),
        start_time:`${start24h}:00`,
        end_time:`${end24h}:00`,
        contractor_id: contractorIdAll,
        location:(g('location')||'').trim() || null,
        tractors:+(g('tractors')||0), bobtails:+(g('bobtails')||0),
        movers:+(g('movers')||0), drivers:+(g('drivers')||0),
        installers:+(g('installers')||0), pctechs:+(g('pctechs')||0),
        supervisors:+(g('supervisors')||0),
        project_managers:+(g('project_managers')||0),
        electricians:+(g('electricians')||0),
        day_notes:(card.querySelector(`[name="day.${idx}.notes"]`)?.value||'').trim() || null,
        status: job.status,
        meta:{}
      });
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
      await loadDay(days[0].work_date); // reload the day we added to
      quickDlg.hide();
    }catch(e){ console.error(e); alert(e.message || 'Failed to save job.'); }
  }
}

// ----------------------- Quick Info (server-rendered) -----------------------
let infoDlg = null;                 // only one open
let infoOutsideAbort = null;        // outside-click listener cleanup

function closeInfoDlg(){
  try{ infoDlg?.hide(); infoDlg?.destroy(); infoDlg?.element?.remove(); }catch(_){}
  if (infoOutsideAbort) { infoOutsideAbort.abort(); infoOutsideAbort=null; }
  infoDlg=null;
}

// ----------------------- Drag/Drop toggle -----------------------
function applyDndState(enabled){
  if (!sch) return;
  sch.allowDragAndDrop = !!enabled;
  sch.allowResizing    = !!enabled;
  sch.dataBind();
}

// ----------------------- Scheduler -----------------------
sch = new ej.schedule.Schedule({
  height:'100%', width:'100%',
  views:[{ option:'TimelineDay', isSelected:true }],
  currentView:'TimelineDay',
  startHour:'00:00', endHour:'24:00',
  resourceHeaderWidth:170,
  selectedDate:new Date(),
  showQuickInfo:false,                 // we use our own dialog

  group:{ resources:['Contractors'], orientation:'Vertical', allowGroupEdit:false },
  resources:[{
    field:'ContractorId', title:'Contractor', name:'Contractors',
    dataSource:[], textField:'name', idField:'id', colorField:'color_hex'
  }],
  eventSettings:{ dataSource:[] },

  // Ensure initial load happens after internal date calc
  created: () => {
    // lock DnD to the toggle state
    applyDndState(dndCheckbox?.checked);
    // first data load aligned to rendered day
    loadDay(getViewYMD());
  },

  // Quick-Add on double click
  cellDoubleClick:(args)=>{
    if (!window.IS_ADMIN) { args.cancel = true; return; }
    if(!args?.startTime || !args?.endTime) return;
    if(!args.element?.classList?.contains('e-work-cells')) return;
    openQuickAddDialog(args); args.cancel=true;
  },

  // Keep data in sync on navigation
  actionComplete: (args) => {
    if (args.requestType === 'dateNavigate') {
      loadDay(getViewYMD());
    }
  },

  // Click event => popup (server-rendered HTML)
  eventClick: async (args)=>{
    try{
      const id=args?.event?.Id; if(!id) return;

      // always close currently open one
      closeInfoDlg();

      const r=await fetch(`${API.popup}?job_day_uid=${encodeURIComponent(id)}`);
      const j=await r.json();
      const html=j?.html||'<div style="padding:16px">Error loading job details.</div>';

      infoDlg = new ej.popups.Dialog({
        cssClass:'qi-dialog',
        isModal:true,
        width:'560px',
        header: (j?.title || 'Job Details'),
        content: html,
        target: document.body,
        showCloseIcon:true,
        animationSettings:{ effect:'Zoom' },
        buttons:[{ buttonModel:{ content:'Close', isPrimary:true }, click:()=>closeInfoDlg() }],
        close: ()=> closeInfoDlg()
      });

      const mount=document.createElement('div'); document.body.appendChild(mount);
      infoDlg.appendTo(mount); infoDlg.show();

      // Close when clicking outside (robust)
      if (infoOutsideAbort) { infoOutsideAbort.abort(); }
      infoOutsideAbort = new AbortController();
      const sig = infoOutsideAbort.signal;

      document.addEventListener('pointerdown', (ev)=>{
        const el = infoDlg?.element;
        if (!el) return;
        if (!el.contains(ev.target)) closeInfoDlg();
      }, { capture:true, signal: sig });

      // Also honor overlay clicks
      infoDlg.overlayClick = () => closeInfoDlg();

    }catch(e){ console.error(e); }
  },

  eventRendered:(args)=>{
    const f=args.data?.files;
    if(f && ((f.bol && f.bol.length) || (f.extra && f.extra.length))){
      const icon=document.createElement('span');
      icon.className='file-clip';
      icon.textContent='📎';
      icon.title='View attachments';
      icon.addEventListener('click',ev=>{ev.stopPropagation(); showAttachments(f);});
      args.element.appendChild(icon);
    }
  },

  // Persist drag / resize
  dragStop: async (args)=>{
    try{
      const ev=args?.data; if(!ev) return;
      const p={ job_day_uid:ev.Id,
        start:fmtLocal(new Date(ev.StartTime)),
        end:fmtLocal(new Date(ev.EndTime)),
        contractor_id: ev.ContractorId ?? null };
      await fetch(API.persistTimeslot,{ method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(p) });
    }catch(e){ console.error(e); }
  },
  resizeStop: async (args)=>{
    try{
      const ev=args?.data; if(!ev) return;
      const p={ job_day_uid:ev.Id,
        start:fmtLocal(new Date(ev.StartTime)),
        end:fmtLocal(new Date(ev.EndTime)),
        contractor_id: ev.ContractorId ?? null };
      await fetch(API.persistTimeslot,{ method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(p) });
    }catch(e){ console.error(e); }
  }
});
sch.appendTo('#Schedule');

// Toggle wiring (after instance exists)
if (dndCheckbox) {
  dndCheckbox.addEventListener('change', () => applyDndState(dndCheckbox.checked));
}

// ----------------------- Data load -----------------------
async function loadDay(dateStr) {
  try {
    const r = await fetch(`${API.fetchDay}?date=${encodeURIComponent(dateStr)}`);
    const j = await r.json();

    const normalizeEvents = (arr=[]) => arr.map(ev => ({
      ...ev,
      StartTime: (ev.StartTime instanceof Date)
        ? ev.StartTime
        : new Date(typeof ev.StartTime === 'string' ? ev.StartTime.replace(' ', 'T') : ev.StartTime),
      EndTime: (ev.EndTime instanceof Date)
        ? ev.EndTime
        : new Date(typeof ev.EndTime === 'string' ? ev.EndTime.replace(' ', 'T') : ev.EndTime),
      ContractorId: ev.ContractorId == null ? null : Number(ev.ContractorId)
    }));

    const normalizeResources = (arr=[]) => arr.map(r => ({
      ...r,
      id: Number(r.id) // must match ContractorId type
    }));

    sch.resources[0].dataSource = normalizeResources(j.resources || []);
    sch.eventSettings.dataSource = normalizeEvents(j.events || []);
    sch.dataBind();

    // console.debug('[loadDay]', dateStr, {events: sch.eventSettings.dataSource.length, resources: sch.resources[0].dataSource.length});
  } catch (e) {
    console.error('Failed to load schedule data:', e);
    sch.eventSettings.dataSource = []; sch.dataBind();
  }
}
