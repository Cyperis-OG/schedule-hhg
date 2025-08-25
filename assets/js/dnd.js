// /095/schedule-ng/assets/js/dnd.js
// Robust drag/resize ON/OFF toggle for Syncfusion Schedule.
// Blocks the drag bootstrap at the earliest event so no halo & no crash.

(() => {
  if (!window.sch) {
    console.warn('dnd.js: window.sch not ready. Load after core.js.');
    return;
  }

  const checkbox = document.getElementById('dragToggle');
  const isOn = () => (checkbox ? !!checkbox.checked : true);

  function applyDndState(on = isOn()) {
    // Flip official capabilities
    window.sch.setProperties({ allowDragAndDrop: on, allowResizing: on }, true);
    window.sch.dataBind();
    // Optional visual cue
    window.sch.element.classList.toggle('dnd-off', !on);
    // Reset any in-progress block state
    blocking.maybe = false;
  }

  // --- EARLY BLOCKERS (capture phase) ---
  // We stop the drag/resize from ever starting when OFF,
  // but we DO NOT call preventDefault on mousedown/touchstart,
  // so a simple click still bubbles and your eventClick works.

  const DRAGGABLE_SELECTOR = '.e-appointment, .e-event-resize';

  const blocking = { maybe: false };

  const isDragHandle = (target) =>
    !!(target && target.closest && target.closest(DRAGGABLE_SELECTOR));

  // Start: mark a potential drag when pointer goes down on a draggable
  const onStart = (e) => {
    if (isOn()) return;
    if (!isDragHandle(e.target)) return;
    // Stop Syncfusion’s mousedown/touchstart from ever seeing this.
    e.stopImmediatePropagation();
    e.stopPropagation();
    // Do NOT preventDefault — we want click to still work later.
    blocking.maybe = true;
  };

  // Move: while OFF and we’re in a potential drag, block moves so no drag can begin
  const onMove = (e) => {
    if (!isOn() && blocking.maybe) {
      e.stopImmediatePropagation();
      e.stopPropagation();
      // No preventDefault here either; we just don’t want their handlers.
    }
  };

  // End: clear the flag
  const onEnd = () => { blocking.maybe = false; };

  // Fallback: some browsers fire HTML5 dragstart — block it hard when OFF
  const onHtml5DragStart = (e) => {
    if (!isOn() && isDragHandle(e.target)) {
      e.preventDefault();
      e.stopImmediatePropagation();
      e.stopPropagation();
    }
  };

  // Attach at CAPTURE so we beat Syncfusion’s listeners
  const root = window.sch.element;
  root.addEventListener('mousedown',   onStart, true);
  root.addEventListener('pointerdown', onStart, true);
  root.addEventListener('touchstart',  onStart, true);

  document.addEventListener('mousemove',    onMove, true);
  document.addEventListener('pointermove',  onMove, true);
  document.addEventListener('touchmove',    onMove, true);

  document.addEventListener('mouseup',      onEnd, true);
  document.addEventListener('pointerup',    onEnd, true);
  document.addEventListener('touchend',     onEnd, true);
  document.addEventListener('pointercancel',onEnd, true);

  root.addEventListener('dragstart', onHtml5DragStart, true);

  // Initial apply + checkbox + keyboard toggle
  applyDndState();
  if (checkbox) checkbox.addEventListener('change', () => applyDndState());
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 'd') {
      if (checkbox) checkbox.checked = !checkbox.checked;
      applyDndState();
    }
  });
})();
