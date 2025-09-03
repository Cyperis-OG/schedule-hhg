import { state, API } from './state.js';
import { getViewYMD, loadDay } from './data.js';
import { openQuickAddDialog } from './quickadd.js';
import { openInfoForEvent } from './quickinfo.js';
import { applyDndState } from './dnd.js';
import { fmtLocal } from './utils.js';

export function createScheduler(){
  state.sch = new ej.schedule.Schedule({
    height:'100%', width:'100%',
    views:[{ option:'TimelineDay', isSelected:true }],
    currentView:'TimelineDay',
    timeZone:'America/Chicago',
    startHour:'00:00', endHour:'24:00',
    resourceHeaderWidth:170,
    selectedDate:new Date(),
    showQuickInfo:false,
    group:{ resources:['Contractors'], orientation:'Vertical', allowGroupEdit:false },
    resources:[{
      field:'ContractorId', title:'Contractor', name:'Contractors',
      dataSource:[], textField:'name', idField:'id', colorField:'color_hex'
    }],
    eventSettings:{ dataSource:[] },

    created: () => {
      // apply DnD state from toggle initially
      const cb=document.getElementById('dragToggle');
      applyDndState(cb ? cb.checked : true);
      // first load aligned to rendered day
      loadDay(getViewYMD());
    },

    cellDoubleClick:(args)=>{
      if (!window.IS_ADMIN) { args.cancel = true; return; }
      if(!args?.startTime || !args?.endTime) return;
      if(!args.element?.classList?.contains('e-work-cells')) return;
      openQuickAddDialog(args); args.cancel=true;
    },
    actionComplete: (args) => {
      if (args.requestType === 'dateNavigate') {
        loadDay(getViewYMD());
      }
    },

    eventClick: (args)=>{
      const id=args?.event?.Id; if(!id) return;
      openInfoForEvent(id);
    },

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
  state.sch.appendTo('#Schedule');
}
