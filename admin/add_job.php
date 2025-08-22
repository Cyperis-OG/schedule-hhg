<?php
/**
 * /home/freeman/public_html/schedule-ng/admin/add_job.php
 * ------------------------------------------------------
 * Template-driven "Add Job" page with prefill support for modal launch.
 *
 * Query Params for prefill (optional):
 *   ?date=YYYY-MM-DD&start=HH:MM&end=HH:MM&contractor_id=123&embed=1
 *
 * Depends on:
 *   /schedule-ng/config/job_form_template.json
 *   /schedule-ng/api/contractors_list.php
 *   /schedule-ng/api/customers_search.php
 *   /schedule-ng/api/job_save.php
 */
include '/home/freeman/job_scheduler.php';

// Load the template JSON
$templatePath = __DIR__ . '/../config/job_form_template.json';
$templateJson = file_exists($templatePath) ? file_get_contents($templatePath) : '{}';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>Add Job — Schedule NG</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    :root{
      --bg:#f6f7fb; --card:#ffffff; --ink:#0f172a; --muted:#6b7280; --line:#e5e7eb;
      --primary:#0e4baa; --primary-ink:#ffffff; --shadow:0 8px 30px rgba(2,6,23,.07); --radius:14px;
    }
    *{ box-sizing:border-box }
    html, body{ height:100% }
    body{ margin:0; background:var(--bg); color:var(--ink); font:14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial }
    a{ color:var(--primary); text-decoration:none }
    .wrap{ max-width:1100px; margin:24px auto; padding:0 16px }
    .toolbar{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px }
    .title{ display:flex; gap:12px; align-items:center }
    .title .icon{ width:36px; height:36px; border-radius:10px; background:var(--primary); color:#fff; display:grid; place-items:center; font-weight:700; box-shadow:var(--shadow) }
    .title h2{ margin:0; font-size:22px }
    .title .sub{ color:var(--muted); font-size:12px }
    .btn{ display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:10px; border:1px solid var(--line); background:#fff; color:var(--ink); cursor:pointer }
    .btn.primary{ background:var(--primary); color:var(--primary-ink); border-color:transparent }
    .btn.ghost{ background:transparent }
    .btn.sm{ padding:6px 10px; border-radius:8px; font-size:13px }
    .card{ background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); padding:16px }
    .grid{ display:grid; gap:12px }
    .cols-2{ grid-template-columns: repeat(2, minmax(0,1fr)) }
    .cols-3{ grid-template-columns: repeat(3, minmax(0,1fr)) }
    @media (max-width:900px){ .cols-2, .cols-3 { grid-template-columns: 1fr } }
    .section{ border:1px solid var(--line); border-radius:12px; padding:12px }
    .sec-h{ display:flex; align-items:center; justify-content:space-between; margin-bottom:10px }
    .sec-h h3{ margin:0; font-size:16px }
    .field{ display:grid; gap:6px }
    .field label{ font-size:12px; color:var(--muted) }
    .field input[type="text"], .field input[type="date"], .field input[type="time"],
    .field input[type="number"], .field select, .field textarea{
      width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:10px; background:#fff; font:inherit
    }
    .field textarea{ min-height:72px; resize:vertical }
    .help{ font-size:11px; color:var(--muted) }
    .pill{ border:1px dashed var(--line); border-radius:999px; padding:6px 10px; color:var(--muted) }
    .day-wrap{ display:grid; gap:12px }
    .day-card{ border:1px solid var(--line); border-radius:12px; padding:12px; background:#fff }
    .day-h{ display:flex; align-items:center; justify-content:space-between; margin-bottom:8px }
    .day-h .tag{ font-weight:600 }
    .danger{ color:#b91c1c }
    .toast{ position:fixed; right:16px; top:16px; background:#111827; color:#fff; padding:10px 14px; border-radius:10px; box-shadow:var(--shadow); opacity:0; transform:translateY(-8px); transition:.2s; z-index:50 }
    .toast.show{ opacity:1; transform:translateY(0) }
    .ac{ position:relative }
    .ac-list{ position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--line); border-radius:10px; margin-top:4px; box-shadow:var(--shadow); max-height:240px; overflow:auto; z-index:25; display:none }
    .ac-item{ padding:8px 10px; cursor:pointer }
    .ac-item:hover{ background:#f1f5f9 }

    /* Embed mode tweaks (when opened inside the modal) */
    .embed .wrap{ max-width:100%; margin:0; padding:12px }
    .embed .toolbar{ margin-bottom:10px }
    .embed .toolbar .back-btn{ display:none } /* hide "Back to Schedule" in modal */
  </style>
</head>
<body>
  <div class="wrap">
    <div class="toolbar">
      <div class="title">
        <div class="icon">J+</div>
        <div>
          <h2>Add Job</h2>
          <div class="sub">Template-driven form. Add one job with one or multiple days.</div>
        </div>
      </div>
      <div class="inline">
        <a class="btn ghost back-btn" href="/schedule-ng/">
          <!-- calendar icon -->
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M7 2v4M17 2v4M3 10h18M5 6h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          Back to Schedule
        </a>
        <button id="saveBtn" class="btn primary">Save Job</button>
      </div>
    </div>

    <div class="card grid">
      <!-- JOB SECTIONS -->
      <div id="jobSections" class="grid"></div>

      <!-- PER-DAY SECTIONS -->
      <div class="section">
        <div class="sec-h">
          <h3>Days</h3>
          <div class="inline">
            <span class="pill">You can add up to <span id="maxDaysLabel">5</span> days</span>
            <button id="addDayBtn" class="btn sm">Add additional day</button>
          </div>
        </div>
        <div id="days" class="day-wrap"></div>
      </div>
    </div>
  </div>

  <div id="toast" class="toast">Saved.</div>

  <script>
    // ---------------- Template / Config ----------------
    const TEMPLATE = <?php echo $templateJson ?: '{}'; ?>;
    document.getElementById('maxDaysLabel').textContent = TEMPLATE.maxDays || 5;

    const API = {
      contractors: '/schedule-ng/api/contractors_list.php',
      customers:   '/schedule-ng/api/customers_search.php',
      save:        '/schedule-ng/api/job_save.php'
    };

    // ---------------- Parse Prefill from URL ----------------
    const params = new URLSearchParams(location.search);
    const PREFILL = {
      date:          params.get('date') || '',
      start:         params.get('start') || '',     // HH:MM
      end:           params.get('end') || '',       // HH:MM
      contractor_id: params.get('contractor_id') ? Number(params.get('contractor_id')) : null,
      embed:         params.get('embed') === '1'
    };
    if (PREFILL.embed) document.body.classList.add('embed');

    // ---------------- State ----------------
    let contractors = [];   // [{id, name, active, display_order, color_hex}]
    let dayCount = 0;

    // ---------------- Utilities ----------------
    const esc = s => (s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
    const toISODate = (d) => d.toISOString().slice(0,10);
    const nowDate = () => toISODate(new Date());
    const showToast = (msg) => { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'), 1600); };

    async function fetchContractors(){
      const r = await fetch(API.contractors);
      const j = await r.json();
      contractors = (j.contractors || []).filter(c => c.active === 1);
    }

    // ---------------- Render Job-Level Sections ----------------
    function renderJobSections(){
      const host = document.getElementById('jobSections');
      host.innerHTML = '';

      const jobSections = TEMPLATE.sections.filter(s => s.level === 'job' || s.level === 'job_meta');

      for (const sec of jobSections) {
        const card = document.createElement('div');
        card.className = 'section';
        card.innerHTML = `
          <div class="sec-h"><h3>${esc(sec.label || 'Section')}</h3></div>
          <div class="grid ${sec.level === 'job' ? 'cols-2' : 'cols-2'}" data-sec="${esc(sec.id)}"></div>
        `;
        host.appendChild(card);

        const grid = card.querySelector('.grid');
        for (const f of (sec.fields || [])) {
          grid.appendChild(renderField(f, `job.${f.key}`, sec.level));
        }
      }
    }

    // Render individual field
    function renderField(f, namePrefix, level){
      const wrap = document.createElement('div');
      wrap.className = 'field';

      // label
      const lab = document.createElement('label');
      lab.textContent = f.label || f.key;
      wrap.appendChild(lab);

      // input
      let input;
      const requiredAttr = f.required ? 'required' : '';
      const def = f.default ?? '';

      if (f.type === 'textarea') {
        input = document.createElement('textarea');
        input.name = namePrefix;
        input.placeholder = f.placeholder || '';
        input.value = def;
      }
      else if (f.type === 'select') {
        input = document.createElement('select');
        input.name = namePrefix;
        (f.options || []).forEach(([val, txt]) => {
          const opt = document.createElement('option');
          opt.value = val; opt.textContent = txt;
          if (String(def) === String(val)) opt.selected = true;
          input.appendChild(opt);
        });
      }
      else if (f.type === 'contractor') {
        input = document.createElement('select');
        input.name = namePrefix;
        input.dataset.needsContractors = '1'; // fill later
      }
      else if (f.type === 'customer') {
        const ac = document.createElement('div'); ac.className = 'ac';
        input = document.createElement('input');
        input.type='text'; input.name=namePrefix; input.autocomplete='off';
        input.placeholder = f.placeholder || 'Start typing a customer…';
        const list=document.createElement('div'); list.className='ac-list';
        ac.appendChild(input); ac.appendChild(list); wrap.appendChild(ac);

        let tId=null;
        input.addEventListener('input', () => {
          const q=input.value.trim();
          if (tId) clearTimeout(tId);
          if (q.length<2){ list.style.display='none'; return; }
          tId=setTimeout(async ()=>{
            const r=await fetch(`${API.customers}?q=${encodeURIComponent(q)}`);
            const j=await r.json(); list.innerHTML='';
            (j.results||[]).forEach(item=>{
              const it=document.createElement('div'); it.className='ac-item';
              it.innerHTML=`<div><strong>${esc(item.name)}</strong></div>
                             <div class="help">Prefers: ${esc(item.preferred_contractor_name || '—')} · Salesman: ${esc(item.default_salesman || '—')}</div>`;
              it.addEventListener('click', ()=>{
                input.value=item.name; list.style.display='none';
                const loc=document.querySelector('input[name="day.0.location"]');
                if (loc && item.default_location) loc.value=item.default_location;
                const sal=document.querySelector('input[name="job.salesman"]');
                if (sal && item.default_salesman) sal.value=item.default_salesman;
                const jobn=document.querySelector('input[name="job.job_number"]');
                if (jobn && item.last_job_number) jobn.value=item.last_job_number;
                const sel=document.querySelector('select[name="day.0.contractor_id"]');
                if (sel && item.preferred_contractor_id) sel.value=String(item.preferred_contractor_id);
              });
              list.appendChild(it);
            });
            list.style.display = (list.children.length ? 'block' : 'none');
          },220);
        });

        if (f.help) { const h=document.createElement('div'); h.className='help'; h.textContent=f.help; wrap.appendChild(h); }
        return wrap;
      }
      else {
        input = document.createElement('input');
        input.type = f.type || 'text';
        input.name = namePrefix;
        input.placeholder = f.placeholder || '';
        if (f.type === 'date' && !def) input.value = new Date().toISOString().slice(0,10);
        else if (typeof def === 'string' || typeof def === 'number') input.value = def;
      }

      if (requiredAttr) input.required = true;
      wrap.appendChild(input);
      if (f.help) { const h=document.createElement('div'); h.className='help'; h.textContent=f.help; wrap.appendChild(h); }
      return wrap;
    }

    // ---------------- Days UI ----------------
    let dayCount = 0;

    function addDay(initial = {}){
      const max = TEMPLATE.maxDays || 5;
      if (dayCount >= max) { showToast(`You can add at most ${max} days.`); return; }

      const idx = dayCount;
      const dayCard = document.createElement('div');
      dayCard.className = 'day-card';
      dayCard.dataset.index = String(idx);
      dayCard.innerHTML = `
        <div class="day-h">
          <div class="tag">Day ${idx + 1}</div>
          <div class="inline">
            <button class="btn sm" data-role="duplicate">Duplicate</button>
            <button class="btn sm danger" data-role="remove">Remove</button>
          </div>
        </div>
        <div class="grid cols-3" data-kind="core"></div>
        <div class="grid cols-3" data-kind="meta"></div>
      `;
      document.getElementById('days').appendChild(dayCard);

      const dayCore = TEMPLATE.sections.find(s => s.id === 'day_core');
      const dayMeta = TEMPLATE.sections.find(s => s.id === 'day_meta');
      const coreWrap = dayCard.querySelector('[data-kind="core"]');
      const metaWrap = dayCard.querySelector('[data-kind="meta"]');

      (dayCore?.fields || []).forEach(f => coreWrap.appendChild(renderField(f, `day.${idx}.${f.key}`, 'day')));
      (dayMeta?.fields || []).forEach(f => metaWrap.appendChild(renderField(f, `day.${idx}.meta.${f.key}`, 'day_meta')));

      // Write initial values if provided
      for (const [k,v] of Object.entries(initial)) {
        if (k === 'meta' && v && typeof v === 'object') continue; // handle meta below
        const target = dayCard.querySelector(`[name="day.${idx}.${k}"]`);
        if (target) target.value = v;
      }
      if (initial.meta) {
        for (const [k,v] of Object.entries(initial.meta)) {
          const target = dayCard.querySelector(`[name="day.${idx}.meta.${k}"]`);
          if (target) target.value = v;
        }
      }

      // Wire actions
      dayCard.querySelector('[data-role="remove"]').addEventListener('click', () => { dayCard.remove(); renumberDays(); });
      dayCard.querySelector('[data-role="duplicate"]').addEventListener('click', () => {
        const vals = collectDay(idx);
        if (vals.work_date) { const d = new Date(vals.work_date + 'T00:00:00'); d.setDate(d.getDate()+1); vals.work_date = toISODate(d); }
        vals.meta = vals.meta || {}; vals.meta.title_suffix = `(Day ${document.querySelectorAll('.day-card').length + 1})`;
        addDay(vals);
      });

      dayCount++;

      // Fill contractor selects after we have contractor list
      dayCard.querySelectorAll('select[data-needsContractors="1"]').forEach(sel => {
        contractors.forEach(c => {
          const opt = document.createElement('option');
          opt.value = String(c.id); opt.textContent = c.name;
          sel.appendChild(opt);
        });
        if (PREFILL.contractor_id && idx === 0) sel.value = String(PREFILL.contractor_id);
      });
    }

    function renumberDays(){
      const cards = [...document.querySelectorAll('.day-card')];
      dayCount = 0;
      cards.forEach((card, i) => {
        card.dataset.index = String(i);
        card.querySelector('.tag').textContent = `Day ${i+1}`;
        card.querySelectorAll('[name^="day."]').forEach(inp => {
          inp.name = inp.name.replace(/day\.\d+\./, `day.${i}.`);
        });
        dayCount++;
      });
    }

    // ---------------- Collect & Save ----------------
    function collectJob(){
      const job = {};
      const meta = {};
      (TEMPLATE.sections.find(s => s.id === 'job_core')?.fields || []).forEach(f => {
        const el = document.querySelector(`[name="job.${f.key}"]`);
        if (el) job[f.key] = el.value.trim();
      });
      (TEMPLATE.sections.find(s => s.id === 'job_meta')?.fields || []).forEach(f => {
        const el = document.querySelector(`[name="job.${f.key}"]`) || document.querySelector(`[name="job_meta.${f.key}"]`);
        const val = el ? el.value.trim() : '';
        if (val !== '') meta[f.key] = val;
      });
      if (!job.title) {
        const cn = document.querySelector('[name="job.customer_name"]')?.value?.trim();
        if (cn) job.title = cn;
      }
      job.meta = meta;
      return job;
    }

    function collectDay(idx){
      const day = {};
      const base = (k) => document.querySelector(`[name="day.${idx}.${k}"]`);
      const getNum = (k) => Number(base(k)?.value || 0);

      day.work_date   = base('work_date')?.value || '';
      day.start_time  = (base('start_time')?.value || '') + ':00';
      day.end_time    = (base('end_time')?.value || '') + ':00';
      day.location    = base('location')?.value || '';
      day.contractor_id = Number(base('contractor_id')?.value || 0) || null;

      day.tractors    = getNum('tractors');
      day.bobtails    = getNum('bobtails');
      day.movers      = getNum('movers');
      day.drivers     = getNum('drivers');
      day.installers  = getNum('installers');
      day.supervisors = getNum('supervisors');
      day.pctechs     = getNum('pctechs');
      day.day_notes   = base('day_notes')?.value || '';
      day.status      = document.querySelector('[name="job.status"]')?.value || 'scheduled';

      const meta = {};
      (TEMPLATE.sections.find(s => s.id === 'day_meta')?.fields || []).forEach(f => {
        const el = document.querySelector(`[name="day.${idx}.meta.${f.key}"]`);
        const v = el ? el.value.trim() : '';
        if (v !== '') meta[f.key] = v;
      });
      if (Object.keys(meta).length) day.meta = meta;
      return day;
    }

    function collectAll(){
      const job = collectJob();
      const days = [...document.querySelectorAll('.day-card')].map((_, i) => collectDay(i));
      return { job, days };
    }

    async function save(){
      const payload = collectAll();
      if (!payload.job.title) { showToast('Please enter a title.'); return; }
      if (!payload.days.length) { showToast('Add at least one day.'); return; }
      if (!payload.days[0].work_date) { showToast('Day 1 needs a date.'); return; }

      const r = await fetch(API.save, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
      const j = await r.json();
      if (!j.ok) { showToast(j.error || 'Save failed'); return; }

      showToast('Job saved!');

      // Notify parent (modal on index.php) so it can reload the day & close itself
      try {
        const d = payload.days[0].work_date;
        window.parent?.postMessage({ type: 'jobSaved', date: d }, '*');
      } catch (e) { /* no-op */ }

      // Also support standalone use: redirect back to schedule
      const d = payload.days[0].work_date;
      setTimeout(() => { window.location.href = `/schedule-ng/?date=${encodeURIComponent(d)}`; }, 650);
    }

    // ---------------- Boot ----------------
    (async function boot(){
      await fetchContractors();
      renderJobSections();

      // Add Day 1 with either prefill (from params) or defaults
      const initialDay = {
        work_date: PREFILL.date || new Date().toISOString().slice(0,10),
        start_time: PREFILL.start || '08:00',
        end_time: PREFILL.end || '12:00'
      };
      addDay(initialDay);

      // After contractors are loaded, pre-select contractor for Day 1 if provided
      if (PREFILL.contractor_id != null) {
        const sel = document.querySelector('select[name="day.0.contractor_id"]');
        if (sel) sel.value = String(PREFILL.contractor_id);
      }

      // Fill contractor selects that were marked
      document.querySelectorAll('select[data-needsContractors="1"]').forEach(sel => {
        // (already filled inside addDay for day selects)
        if (!sel.options.length) {
          contractors.forEach(c => {
            const opt = document.createElement('option');
            opt.value = String(c.id); opt.textContent = c.name;
            sel.appendChild(opt);
          });
        }
      });

      document.getElementById('addDayBtn').addEventListener('click', () => {
        const idx = document.querySelectorAll('.day-card').length - 1;
        const last = collectDay(idx);
        if (last.work_date) { const d = new Date(last.work_date + 'T00:00:00'); d.setDate(d.getDate()+1); last.work_date = toISODate(d); }
        last.meta = last.meta || {}; last.meta.title_suffix = `(Day ${idx + 2})`;
        addDay(last);
      });

      document.getElementById('saveBtn').addEventListener('click', save);
    })();
  </script>
  
  <script>
  // === End-time autofill: Start + 4h, capped at 23:59 (same day) =================
  function addHoursClamp(hhmm, hours) {
    if (!hhmm) return '';
    const [h, m] = hhmm.split(':').map(Number);
    let eh = h + hours, em = m;
    if (eh > 23 || (eh === 23 && em > 59)) { eh = 23; em = 59; }
    return String(eh).padStart(2,'0') + ':' + String(em).padStart(2,'0');
  }

  // Wire for every day card: if user hasn't typed an End yet, keep it auto-updating
  function wireDayAutoEnd(dayIndex) {
    const start = document.querySelector(`[name="day.${dayIndex}.start_time"]`);
    const end   = document.querySelector(`[name="day.${dayIndex}.end_time"]`);
    if (!start || !end) return;

    let endTouched = false;
    end.addEventListener('input', () => { endTouched = true; });

    const setDefault = () => { if (!endTouched) end.value = addHoursClamp(start.value || '08:00', 4); };

    // initial default
    setDefault();
    // update when start changes (unless user edited end)
    start.addEventListener('input', setDefault);
  }

  // Call this after rendering Day 0, and after you add any new day dynamically
  document.addEventListener('DOMContentLoaded', () => {
    wireDayAutoEnd(0);
  });

  // If your "Add additional day" code creates new cards, call wireDayAutoEnd(newIndex) right after.
  </script>

</body>
</html>
