<?php
/**
 * Main Live Dashboard
 * IoT Smart Water Quality Management System
 */

require_once __DIR__ . '/includes/functions.php';

// Authentication requirement
require_login();

// Initial data load for immediate server-side render
$settings = get_settings();
$deviceState = get_device_state();
$recent = get_recent_sensor_records(3600);
$latest = !empty($recent) ? end($recent) : [
    'tds' => 180.0,
    'turbidity' => 1.2,
    'temperature' => 24.5,
    'ph' => 7.2,
    'score' => 96,
    'status' => 'good',
    'timestamp' => date(DATETIME_FORMAT),
    'issues' => [],
    'valve_state' => 1,
    'valve_mode' => 'auto'
];

$isValveOn = !empty($deviceState['valve_state']);
$isAuto = ($deviceState['valve_mode'] === 'auto');

require_once __DIR__ . '/includes/header.php';
?>

<!-- EMERGENCY ALERT BANNER (Revealed dynamically or SSR when quality is bad) -->
<div id="emergencyAlertBanner" class="<?= ($latest['status'] === 'bad') ? '' : 'hidden' ?> mb-6 water-card p-5 border-red-500/60 bg-red-950/70 alert-banner-pulsing">
  <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
    <div class="flex items-start gap-4">
      <div class="p-3 bg-red-600/30 rounded-2xl border border-red-500 text-red-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h3 class="text-xl font-black text-red-200 tracking-wide">WATER QUALITY CRITICAL ALERT!</h3>
          <span class="badge badge-error uppercase font-mono text-xs">Emergency Triggered</span>
        </div>
        <p class="text-sm text-red-300 mt-1 font-medium">Contamination or abnormal parameters detected. Solenoid Valve action engaged.</p>
        <ul id="alertIssuesList" class="mt-2 text-xs font-mono text-red-200 space-y-1">
          <?php if (!empty($latest['issues']) && is_array($latest['issues'])): ?>
            <?php foreach ($latest['issues'] as $issue): ?>
              <li class="flex items-center gap-2">⚠️ <?= htmlspecialchars($issue) ?></li>
            <?php endforeach; ?>
          <?php else: ?>
            <li class="flex items-center gap-2">⚠️ Threshold limit exceeded</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <a href="settings.php" class="btn btn-sm btn-outline border-red-400 text-red-200 hover:bg-red-800">Adjust Thresholds</a>
    </div>
  </div>
</div>

<!-- TOP STATUS & OVERVIEW BAR -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center gap-3">
      Live Water Telemetry
      <span class="text-xs px-2.5 py-1 rounded-full bg-sky-500/20 text-sky-300 border border-sky-500/30 font-mono font-normal">
        10s Interval
      </span>
    </h1>
    <p class="text-slate-400 text-xs sm:text-sm mt-1">Real-time biochemical sensor metrics & automated valve actuation</p>
  </div>

  <div class="flex items-center flex-wrap gap-2.5">
    <!-- Live Heartbeat Indicator -->
    <div class="water-card px-3.5 py-2 flex items-center gap-2.5 text-xs">
      <span id="deviceOnlineDot" class="w-2.5 h-2.5 rounded-full bg-emerald-400 pulse-indicator"></span>
      <span id="deviceOnlineText" class="font-semibold text-emerald-400">ESP32 Connected</span>
      <span class="text-slate-600">|</span>
      <span class="text-slate-400 font-mono text-[11px]" id="lastSyncText"><?= htmlspecialchars(format_datetime(isset($latest['epoch']) ? $latest['epoch'] : (isset($latest['timestamp']) ? $latest['timestamp'] : time()))) ?></span>
    </div>

    <!-- Quick Simulation Tools -->
    <div class="dropdown dropdown-end">
      <div tabindex="0" role="button" class="btn btn-sm btn-outline border-sky-600/50 text-sky-300 hover:bg-sky-900/30 gap-1.5">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-sky-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
        <span>Test Hardware Sim</span>
      </div>
      <ul tabindex="0" class="dropdown-content z-[2] menu p-2 shadow-2xl bg-slate-900 rounded-box w-64 border border-sky-800 text-xs mt-2">
        <li class="menu-title text-sky-400">Simulate Hardware Feeds</li>
        <li><button id="btnSimNormal" class="text-emerald-400 hover:bg-emerald-950/40">💧 Inject Safe Normal Water</button></li>
        <li><button id="btnSimBad" class="text-rose-400 hover:bg-rose-950/40">🚨 Inject Contaminated (Bad) Water</button></li>
      </ul>
    </div>
  </div>
</div>

<!-- SENSOR GAUGES GRID (4 PRIMARY SENSORS) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
  
  <!-- SENSOR 1: TDS (Total Dissolved Solids) -->
  <div class="water-card p-5 relative overflow-hidden group">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl bg-sky-500/15 border border-sky-500/30 flex items-center justify-center text-sky-400">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
        <div>
          <span class="text-xs uppercase font-bold tracking-wider text-slate-400">TDS Sensor</span>
          <div class="text-[11px] text-slate-500 font-mono">Pin 34 (Analog)</div>
        </div>
      </div>
      <span id="badgeTds" class="badge badge-success gap-1 text-xs font-semibold">Safe</span>
    </div>

    <div class="mt-4 flex items-baseline justify-between">
      <div>
        <span id="valTds" class="text-3xl font-extrabold metric-value text-sky-400"><?= number_format($latest['tds'], 1) ?></span>
        <span class="text-xs text-slate-400 font-medium ml-1">ppm</span>
      </div>
      <div class="text-right text-[11px] text-slate-500">
        Safe: &lt; <?= $settings['thresholds']['tds_max'] ?> ppm
      </div>
    </div>

    <div class="w-full bg-slate-800/80 rounded-full h-1.5 mt-3 overflow-hidden">
      <div class="bg-gradient-to-r from-cyan-500 to-sky-400 h-1.5 rounded-full transition-all duration-500" style="width: <?= min(100, ($latest['tds'] / $settings['thresholds']['tds_max']) * 100) ?>%"></div>
    </div>
  </div>

  <!-- SENSOR 2: Turbidity Sensor -->
  <div class="water-card p-5 relative overflow-hidden group">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-400">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
          <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Turbidity</span>
          <div class="text-[11px] text-slate-500 font-mono">Pin 35 (Analog)</div>
        </div>
      </div>
      <span id="badgeTurbidity" class="badge badge-success gap-1 text-xs font-semibold">Clear</span>
    </div>

    <div class="mt-4 flex items-baseline justify-between">
      <div>
        <span id="valTurbidity" class="text-3xl font-extrabold metric-value text-amber-400"><?= number_format($latest['turbidity'], 2) ?></span>
        <span class="text-xs text-slate-400 font-medium ml-1">NTU</span>
      </div>
      <div class="text-right text-[11px] text-slate-500">
        Max: <?= $settings['thresholds']['turbidity_max'] ?> NTU
      </div>
    </div>

    <div class="w-full bg-slate-800/80 rounded-full h-1.5 mt-3 overflow-hidden">
      <div class="bg-gradient-to-r from-amber-500 to-yellow-300 h-1.5 rounded-full transition-all duration-500" style="width: <?= min(100, ($latest['turbidity'] / $settings['thresholds']['turbidity_max']) * 100) ?>%"></div>
    </div>
  </div>

  <!-- SENSOR 3: pH Sensor -->
  <div class="water-card p-5 relative overflow-hidden group">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 2v7.31L4 19h16l-6-9.69V2z"/></svg>
        </div>
        <div>
          <span class="text-xs uppercase font-bold tracking-wider text-slate-400">pH Level</span>
          <div class="text-[11px] text-slate-500 font-mono">Pin 32 (Po)</div>
        </div>
      </div>
      <span id="badgePh" class="badge badge-success gap-1 text-xs font-semibold">Neutral 7.2</span>
    </div>

    <div class="mt-4 flex items-baseline justify-between">
      <div>
        <span id="valPh" class="text-3xl font-extrabold metric-value text-emerald-400"><?= number_format($latest['ph'], 2) ?></span>
        <span class="text-xs text-slate-400 font-medium ml-1">pH</span>
      </div>
      <div class="text-right text-[11px] text-slate-500">
        Range: <?= $settings['thresholds']['ph_min'] ?> - <?= $settings['thresholds']['ph_max'] ?>
      </div>
    </div>

    <div class="w-full bg-slate-800/80 rounded-full h-1.5 mt-3 overflow-hidden">
      <div id="phProgressBar" class="bg-gradient-to-r from-emerald-500 to-teal-300 h-1.5 rounded-full transition-all duration-500" style="width: <?= min(100, ($latest['ph'] / 14) * 100) ?>%"></div>
    </div>
  </div>

  <!-- SENSOR 4: DS18B20 Temperature Sensor -->
  <div class="water-card p-5 relative overflow-hidden group">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl bg-orange-500/15 border border-orange-500/30 flex items-center justify-center text-orange-400">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>
        </div>
        <div>
          <span class="text-xs uppercase font-bold tracking-wider text-slate-400">Temperature</span>
          <div class="text-[11px] text-slate-500 font-mono">Pin 4 (OneWire)</div>
        </div>
      </div>
      <span id="badgeTemp" class="badge badge-info gap-1 text-xs font-semibold">Normal</span>
    </div>

    <div class="mt-4 flex items-baseline justify-between">
      <div>
        <span id="valTemp" class="text-3xl font-extrabold metric-value text-orange-400"><?= number_format($latest['temperature'], 1) ?></span>
        <span class="text-xs text-slate-400 font-medium ml-1">°C</span>
      </div>
      <div class="text-right text-[11px] text-slate-500">
        Safe: <?= $settings['thresholds']['temp_min'] ?> - <?= $settings['thresholds']['temp_max'] ?> °C
      </div>
    </div>

    <div class="w-full bg-slate-800/80 rounded-full h-1.5 mt-3 overflow-hidden">
      <div class="bg-gradient-to-r from-orange-500 to-amber-300 h-1.5 rounded-full transition-all duration-500" style="width: <?= min(100, ($latest['temperature'] / 50) * 100) ?>%"></div>
    </div>
  </div>

</div>

<!-- ROW 2: SOLENOID VALVE RELAY 1 CONTROL & WATER QUALITY INDEX -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
  
  <!-- ACTUATOR: SOLENOID VALVE RELAY 1 (AUTOMATIC + MANUAL OVERRIDE) -->
  <div id="solenoidValveCard" class="water-card p-6 lg:col-span-2 <?= $isValveOn ? 'valve-on-glow' : 'valve-off-glow' ?> flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-2xl bg-cyan-500/15 border border-cyan-500/40 flex items-center justify-center text-cyan-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
          </div>
          <div>
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
              Solenoid Valve (Relay 1)
              <span id="valveModeBadge" class="badge <?= $isAuto ? 'badge-info' : 'badge-warning' ?> badge-outline font-mono text-xs uppercase">
                Mode: <?= strtoupper($deviceState['valve_mode']) ?>
              </span>
            </h3>
            <p class="text-xs text-slate-400 font-mono">ESP32 Pin 26 • Active LOW (LOW = Flow Enabled)</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button id="btnSwitchMode" class="btn btn-sm btn-ghost border border-slate-700 hover:border-sky-500 text-xs">
            <?= $isAuto ? 'Switch to Manual Mode' : 'Switch to Auto Mode' ?>
          </button>
        </div>
      </div>

      <div class="mt-6 p-4 rounded-xl bg-slate-900/60 border border-slate-800/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="w-3.5 h-3.5 rounded-full <?= $isValveOn ? 'bg-emerald-400 animate-pulse shadow-lg shadow-emerald-500/50' : 'bg-rose-500' ?>"></div>
          <div>
            <div id="valveStatusText" class="<?= $isValveOn ? 'text-lg font-bold text-emerald-400' : 'text-lg font-bold text-rose-400' ?> tracking-wide">
              <?= $isValveOn ? 'VALVE OPEN (WATER FLOW ENABLED)' : 'VALVE SHUT (FLOW CUT OFF)' ?>
            </div>
            <div class="text-xs text-slate-400">
              <?= $isAuto ? 'Automated protection is active. Valve shuts off if water quality becomes bad.' : 'Manual override active. Operator holds direct control.' ?>
            </div>
          </div>
        </div>

        <div>
          <button id="btnToggleValve" <?= $isAuto ? 'disabled' : '' ?> class="btn <?= $isValveOn ? 'btn-error' : 'btn-success' ?> btn-sm font-semibold shadow-md shadow-emerald-500/10">
            <?= $isValveOn ? 'Emergency Close Valve' : 'Open Valve (Enable Flow)' ?>
          </button>
        </div>
      </div>
    </div>

    <div class="mt-4 pt-3 border-t border-slate-800/60 flex items-center justify-between text-[11px] text-slate-500 font-mono">
      <span>Auto-Protection Rule: Shutoff on TDS &gt; <?= $settings['thresholds']['tds_max'] ?> ppm | Turbidity &gt; <?= $settings['thresholds']['turbidity_max'] ?> NTU</span>
      <span class="text-sky-400 hover:underline"><a href="settings.php">Rule Settings &rarr;</a></span>
    </div>
  </div>

  <!-- WATER QUALITY INDEX SCORE -->
  <div class="water-card p-6 flex flex-col items-center justify-center text-center">
    <span class="text-xs uppercase font-bold tracking-wider text-slate-400 mb-2">Overall Water Quality Index</span>
    
    <?php $currentScore = intval(isset($latest['score']) ? $latest['score'] : 96); ?>
    <div id="scoreRadialProgress" class="radial-progress text-emerald-400 font-extrabold my-2" style="--value:<?= $currentScore ?>; --size:7.5rem; --thickness: 8px;" role="progressbar">
      <span id="valQualityScore" class="text-2xl font-mono"><?= $currentScore ?>%</span>
    </div>

    <div id="textQualityStatus" class="text-sm font-bold text-emerald-400 uppercase tracking-wide mt-1">
      <?= ($latest['status'] === 'bad') ? 'CRITICAL / CONTAMINATED' : (($latest['status'] === 'warning') ? 'ATTENTION REQUIRED' : 'EXCELLENT & SAFE') ?>
    </div>
    <div class="text-[11px] text-slate-400 mt-1 max-w-xs">
      Evaluated across TDS, NTU Turbidity, pH balance, and temperature standards
    </div>
  </div>

</div>

<!-- ROW 3: LINE GRAPH FOR ALL SENSORS (LAST 1 HOUR, REALTIME UPDATE 10 SEC) -->
<div class="water-card p-6 mb-6">
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">
    <div>
      <h3 class="text-lg font-bold text-white flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-sky-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        1-Hour Real-time Telemetry Trend
      </h3>
      <p class="text-xs text-slate-400">Continuous 10-second multi-parameter live stream</p>
    </div>

    <!-- Filter Buttons -->
    <div class="flex items-center gap-1 bg-slate-900/80 p-1 rounded-xl border border-slate-800 text-xs">
      <button data-metric="all" class="chart-filter-btn px-3 py-1.5 rounded-lg btn-active bg-cyan-500 text-white font-medium transition">All Sensors</button>
      <button data-metric="tds" class="chart-filter-btn px-3 py-1.5 rounded-lg text-slate-400 hover:text-white transition">TDS</button>
      <button data-metric="turbidity" class="chart-filter-btn px-3 py-1.5 rounded-lg text-slate-400 hover:text-white transition">Turbidity</button>
      <button data-metric="ph" class="chart-filter-btn px-3 py-1.5 rounded-lg text-slate-400 hover:text-white transition">pH</button>
      <button data-metric="temp" class="chart-filter-btn px-3 py-1.5 rounded-lg text-slate-400 hover:text-white transition">Temp</button>
    </div>
  </div>

  <div class="relative w-full h-72 sm:h-80">
    <canvas id="waterQualityChart"></canvas>
  </div>
</div>

<!-- HARDWARE DIAGNOSTICS MINI BAR -->
<div class="water-card p-4 flex flex-wrap items-center justify-between gap-4 text-xs font-mono text-slate-400">
  <div class="flex items-center gap-2">
    <span class="text-sky-400 font-bold">ESP32 30-Pin Diagnostics:</span>
    <span>CPU Temp: <strong id="espCpuTempVal" class="text-slate-200"><?= number_format($deviceState['cpu_temp'], 1) ?> °C</strong></span>
  </div>
  <div class="flex items-center gap-4">
    <span>Free RAM: <strong id="espRamVal" class="text-slate-200"><?= round($deviceState['free_ram'] / 1024) ?> KB Free</strong></span>
    <span>•</span>
    <span>Wi-Fi RSSI: <strong class="text-slate-200"><?= $deviceState['rssi'] ?> dBm</strong></span>
    <span>•</span>
    <a href="history.php" class="text-sky-400 hover:underline flex items-center gap-1 font-sans">
      View All Historical Logs &rarr;
    </a>
  </div>
</div>

<!-- Dashboard Controller Script -->
<script src="assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
