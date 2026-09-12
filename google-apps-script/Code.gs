/**
 * Bound to the Campus Visitor Registration response spreadsheet.
 * Replace DASHBOARD_TOKEN with the same value used by Laravel's .env file.
 */
// Set this value in Apps Script before deploying the web app.
const DASHBOARD_TOKEN = 'REPLACE_WITH_GOOGLE_DASHBOARD_TOKEN';
const DATABASE_SHEET = 'Visitor Database';
const RESPONSE_SHEET = 'Form Responses 1';

function doGet(e) {
  if (!isAuthorized_(e)) return json_({error: 'Unauthorized'}, 401);

  const sheet = SpreadsheetApp.getActive().getSheetByName(DATABASE_SHEET);
  if (!sheet) return json_({error: `Missing sheet: ${DATABASE_SHEET}`}, 500);

  const values = sheet.getDataRange().getValues();
  const headers = values.shift() || [];
  const rows = values
    .filter(row => row.some(value => value !== ''))
    .map(row => rowToObject_(headers, row));

  if (e.parameter.visitor_id) {
    const visitor = rows.find(item => normalizeVisitorId_(item['Visitor ID']) === normalizeVisitorId_(e.parameter.visitor_id));
    if (!visitor) return json_({error: 'Visitor not found'}, 404);
    return json_(withQr_(visitor));
  }

  const todayKey = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd');
  const todayRows = rows.filter(visitor => String(visitor['Check-in'] || '').slice(0, 10) === todayKey);
  const inside = todayRows.filter(visitor => String(visitor['Status']).toUpperCase() === 'INSIDE');
  const accounted = inside.filter(visitor => String(visitor['Accounted'] || '').toUpperCase() === 'ACCOUNTED').length;

  return json_({
    registered: todayRows.length,
    inside: inside.length,
    checked_out: todayRows.filter(visitor => String(visitor['Status']).toUpperCase() === 'OUT').length,
    accounted: accounted,
    unaccounted: inside.length - accounted,
    visitors: inside,
    updated_at: new Date().toISOString()
  });
}

function doPost(e) {
  if (!isAuthorized_(e)) return json_({error: 'Unauthorized'}, 401);

  const body = JSON.parse(e.postData.contents || '{}');

  if (body.action === 'email') {
    if (!body.visitor_id || !body.email) return json_({error: 'Visitor ID and email are required'}, 400);
    const sheet = SpreadsheetApp.getActive().getSheetByName(DATABASE_SHEET);
    const values = sheet.getDataRange().getValues();
    const headers = values.shift() || [];
    const rows = values
      .filter(row => row.some(value => value !== ''))
      .map(row => rowToObject_(headers, row));
    const visitor = rows.find(item => normalizeVisitorId_(item['Visitor ID']) === normalizeVisitorId_(body.visitor_id));
    if (!visitor) return json_({error: 'Visitor not found'}, 404);

    const pass = withQr_(visitor);
    const recipient = normalizeEmail_(body.email);
    if (!recipient) return json_({error: 'A valid recipient email is required'}, 400);
    const subject = `Visitor QR Pass - ${pass['Visitor ID']}`;
    const plainBody = `Hello ${pass.Name || 'Visitor'},\n\nYour temporary campus visitor pass is ready.\nVisitor ID: ${pass['Visitor ID']}\n\nPlease present the QR pass when checking in and checking out.`;
    try {
      const htmlBody = [
        `<p>Hello ${escapeHtml_(pass.Name || 'Visitor')},</p>`,
        '<p>Your temporary campus visitor pass is below.</p>',
        `<p><strong>Visitor ID:</strong> ${escapeHtml_(pass['Visitor ID'])}</p>`,
        `<p><img src="${pass.qr_url}" alt="Visitor QR code" width="220" height="220"></p>`,
        `<p>If the image is blocked, open the QR code here: <a href="${pass.qr_url}">${pass.qr_url}</a></p>`,
        '<p>Please present this QR code when checking in and checking out.</p>'
      ].join('');
      MailApp.sendEmail({to: recipient, subject: subject, body: plainBody + `\n\nQR code: ${pass.qr_url}`, htmlBody: htmlBody});
    } catch (error) {
      console.error(error);
      return json_({error: `Email send failed: ${error.message}`}, 500);
    }
    return json_({ok: true, visitor_id: pass['Visitor ID'], email: recipient, sent_to: recipient});
  }

  if (!['checkin', 'checkout', 'accountability'].includes(body.action) || !body.visitor_id) {
    return json_({error: 'Expected action=checkin, checkout, or accountability and visitor_id'}, 400);
  }

  const sheet = SpreadsheetApp.getActive().getSheetByName(DATABASE_SHEET);
  const values = sheet.getDataRange().getValues();
  const headers = values.shift() || [];
  const idColumn = headers.indexOf('Visitor ID');
  const checkinColumn = headers.indexOf('Check-in');
  const checkoutColumn = headers.indexOf('Check-out');
  const statusColumn = headers.indexOf('Status');
  const accountedColumn = ensureColumn_(sheet, headers, 'Accounted');
  const rowIndex = values.findIndex(row =>
    normalizeVisitorId_(row[idColumn]) === normalizeVisitorId_(body.visitor_id)
  );

  if (rowIndex < 0) return json_({error: 'Visitor not found'}, 404);

  const sheetRow = rowIndex + 2;
  if (body.action === 'accountability') {
    const accountability = String(body.accountability || '').toUpperCase();
    if (!['ACCOUNTED', 'UNACCOUNTED'].includes(accountability)) {
      return json_({error: 'Accountability must be ACCOUNTED or UNACCOUNTED'}, 400);
    }
    sheet.getRange(sheetRow, accountedColumn).setValue(accountability);
    return json_({
      ok: true,
      visitor_id: body.visitor_id,
      accountability: accountability
    });
  }
  if (body.action === 'checkin') {
    sheet.getRange(sheetRow, checkinColumn + 1).setValue(new Date());
    sheet.getRange(sheetRow, checkoutColumn + 1).clearContent();
    sheet.getRange(sheetRow, statusColumn + 1).setValue('INSIDE');
    sheet.getRange(sheetRow, accountedColumn).setValue('UNACCOUNTED');
    return json_({ok: true, visitor_id: body.visitor_id, status: 'INSIDE', recorded_at: new Date().toISOString()});
  }

  sheet.getRange(sheetRow, checkoutColumn + 1).setValue(new Date());
  sheet.getRange(sheetRow, statusColumn + 1).setValue('OUT');
  return json_({ok: true, visitor_id: body.visitor_id, status: 'OUT', recorded_at: new Date().toISOString()});
}

function onFormSubmit(e) {
  const database = SpreadsheetApp.getActive().getSheetByName(DATABASE_SHEET);
  const headers = database.getRange(1, 1, 1, database.getLastColumn()).getValues()[0];
  ensureColumn_(database, headers, 'Accounted');
  const row = e.values || [];
  const existingIds = database.getLastRow() > 1
    ? database
      .getRange(2, 1, database.getLastRow() - 1, 1)
      .getValues()
      .flat()
      .map(value => Number(String(value).replace(/^VIS-/i, '')) || 0)
    : [];
  const nextId = `VIS-${String(Math.max(...existingIds, 0) + 1).padStart(6, '0')}`;
  const checkIn = row[0] || new Date();
  const validUntil = new Date(checkIn);
  validUntil.setDate(validUntil.getDate() + 7);

  database.appendRow([
    nextId,
    row[1] || '',
    row[2] || '',
    row[3] || '',
    row[4] || '',
    row[5] || '',
    row[6] || '',
    checkIn,
    '',
    'INSIDE',
    validUntil,
    'UNACCOUNTED'
  ]);
}

function ensureColumn_(sheet, headers, name) {
  const existing = headers.indexOf(name);
  if (existing >= 0) return existing + 1;
  const column = headers.length + 1;
  sheet.getRange(1, column).setValue(name);
  return column;
}

function isAuthorized_(e) {
  return e && e.parameter && e.parameter.token === DASHBOARD_TOKEN;
}

function rowToObject_(headers, row) {
  return headers.reduce((result, header, index) => {
    result[header] = row[index] instanceof Date ? row[index].toISOString() : row[index];
    return result;
  }, {});
}

function withQr_(visitor) {
  const visitorId = String(visitor['Visitor ID'] || '');
  return Object.assign({}, visitor, {
    qr_value: visitorId,
    qr_url: `https://quickchart.io/qr?size=240&text=${encodeURIComponent(visitorId)}`
  });
}

function normalizeVisitorId_(value) {
  return String(value || '').trim().toUpperCase();
}

function normalizeEmail_(value) {
  return String(value || '').trim().toLowerCase();
}

function escapeHtml_(value) {
  return String(value || '').replace(/[&<>\"]/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;'}[character]));
}

function json_(payload, status) {
  return ContentService.createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

function installTrigger() {
  const spreadsheet = SpreadsheetApp.getActive();
  ScriptApp.getProjectTriggers().forEach((trigger) => {
    if (trigger.getHandlerFunction() === 'onFormSubmit') {
      ScriptApp.deleteTrigger(trigger);
    }
  });
  ScriptApp.newTrigger('onFormSubmit')
    .forSpreadsheet(spreadsheet)
    .onFormSubmit()
    .create();
}
