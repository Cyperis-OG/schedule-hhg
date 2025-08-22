import { state } from './state.js';

export function applyDndState(enabled){
  if (!state.sch) return;
  state.sch.allowDragAndDrop = !!enabled;
  state.sch.allowResizing    = !!enabled;
  state.sch.dataBind();
}

export function installDndToggle(){
  const cb = document.getElementById('dragToggle');
  if (!cb) return null;
  cb.addEventListener('change', () => applyDndState(cb.checked));
  return cb;
}
