import { state, API, MAX_DAYS } from './state.js';
import { pad2, toYMD, fmtLocal, tripleTo24h, addHoursClamp, h24ToTriple } from './utils.js';
import { loadDay } from './data.js';

export function openQuickAddDialog({startTime, endTime, groupIndex}){
  let contractorId=null;
  try {
    const res=state.sch.getResourcesByIndex(groupIndex);
    contractorId=res?.resourceData?.id ?? res?.resourceData?.ContractorId ?? null;
  } catch(_){}

  const dateYMD=toYMD(startTime);
  const start24=`${pad2(startTime.getHours())}:${pad2(startTime.getMinutes())}`;
  const end24=addHoursClamp(start24,4);

  const contractorOptionsHtml = () => {
    const ds = state.sch?.resources?.[0]?.dataSource || [];
    return ds.map(c=>`<option value="${String(c.id)}">${c.name}</option>`).join('');
  };

  const escHtml = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

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
          <label>Requester Name</label>
          <input name="job.salesman" type="text" placeholder="Optional" />
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

  function createDayCard(index, initial){
    const date  = initial?.date   || dateYMD;
    const start = h24ToTriple(initial?.start24 || start24);
    const end   = h24ToTriple(initial?.end24   || end24);
    const loc   = initial?.location || '';
    const equipment = initial?.equipment || '';
    const weight    = initial?.weight ?? '';

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
          <div class="qa-row"><label>TTrailers</label>       <input name="day.${index}.tractors"         type="number" min="0" step="1" value="0" /></div>
          <div class="qa-row"><label>Bobtails</label>        <input name="day.${index}.bobtails"         type="number" min="0" step="1" value="0" /></div>
          <div class="qa-row"><label>Movers</label>          <input name="day.${index}.movers"           type="number" min="0" step="1" value="0" /></div>
          <div class="qa-row"><label>Drivers</label>         <input name="day.${index}.drivers"          type="number" min="0" step="1" value="0" /></div>
          <div class="qa-row"><label>Installers</label>      <input name="day.${index}.installers"       type="number" min="0" step="1" value="0" /></div>
          <div class="qa-row"><label>PC Techs</label>        <input name="day.${index}.pctechs"          type="number" min="0" step="1" value="0" /></div>
          <div class="qa-row"><label>Supervisors</label>     <input name="day.${index}.supervisors"      type="number" min="0" step="1" value="0" /></div>
          <div class="qa-row"><label>Project Managers</label><input name="day.${index}.project_managers" type="number" min="0" step="1" value="0" /></div>
          <div class="qa-row"><label>Electricians</label>    <input name="day.${index}.electricians"     type="number" min="0" step="1" value="0" /></div>
          <div class="qa-row"><label>Equipment</label>       <input name="day.${index}.equipment"        type="text" value="${equipment ? escHtml(equipment) : ''}" placeholder="Optional" /></div>
          <div class="qa-row"><label>Weight</label>          <input name="day.${index}.weight"           type="number" min="0" step="0.01" value="${weight !== '' ? escHtml(weight) : ''}" placeholder="Optional" /></div>
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
      equipment: g('equipment'),
      weight: g('weight'),
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
      pctechs:0, supervisors:0, project_managers:0, electricians:0,
      equipment:'', weight:'', notes:''
    });
  });

  state.dialogs.quick = new ej.popups.Dialog({
    cssClass:'qa-dialog qi-dialog',
    header:`Add Job — ${dateYMD}`,
    isModal:true, showCloseIcon:true, width:'1040px', target:document.body,
    animationSettings:{effect:'Zoom'},
    content:host,
    buttons:[
      { buttonModel:{content:'Cancel'}, click:()=>state.dialogs.quick.hide() },
      { buttonModel:{content:'Save', isPrimary:true}, click:()=>saveQuickAdd() }
    ],
    open:()=>{
      daysWrap.innerHTML=''; dayCount=0;
      const topCtr=host.querySelector('select[name="job.contractor_id"]');
      if(topCtr && contractorId!=null) topCtr.value=String(contractorId);
      addDay({ date:dateYMD, start24, end24 });
      const footer=state.dialogs.quick.element.querySelector('.e-footer-content');
      footer.prepend(addAnotherBtn);
    },
    close:()=>{ try{ state.dialogs.quick.destroy(); state.dialogs.quick.element.remove(); }catch(_){}
      state.dialogs.quick=null; }
  });

  const dlgHost=document.createElement('div'); document.body.appendChild(dlgHost);
  state.dialogs.quick.appendTo(dlgHost); state.dialogs.quick.show();

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
      service_type: get('job.service_type').trim() || null,
      status: get('job.status') || 'needs_paperwork'
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
        equipment:(g('equipment')||'').trim() || null,
        weight:(()=>{ const v=(g('weight')||'').trim(); if(v==='') return null; const n=Number(v); return Number.isNaN(n)?null:n; })(),
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
      await loadDay(days[0].work_date);
      state.dialogs.quick.hide();
    }catch(e){ console.error(e); alert(e.message || 'Failed to save job.'); }
  }
}
