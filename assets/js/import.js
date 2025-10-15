(function(){
  if (!window.IS_ADMIN) return;
  const API_URL = './api/import_schedule.php';
  let dlg = null;

  function openDialog(){
    const form = document.createElement('form');
    form.id = 'importForm';

    const note = document.createElement('p');
    note.textContent = 'Upload an .xlsx spreadsheet that matches the HHG import layout.';
    note.style.marginBottom = '12px';

    const input = document.createElement('input');
    input.type = 'file';
    input.name = 'xlsx';
    input.accept = '.xlsx';
    input.required = true;

    form.appendChild(note);
    form.appendChild(input);

    dlg = new ej.popups.Dialog({
      header: 'Import Schedule',
      content: form,
      isModal: true,
      showCloseIcon: true,
      width: '400px',
      target: document.body,
      position: { X: 'center', Y: 'center' },
      buttons: [
        { buttonModel: { content: 'Cancel' }, click: () => dlg.hide() },
        { buttonModel: { content: 'Import', isPrimary: true }, click: submitImport }
      ],
      overlayClick: () => dlg.hide(),
      close: () => { try { dlg.destroy(); } catch(_){} }
    });
    const host = document.createElement('div');
    document.body.appendChild(host);
    dlg.appendTo(host);
    dlg.show();
  }

  async function submitImport(){
    const fileInput = document.querySelector('#importForm input[type="file"]');
    const file = fileInput?.files?.[0];
    if (!file) { alert('Please select an .xlsx file.'); return; }

    if (!/\.xlsx$/i.test(file.name)) {
      alert('The importer only supports .xlsx spreadsheets.');
      fileInput.value = '';
      return;
    }
    const fd = new FormData();
    fd.append('xlsx', file, file.name);
    try {
      const res = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Import failed');
      window.location.reload();
    } catch (e) {
      alert(e.message);
    }
  }

  document.getElementById('importBtn')?.addEventListener('click', openDialog);
})();