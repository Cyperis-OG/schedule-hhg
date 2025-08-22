export const pad2 = (n)=>(n<10?'0':'')+n;
export const toYMD = (d)=>`${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
export const fmtLocal = (d)=>`${toYMD(d)} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;

export function tripleTo24h(hStr,mStr,ap){
  let h=Math.max(1,Math.min(12,parseInt(hStr||'0',10)||12));
  let m=Math.max(0,Math.min(59,parseInt(mStr||'0',10)||0));
  ap=(ap||'AM').toUpperCase();
  if(ap==='PM'&&h!==12)h+=12;
  if(ap==='AM'&&h===12)h=0;
  return `${pad2(h)}:${pad2(m)}`;
}
export function addHoursClamp(hhmm,add){
  const [h,m]=hhmm.split(':').map(Number);
  let eh=h+add, em=m;
  if(eh>23 || (eh===23&&em>59)){ eh=23; em=59; }
  return `${pad2(eh)}:${pad2(em)}`;
}
export function h24ToTriple(hhmm){
  const [H,M]=hhmm.split(':').map(Number);
  const ap=H>=12?'PM':'AM';
  let h=H%12; if(h===0)h=12;
  return {h:String(h), m:pad2(M), ap};
}
