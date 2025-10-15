(function(){
  if (!window.IS_ADMIN) return;
  const API_URL = './api/import_schedule.php';
  let dlg = null;

  function openDialog(){
    const form = document.createElement('form');
    form.id = 'importForm';
    form.innerHTML = '<input type="file" name="xlsx" accept=".xlsx" required />';

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
    const file = document.querySelector('#importForm input[type="file"]').files[0];
    if (!file) { alert('Please select an XLSX file.'); return; }
    const fd = new FormData();
    fd.append('xlsx', file, file.name);
    try {
      const res = await fetch(API_URL, { method: 'POST', body: fd });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Import failed');
      window.location.reload();
    } catch (e) {
      alert(e.message);
    }
  }

  document.getElementById('importBtn')?.addEventListener('click', openDialog);
})();