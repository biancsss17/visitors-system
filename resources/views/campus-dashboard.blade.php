<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://cdn.tailwindcss.com">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<title>Campus Visitor &amp; Evacuation System</title>
<!-- Tailwind CSS CDN with plugins -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script defer src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<!-- Tailwind Configuration for custom theme matching the infographic -->
<script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            campus: {
              navy: '#0b2545',
              blue: '#134074',
              accent: '#0077b6',
              light: '#eef4f8',
              surface: '#ffffff'
            },
            status: {
              inside: '#10b981',
              insideBg: '#ecfdf5',
              out: '#ef4444',
              outBg: '#fef2f2',
              warning: '#f59e0b'
            }
          },
          fontFamily: {
            sans: ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif']
          }
        }
      }
    }
  </script>
<!-- BEGIN: Custom Styles -->
<style data-purpose="custom-scrollbars-and-safe-area">
    /* iOS Safe Area adjustments */
    body {
      padding-top: env(safe-area-inset-top);
      padding-bottom: env(safe-area-inset-bottom);
      -webkit-tap-highlight-color: transparent;
    }
    
    /* Hide scrollbar for horizontal categories while maintaining swipeability */
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    /* Pulse animation for emergency indicator */
    @keyframes alert-pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.8; transform: scale(1.02); }
    }
    .emergency-glow {
      box-shadow: 0 0 15px rgba(220, 38, 38, 0.4);
      animation: alert-pulse 3s infinite ease-in-out;
    }
  </style>
<!-- END: Custom Styles -->
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen pb-6 selection:bg-sky-200">
<!-- BEGIN: TopAppHeader -->
<header class="bg-campus-navy text-white sticky top-0 z-40 shadow-md border-b border-sky-900" data-purpose="app-header">
<div class="px-4 py-3">
<!-- Top status & branding line -->
<div class="flex items-center justify-between">
<div class="flex items-center space-x-2.5">
<!-- Campus Shield & Building Icon -->
<div class="w-9 h-9 rounded-lg bg-sky-600/30 border border-sky-400/40 flex items-center justify-center p-1.5 shadow-inner">
<svg class="w-full h-full text-sky-200 fill-current" viewbox="0 0 24 24">
<path d="M12 2L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-3zm-1 6h2v2h-2V8zm0 4h2v6h-2v-6z"></path>
</svg>
</div>
<div>
<div class="flex items-center space-x-1.5">
<span class="text-xs font-semibold tracking-wider text-sky-300 uppercase">XYZ College</span>
<span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
</div>
<h1 class="text-sm font-bold tracking-tight text-white leading-tight">Visitor &amp; Evacuation Manager</h1>
</div>
</div>
</div>
<!-- Live Sync status sub-row with Google Sheets & DB indicator -->
<div class="mt-2 pt-2 border-t border-sky-900/60 flex items-center justify-between text-[11px] text-sky-200/80">
<span class="flex items-center space-x-1">
<svg class="w-3 h-3 text-emerald-400 inline" fill="currentColor" viewbox="0 0 20 20">
<circle cx="10" cy="10" r="8"></circle>
</svg>
<a class="hover:text-white underline-offset-2 hover:underline" href="{{ config('services.google.sheets_url', '#') }}" target="_blank" rel="noreferrer">Live Synced (Google Sheets DB)</a>
</span>
</div>
</div>
</header>
<!-- END: TopAppHeader -->
<!-- BEGIN: QRScannerModal -->
<div id="qr-scanner-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/75 p-4" role="dialog" aria-modal="true" aria-labelledby="qr-scanner-title">
  <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
    <div class="flex items-center justify-between bg-campus-navy px-4 py-3 text-white">
      <div>
        <h2 id="qr-scanner-title" class="text-sm font-black">Scan Visitor QR Code</h2>
        <p id="qr-scanner-status" class="text-[11px] text-sky-200">Choose an action, then allow camera access.</p>
      </div>
      <button id="close-qr-scanner" class="rounded-full px-2 py-1 text-xl leading-none hover:bg-white/10" type="button" aria-label="Close scanner">×</button>
    </div>
    <div class="space-y-3 p-4">
      <div class="grid grid-cols-2 gap-2">
        <button id="qr-checkin-action" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white" type="button">Check In</button>
        <button id="qr-checkout-action" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white" type="button">Check Out</button>
      </div>
      <div class="relative overflow-hidden rounded-xl bg-slate-900">
        <video id="qr-camera" class="aspect-square w-full object-cover" autoplay muted playsinline></video>
        <div class="pointer-events-none absolute inset-10 rounded-xl border-2 border-white/80"></div>
      </div>
      <p class="text-center text-xs text-slate-500">Point the camera at the visitor QR code.</p>
      <button id="exit-qr-scanner" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50" type="button">Exit Scanner</button>
    </div>
  </div>
</div>
<!-- END: QRScannerModal -->
<!-- BEGIN: VisitorPassModal -->
<div id="visitor-pass-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/75 p-4" role="dialog" aria-modal="true" aria-labelledby="visitor-pass-title">
  <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
    <div class="flex items-center justify-between bg-campus-navy px-4 py-3 text-white">
      <div>
        <h2 id="visitor-pass-title" class="text-sm font-black">Visitor QR Pass</h2>
        <p class="text-[11px] text-sky-200">Load the unique QR code from Visitor Database.</p>
      </div>
      <button id="close-visitor-pass" class="rounded-full px-2 py-1 text-xl leading-none hover:bg-white/10" type="button" aria-label="Close pass">×</button>
    </div>
    <div class="space-y-3 p-4">
      <label class="block text-xs font-bold text-slate-700" for="pass-visitor-id">Visitor ID</label>
      <div class="flex gap-2">
        <input id="pass-visitor-id" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm" placeholder="VIS-000125" autocomplete="off">
        <button id="load-visitor-pass" class="rounded-lg bg-campus-blue px-3 py-2 text-xs font-bold text-white" type="button">Load QR</button>
      </div>
      <p id="visitor-pass-status" class="text-xs text-slate-500">Enter the Visitor ID printed on the registration record.</p>
      <div id="visitor-pass-preview" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
        <img id="visitor-pass-qr" class="mx-auto h-48 w-48 rounded-lg bg-white p-2" alt="Visitor QR code">
        <p id="visitor-pass-name" class="mt-3 text-sm font-black text-campus-navy"></p>
        <p id="visitor-pass-id" class="font-mono text-xs text-slate-500"></p>
        <div class="mt-4 grid grid-cols-2 gap-2">
          <button id="print-visitor-pass" class="rounded-lg bg-campus-navy px-3 py-2 text-xs font-bold text-white" type="button">Print Pass</button>
          <button id="email-visitor-pass" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white" type="button">Email Pass</button>
        </div>
        <div id="visitor-email-panel" class="mt-3 hidden text-left">
          <label class="block text-xs font-bold text-slate-700" for="visitor-pass-email">Email address</label>
          <div class="mt-1 flex gap-2">
            <input id="visitor-pass-email" type="email" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm" placeholder="visitor@example.com">
            <button id="send-visitor-pass" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white" type="button">Send</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- END: VisitorPassModal -->
<!-- BEGIN: MainContent -->
<main class="w-full max-w-none px-4 py-6 sm:px-6 lg:px-8 space-y-5">
<!-- BEGIN: EmergencyEvacuationBanner -->
<!-- Emergency Evacuation & Fire Drill Alert Card -->
<section class="emergency-glow bg-gradient-to-r from-red-600 via-rose-600 to-red-700 rounded-xl p-3.5 text-white shadow-lg relative overflow-hidden" data-purpose="evacuation-banner">
<div class="absolute -right-6 -bottom-6 opacity-15 pointer-events-none">
<svg class="w-32 h-32 text-white fill-current" viewbox="0 0 24 24">
<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"></path>
</svg>
</div>
<div class="flex items-start justify-between relative z-10">
<div class="space-y-0.5">
<div class="flex items-center space-x-1.5">
<span class="text-xs text-rose-100 font-medium">Muster Headcount</span>
</div>
<h2 class="text-base font-black tracking-wide text-white">FIRE DRILL ACCOUNTABILITY</h2>
<p id="banner-sync-status" class="text-[10px] text-rose-100">Connecting to Google Sheets…</p>
</div>
</div>
</section>
<!-- END: EmergencyEvacuationBanner -->
<!-- BEGIN: KeyMetricsBar -->
<!-- 4-Tile Live Counter -->
<section class="grid grid-cols-4 gap-2" data-purpose="metrics-summary-bar">
<!-- Total Visitors Registered -->
<div class="bg-white rounded-xl p-2.5 shadow-sm border border-slate-200 text-center flex flex-col justify-between">
<span class="text-[10px] font-semibold text-slate-500 uppercase tracking-tighter line-clamp-1">Registered</span>
<span id="summary-registered" class="text-xl font-bold text-sky-800 my-0.5">—</span>
</div>
<!-- Today's visitor records -->
<div class="bg-white rounded-xl p-2.5 shadow-sm border-2 border-emerald-500/50 bg-emerald-50/20 text-center flex flex-col justify-between">
<span class="text-[10px] font-bold text-emerald-800 uppercase tracking-tighter line-clamp-1">Inside</span>
<span id="summary-inside" class="text-xl font-black text-emerald-600 my-0.5">—</span>
<span class="text-[9px] text-emerald-600 font-semibold">On Campus</span>
</div>
<!-- Checked Out -->
<div class="bg-white rounded-xl p-2.5 shadow-sm border border-slate-200 text-center flex flex-col justify-between">
<span class="text-[10px] font-semibold text-slate-500 uppercase tracking-tighter line-clamp-1">Exited</span>
<span id="summary-checked-out" class="text-xl font-bold text-slate-700 my-0.5">—</span>
<span class="text-[9px] text-rose-500 font-medium">Status: OUT</span>
</div>
<!-- Accountability % -->
<div class="bg-white rounded-xl p-2.5 shadow-sm border border-slate-200 text-center flex flex-col justify-between">
<span class="text-[10px] font-semibold text-slate-500 uppercase tracking-tighter line-clamp-1">Safety Rate</span>
<span id="summary-safety-rate" class="text-xl font-extrabold text-blue-600 my-0.5">—%</span>
<span id="summary-safety-detail" class="text-[9px] text-emerald-600 font-bold">—/— Safe</span>
</div>
</section>
<!-- END: KeyMetricsBar -->
<!-- BEGIN: PrimaryActionWorkflows -->
<section class="space-y-2" data-purpose="primary-quick-actions">
<div class="flex items-center justify-between px-0.5">
<h3 class="text-sm font-bold tracking-wider text-slate-500 uppercase">Security Actions</h3>
</div>
<div class="grid grid-cols-3 gap-2.5">
<a href="{{ config('services.google.form_url', '#') }}" target="_blank" rel="noreferrer" data-google-form="{{ config('services.google.form_url', '#') }}" class="bg-campus-navy active:bg-slate-900 text-white p-3 rounded-xl shadow-sm flex flex-col items-center justify-center text-center group transition-transform active:scale-95">
<div class="w-8 h-8 rounded-full bg-blue-500/20 text-sky-300 flex items-center justify-center mb-1.5 group-hover:scale-110 transition-transform">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24">
<path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
</svg>
</div>
<span class="text-xs font-bold leading-tight">Register Visitor</span>
<span class="text-[9px] text-sky-200/70 mt-0.5 font-normal">Google Form</span>
</a>
<button id="scan-qr-button" data-qr-endpoint="{{ config('services.google.qr_endpoint', '') }}" class="bg-campus-blue active:bg-sky-800 text-white p-3 rounded-xl shadow-sm flex flex-col items-center justify-center text-center group transition-transform active:scale-95 border border-sky-400/30" type="button">
<div class="w-8 h-8 rounded-full bg-sky-400/20 text-sky-200 flex items-center justify-center mb-1.5 group-hover:scale-110 transition-transform">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24">
<path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
</svg>
</div>
<span class="text-xs font-bold leading-tight">Scan QR Code</span>
<span class="text-[9px] text-sky-200/70 mt-0.5 font-normal">Check-in / Out</span>
</button>
<button id="print-pass-button" class="bg-white active:bg-slate-50 text-campus-navy p-3 rounded-xl shadow-sm border border-slate-200 flex flex-col items-center justify-center text-center group transition-transform active:scale-95" type="button">
<div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-1.5 group-hover:scale-110 transition-transform">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24">
<path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
</svg>
</div>
<span class="text-xs font-bold leading-tight">Print / Pass</span>
<span class="text-[9px] text-slate-500 mt-0.5 font-normal">Digital SMS/Print</span>
</button>
</div>
</section>
<!-- END: PrimaryActionWorkflows -->
<!-- BEGIN: FireDrillEmergencyDashboard -->
<section id="evacuation" class="overflow-hidden rounded-xl border border-sky-200 bg-white shadow-sm" data-purpose="fire-drill-dashboard">
  <div class="flex items-center gap-3 bg-gradient-to-r from-red-700 via-red-600 to-rose-600 px-4 py-3 text-white">
    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-xl">🔥</span>
    <div>
      <h2 class="text-sm font-black uppercase tracking-wide sm:text-base">Fire Drill / Emergency Mode</h2>
      <p id="dashboard-updated-at" class="text-[10px] text-red-100">Waiting for Google Sheets sync…</p>
    </div>
  </div>

  <div class="grid grid-cols-1 gap-4 p-3 lg:grid-cols-[minmax(0,1fr)_220px]">
    <div class="overflow-hidden rounded-lg border border-slate-200">
      <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
        <h3 id="dashboard-inside-heading" class="text-xs font-black uppercase tracking-wider text-slate-700">Today's Visitors</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11px]">
          <thead class="bg-slate-100 text-[10px] uppercase tracking-wide text-slate-600">
            <tr>
              <th class="px-3 py-2 font-bold">Visitor ID</th>
              <th class="px-3 py-2 font-bold">Name</th>
              <th class="px-3 py-2 font-bold">Company</th>
              <th class="px-3 py-2 font-bold">Location</th>
              <th class="px-3 py-2 font-bold">Check-in</th>
              <th class="px-3 py-2 font-bold">Check-out</th>
              <th class="px-3 py-2 font-bold">Accountability</th>
            </tr>
          </thead>
          <tbody id="dashboard-inside-body" class="divide-y divide-slate-100 text-slate-700">
            <tr><td colspan="7" class="px-3 py-4 text-center text-slate-400">Connecting to Google Sheets…</td></tr>
          </tbody>
        </table>
      </div>
      <p id="dashboard-more" class="border-t border-slate-100 px-3 py-2 text-[10px] font-medium text-slate-500"></p>
    </div>

    <aside class="grid grid-cols-2 gap-3 lg:grid-cols-1">
      <div class="flex flex-col items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-center">
        <span class="mb-2 flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500 text-lg text-white">✓</span>
        <span class="text-[10px] font-black uppercase text-emerald-800">Accounted</span>
        <strong id="dashboard-accounted" class="text-2xl font-black text-emerald-700">— / —</strong>
        <span id="dashboard-accounted-rate" class="mt-2 flex h-14 w-14 items-center justify-center rounded-full border-4 border-emerald-500 text-sm font-black text-emerald-700">—%</span>
      </div>
      <div class="flex flex-col items-center justify-center rounded-lg border border-rose-200 bg-rose-50 p-4 text-center">
        <span class="mb-2 flex h-8 w-8 items-center justify-center rounded-full bg-rose-600 text-lg font-black text-white">!</span>
        <span class="text-[10px] font-black uppercase text-rose-800">Unaccounted</span>
        <strong id="dashboard-unaccounted" class="text-2xl font-black text-rose-700">—</strong>
      </div>
    </aside>
  </div>
</section>
<!-- END: FireDrillEmergencyDashboard -->
</main>
<!-- END: MainContent -->
<!-- BEGIN: BottomNavigation -->
<!-- Mobile iOS style persistent tab navigation -->
<nav class="hidden" data-purpose="bottom-tab-bar">
<div class="flex items-center justify-around">
<!-- 1. Dashboard -->
<a aria-current="page" class="flex flex-col items-center py-1 px-2 text-campus-blue" href="#dashboard">
<svg class="w-5 h-5" fill="currentColor" viewbox="0 0 20 20">
<path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
</svg>
<span class="text-[10px] font-bold mt-0.5">Dashboard</span>
</a>
<!-- 2. Visitors -->
<a class="flex flex-col items-center py-1 px-2 text-slate-400 hover:text-campus-blue transition-colors" href="#visitors">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
<path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
</svg>
<span class="text-[10px] font-medium mt-0.5">Visitors (18)</span>
</a>
<!-- 4. Evacuation Mode -->
<a class="flex flex-col items-center py-1 px-2 text-red-600 hover:text-red-700 transition-colors" href="#evacuation">
<div class="relative">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
<path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
</svg>
<span class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-red-600 animate-ping"></span>
</div>
<span class="text-[10px] font-bold mt-0.5">Evacuate</span>
</a>
<!-- 5. Settings / Logs -->
<a class="flex flex-col items-center py-1 px-2 text-slate-400 hover:text-campus-blue transition-colors" href="#settings">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
<path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
<path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
</svg>
<span class="text-[10px] font-medium mt-0.5">Admin</span>
</a>
</div>
</nav>
<!-- END: BottomNavigation -->
<!-- BEGIN: InteractiveScripts -->
<script data-purpose="ui-interactions">
    // Add lightweight feedback and connect the dashboard actions.
    document.addEventListener('DOMContentLoaded', () => {
      const buttons = document.querySelectorAll('button');
      buttons.forEach(button => {
        button.addEventListener('click', () => {
          // Visual click feedback
          button.classList.add('opacity-80');
          setTimeout(() => button.classList.remove('opacity-80'), 150);
        });
      });

      const showMessage = (message) => window.alert(message);
      const googleForm = document.querySelector('[data-google-form]');
      if (googleForm?.dataset.googleForm.includes('YOUR_FORM_ID')) {
        googleForm.addEventListener('click', (event) => {
          event.preventDefault();
          showMessage('Add your real Google Form URL to GOOGLE_FORM_URL in .env before registering visitors.');
        });
      }

      const scannerModal = document.querySelector('#qr-scanner-modal');
      const camera = document.querySelector('#qr-camera');
      const scannerStatus = document.querySelector('#qr-scanner-status');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
      let cameraStream = null;
      let scanAction = 'checkout';
      let scanFrame = null;
      let barcodeDetector = null;
      let scanSubmitting = false;
      const scanCanvas = document.createElement('canvas');
      const scanContext = scanCanvas.getContext('2d', {willReadFrequently: true});

      const stopScanner = () => {
        if (scanFrame) cancelAnimationFrame(scanFrame);
        scanFrame = null;
        cameraStream?.getTracks().forEach(track => track.stop());
        cameraStream = null;
        if (camera) camera.srcObject = null;
        scannerModal?.classList.add('hidden');
        scannerModal?.classList.remove('flex');
        scanSubmitting = false;
      };

      const submitScan = async (rawValue) => {
        if (scanSubmitting) return;
        const match = String(rawValue).match(/VIS-\d+/i);
        const visitorId = match ? match[0].toUpperCase() : String(rawValue).trim();
        if (!visitorId) return;
        // A QR read is complete as soon as a valid ID is captured. Stop the
        // camera immediately so the user gets feedback without waiting for
        // the Google Sheets round-trip.
        stopScanner();
        scanSubmitting = true;
        scannerStatus.textContent = `Saving ${scanAction === 'checkin' ? 'check-in' : 'check-out'} for ${visitorId}…`;
        showMessage(`✓ ${visitorId} QR captured. Saving status…`);
        try {
          const response = await fetch('/api/visitor-status', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            body: JSON.stringify({visitor_id: visitorId, action: scanAction})
          });
          const data = await response.json();
          if (!response.ok || !data.ok) throw new Error(data.error || 'Google Sheets did not confirm the update.');
          stopScanner();
          const actionLabel = scanAction === 'checkin' ? 'checked in' : 'checked out';
          showMessage(`✓ ${visitorId} ${actionLabel} successfully. Google Sheets was updated.`);
          await refreshEmergencyDashboard();
        } catch (error) {
          scanSubmitting = false;
          scannerStatus.textContent = error.message || 'The Google Sheets update failed.';
          showMessage(error.message || 'The Google Sheets update failed.');
        }
      };

      const scanFrameLoop = async () => {
        if (!cameraStream || (!barcodeDetector && !window.jsQR)) return;
        try {
          let rawValue = '';
          if (barcodeDetector) {
            const codes = await barcodeDetector.detect(camera);
            rawValue = codes.length ? codes[0].rawValue : '';
          } else if (window.jsQR && camera.videoWidth) {
            scanCanvas.width = camera.videoWidth;
            scanCanvas.height = camera.videoHeight;
            scanContext.drawImage(camera, 0, 0, scanCanvas.width, scanCanvas.height);
            const code = window.jsQR(scanContext.getImageData(0, 0, scanCanvas.width, scanCanvas.height).data, scanCanvas.width, scanCanvas.height);
            rawValue = code?.data || '';
          }
          if (rawValue) {
            scanFrame = null;
            await submitScan(rawValue);
            return;
          }
        } catch (error) {
          scannerStatus.textContent = 'Unable to read the QR code. Keep it inside the frame.';
        }
        scanFrame = requestAnimationFrame(scanFrameLoop);
      };

      const openScanner = async () => {
        scannerModal?.classList.remove('hidden');
        scannerModal?.classList.add('flex');
        scannerStatus.textContent = `Scanning to ${scanAction === 'checkin' ? 'check in' : 'check out'}…`;
        if (!navigator.mediaDevices?.getUserMedia || (!('BarcodeDetector' in window) && !window.jsQR)) {
          scannerStatus.textContent = 'QR scanning is not supported in this browser.';
          return;
        }
        try {
          barcodeDetector = 'BarcodeDetector' in window ? new BarcodeDetector({formats: ['qr_code']}) : null;
          cameraStream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}, width: {ideal: 640}, height: {ideal: 480}}, audio: false});
          camera.srcObject = cameraStream;
          await camera.play();
          scanFrameLoop();
        } catch (error) {
          scannerStatus.textContent = 'Camera access was blocked. Allow camera access and try again.';
        }
      };

      document.querySelector('#qr-checkin-action')?.addEventListener('click', () => {
        scanAction = 'checkin';
        openScanner();
      });

      document.querySelector('#qr-checkout-action')?.addEventListener('click', () => {
        scanAction = 'checkout';
        openScanner();
      });
      document.querySelector('#close-qr-scanner')?.addEventListener('click', stopScanner);
      document.querySelector('#exit-qr-scanner')?.addEventListener('click', stopScanner);

      const dashboardEndpoint = '/api/dashboard';
      const escapeHtml = value => String(value ?? '').replace(/[&<>'\"]/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '\"': '&quot;'
      }[character]));

      const formatTime = value => value
        ? new Date(value).toLocaleTimeString('en-PH', {hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'})
        : '—';

      const isAccounted = visitor => String(visitor.Accounted || '').toUpperCase() === 'ACCOUNTED';

      const renderVisitorRow = visitor => {
        const visitorId = escapeHtml(visitor['Visitor ID']);
        const accounted = isAccounted(visitor);
        const selectedAccounted = accounted ? 'selected' : '';
        const selectedUnaccounted = accounted ? '' : 'selected';
        const colorClass = accounted ? 'text-emerald-700' : 'text-rose-700';

        const checkedOut = String(visitor.Status || '').toUpperCase() === 'OUT';
        const rowClass = checkedOut ? 'bg-slate-50 text-slate-500' : '';

        return `
          <tr class="${rowClass}">
            <td class="px-3 py-2 font-mono">${visitorId}</td>
            <td class="px-3 py-2 font-semibold">${escapeHtml(visitor.Name)}</td>
            <td class="px-3 py-2">${escapeHtml(visitor['Company/Organization'])}</td>
            <td class="px-3 py-2">${escapeHtml(visitor['Host/Location'])}</td>
            <td class="px-3 py-2">${formatTime(visitor['Check-in'])}</td>
            <td class="px-3 py-2">${formatTime(visitor['Check-out'])}</td>
            <td class="px-3 py-2">
              <select data-accountability-id="${visitorId}" ${checkedOut ? 'disabled' : ''} class="rounded-md border-slate-300 py-1 text-xs font-bold ${colorClass} ${checkedOut ? 'cursor-not-allowed opacity-60' : ''}">
                <option value="UNACCOUNTED" ${selectedUnaccounted}>Unaccounted</option>
                <option value="ACCOUNTED" ${selectedAccounted}>Accounted</option>
              </select>
            </td>
          </tr>`;
      };

      const renderVisitorTable = visitors => visitors.length
        ? visitors.slice(0, 10).map(renderVisitorRow).join('')
        : '<tr><td colspan="7" class="px-3 py-4 text-center text-slate-400">No visitors registered today.</td></tr>';

      let latestDashboardData = null;
      let lastVisitorTableSignature = '';

      const updateAccountabilitySummary = () => {
        const visitors = latestDashboardData?.visitors || [];
        const inside = Number(latestDashboardData?.inside ?? visitors.length);
        const accounted = visitors.filter(isAccounted).length;
        const unaccounted = Math.max(inside - accounted, 0);
        const rate = inside ? `${Math.round((accounted / inside) * 100)}%` : '—%';

        document.querySelector('#dashboard-accounted').textContent = `${accounted} / ${inside}`;
        document.querySelector('#dashboard-accounted-rate').textContent = rate;
        document.querySelector('#dashboard-unaccounted').textContent = unaccounted;
      };

      let dashboardRequest = null;
      const refreshEmergencyDashboard = async () => {
        if (!dashboardEndpoint || document.hidden || dashboardRequest) return;
        const controller = new AbortController();
        dashboardRequest = controller;
        try {
          const response = await fetch(dashboardEndpoint, {
            cache: 'no-store',
            signal: controller.signal
          });
          if (!response.ok) throw new Error('Dashboard sync failed');
          const data = await response.json();
          latestDashboardData = data;
          const visitors = Array.isArray(data.visitors) ? data.visitors : [];
          const registered = Number(data.registered ?? 0);
          const inside = Number(data.inside ?? 0);
          const checkedOut = Number(data.checked_out ?? 0);
          const accounted = Number(data.accounted ?? visitors.filter(visitor => isAccounted(visitor)).length);
          const unaccounted = Number(data.unaccounted ?? Math.max(inside - accounted, 0));
          const safetyRate = inside ? Math.round((accounted / inside) * 100) : 0;
          document.querySelector('#summary-registered').textContent = registered;
          document.querySelector('#summary-inside').textContent = inside;
          document.querySelector('#summary-checked-out').textContent = checkedOut;
          document.querySelector('#summary-safety-rate').textContent = `${safetyRate}%`;
          document.querySelector('#summary-safety-detail').textContent = `${accounted}/${inside} Safe`;
          const syncLabel = data.sync_warning ? 'Cached Google Sheets data' : 'Live Google Sheets sync';
          document.querySelector('#banner-sync-status').textContent = `${syncLabel} • ${new Date(data.updated_at || Date.now()).toLocaleTimeString('en-PH', {hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'})}`;
          document.querySelector('#dashboard-inside-heading').textContent = `Today’s Visitors (${visitors.length})`;
          document.querySelector('#dashboard-accounted').textContent = `${accounted} / ${inside}`;
          document.querySelector('#dashboard-accounted-rate').textContent = inside ? `${Math.round((accounted / inside) * 100)}%` : '—%';
          document.querySelector('#dashboard-unaccounted').textContent = unaccounted;
          document.querySelector('#dashboard-updated-at').textContent = `${syncLabel} • ${new Date(data.updated_at || Date.now()).toLocaleString('en-PH', {hour12: true, timeZone: 'Asia/Manila'})}`;
          const tableSignature = JSON.stringify(visitors.map(visitor => [
            visitor['Visitor ID'], visitor.Status, visitor.Accounted,
            visitor['Check-in'], visitor['Check-out']
          ]));
          if (tableSignature !== lastVisitorTableSignature) {
            document.querySelector('#dashboard-inside-body').innerHTML = renderVisitorTable(visitors);
            lastVisitorTableSignature = tableSignature;
          }
          document.querySelector('#dashboard-more').textContent = visitors.length > 10 ? `... and ${visitors.length - 10} more visitors today` : '';
        } catch (error) {
          if (error.name === 'AbortError') return;
          document.querySelector('#dashboard-updated-at').textContent = 'Google Sheets sync unavailable';
          document.querySelector('#banner-sync-status').textContent = 'Google Sheets sync unavailable';
        } finally {
          dashboardRequest = null;
        }
      };

      const updateAccountability = async event => {
        const selector = event.target.closest('[data-accountability-id]');
        if (!selector) return;
        const previousValue = selector.dataset.previousValue || 'UNACCOUNTED';
        selector.dataset.previousValue = selector.value;
        selector.classList.toggle('text-emerald-700', selector.value === 'ACCOUNTED');
        selector.classList.toggle('text-rose-700', selector.value !== 'ACCOUNTED');
        const visitor = latestDashboardData?.visitors?.find(item =>
          String(item['Visitor ID']) === selector.dataset.accountabilityId
        );

        if (visitor) {
          visitor.Accounted = selector.value;
          updateAccountabilitySummary();
        }

        selector.disabled = true;
        try {
          const response = await fetch('/api/visitor-status', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            body: JSON.stringify({visitor_id: selector.dataset.accountabilityId, action: 'accountability', accountability: selector.value})
          });
          const data = await response.json();
          if (!response.ok || !data.ok) throw new Error(data.error || 'Accountability update failed.');
          refreshEmergencyDashboard();
        } catch (error) {
          selector.value = previousValue;
          selector.classList.toggle('text-emerald-700', previousValue === 'ACCOUNTED');
          selector.classList.toggle('text-rose-700', previousValue !== 'ACCOUNTED');
          if (visitor) {
            visitor.Accounted = previousValue;
            updateAccountabilitySummary();
          }
          document.querySelector('#banner-sync-status').textContent = error.message || 'Accountability update failed.';
          refreshEmergencyDashboard();
        } finally {
          selector.disabled = false;
        }
      };

      document.querySelector('#dashboard-inside-body')?.addEventListener('change', updateAccountability);

      document.querySelector('#scan-qr-button')?.addEventListener('click', openScanner);

      const passModal = document.querySelector('#visitor-pass-modal');
      const passStatus = document.querySelector('#visitor-pass-status');
      const passPreview = document.querySelector('#visitor-pass-preview');
      const passIdInput = document.querySelector('#pass-visitor-id');
      let selectedPass = null;

      const closePassModal = () => {
        passModal?.classList.add('hidden');
        passModal?.classList.remove('flex');
      };

      const openPassModal = () => {
        passModal?.classList.remove('hidden');
        passModal?.classList.add('flex');
        passIdInput?.focus();
      };

      const loadVisitorPass = async () => {
        const visitorId = passIdInput.value.trim().toUpperCase();
        if (!visitorId) {
          passStatus.textContent = 'Enter a Visitor ID first.';
          return;
        }
        passStatus.textContent = 'Loading visitor from Google Sheets…';
        passPreview.classList.add('hidden');
        try {
          const response = await fetch('/api/visitor-pass', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            body: JSON.stringify({visitor_id: visitorId, action: 'lookup'})
          });
          const data = await response.json();
          if (!response.ok) throw new Error(data.error || 'Visitor was not found.');
          selectedPass = data;
          document.querySelector('#visitor-pass-qr').src = data.qr_url;
          document.querySelector('#visitor-pass-name').textContent = data.Name || 'Campus Visitor';
          document.querySelector('#visitor-pass-id').textContent = data['Visitor ID'];
          passPreview.classList.remove('hidden');
          passStatus.textContent = 'QR code loaded from Visitor Database.';
        } catch (error) {
          passStatus.textContent = error.message || 'Unable to load the visitor.';
        }
      };

      const printLoadedPass = () => {
        if (!selectedPass) return;
        const qrUrl = document.querySelector('#visitor-pass-qr').src;
        const printWindow = window.open('', '_blank', 'width=480,height=640');
        if (!printWindow) return showMessage('Please allow pop-ups to print the visitor pass.');
        printWindow.document.write(`<html><head><title>Visitor Pass ${escapeHtml(selectedPass['Visitor ID'])}</title><style>body{font-family:Arial,sans-serif;text-align:center;padding:32px;color:#0b2545}.pass{border:2px solid #0b2545;border-radius:16px;padding:24px;max-width:320px;margin:auto}img{width:240px;height:240px}.id{font-family:monospace;font-weight:bold}</style></head><body><div class="pass"><h2>XYZ COLLEGE</h2><p>Campus Visitor</p><img src="${qrUrl}" alt="QR code"><h3>${escapeHtml(selectedPass.Name || 'Visitor')}</h3><p class="id">${escapeHtml(selectedPass['Visitor ID'])}</p><p>Please scan when entering and exiting.</p></div><script>window.onload=()=>window.print();<\/script></body></html>`);
        printWindow.document.close();
      };

      document.querySelector('#print-pass-button')?.addEventListener('click', openPassModal);
      document.querySelector('#close-visitor-pass')?.addEventListener('click', closePassModal);
      document.querySelector('#load-visitor-pass')?.addEventListener('click', loadVisitorPass);
      passIdInput?.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
          loadVisitorPass();
        }
      });
      document.querySelector('#print-visitor-pass')?.addEventListener('click', printLoadedPass);
      document.querySelector('#email-visitor-pass')?.addEventListener('click', () => document.querySelector('#visitor-email-panel')?.classList.toggle('hidden'));
      document.querySelector('#send-visitor-pass')?.addEventListener('click', async () => {
        const email = document.querySelector('#visitor-pass-email').value.trim();
        if (!selectedPass || !email) return showMessage('Enter the visitor email address first.');
        passStatus.textContent = 'Sending QR pass…';
        try {
          const response = await fetch('/api/visitor-pass', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            body: JSON.stringify({visitor_id: selectedPass['Visitor ID'], action: 'email', email})
          });
          const data = await response.json();
          if (!response.ok) throw new Error(data.error || 'Email could not be sent.');
          const sentTo = data.sent_to || data.email || email;
          passStatus.textContent = `✓ QR pass sent successfully to ${sentTo}. Returning to dashboard in 3 seconds…`;
          passStatus.classList.add('font-bold', 'text-emerald-600');
          window.setTimeout(() => {
            closePassModal();
            window.scrollTo({top: 0, behavior: 'smooth'});
          }, 3000);
        } catch (error) {
          passStatus.textContent = error.message || 'Email could not be sent.';
        }
      });
      refreshEmergencyDashboard();
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshEmergencyDashboard();
      });
      window.setInterval(refreshEmergencyDashboard, 10000);
    });
  </script>
<!-- END: InteractiveScripts -->
</body></html>
