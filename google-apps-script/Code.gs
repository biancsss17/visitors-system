const DASHBOARD_TOKEN = 'REPLACE_WITH_GOOGLE_DASHBOARD_TOKEN';
const VISITORS_SHEET_NAME = 'Visitors';
const VISIT_LOGS_SHEET_NAME = 'Visit Logs';
const LEGACY_SHEET_NAME = 'Visitor Database';
const MANILA_TIME_ZONE = 'Asia/Manila';
const VISITOR_HEADERS = ['Visitor ID','Name','Email','Address','Company/Organization','Visitor Type','Purpose','Host/Location','Contact No.','Status','Accounted','Created At'];
const VISIT_HEADERS = ['Visit ID','Visitor ID','Check-in','Check-out','Status','Accounted','Created At'];

function doGet(request) {
  if (!isAuthorized(request)) return jsonResponse({error:'Unauthorized'});
  ensureDatabase();
  const id = request.parameter && request.parameter.visitor_id;
  if (id) { const visitor = findVisitor(readVisitors(), id); return visitor ? jsonResponse(Object.assign(addQrCode(visitor), {visit_history: visitsFor(visitor['Visitor ID'])})) : jsonResponse({error:'Visitor not found'}); }
  return jsonResponse(buildDashboardSummary());
}

function doPost(request) {
  if (!isAuthorized(request)) return jsonResponse({error:'Unauthorized'});
  const body = parseRequestBody(request);
  if (!body || !body.action || !body.visitor_id) return jsonResponse({error:'Expected action and visitor_id'});
  ensureDatabase();
  if (body.action === 'email') return sendVisitorPass(body);
  const visitor = findVisitor(readVisitors(), body.visitor_id);
  if (!visitor) return jsonResponse({error:'Visitor not found'});
  if (body.action === 'checkin') return saveCheckIn(visitor['Visitor ID']);
  if (body.action === 'checkout') return saveCheckOut(visitor['Visitor ID']);
  if (body.action === 'accountability') return saveAccountability(visitor['Visitor ID'], body.accountability);
  return jsonResponse({error:'Unsupported action'});
}

function saveCheckIn(visitorId) {
  const latest = latestVisit(visitorId);
  if (latest && latest.Status === 'INSIDE') return jsonResponse({ok:true,visitor_id:visitorId,visit_id:latest['Visit ID'],status:'INSIDE',recorded_at:toIso(latest['Check-in'])});
  const now = new Date(), visitId = createNextVisitId();
  getSheet(VISIT_LOGS_SHEET_NAME).appendRow([visitId,visitorId,now,'','INSIDE','UNACCOUNTED',now]);
  updateVisitorStatus(visitorId,'INSIDE','UNACCOUNTED');
  return jsonResponse({ok:true,visitor_id:visitorId,visit_id:visitId,status:'INSIDE',recorded_at:now.toISOString()});
}

function saveCheckOut(visitorId) {
  const sheet = getSheet(VISIT_LOGS_SHEET_NAME), table = readTable(sheet);
  const index = table.rows.findIndex(row => String(row[1]).toUpperCase() === visitorId.toUpperCase() && String(row[4]).toUpperCase() === 'INSIDE');
  if (index < 0) return jsonResponse({error:'No open visit found for this visitor'});
  const now = new Date(), row = index + 2;
  sheet.getRange(row,4).setValue(now); sheet.getRange(row,5).setValue('OUT'); updateVisitorStatus(visitorId,'OUT');
  return jsonResponse({ok:true,visitor_id:visitorId,visit_id:table.rows[index][0],status:'OUT',recorded_at:now.toISOString()});
}

function saveAccountability(visitorId, value) {
  value = String(value || '').toUpperCase();
  if (!['ACCOUNTED','UNACCOUNTED'].includes(value)) return jsonResponse({error:'Accountability must be ACCOUNTED or UNACCOUNTED'});
  const latest = latestVisit(visitorId); if (!latest) return jsonResponse({error:'No visit found'});
  const sheet = getSheet(VISIT_LOGS_SHEET_NAME), table = readTable(sheet), index = table.rows.findIndex(row => row[0] === latest['Visit ID']);
  sheet.getRange(index+2,6).setValue(value); return jsonResponse({ok:true,visitor_id:visitorId,accountability:value});
}

function buildDashboardSummary() {
  const profiles = readVisitors(), logs = readVisits(), today = Utilities.formatDate(new Date(),MANILA_TIME_ZONE,'yyyy-MM-dd');
  const todays = logs.filter(log => log['Check-in'] && Utilities.formatDate(new Date(log['Check-in']),MANILA_TIME_ZONE,'yyyy-MM-dd') === today).map(log => Object.assign({},findVisitor(profiles,log['Visitor ID']) || {},log));
  const inside = todays.filter(v => v.Status === 'INSIDE'), accounted = inside.filter(v => v.Accounted === 'ACCOUNTED').length;
  return {registered:profiles.length,inside:inside.length,checked_out:todays.filter(v => v.Status === 'OUT').length,accounted,unaccounted:inside.length-accounted,visitors:todays,updated_at:new Date().toISOString()};
}

function onFormSubmit(event) {
  ensureDatabase(); const values = event.values || [], checkIn = values[0] ? new Date(values[0]) : new Date();
  const visitor = findOrCreateProfile({Name:values[1] || '',Company:values[2] || '',Type:values[3] || '',Purpose:values[4] || '',Host:values[5] || '',Contact:values[6] || '',Email:values[7] || ''});
  appendVisit(visitor['Visitor ID'],checkIn);
}
function appendVisit(visitorId, checkIn) { const now = checkIn || new Date(); getSheet(VISIT_LOGS_SHEET_NAME).appendRow([createNextVisitId(),visitorId,now,'','INSIDE','UNACCOUNTED',now]); updateVisitorStatus(visitorId,'INSIDE','UNACCOUNTED'); }
function findOrCreateProfile(data) {
  const existing = readVisitors().find(v => (data.Email && normalizeEmail(v.Email) === normalizeEmail(data.Email)) || (normalize(v.Name) === normalize(data.Name) && normalize(v['Company/Organization']) === normalize(data.Company)));
  if (existing) return existing;
  const id = createNextVisitorId(), now = new Date(); getSheet(VISITORS_SHEET_NAME).appendRow([id,data.Name,data.Email,'',data.Company,data.Type,data.Purpose,data.Host,data.Contact,'OUT','UNACCOUNTED',now]); return {'Visitor ID':id,Name:data.Name};
}
function latestVisit(id) { return readVisits().filter(v => String(v['Visitor ID']).toUpperCase() === id.toUpperCase()).sort((a,b) => new Date(b['Created At'])-new Date(a['Created At']))[0]; }
function visitsFor(id) { return readVisits().filter(v => String(v['Visitor ID']).toUpperCase() === id.toUpperCase()).sort((a,b) => new Date(a['Created At'])-new Date(b['Created At'])); }
function updateVisitorStatus(id,status,accounted) { const sheet=getSheet(VISITORS_SHEET_NAME), table=readTable(sheet), i=table.rows.findIndex(r=>String(r[0]).toUpperCase()===id.toUpperCase()); if(i>=0){sheet.getRange(i+2,10).setValue(status);if(accounted)sheet.getRange(i+2,11).setValue(accounted);} }
function readVisitors() { return readTable(getSheet(VISITORS_SHEET_NAME)).rows.filter(r=>r.some(Boolean)).map(r=>rowObject(VISITOR_HEADERS,r)); }
function readVisits() { return readTable(getSheet(VISIT_LOGS_SHEET_NAME)).rows.filter(r=>r.some(Boolean)).map(r=>rowObject(VISIT_HEADERS,r)); }
function readTable(sheet) { const values=sheet.getDataRange().getValues(); return {headers:values.shift()||[],rows:values}; }
function rowObject(headers,row) { return headers.reduce((o,h,i)=>{o[h]=row[i] instanceof Date?row[i].toISOString():row[i];return o;},{}); }
function ensureDatabase() { const ss=SpreadsheetApp.getActive(); let visitors=ss.getSheetByName(VISITORS_SHEET_NAME), logs=ss.getSheetByName(VISIT_LOGS_SHEET_NAME); if(!visitors){visitors=ss.insertSheet(VISITORS_SHEET_NAME);visitors.appendRow(VISITOR_HEADERS);} if(!logs){logs=ss.insertSheet(VISIT_LOGS_SHEET_NAME);logs.appendRow(VISIT_HEADERS);} migrateLegacy(ss,visitors,logs); ss.setSpreadsheetTimeZone(MANILA_TIME_ZONE); }
function migrateLegacy(ss,visitors,logs) { const legacy=ss.getSheetByName(LEGACY_SHEET_NAME); if(!legacy||legacy.getLastRow()<2||visitors.getLastRow()>1)return; const old=readTable(legacy); old.rows.filter(r=>r.some(Boolean)).forEach(r=>{const v=rowObject(old.headers,r),id=String(v['Visitor ID']||createNextVisitorId()),now=v['Check-in']||new Date();visitors.appendRow([id,v.Name||'',v.Email||'','',v['Company/Organization']||'',v['Visitor Type']||'',v.Purpose||'',v['Host/Location']||'',v['Contact No.']||'',v.Status||'OUT',v.Accounted||'UNACCOUNTED',now]);if(v['Check-in'])logs.appendRow([createNextVisitId(),id,v['Check-in'],v['Check-out']||'',v.Status||'OUT',v.Accounted||'UNACCOUNTED',now]);}); }
function getSheet(name) { return SpreadsheetApp.getActive().getSheetByName(name); }
function createNextVisitorId() { return nextId(readVisitors().map(v=>v['Visitor ID']),'VIS-'); }
function createNextVisitId() { return nextId(readVisits().map(v=>v['Visit ID']),'VISIT-'); }
function nextId(ids,prefix) { const n=ids.map(v=>Number(String(v).replace(new RegExp('^'+prefix,'i'),''))||0);return prefix+String(Math.max(0,...n)+1).padStart(6,'0'); }
function findVisitor(visitors,id) { id=normalizeVisitorId(id);return visitors.find(v=>normalizeVisitorId(v['Visitor ID'])===id); }
function addQrCode(v) { const id=String(v['Visitor ID']||'');return Object.assign({},v,{qr_value:id,qr_url:'https://quickchart.io/qr?size=240&text='+encodeURIComponent(id)}); }
function sendVisitorPass(body) { const v=findVisitor(readVisitors(),body.visitor_id),email=normalizeEmail(body.email);if(!v)return jsonResponse({error:'Visitor not found'});if(!email)return jsonResponse({error:'A valid recipient email is required'});const pass=addQrCode(v);MailApp.sendEmail({to:email,subject:'Visitor QR Pass - '+pass['Visitor ID'],body:'Visitor ID: '+pass['Visitor ID']+'\nQR code: '+pass.qr_url});return jsonResponse({ok:true,visitor_id:pass['Visitor ID'],email,sent_to:email}); }
function parseRequestBody(r) { try{return JSON.parse(r.postData.contents||'{}');}catch(e){return null;} } function isAuthorized(r){return r&&r.parameter&&r.parameter.token===DASHBOARD_TOKEN;} function normalize(v){return String(v||'').trim().toLowerCase();} function normalizeEmail(v){return normalize(v);} function normalizeVisitorId(v){return String(v||'').trim().toUpperCase();} function toIso(v){return v instanceof Date?v.toISOString():new Date(v).toISOString();} function jsonResponse(p){return ContentService.createTextOutput(JSON.stringify(p)).setMimeType(ContentService.MimeType.JSON);}
function setupDatabase(){ensureDatabase();} function installTrigger(){const ss=SpreadsheetApp.getActive();ScriptApp.getProjectTriggers().forEach(t=>{if(t.getHandlerFunction()==='onFormSubmit')ScriptApp.deleteTrigger(t);});ScriptApp.newTrigger('onFormSubmit').forSpreadsheet(ss).onFormSubmit().create();}

// Performance override: check-in uses one Visit Logs read and a targeted profile update.
function saveCheckIn(visitorId) {
  const sheet = getSheet(VISIT_LOGS_SHEET_NAME);
  const values = sheet.getDataRange().getValues();
  const now = new Date();
  let latestIndex = -1;
  for (let i = values.length - 1; i >= 1; i--) {
    if (String(values[i][1] || '').trim().toUpperCase() === visitorId.toUpperCase()) {
      latestIndex = i;
      break;
    }
  }
  if (latestIndex >= 1 && String(values[latestIndex][4] || '').trim().toUpperCase() === 'INSIDE') {
    const openedAt = values[latestIndex][2] instanceof Date ? values[latestIndex][2] : new Date(values[latestIndex][2]);
    if (!isNaN(openedAt.getTime()) && (now.getTime() - openedAt.getTime()) < 24 * 60 * 60 * 1000) {
      return jsonResponse({ok:true, visitor_id:visitorId, visit_id:values[latestIndex][0], status:'INSIDE', recorded_at:openedAt.toISOString()});
    }
    sheet.getRange(latestIndex + 1, 4).setValue(now);
    sheet.getRange(latestIndex + 1, 5).setValue('OUT');
  }
  let maxVisit = 0;
  for (let i = 1; i < values.length; i++) {
    const n = Number(String(values[i][0] || '').replace(/^VISIT-/i, '')) || 0;
    if (n > maxVisit) maxVisit = n;
  }
  const visitId = 'VISIT-' + String(maxVisit + 1).padStart(6, '0');
  sheet.appendRow([visitId, visitorId, now, '', 'INSIDE', 'UNACCOUNTED', now]);
  updateVisitorStatusFast(visitorId, 'INSIDE', 'UNACCOUNTED');
  return jsonResponse({ok:true, visitor_id:visitorId, visit_id:visitId, status:'INSIDE', recorded_at:now.toISOString()});
}

function updateVisitorStatusFast(id, status, accounted) {
  const sheet = getSheet(VISITORS_SHEET_NAME);
  const cell = sheet.createTextFinder(id).matchEntireCell(true).findNext();
  if (!cell) return;
  sheet.getRange(cell.getRow(), 10).setValue(status);
  if (accounted) sheet.getRange(cell.getRow(), 11).setValue(accounted);
}

// Separate-sheet mode: Visitor Profile stores one row per person; Visit Logs stores every visit.
const VISITOR_PROFILE_SHEET_NAME = VISITORS_SHEET_NAME;
const SINGLE_SHEET_LOG_START_COL = 14;
const SINGLE_SHEET_LOG_HEADERS = ['Visit ID','Visitor ID','Check-in','Check-out','Status','Accounted','Created At'];
function ensureDatabase() {
  const ss=SpreadsheetApp.getActive();
  let profiles=ss.getSheetByName(VISITOR_PROFILE_SHEET_NAME) || ss.getSheetByName(VISITORS_SHEET_NAME);
  if(!profiles) profiles=ss.insertSheet(VISITOR_PROFILE_SHEET_NAME);
  if(profiles.getName()!==VISITOR_PROFILE_SHEET_NAME) profiles.setName(VISITOR_PROFILE_SHEET_NAME);
  if(profiles.getLastRow()<1) profiles.appendRow(VISITOR_HEADERS);
  let logs=ss.getSheetByName(VISIT_LOGS_SHEET_NAME);
  if(!logs) logs=ss.insertSheet(VISIT_LOGS_SHEET_NAME);
  if(logs.getLastRow()<1) logs.appendRow(SINGLE_SHEET_LOG_HEADERS);
  const props=PropertiesService.getScriptProperties();
  if(props.getProperty('separate_sheets_migrated')!=='1'){
    const header=profiles.getRange(1,SINGLE_SHEET_LOG_START_COL,1,SINGLE_SHEET_LOG_HEADERS.length).getValues()[0];
    const unified=header.join('|')===SINGLE_SHEET_LOG_HEADERS.join('|')?readUnifiedVisitsRaw(profiles):[];
    if(unified.length && logs.getLastRow()<=1) logs.getRange(2,1,unified.length,7).setValues(unified);
    if(header.join('|')===SINGLE_SHEET_LOG_HEADERS.join('|')) profiles.getRange(1,SINGLE_SHEET_LOG_START_COL,profiles.getMaxRows(),SINGLE_SHEET_LOG_HEADERS.length).clearContent();
    props.setProperty('separate_sheets_migrated','1');
  }
  ss.setSpreadsheetTimeZone(MANILA_TIME_ZONE);
}
function getUnifiedSheet() { const s=SpreadsheetApp.getActive().getSheetByName(VISITOR_PROFILE_SHEET_NAME); if(!s)throw new Error('Visitor Profile sheet not found'); return s; }
function getVisitLogSheet() { const s=SpreadsheetApp.getActive().getSheetByName(VISIT_LOGS_SHEET_NAME); if(!s)throw new Error('Visit Logs sheet not found'); return s; }
function readUnifiedVisitsRaw(sheet) { const n=Math.max(sheet.getLastRow()-1,0); return n?n?sheet.getRange(2,SINGLE_SHEET_LOG_START_COL,n,7).getValues().filter(r=>r.some(Boolean)):[]:[]; }
function readVisitors() { const sheet=getUnifiedSheet(), n=Math.max(sheet.getLastRow()-1,0); if(!n)return []; return sheet.getRange(2,1,n,12).getValues().filter(r=>r.some(Boolean)).map(r=>rowObject(VISITOR_HEADERS,r)); }
function readVisits() { const sheet=getVisitLogSheet(), n=Math.max(sheet.getLastRow()-1,0); return n?sheet.getRange(2,1,n,7).getValues().filter(r=>r.some(Boolean)).map(r=>rowObject(SINGLE_SHEET_LOG_HEADERS,r)):[]; }
function latestVisit(id) { return readVisits().filter(v=>String(v['Visitor ID']).toUpperCase()===id.toUpperCase()).sort((a,b)=>new Date(b['Created At'])-new Date(a['Created At']))[0]; }
function visitsFor(id) { return readVisits().filter(v=>String(v['Visitor ID']).toUpperCase()===id.toUpperCase()).sort((a,b)=>new Date(a['Created At'])-new Date(b['Created At'])); }
function createNextVisitorId() { return nextId(readVisitors().map(v=>v['Visitor ID']),'VIS-'); }
function createNextVisitId() { return nextId(readVisits().map(v=>v['Visit ID']),'VISIT-'); }
function updateVisitorStatus(id,status,accounted) { const sheet=getUnifiedSheet(), cell=sheet.createTextFinder(id).matchEntireCell(true).findNext(); if(!cell)return; sheet.getRange(cell.getRow(),10).setValue(status); if(accounted)sheet.getRange(cell.getRow(),11).setValue(accounted); }
function saveCheckIn(visitorId) {
  const sheet=getVisitLogSheet(), rows=readVisits().map(v=>SINGLE_SHEET_LOG_HEADERS.map(h=>v[h])), now=new Date(); let idx=-1;
  for(let i=rows.length-1;i>=0;i--) if(String(rows[i][1]||'').toUpperCase()===visitorId.toUpperCase()){idx=i;break;}
  if(idx>=0 && String(rows[idx][4]||'').toUpperCase()==='INSIDE') { const opened=rows[idx][2] instanceof Date?rows[idx][2]:new Date(rows[idx][2]); if(!isNaN(opened.getTime()) && now-opened<24*60*60*1000)return jsonResponse({ok:true,visitor_id:visitorId,visit_id:rows[idx][0],status:'INSIDE',recorded_at:opened.toISOString()}); sheet.getRange(idx+2,4).setValue(now); sheet.getRange(idx+2,5).setValue('OUT'); }
  let max=0; rows.forEach(r=>{const n=Number(String(r[0]||'').replace(/^VISIT-/i,''))||0;if(n>max)max=n;}); const visitId='VISIT-'+String(max+1).padStart(6,'0'); sheet.appendRow([visitId,visitorId,now,'','INSIDE','UNACCOUNTED',now]); updateVisitorStatus(visitorId,'INSIDE','UNACCOUNTED'); return jsonResponse({ok:true,visitor_id:visitorId,visit_id:visitId,status:'INSIDE',recorded_at:now.toISOString()});
}
function saveCheckOut(visitorId) { const sheet=getVisitLogSheet(), rows=readVisits().map(v=>SINGLE_SHEET_LOG_HEADERS.map(h=>v[h])), idx=rows.findIndex(r=>String(r[1]||'').toUpperCase()===visitorId.toUpperCase()&&String(r[4]||'').toUpperCase()==='INSIDE'); if(idx<0)return jsonResponse({error:'No open visit found for this visitor'}); const now=new Date(); sheet.getRange(idx+2,4).setValue(now); sheet.getRange(idx+2,5).setValue('OUT'); updateVisitorStatus(visitorId,'OUT'); return jsonResponse({ok:true,visitor_id:visitorId,visit_id:rows[idx][0],status:'OUT',recorded_at:now.toISOString()}); }
function saveAccountability(visitorId,value) { value=String(value||'').toUpperCase(); if(!['ACCOUNTED','UNACCOUNTED'].includes(value))return jsonResponse({error:'Accountability must be ACCOUNTED or UNACCOUNTED'}); const latest=latestVisit(visitorId); if(!latest)return jsonResponse({error:'No visit found'}); const sheet=getVisitLogSheet(), rows=readVisits().map(v=>SINGLE_SHEET_LOG_HEADERS.map(h=>v[h])), idx=rows.findIndex(r=>r[0]===latest['Visit ID']); if(idx<0)return jsonResponse({error:'Visit not found'}); sheet.getRange(idx+2,6).setValue(value); return jsonResponse({ok:true,visitor_id:visitorId,accountability:value}); }
function appendVisit(visitorId,checkIn) { const sheet=getVisitLogSheet(), now=checkIn||new Date(), id=createNextVisitId(); sheet.appendRow([id,visitorId,now,'','INSIDE','UNACCOUNTED',now]); updateVisitorStatus(visitorId,'INSIDE','UNACCOUNTED'); }
function findOrCreateProfile(data) { const existing=readVisitors().find(v=>(data.Email&&normalizeEmail(v.Email)===normalizeEmail(data.Email))||(normalize(v.Name)===normalize(data.Name)&&normalize(v['Company/Organization'])===normalize(data.Company))); if(existing)return existing; const sheet=getUnifiedSheet(), id=createNextVisitorId(), now=new Date(); sheet.getRange(sheet.getLastRow()+1,1,1,12).setValues([[id,data.Name,data.Email,'',data.Company,data.Type,data.Purpose,data.Host,data.Contact,'OUT','UNACCOUNTED',now]]); return {'Visitor ID':id,Name:data.Name}; }
function buildDashboardSummary() { const profiles=readVisitors(),logs=readVisits(),today=Utilities.formatDate(new Date(),MANILA_TIME_ZONE,'yyyy-MM-dd'),todays=logs.filter(log=>log['Check-in']&&Utilities.formatDate(new Date(log['Check-in']),MANILA_TIME_ZONE,'yyyy-MM-dd')===today).map(log=>Object.assign({},findVisitor(profiles,log['Visitor ID'])||{},log)),inside=todays.filter(v=>v.Status==='INSIDE'),accounted=inside.filter(v=>v.Accounted==='ACCOUNTED').length; return {registered:profiles.length,inside:inside.length,checked_out:todays.filter(v=>v.Status==='OUT').length,accounted,unaccounted:inside.length-accounted,visitors:todays,updated_at:new Date().toISOString()}; }
function doGet(request) { if(!isAuthorized(request))return jsonResponse({error:'Unauthorized'}); ensureDatabase(); const id=request.parameter&&request.parameter.visitor_id; if(id){const v=findVisitor(readVisitors(),id);return v?jsonResponse(Object.assign(addQrCode(v),{visit_history:visitsFor(v['Visitor ID'])})):jsonResponse({error:'Visitor not found'});} return jsonResponse(buildDashboardSummary()); }
function doPost(request) { if(!isAuthorized(request))return jsonResponse({error:'Unauthorized'}); const body=parseRequestBody(request); if(!body||!body.action||!body.visitor_id)return jsonResponse({error:'Expected action and visitor_id'}); ensureDatabase(); if(body.action==='email')return sendVisitorPass(body); const visitor=findVisitor(readVisitors(),body.visitor_id); if(!visitor)return jsonResponse({error:'Visitor not found'}); if(body.action==='checkin')return saveCheckIn(visitor['Visitor ID']); if(body.action==='checkout')return saveCheckOut(visitor['Visitor ID']); if(body.action==='accountability')return saveAccountability(visitor['Visitor ID'],body.accountability); return jsonResponse({error:'Unsupported action'}); }
function onFormSubmit(event) { ensureDatabase(); const values=event.values||[],checkIn=values[0]?new Date(values[0]):new Date(),visitor=findOrCreateProfile({Name:values[1]||'',Company:values[2]||'',Type:values[3]||'',Purpose:values[4]||'',Host:values[5]||'',Contact:values[6]||'',Email:values[7]||''}); appendVisit(visitor['Visitor ID'],checkIn); }
function setupSingleSheet(){ ensureDatabase(); }
