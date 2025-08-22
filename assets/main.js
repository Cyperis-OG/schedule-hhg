import { createScheduler } from './scheduler.js';
import { installDndToggle, applyDndState } from './dnd.js';

document.addEventListener('DOMContentLoaded', () => {
  createScheduler();
  const cb = installDndToggle();
  if (cb) applyDndState(cb.checked);
});
