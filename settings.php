<?php
/**
 * System Settings & ESP32 Hardware Diagnostics
 * IoT Smart Water Quality Management System
 */

require_once __DIR__ . '/includes/functions.php';

// Authentication requirement
require_login();

$settings = get_settings();
$deviceState = get_device_state();
$successMsg = '';
$errorMsg = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // 1. Update General Branding & Logo
    if ($action === 'save_branding') {
        $settings['app_name'] = trim(isset($_POST['app_name']) ? $_POST['app_name'] : 'AquaSense IoT');
        $settings['footer_text'] = trim(isset($_POST['footer_text']) ? $_POST['footer_text'] : '');

        // Logo Upload Handling (Requirement 11)
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['logo_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'ico'];

            if (in_array($ext, $allowedExts)) {
                $filename = 'logo_' . time() . '.' . $ext;
                $targetPath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $settings['logo_path'] = 'uploads/' . $filename;
                } else {
                    $errorMsg = 'Failed to move uploaded logo file.';
                }
            } else {
                $errorMsg = 'Invalid file format. Please upload PNG, JPG, SVG or WEBP.';
            }
        }

        save_settings($settings);
        $successMsg = 'Branding & customization settings saved successfully!';
    }

    // 2. Update Sensor Thresholds (Requirement 9)
    elseif ($action === 'save_thresholds') {
        $settings['thresholds']['tds_min'] = floatval(isset($_POST['tds_min']) ? $_POST['tds_min'] : 50);
        $settings['thresholds']['tds_max'] = floatval(isset($_POST['tds_max']) ? $_POST['tds_max'] : 500);
        $settings['thresholds']['turbidity_max'] = floatval(isset($_POST['turbidity_max']) ? $_POST['turbidity_max'] : 5.0);
        $settings['thresholds']['temp_min'] = floatval(isset($_POST['temp_min']) ? $_POST['temp_min'] : 15.0);
        $settings['thresholds']['temp_max'] = floatval(isset($_POST['temp_max']) ? $_POST['temp_max'] : 35.0);
        $settings['thresholds']['ph_min'] = floatval(isset($_POST['ph_min']) ? $_POST['ph_min'] : 6.5);
        $settings['thresholds']['ph_max'] = floatval(isset($_POST['ph_max']) ? $_POST['ph_max'] : 8.5);

        save_settings($settings);
        $successMsg = 'Sensor threshold ranges updated successfully!';
    }

    // 3. Update Solenoid Valve Automation Rules (Requirement 9)
    elseif ($action === 'save_valve_rules') {
        $vMode = isset($_POST['valve_mode']) ? $_POST['valve_mode'] : 'auto';
        $settings['valve_control']['mode'] = ($vMode === 'manual') ? 'manual' : 'auto';
        $settings['valve_control']['auto_shutoff_on_bad'] = isset($_POST['auto_shutoff_on_bad']);
        $settings['valve_control']['auto_reopen_on_good'] = isset($_POST['auto_reopen_on_good']);

        save_settings($settings);
        $successMsg = 'Solenoid Valve automation rules saved!';
    }

    // 4. Update Telegram Settings (Requirement 9)
    elseif ($action === 'save_telegram') {
        $settings['telegram']['enabled'] = isset($_POST['telegram_enabled']);
        $settings['telegram']['bot_token'] = trim(isset($_POST['telegram_bot_token']) ? $_POST['telegram_bot_token'] : '');
        $settings['telegram']['chat_id'] = trim(isset($_POST['telegram_chat_id']) ? $_POST['telegram_chat_id'] : '');
        $settings['telegram']['cooldown_seconds'] = intval(isset($_POST['telegram_cooldown']) ? $_POST['telegram_cooldown'] : 300);

        save_settings($settings);
        $successMsg = 'Telegram notification settings updated!';
    }

    // 5. Update Wi-Fi Configuration for ESP32 (Requirement 12)
    elseif ($action === 'save_wifi') {
        $settings['wifi']['ssid'] = trim(isset($_POST['wifi_ssid']) ? $_POST['wifi_ssid'] : '');
        $settings['wifi']['password'] = trim(isset($_POST['wifi_password']) ? $_POST['wifi_password'] : '');
        $settings['wifi']['server_ip'] = trim(isset($_POST['server_ip']) ? $_POST['server_ip'] : '');
        $settings['wifi']['server_port'] = intval(isset($_POST['server_port']) ? $_POST['server_port'] : 80);
        $settings['wifi']['server_cookie'] = trim(isset($_POST['server_cookie']) ? $_POST['server_cookie'] : '');

        save_settings($settings);
        $successMsg = 'ESP32 Wi-Fi & Cloud Server credentials saved! ESP32 will synchronize on next query.';
    }

    // 6. Factory Reset (Requirement 38)
    elseif ($action === 'factory_reset') {
        reset_system_data();
        $successMsg = 'SYSTEM RESET APPLIED: All sensor records deleted. EEPROM erase command queued for ESP32.';
    }
}

// Refresh settings and device state after saves
$settings = get_settings();
$deviceState = get_device_state();

// Calculate ESP32 RAM usage
$totalRam = intval(isset($deviceState['total_ram']) ? $deviceState['total_ram'] : 327680);
$freeRam = intval(isset($deviceState['free_ram']) ? $deviceState['free_ram'] : 184320);
$usedRam = max(0, $totalRam - $freeRam);
$ramUsagePercent = round(($usedRam / $totalRam) * 100);

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
  <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center gap-3">
    System Settings & Hardware Diagnostics
  </h1>
  <p class="text-slate-400 text-xs sm:text-sm mt-1">Configure automation rules, telemetry limits, Telegram alerts, and inspect ESP32 micro-diagnostics</p>
</div>

<!-- ALERT MESSAGES -->
<?php if (!empty($successMsg)): ?>
  <div class="alert alert-success shadow-lg mb-6 text-sm font-medium border border-emerald-500/40">
    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-5 w-5" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    <span><?= htmlspecialchars($successMsg) ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
  <div class="alert alert-error shadow-lg mb-6 text-sm font-medium border border-rose-500/40">
    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-5 w-5" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    <span><?= htmlspecialchars($errorMsg) ?></span>
  </div>
<?php endif; ?>

<!-- ESP32 HARDWARE HEALTH DIAGNOSTICS (Requirement 14) -->
<div class="water-card p-6 mb-8 border-sky-500/40 bg-slate-900/90">
  <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-12 h-12 rounded-2xl bg-sky-500/20 border border-sky-400/40 flex items-center justify-center text-sky-300">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
      </div>
      <div>
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
          ESP32 30-Pin Hardware Telemetry & Health
          <span class="badge badge-success badge-sm font-mono text-[10px]">Active</span>
        </h2>
        <p class="text-xs text-slate-400 font-mono">Microcontroller diagnostic stream (Requirement #14)</p>
      </div>
    </div>
    
    <div class="text-right text-xs font-mono text-slate-400">
      Last Ping: <span class="text-sky-400 font-bold"><?= htmlspecialchars(isset($deviceState['last_ping']) ? $deviceState['last_ping'] : 'None') ?></span>
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- CPU Temperature -->
    <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
      <div class="text-xs text-slate-400 uppercase font-bold tracking-wider">CPU Temperature</div>
      <div class="mt-2 text-2xl font-mono font-bold text-orange-400">
        <?= number_format($deviceState['cpu_temp'], 1) ?> °C
      </div>
      <div class="text-[11px] text-slate-500 mt-1 font-mono">ESP32 Core Internal Sensor</div>
    </div>

    <!-- Free RAM / Memory -->
    <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
      <div class="text-xs text-slate-400 uppercase font-bold tracking-wider">Free Heap RAM</div>
      <div class="mt-2 text-2xl font-mono font-bold text-sky-400">
        <?= round($freeRam / 1024) ?> <span class="text-sm font-sans font-normal text-slate-400">KB Free</span>
      </div>
      <div class="w-full bg-slate-800 rounded-full h-1.5 mt-2">
        <div class="bg-sky-400 h-1.5 rounded-full" style="width: <?= $ramUsagePercent ?>%"></div>
      </div>
      <div class="text-[11px] text-slate-500 mt-1 font-mono">Usage: <?= $ramUsagePercent ?>% of 320 KB</div>
    </div>

    <!-- WiFi Signal (RSSI) -->
    <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
      <div class="text-xs text-slate-400 uppercase font-bold tracking-wider">Wi-Fi RSSI Strength</div>
      <div class="mt-2 text-2xl font-mono font-bold text-emerald-400">
        <?= $deviceState['rssi'] ?> <span class="text-sm font-sans font-normal text-slate-400">dBm</span>
      </div>
      <div class="text-[11px] text-slate-500 mt-1 font-mono">Signal: Excellent Link</div>
    </div>

    <!-- System Uptime -->
    <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
      <div class="text-xs text-slate-400 uppercase font-bold tracking-wider">Device Uptime</div>
      <div class="mt-2 text-2xl font-mono font-bold text-cyan-300">
        <?php
          $uptime = intval(isset($deviceState['uptime_sec']) ? $deviceState['uptime_sec'] : 0);
          $hours = floor($uptime / 3600);
          $mins = floor(($uptime % 3600) / 60);
          $secs = $uptime % 60;
          echo sprintf("%02dh %02dm %02ds", $hours, $mins, $secs);
        ?>
      </div>
      <div class="text-[11px] text-slate-500 mt-1 font-mono">Node IP: <?= htmlspecialchars($deviceState['ip_address']) ?></div>
    </div>
  </div>
</div>

<!-- SETTINGS TABS & FORMS -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

  <!-- FORM 1: SENSOR THRESHOLD RANGES (Requirement 9) -->
  <div class="water-card p-6">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-sky-500/15 text-sky-400 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </div>
      <div>
        <h3 class="text-lg font-bold text-white">Water Quality Thresholds</h3>
        <p class="text-xs text-slate-400">Min and Max acceptable limits before bad water alert triggers</p>
      </div>
    </div>

    <form method="POST" action="settings.php" class="space-y-4">
      <input type="hidden" name="action" value="save_thresholds">

      <!-- TDS Limits -->
      <div class="grid grid-cols-2 gap-3">
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">TDS Min (ppm)</span></label>
          <input type="number" step="0.1" name="tds_min" value="<?= htmlspecialchars($settings['thresholds']['tds_min']) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700" required>
        </div>
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">TDS Max (ppm)</span></label>
          <input type="number" step="0.1" name="tds_max" value="<?= htmlspecialchars($settings['thresholds']['tds_max']) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700" required>
        </div>
      </div>

      <!-- Turbidity Limit -->
      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Turbidity Max (NTU)</span></label>
        <input type="number" step="0.1" name="turbidity_max" value="<?= htmlspecialchars($settings['thresholds']['turbidity_max']) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700" required>
      </div>

      <!-- pH Limits -->
      <div class="grid grid-cols-2 gap-3">
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">pH Min (Safe)</span></label>
          <input type="number" step="0.05" name="ph_min" value="<?= htmlspecialchars($settings['thresholds']['ph_min']) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700" required>
        </div>
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">pH Max (Safe)</span></label>
          <input type="number" step="0.05" name="ph_max" value="<?= htmlspecialchars($settings['thresholds']['ph_max']) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700" required>
        </div>
      </div>

      <!-- Temperature Limits -->
      <div class="grid grid-cols-2 gap-3">
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Temp Min (°C)</span></label>
          <input type="number" step="0.1" name="temp_min" value="<?= htmlspecialchars($settings['thresholds']['temp_min']) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700" required>
        </div>
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Temp Max (°C)</span></label>
          <input type="number" step="0.1" name="temp_max" value="<?= htmlspecialchars($settings['thresholds']['temp_max']) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700" required>
        </div>
      </div>

      <button type="submit" class="btn btn-sm btn-info w-full mt-2 font-semibold">Save Threshold Ranges</button>
    </form>
  </div>

  <!-- FORM 2: SOLENOID VALVE RELAY 1 AUTOMATION RULES (Requirement 9) -->
  <div class="water-card p-6">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-cyan-500/15 text-cyan-400 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
      </div>
      <div>
        <h3 class="text-lg font-bold text-white">Solenoid Valve Automation (Relay 1)</h3>
        <p class="text-xs text-slate-400">Automatic valve behavior based on live sensor readings</p>
      </div>
    </div>

    <form method="POST" action="settings.php" class="space-y-4">
      <input type="hidden" name="action" value="save_valve_rules">

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Default Valve Operating Mode</span></label>
        <select name="valve_mode" class="select select-bordered select-sm bg-slate-900 border-slate-700">
          <option value="auto" <?= ($settings['valve_control']['mode'] === 'auto') ? 'selected' : '' ?>>Automatic (Sensor Driven)</option>
          <option value="manual" <?= ($settings['valve_control']['mode'] === 'manual') ? 'selected' : '' ?>>Manual Override (User Controls Button)</option>
        </select>
      </div>

      <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800 space-y-3">
        <label class="cursor-pointer flex items-center justify-between">
          <span class="text-xs text-slate-300">
            <strong>Auto Shut-Off on Bad Water:</strong><br>
            <span class="text-[11px] text-slate-500">Instantly shut Solenoid Valve if TDS/Turbidity/pH violates thresholds.</span>
          </span>
          <input type="checkbox" name="auto_shutoff_on_bad" class="toggle toggle-info toggle-sm" <?= !empty($settings['valve_control']['auto_shutoff_on_bad']) ? 'checked' : '' ?> />
        </label>

        <div class="divider my-1 opacity-20"></div>

        <label class="cursor-pointer flex items-center justify-between">
          <span class="text-xs text-slate-300">
            <strong>Auto Reopen when Quality Restores:</strong><br>
            <span class="text-[11px] text-slate-500">Automatically re-enable flow when water parameters return to safe range.</span>
          </span>
          <input type="checkbox" name="auto_reopen_on_good" class="toggle toggle-info toggle-sm" <?= !empty($settings['valve_control']['auto_reopen_on_good']) ? 'checked' : '' ?> />
        </label>
      </div>

      <div class="p-3 rounded-lg bg-sky-950/30 border border-sky-800/40 text-[11px] font-mono text-sky-300">
        Relay 1 Logic: Active LOW on GPIO 26. When LOW: SV ON (Flow Enabled). When HIGH: SV OFF (Flow Cut Off).
      </div>

      <button type="submit" class="btn btn-sm btn-info w-full mt-2 font-semibold">Save Valve Automation Rules</button>
    </form>
  </div>

  <!-- FORM 3: TELEGRAM BOT NOTIFICATIONS (Requirement 9 & 8) -->
  <div class="water-card p-6">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-sky-400/15 text-sky-400 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
      </div>
      <div>
        <h3 class="text-lg font-bold text-white">Telegram Alerts API</h3>
        <p class="text-xs text-slate-400">Instant notification dispatched to your phone on contamination</p>
      </div>
    </div>

    <form method="POST" action="settings.php" class="space-y-4">
      <input type="hidden" name="action" value="save_telegram">

      <div class="flex items-center justify-between p-3 rounded-xl bg-slate-950/60 border border-slate-800">
        <span class="text-xs text-slate-300 font-semibold">Enable Telegram Notifications</span>
        <input type="checkbox" name="telegram_enabled" class="toggle toggle-info toggle-sm" <?= !empty($settings['telegram']['enabled']) ? 'checked' : '' ?> />
      </div>

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Bot API Token</span></label>
        <input type="text" id="telegramBotToken" name="telegram_bot_token" value="<?= htmlspecialchars($settings['telegram']['bot_token']) ?>" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" class="input input-bordered input-sm bg-slate-900 border-slate-700 font-mono text-xs">
      </div>

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Telegram Chat ID</span></label>
        <input type="text" id="telegramChatId" name="telegram_chat_id" value="<?= htmlspecialchars($settings['telegram']['chat_id']) ?>" placeholder="e.g. 987654321 or -100xxxxxxxx" class="input input-bordered input-sm bg-slate-900 border-slate-700 font-mono text-xs">
      </div>

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Alert Cooldown (Seconds)</span></label>
        <input type="number" name="telegram_cooldown" value="<?= htmlspecialchars(isset($settings['telegram']['cooldown_seconds']) ? $settings['telegram']['cooldown_seconds'] : 300) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700 text-xs">
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="btn btn-sm btn-info flex-1 font-semibold">Save Telegram Config</button>
        <button type="button" id="btnTestTelegram" class="btn btn-sm btn-outline border-sky-500 text-sky-400 hover:bg-sky-950/40">Test Alert</button>
      </div>
      <div id="telegramTestResult" class="text-xs mt-1 hidden"></div>
    </form>
  </div>

  <!-- FORM 4: ESP32 WI-FI CREDENTIALS & SERVER IP (Requirement 12, 36, 37) -->
  <div class="water-card p-6">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-blue-500/15 text-blue-400 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
      </div>
      <div>
        <h3 class="text-lg font-bold text-white">ESP32 Wi-Fi & Endpoint Sync</h3>
        <p class="text-xs text-slate-400">Fixed Node Wi-Fi: Connects directly to Hotspot <strong>ESP32</strong> (Pass: 12345678)</p>
      </div>
    </div>

    <form method="POST" action="settings.php" class="space-y-4">
      <input type="hidden" name="action" value="save_wifi">

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Target Wi-Fi SSID</span></label>
        <input type="text" name="wifi_ssid" value="<?= htmlspecialchars($settings['wifi']['ssid']) ?>" placeholder="ESP32" class="input input-bordered input-sm bg-slate-900 border-slate-700" required>
      </div>

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Target Wi-Fi Password</span></label>
        <input type="text" name="wifi_password" value="<?= htmlspecialchars($settings['wifi']['password']) ?>" placeholder="12345678" class="input input-bordered input-sm bg-slate-900 border-slate-700 font-mono text-xs">
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2 form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Web Server IP / Domain</span></label>
          <input type="text" name="server_ip" value="<?= htmlspecialchars($settings['wifi']['server_ip']) ?>" placeholder="waterquality.infinityfree.io" class="input input-bordered input-sm bg-slate-900 border-slate-700 font-mono text-xs" required>
        </div>
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Port</span></label>
          <input type="number" name="server_port" value="<?= htmlspecialchars(isset($settings['wifi']['server_port']) ? $settings['wifi']['server_port'] : 80) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700 font-mono text-xs" required>
        </div>
      </div>

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Server Cookie (Optional for Cloud / InfinityFree)</span></label>
        <input type="text" name="server_cookie" value="<?= htmlspecialchars(isset($settings['wifi']['server_cookie']) ? $settings['wifi']['server_cookie'] : '') ?>" placeholder="e.g. __test=xxxxxxxxxxxxxxxxxxxx" class="input input-bordered input-sm bg-slate-900 border-slate-700 font-mono text-xs">
      </div>

      <div class="p-3 rounded-lg bg-slate-950/60 border border-slate-800 text-[11px] text-slate-400 space-y-1">
        <div>📶 <strong>Hotspot Name:</strong> <code class="text-sky-300">ESP32</code> | <strong>Password:</strong> <code class="text-sky-300">12345678</code></div>
        <div>🌐 <strong>Live Cloud:</strong> <code class="text-emerald-400">https://waterquality.infinityfree.io</code></div>
        <div>💡 Turn ON your phone's personal hotspot with Name <strong>ESP32</strong> and Password <strong>12345678</strong> (set to 2.4 GHz band). The ESP32 connects automatically!</div>
      </div>

      <button type="submit" class="btn btn-sm btn-info w-full mt-2 font-semibold">Save Wi-Fi Configuration</button>
    </form>
  </div>

  <!-- FORM 5: APP BRANDING & LOGO UPLOAD (Requirement 10 & 11) -->
  <div class="water-card p-6">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-sky-500/15 text-sky-400 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      </div>
      <div>
        <h3 class="text-lg font-bold text-white">App Branding & Logo</h3>
        <p class="text-xs text-slate-400">Custom project title, footer copyright, and custom logo upload</p>
      </div>
    </div>

    <form method="POST" action="settings.php" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="action" value="save_branding">

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Application Name</span></label>
        <input type="text" name="app_name" value="<?= htmlspecialchars($settings['app_name']) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700" required>
      </div>

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Footer Text</span></label>
        <input type="text" name="footer_text" value="<?= htmlspecialchars($settings['footer_text']) ?>" class="input input-bordered input-sm bg-slate-900 border-slate-700">
      </div>

      <div class="form-control">
        <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Upload Custom Logo (PNG, JPG, SVG)</span></label>
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-xl bg-slate-900 border border-slate-700 p-2 flex items-center justify-center">
            <img src="<?= htmlspecialchars($settings['logo_path']) ?>" alt="Logo Preview" class="max-w-full max-h-full object-contain">
          </div>
          <input type="file" name="logo_file" accept="image/*" class="file-input file-input-bordered file-input-sm w-full bg-slate-900 border-slate-700 text-xs">
        </div>
      </div>

      <button type="submit" class="btn btn-sm btn-info w-full mt-2 font-semibold">Save Branding & Logo</button>
    </form>
  </div>

  <!-- SECTION 6: FACTORY SYSTEM RESET & ESP32 EEPROM ERASE (Requirement 38) -->
  <div class="water-card p-6 border-rose-500/40 bg-rose-950/20">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
      </div>
      <div>
        <h3 class="text-lg font-bold text-rose-300">Danger Zone: System Reset</h3>
        <p class="text-xs text-rose-400">Purge telemetry logs and force ESP32 to clear EEPROM</p>
      </div>
    </div>

    <div class="p-3.5 rounded-xl bg-rose-950/40 border border-rose-800/40 text-xs text-rose-200 space-y-2 mb-4">
      <p>⚠️ <strong>Warning:</strong> Clicking reset will permanently perform the following actions:</p>
      <ul class="list-disc list-inside space-y-1 text-[11px] font-mono text-rose-300">
        <li>Delete all sensor historical logs in <code>data.json</code></li>
        <li>Send hardware reset flag to the ESP32 board</li>
        <li>Wipe ESP32 saved EEPROM Wi-Fi configurations</li>
        <li>ESP32 will reboot directly into Setup Mode hotspot!</li>
      </ul>
    </div>

    <button onclick="confirmResetModal.showModal()" class="btn btn-error btn-sm w-full font-bold shadow-lg shadow-rose-950/40">
      Reset System & Clear ESP32 EEPROM
    </button>
  </div>

</div>

<!-- Confirmation Modal for Factory Reset -->
<dialog id="confirmResetModal" class="modal">
  <div class="modal-box bg-slate-900 border border-rose-500/50 text-slate-100">
    <h3 class="font-bold text-lg text-rose-400 flex items-center gap-2">
      ⚠️ Confirm Factory System Reset
    </h3>
    <p class="py-4 text-sm text-slate-300">
      Are you sure you want to delete all sensor telemetry records and force the ESP32 to erase EEPROM and return to Setup Mode?
    </p>
    <div class="modal-action">
      <form method="dialog">
        <button class="btn btn-sm btn-ghost">Cancel</button>
      </form>
      <form method="POST" action="settings.php">
        <input type="hidden" name="action" value="factory_reset">
        <button type="submit" class="btn btn-sm btn-error font-bold">Yes, Reset System</button>
      </form>
    </div>
  </div>
</dialog>

<script>
  // Telegram Bot Test Button AJAX
  document.getElementById('btnTestTelegram')?.addEventListener('click', async () => {
    const botToken = document.getElementById('telegramBotToken').value;
    const chatId = document.getElementById('telegramChatId').value;
    const resultBox = document.getElementById('telegramTestResult');

    resultBox.className = 'text-xs mt-2 text-sky-400 font-mono';
    resultBox.textContent = 'Sending test message...';
    resultBox.classList.remove('hidden');

    try {
      const res = await fetch('api/test_telegram.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bot_token: botToken, chat_id: chatId })
      });
      const data = await res.json();
      if (data.success) {
        resultBox.className = 'text-xs mt-2 text-emerald-400 font-mono';
        resultBox.textContent = '✅ ' + data.message;
      } else {
        resultBox.className = 'text-xs mt-2 text-rose-400 font-mono';
        resultBox.textContent = '❌ ' + (data.error || 'Failed to send test alert.');
      }
    } catch (e) {
      resultBox.className = 'text-xs mt-2 text-rose-400 font-mono';
      resultBox.textContent = '❌ Network error testing Telegram API.';
    }
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
