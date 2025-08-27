<?php
/**
 * /095/schedule-ng/admin/contractors.php
 * ----------------------------------
 * Contractors Admin (self-contained, no external CDNs).
 *
 * Features:
 *  - Add contractor (modal: name + color)
 *  - Inline edit (name + color)
 *  - Enable/Disable toggle
 *  - Drag-and-drop reordering (persists immediately)
 *  - Quick filter
 *
 * Depends on your existing endpoints:
 *   GET  /095/schedule-ng/api/contractors_list.php
 *   POST /095/schedule-ng/api/contractors_mutate.php
 *
 * Tip: If you still see the old look after deploying,
 *      hard-refresh the page (Ctrl/Cmd + Shift + R) to bust cache.
 */
include '/home/freeman/job_scheduler.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Contractors — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#f6f7fb;
      --card:#ffffff;
      --ink:#0f172a;
      --muted:#6b7280;
      --line:#e5e7eb;
      --primary:#0e4baa;
      --primary-ink:#ffffff;
      --success:#14b8a6;
      --warn:#f59e0b;
      --danger:#ef4444;
      --shadow: 0 8px 30px rgba(2,6,23,.07);
      --radius:14px;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background:var(--bg);
      color:var(--ink);
      font: 14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji","Segoe UI Emoji";
    }
    a{ color:var(--primary); text-decoration:none }
    .wrap{ max-width:1100px; margin:24px auto; padding:0 16px }
    .toolbar{
      display:flex; gap:12px; align-items:center; justify-content:space-between; margin-bottom:16px;
    }
    .title{
      display:flex; gap:12px; align-items:center;
    }
    .title .icon{
      width:36px; height:36px; border-radius:10px; background:var(--primary); color:#fff;
      display:grid; place-items:center; font-weight:700; box-shadow: var(--shadow);
    }
    .title h2{ margin:0; font-size:22px }
    .title .sub{ color:var(--muted); font-size:12px }

    .btn{
      display:inline-flex; align-items:center; gap:8px;
      padding:8px 12px; border-radius:10px; border:1px solid var(--line); background:#fff; color:var(--ink);
      cursor:pointer;
    }
    .btn.primary{ background:var(--primary); color:var(--primary-ink); border-color: transparent; }
    .btn.ghost{ background:transparent }
    .btn.sm{ padding:6px 10px; border-radius:8px; font-size:13px }
    .btn.warn{ border-color:var(--warn); color:var(--warn) }
    .btn.succ{ border-color:var(--success); color:var(--success) }

    .card{
      background:var(--card); border-radius:var(--radius); box-shadow: var(--shadow);
      padding:16px;
    }

    .controls{ display:flex; gap:12px; align-items:center; justify-content:space-between; margin-bottom:10px }
    .input{
      display:flex; align-items:center; gap:8px; border:1px solid var(--line); border-radius:10px; padding:6px 10px; background:#fff;
      min-width:280px;
    }
    .input input{ border:0; outline:0; font:inherit; width:100%; background:transparent }

    .table-wrap{ overflow:auto; border-radius:12px; border:1px solid var(--line) }
    table{ width:100%; border-collapse:collapse; background:#fff }
    thead th{
      position:sticky; top:0; z-index:1;
      background:#fafafa; border-bottom:1px solid var(--line); text-align:left; font-weight:600; padding:10px 12px;
    }
    tbody td{ border-top:1px solid var(--line); padding:10px 12px; vertical-align:middle }
    .order-col{ width:56px; text-align:center }
    .actions-col{ width:260px }

    .drag-handle{ cursor:grab; color:var(--muted); font-size:18px; user-select:none }
    tr.dragging{ opacity:.55 }

    .badge{ display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; }
    .badge.success{ background:#ecfdf5; color:#0f766e }
    .badge.muted{ background:#f1f5f9; color:#475569 }

    .color-dot{ width:14px; height:14px; border-radius:50%; border:1px solid var(--line); display:inline-block; vertical-align:middle; margin-right:6px }

    .inline{ display:flex; gap:8px; align-items:center }
    .inline input[type="text"]{ border:1px solid var(--line); border-radius:8px; padding:6px 8px; width:280px }
    .inline input[type="color"]{ border:1px solid var(--line); border-radius:8px; height:32px; width:42px; padding:3px; background:#fff }

    .empty{
      text-align:center; color:var(--muted); padding:28px 0;
    }

    /* Toast */
    .toast{
      position:fixed; right:16px; top:16px; background:#111827; color:#fff; padding:10px 14px; border-radius:10px;
      box-shadow: var(--shadow); opacity:0; transform: translateY(-8px); transition: .2s;
    }
    .toast.show{ opacity:1; transform: translateY(0) }

    /* Modal (no external libs) */
    .modal-backdrop{
      position:fixed; inset:0; background:rgba(15,23,42,.35); display:none; z-index:20;
    }
    .modal-backdrop.show{ display:block }
    .modal{
      position:fixed; inset:0; display:grid; place-items:center; z-index:21; pointer-events:none;
    }
    .modal .panel{
      pointer-events:auto; width:520px; max-width:calc(100vw - 24px);
      background:#fff; border-radius:16px; box-shadow: var(--shadow);
      transform: translateY(10px); opacity:0; transition:.2s; padding:16px;
    }
    .modal.show .panel{ transform:none; opacity:1 }
    .modal .hdr{ display:flex; justify-content:space-between; align-items:center; margin-bottom:8px }
    .modal .hdr h3{ margin:0; font-size:18px }
    .modal .body{ display:grid; gap:10px; margin:8px 0 }
    .field label{ display:block; font-size:12px; color:var(--muted) }
    .field input[type="text"]{ width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:10px }
    .footer{ display:flex; justify-content:flex-end; gap:10px; margin-top:10px }
  </style>
</head>
<body>
  <div class="wrap">
    <!-- Top toolbar -->
    <div class="toolbar">
      <div class="title">
        <div class="icon">C</div>
        <div>
          <h2>Contractors</h2>
          <div class="sub">Add, edit, enable/disable, and drag to reorder (top → bottom)</div>
        </div>
      </div>
      <div class="inline">
        <a class="btn ghost" href="/095/schedule-ng/">
          <!-- calendar icon -->
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M7 2v4M17 2v4M3 10h18M5 6h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          Back to Schedule
        </a>
        <button class="btn primary" id="openAdd">
          <!-- plus icon -->
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          Add Contractor
        </button>
      </div>
    </div>

    <!-- Card -->
    <div class="card">
      <div class="controls">
        <div class="input">
          <!-- search icon -->
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="#9aa2b1" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="#9aa2b1" stroke-width="2" stroke-linecap="round"/></svg>
          <input id="filter" type="search" placeholder="Filter by name…" />
        </div>
        <span class="badge muted">Drag rows to change order</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th class="order-col">Order</th>
              <th>Name</th>
              <th>Email(s)</th>
              <th>Status</th>
              <th>Color</th>
              <th class="actions-col">Actions</th>
            </tr>
          </thead>
          <tbody id="rows"></tbody>
        </table>
      </div>

      <div id="empty" class="empty" style="display:none">
        No contractors yet. Click <strong>Add Contractor</strong> to create your first one.
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div id="toast" class="toast">Saved.</div>

  <!-- Modal (Add Contractor) -->
  <div id="backdrop" class="modal-backdrop"></div>
  <div id="modal" class="modal">
    <div class="panel">
      <div class="hdr">
        <h3>Add Contractor</h3>
        <button id="closeAdd" class="btn">Close</button>
      </div>
      <div class="body">
        <div class="field">
          <label>Name</label>
          <input id="addName" type="text" placeholder="e.g., Eddie" />
        </div>
        <div class="field">
          <label>Email(s)</label>
          <input id="addEmail" type="text" placeholder="e.g., user@example.com" />
        </div>
        <div class="field">
          <label>Color (optional)</label>
          <div class="inline">
            <input id="addColorPick" type="color" value="#0E4BAA" />
            <input id="addColorHex" type="text" placeholder="#0E4BAA" value="#0E4BAA" />
          </div>
        </div>
      </div>
      <div class="footer">
        <button class="btn" id="cancelAdd">Cancel</button>
        <button class="btn primary" id="saveAdd">Save</button>
      </div>
    </div>
  </div>

  <script>
    // -------------------- Config --------------------
    const API = {
      list:   '/095/schedule-ng/api/contractors_list.php',
      mutate: '/095/schedule-ng/api/contractors_mutate.php'
    };

    // -------------------- DOM refs --------------------
    const rowsEl   = document.getElementById('rows');
    const filterEl = document.getElementById('filter');
    const emptyEl  = document.getElementById('empty');
    const toastEl  = document.getElementById('toast');

    const openAdd  = document.getElementById('openAdd');
    const closeAdd = document.getElementById('closeAdd');
    const cancelAdd= document.getElementById('cancelAdd');
    const saveAdd  = document.getElementById('saveAdd');
    const mBack    = document.getElementById('backdrop');
    const mRoot    = document.getElementById('modal');
    const addName  = document.getElementById('addName');
    const addEmail = document.getElementById('addEmail');
    const addColorPick = document.getElementById('addColorPick');
    const addColorHex  = document.getElementById('addColorHex');

    let allItems = [];

    // -------------------- Utils --------------------
    const esc = s => (s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
    const showToast = (msg) => {
      toastEl.textContent = msg;
      toastEl.classList.add('show');
      setTimeout(()=>toastEl.classList.remove('show'), 1600);
    };
    const showModal = (on) => {
      mBack.classList.toggle('show', on);
      mRoot.classList.toggle('show', on);
      if (on) { addName.focus(); }
    };

    // -------------------- API --------------------
    async function load(){
      const r = await fetch(API.list);
      const j = await r.json();
      allItems = j.contractors || [];
      render(allItems);
    }
    async function mutate(payload){
      const r = await fetch(API.mutate, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || 'Operation failed');
      return j;
    }

    // -------------------- Rendering --------------------
    function render(items){
      rowsEl.innerHTML = '';
      if (!items.length) {
        emptyEl.style.display = '';
        return;
      }
      emptyEl.style.display = 'none';
      for (const c of items) rowsEl.appendChild(rowFor(c));
      wireDnD();
    }

    function rowFor(c){
      const tr = document.createElement('tr');
      tr.dataset.id = c.id;

      tr.innerHTML = `
        <td class="order-col"><span class="drag-handle" title="Drag to reorder">☰</span></td>
        <td>
          <div class="name-view">${esc(c.name)}</div>
          <div class="name-edit inline" style="display:none">
            <input class="edit-name" type="text" value="${esc(c.name)}" />
          </div>
          </td>
          <td>
            <div class="email-view">${esc(c.email_notify || '')}</div>
            <div class="email-edit" style="display:none">
              <input class="edit-email" type="text" value="${esc(c.email_notify || '')}" />
            </div>
          </td>
          <td>
            <span class="badge ${c.active ? 'success' : 'muted'}">${c.active ? 'Active' : 'Disabled'}</span>
          </td>
          <td>
            ${(c.color_hex ? `<span class="color-dot" style="background:${esc(c.color_hex)}"></span>` : `<span class="color-dot" style="background:#fff"></span>`)}
            <span class="color-view">${esc(c.color_hex || '')}</span>
            <div class="color-edit inline" style="display:none; margin-top:6px">
              <input class="edit-color-pick" type="color" value="${esc(c.color_hex || '#0E4BAA')}" />
              <input class="edit-color-hex" type="text" value="${esc(c.color_hex || '')}" placeholder="#0E4BAA" />
          </div>
        </td>
        <td class="actions-col">
          <div class="inline">
            <button class="btn sm edit-btn">Edit</button>
            <button class="btn sm succ save-btn" style="display:none">Save</button>
            <button class="btn sm" style="display:none"  data-role="cancel">Cancel</button>
            <button class="btn sm ${c.active ? 'warn' : 'succ'} toggle-btn">${c.active ? 'Disable' : 'Enable'}</button>
          </div>
        </td>
      `;

      // Wires
      const editBtn = tr.querySelector('.edit-btn');
      const saveBtn = tr.querySelector('.save-btn');
      const cancelBtn = tr.querySelector('[data-role="cancel"]');
      const toggleBtn = tr.querySelector('.toggle-btn');

      const nameView = tr.querySelector('.name-view');
      const nameEdit = tr.querySelector('.name-edit');
      const nameInp  = tr.querySelector('.edit-name');

      const emailView = tr.querySelector('.email-view');
      const emailEdit = tr.querySelector('.email-edit');
      const emailInp  = tr.querySelector('.edit-email');

      const colorView = tr.querySelector('.color-view');
      const colorEdit = tr.querySelector('.color-edit');
      const colorPick = tr.querySelector('.edit-color-pick');
      const colorHex  = tr.querySelector('.edit-color-hex');

      editBtn.addEventListener('click', () => {
        editBtn.style.display = 'none';
        saveBtn.style.display = '';
        cancelBtn.style.display = '';
        nameView.style.display = 'none';
        nameEdit.style.display = '';
        emailView.style.display = 'none';
        emailEdit.style.display = '';
        colorView.style.display = 'none';
        colorEdit.style.display = '';
        nameInp.focus();
      });

      cancelBtn.addEventListener('click', () => {
        editBtn.style.display = '';
        saveBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
        nameView.style.display = '';
        nameEdit.style.display = 'none';
        emailView.style.display = '';
        emailEdit.style.display = 'none';
        colorView.style.display = '';
        colorEdit.style.display = 'none';
      });

      colorPick.addEventListener('input', () => { colorHex.value = colorPick.value; });

      saveBtn.addEventListener('click', async () => {
        const id = Number(tr.dataset.id);
        const name = nameInp.value.trim();
        const email_notify = emailInp.value.trim();
        const color_hex = colorHex.value.trim();
        if (!name) return;
        try {
          await mutate({ action:'update', id, name, color_hex, email_notify });
          showToast('Contractor updated');
          await load();
        } catch(e){ showToast(e.message); }
      });

      toggleBtn.addEventListener('click', async () => {
        const id = Number(tr.dataset.id);
        try {
          await mutate({ action:'toggle', id });
          showToast('Status updated');
          await load();
        } catch(e){ showToast(e.message); }
      });

      if (!c.active) tr.style.opacity = .55;

      return tr;
    }

    // -------------------- Drag & Drop --------------------
    function wireDnD(){
      let dragging = null;
      rowsEl.querySelectorAll('tr').forEach(tr => {
        tr.draggable = true;

        tr.addEventListener('dragstart', (e) => {
          dragging = tr;
          tr.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', tr.dataset.id); // Firefox requirement
        });

        tr.addEventListener('dragend', async () => {
          if (dragging) dragging.classList.remove('dragging');
          dragging = null;
          // Persist order after drop
          const ids = [...rowsEl.querySelectorAll('tr')].map(x => Number(x.dataset.id));
          if (!ids.length) return;
          try {
            await mutate({ action: 'reorder', ids });
            showToast('Order saved');
          } catch(e){ showToast(e.message); }
        });

        tr.addEventListener('dragover', (e) => {
          e.preventDefault();
          if (!dragging || dragging === tr) return;
          const rect = tr.getBoundingClientRect();
          const before = (e.clientY - rect.top) < rect.height/2;
          rowsEl.insertBefore(dragging, before ? tr : tr.nextSibling);
        });
      });
    }

    // -------------------- Filter --------------------
    filterEl.addEventListener('input', () => {
      const q = filterEl.value.toLowerCase().trim();
      const filtered = allItems.filter(c => (c.name || '').toLowerCase().includes(q));
      render(filtered);
    });

    // -------------------- Modal wiring --------------------
    openAdd.addEventListener('click', () => showModal(true));
    closeAdd.addEventListener('click', () => showModal(false));
    cancelAdd.addEventListener('click', () => showModal(false));
    mBack.addEventListener('click', () => showModal(false));
    addColorPick.addEventListener('input', () => { addColorHex.value = addColorPick.value; });

    saveAdd.addEventListener('click', async () => {
      const name = addName.value.trim();
      const email_notify = addEmail.value.trim();
      const color_hex = addColorHex.value.trim();
      if (!name) { addName.focus(); return; }
      try {
        await mutate({ action:'add', name, color_hex, email_notify });
        addName.value = '';
        addEmail.value = '';
        addColorHex.value = '#0E4BAA';
        addColorPick.value = '#0E4BAA';
        showModal(false);
        showToast('Contractor added');
        await load();
      } catch(e){ showToast(e.message); }
    });

    // -------------------- Boot --------------------
    load();
  </script>
</body>
</html>
