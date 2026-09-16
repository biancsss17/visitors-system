const DASHBOARD_TOKEN = 'REPLACE_WITH_GOOGLE_DASHBOARD_TOKEN';
const DATABASE_SHEET_NAME = 'Visitor Database';
const MANILA_TIME_ZONE = 'Asia/Manila';

/** Returns dashboard data, or one visitor when visitor_id is supplied. */
function doGet(request) {
  if (!isAuthorized(request)) return jsonResponse({ error: 'Unauthorized' });

  const sheet = getDatabaseSheet();
  if (!sheet) return jsonResponse({ error: `Missing sheet: ${DATABASE_SHEET_NAME}` });

  const visitors = readVisitors(sheet);
  const requestedId = request.parameter && request.parameter.visitor_id;

  if (requestedId) {
    const visitor = findVisitor(visitors, requestedId);
    return visitor
      ? jsonResponse(addQrCode(visitor))
      : jsonResponse({ error: 'Visitor not found' });
  }

  return jsonResponse(buildDashboardSummary(visitors));
}

/** Handles QR email, check-in, check-out, and accountability updates. */
function doPost(request) {
  if (!isAuthorized(request)) return jsonResponse({ error: 'Unauthorized' });

  const body = parseRequestBody(request);
  if (!body) return jsonResponse({ error: 'Invalid request body' });
  if (body.action === 'email') return sendVisitorPass(body);

  const validActions = ['checkin', 'checkout', 'accountability'];
  if (!validActions.includes(body.action) || !body.visitor_id) {
    return jsonResponse({
      error: 'Expected action=checkin, checkout, or accountability and visitor_id'
    });
  }

  const sheet = getDatabaseSheet();
  if (!sheet) return jsonResponse({ error: `Missing sheet: ${DATABASE_SHEET_NAME}` });

  const table = readSheetTable(sheet);
  const rowIndex = findVisitorRowIndex(table.rows, table.headers, body.visitor_id);
  if (rowIndex < 0) return jsonResponse({ error: 'Visitor not found' });

  const rowNumber = rowIndex + 2;
  const columns = getColumnNumbers(table.headers, sheet);

  if (body.action === 'accountability') {
    return saveAccountability(sheet, rowNumber, columns.accounted, body);
  }
  if (body.action === 'checkin') {
    return saveCheckIn(sheet, rowNumber, columns, body.visitor_id);
  }
  return saveCheckOut(sheet, rowNumber, columns, body.visitor_id);
}

function sendVisitorPass(body) {
  if (!body.visitor_id || !body.email) {
    return jsonResponse({ error: 'Visitor ID and email are required' });
  }

  const recipient = normalizeEmail(body.email);
  if (!recipient) return jsonResponse({ error: 'A valid recipient email is required' });

  const sheet = getDatabaseSheet();
  if (!sheet) return jsonResponse({ error: `Missing sheet: ${DATABASE_SHEET_NAME}` });

  const visitor = findVisitor(readVisitors(sheet), body.visitor_id);
  if (!visitor) return jsonResponse({ error: 'Visitor not found' });

  const pass = addQrCode(visitor);
  const visitorId = pass['Visitor ID'];
  const qrUrl = pass.qr_url;
  const visitorName = pass.Name || 'Visitor';

  const plainText = [
    `Hello ${visitorName},`,
    '',
    'Your temporary campus visitor pass is ready.',
    `Visitor ID: ${visitorId}`,
    '',
    `QR code: ${qrUrl}`,
    '',
    'Please present this QR code when checking in and checking out.'
  ].join('\n');

  const html = [
    `<p>Hello ${escapeHtml(visitorName)},</p>`,
    '<p>Your temporary campus visitor pass is ready.</p>',
    `<p><strong>Visitor ID:</strong> ${escapeHtml(visitorId)}</p>`,
    `<p><img src="${qrUrl}" alt="Visitor QR code" width="220" height="220"></p>`,
    `<p>If the image is blocked, open the QR code here: <a href="${qrUrl}">${qrUrl}</a></p>`,
    '<p>Please present this QR code when checking in and checking out.</p>'
  ].join('');

  try {
    MailApp.sendEmail({ to: recipient, subject: `Visitor QR Pass - ${visitorId}`, body: plainText, htmlBody: html });
  } catch (error) {
    console.error(error);
    return jsonResponse({ error: `Email send failed: ${error.message}` });
  }

  return jsonResponse({ ok: true, visitor_id: visitorId, email: recipient, sent_to: recipient });
}

function saveAccountability(sheet, rowNumber, columnNumber, body) {
  const value = String(body.accountability || '').toUpperCase();
  if (!['ACCOUNTED', 'UNACCOUNTED'].includes(value)) {
    return jsonResponse({ error: 'Accountability must be ACCOUNTED or UNACCOUNTED' });
  }

  sheet.getRange(rowNumber, columnNumber).setValue(value);
  return jsonResponse({ ok: true, visitor_id: body.visitor_id, accountability: value });
}

function saveCheckIn(sheet, rowNumber, columns, visitorId) {
  const recordedAt = new Date();
  sheet.getRange(rowNumber, columns.checkIn).setValue(recordedAt);
  sheet.getRange(rowNumber, columns.checkOut).clearContent();
  sheet.getRange(rowNumber, columns.status).setValue('INSIDE');
  sheet.getRange(rowNumber, columns.accounted).setValue('UNACCOUNTED');
  return jsonResponse({ ok: true, visitor_id: visitorId, status: 'INSIDE', recorded_at: recordedAt.toISOString() });
}

function saveCheckOut(sheet, rowNumber, columns, visitorId) {
  const recordedAt = new Date();
  sheet.getRange(rowNumber, columns.checkOut).setValue(recordedAt);
  sheet.getRange(rowNumber, columns.status).setValue('OUT');
  return jsonResponse({ ok: true, visitor_id: visitorId, status: 'OUT', recorded_at: recordedAt.toISOString() });
}

function buildDashboardSummary(visitors) {
  const today = Utilities.formatDate(new Date(), MANILA_TIME_ZONE, 'yyyy-MM-dd');
  const todaysVisitors = visitors.filter(visitor => {
    if (!visitor['Check-in']) return false;
    return Utilities.formatDate(new Date(visitor['Check-in']), MANILA_TIME_ZONE, 'yyyy-MM-dd') === today;
  });

  const insideVisitors = todaysVisitors.filter(visitor => visitor.Status === 'INSIDE');
  const accountedCount = insideVisitors.filter(visitor => visitor.Accounted === 'ACCOUNTED').length;

  return {
    // Registered is the permanent total. The daily list below remains limited
    // to today's activity for the emergency dashboard.
    registered: visitors.length,
    inside: insideVisitors.length,
    checked_out: todaysVisitors.filter(visitor => visitor.Status === 'OUT').length,
    accounted: accountedCount,
    unaccounted: insideVisitors.length - accountedCount,
    // Keep today's checked-out records visible on the website. The sheet is the
    // permanent record; the website only changes its daily view.
    visitors: todaysVisitors,
    updated_at: new Date().toISOString()
  };
}

function onFormSubmit(event) {
  const sheet = getDatabaseSheet();
  if (!sheet) return;

  removeOldValidUntilColumn(sheet);
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  ensureColumn(sheet, headers, 'Accounted');

  const values = event.values || [];
  const checkInTime = values[0] || new Date();
  sheet.appendRow([
    createNextVisitorId(sheet),
    values[1] || '', values[2] || '', values[3] || '', values[4] || '',
    values[5] || '', values[6] || '', checkInTime, '', 'INSIDE', 'UNACCOUNTED'
  ]);
}

function createNextVisitorId(sheet) {
  if (sheet.getLastRow() < 2) return 'VIS-000001';

  const ids = sheet.getRange(2, 1, sheet.getLastRow() - 1, 1).getValues().flat();
  const numbers = ids.map(value => Number(String(value).replace(/^VIS-/i, '')) || 0);
  return `VIS-${String(Math.max(...numbers, 0) + 1).padStart(6, '0')}`;
}

function getDatabaseSheet() {
  return SpreadsheetApp.getActive().getSheetByName(DATABASE_SHEET_NAME);
}

function readVisitors(sheet) {
  const table = readSheetTable(sheet);
  return table.rows
    .filter(row => row.some(value => value !== ''))
    .map(row => convertRowToVisitor(table.headers, row));
}

function readSheetTable(sheet) {
  const values = sheet.getDataRange().getValues();
  return { headers: values.shift() || [], rows: values };
}

function convertRowToVisitor(headers, row) {
  return headers.reduce((visitor, header, index) => {
    visitor[header] = row[index] instanceof Date ? row[index].toISOString() : row[index];
    return visitor;
  }, {});
}

function findVisitor(visitors, visitorId) {
  const requestedId = normalizeVisitorId(visitorId);
  return visitors.find(visitor => normalizeVisitorId(visitor['Visitor ID']) === requestedId);
}

function findVisitorRowIndex(rows, headers, visitorId) {
  const idColumn = headers.indexOf('Visitor ID');
  const requestedId = normalizeVisitorId(visitorId);
  return rows.findIndex(row => normalizeVisitorId(row[idColumn]) === requestedId);
}

function getColumnNumbers(headers, sheet) {
  return {
    checkIn: headers.indexOf('Check-in') + 1,
    checkOut: headers.indexOf('Check-out') + 1,
    status: headers.indexOf('Status') + 1,
    accounted: ensureColumn(sheet, headers, 'Accounted')
  };
}

function removeOldValidUntilColumn(sheet) {
  const lastColumn = sheet.getLastColumn();
  if (!lastColumn) return;

  const headers = sheet.getRange(1, 1, 1, lastColumn).getValues()[0];
  const oldColumn = headers.findIndex(header =>
    String(header).trim().toLowerCase() === 'valid until'
  );
  if (oldColumn >= 0) sheet.deleteColumn(oldColumn + 1);
}

function ensureColumn(sheet, headers, columnName) {
  const existingColumn = headers.indexOf(columnName);
  if (existingColumn >= 0) return existingColumn + 1;

  const newColumnNumber = headers.length + 1;
  sheet.getRange(1, newColumnNumber).setValue(columnName);
  return newColumnNumber;
}

function addQrCode(visitor) {
  const visitorId = String(visitor['Visitor ID'] || '');
  return Object.assign({}, visitor, {
    qr_value: visitorId,
    qr_url: `https://quickchart.io/qr?size=240&text=${encodeURIComponent(visitorId)}`
  });
}

function parseRequestBody(request) {
  try {
    return JSON.parse(request.postData.contents || '{}');
  } catch (error) {
    return null;
  }
}

function isAuthorized(request) {
  return request && request.parameter && request.parameter.token === DASHBOARD_TOKEN;
}

function normalizeVisitorId(value) {
  return String(value || '').trim().toUpperCase();
}

function normalizeEmail(value) {
  return String(value || '').trim().toLowerCase();
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>\"]/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;'
  }[character]));
}

function jsonResponse(payload) {
  return ContentService.createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

function setupDatabase() {
  const sheet = getDatabaseSheet();
  if (!sheet) throw new Error(`Missing sheet: ${DATABASE_SHEET_NAME}`);

  removeOldValidUntilColumn(sheet);
  sheet.getParent().setSpreadsheetTimeZone(MANILA_TIME_ZONE);
  ensureColumn(sheet, sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0], 'Accounted');
}

function installTrigger() {
  const spreadsheet = SpreadsheetApp.getActive();
  ScriptApp.getProjectTriggers().forEach(trigger => {
    if (trigger.getHandlerFunction() === 'onFormSubmit') ScriptApp.deleteTrigger(trigger);
  });

  ScriptApp.newTrigger('onFormSubmit')
    .forSpreadsheet(spreadsheet)
    .onFormSubmit()
    .create();
}
