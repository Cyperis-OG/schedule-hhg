import { state, API } from './state.js';

export function closeInfoDlg(){
  try { state.dialogs.info?.hide(); state.dialogs.info?.destroy(); state.dialogs.info?.element?.remove(); } catch(_){}
  if (state.dialogs.infoOutsideAbort) { state.dialogs.infoOutsideAbort.abort(); state.dialogs.infoOutsideAbort=null; }
  state.dialogs.info = null;
}

export async function openInfoForEvent(jobDayUid){
  try{
    // only one at a time
    closeInfoDlg();

    const r=await fetch(`${API.popup}?job_day_uid=${encodeURIComponent(jobDayUid)}`);
    const j=await r.json();
    const html=j?.html || '<div style="padding:16px">Error loading job details.</div>';

    state.dialogs.info = new ej.popups.Dialog({
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

    const host=document.createElement('div'); document.body.appendChild(host);
    state.dialogs.info.appendTo(host); state.dialogs.info.show();

    // Outside click to close (robust)
    if (state.dialogs.infoOutsideAbort) state.dialogs.infoOutsideAbort.abort();
    state.dialogs.infoOutsideAbort = new AbortController();
    const sig = state.dialogs.infoOutsideAbort.signal;

    document.addEventListener('pointerdown', (ev)=>{
      const el = state.dialogs.info?.element;
      if (!el) return;
      if (!el.contains(ev.target)) closeInfoDlg();
    }, { capture:true, signal: sig });

    // Also honor overlay clicks
    state.dialogs.info.overlayClick = () => closeInfoDlg();

  }catch(e){ console.error(e); }
}
