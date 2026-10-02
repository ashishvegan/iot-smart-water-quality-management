<?php
/**
 * ESP32 Telemetry Receiver & Decision Engine
 * Endpoint: POST /api/telemetry.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/functions.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Support GET for quick sanity check
    echo json_encode([
        'status' => 'online',
        'message' => 'Telemetry API endpoint ready. Use POST to send sensor telemetry.'
    ]);
    exit;
}

// Read raw JSON or POST data
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

if (!is_array($inputData)) {
    $inputData = $_POST;
}

if (empty($inputData)) {
    http_response_code(400);
    echo json_encode(['error' => 'No sensor data provided']);
    exit;
}

$settings = get_settings();
$deviceState = get_device_state();

// Parse Sensor Values
$tds = isset($inputData['tds']) ? floatval($inputData['tds']) : 0.0;
$turbidity = isset($inputData['turbidity']) ? floatval($inputData['turbidity']) : 0.0;
$temp = isset($inputData['temperature']) ? floatval($inputData['temperature']) : 25.0;
$ph = isset($inputData['ph']) ? floatval($inputData['ph']) : 7.0;

// Parse Diagnostic Values
$freeRam = isset($inputData['free_ram']) ? intval($inputData['free_ram']) : $deviceState['free_ram'];
$cpuTemp = isset($inputData['cpu_temp']) ? floatval($inputData['cpu_temp']) : $deviceState['cpu_temp'];
$rssi = isset($inputData['rssi']) ? intval($inputData['rssi']) : $deviceState['rssi'];
$uptime = isset($inputData['uptime_sec']) ? intval($inputData['uptime_sec']) : 0;
$deviceIp = isset($inputData['ip']) ? $inputData['ip'] : $_SERVER['REMOTE_ADDR'];

// Evaluate Water Quality
$evaluation = evaluate_water_quality([
    'tds' => $tds,
    'turbidity' => $turbidity,
    'temperature' => $temp,
    'ph' => $ph
], $settings['thresholds']);

// Determine Relay 1 (Solenoid Valve) State
$currentValveState = intval($deviceState['valve_state']);
$valveMode = isset($settings['valve_control']['mode']) ? $settings['valve_control']['mode'] : 'auto';

if ($valveMode === 'auto') {
    if ($evaluation['is_bad']) {
        if (!empty($settings['valve_control']['auto_shutoff_on_bad'])) {
            $currentValveState = 0; // SV OFF: Solenoid Valve Closed / Water Flow Cut Off
        }
    } else {
        if (!empty($settings['valve_control']['auto_reopen_on_good'])) {
            $currentValveState = 1; // SV ON: Solenoid Valve Open / Water Flow Enabled
        }
    }
} else {
    // Manual mode: honor configured manual state
    $currentValveState = intval(isset($settings['valve_control']['manual_state']) ? $settings['valve_control']['manual_state'] : 1);
}

// Telegram Alert Check
$now = time();
if ($evaluation['is_bad'] && !empty($settings['telegram']['enabled'])) {
    $lastAlert = intval(isset($settings['telegram']['last_alert_time']) ? $settings['telegram']['last_alert_time'] : 0);
    $cooldown = intval(isset($settings['telegram']['cooldown_seconds']) ? $settings['telegram']['cooldown_seconds'] : 300);
    
    if (($now - $lastAlert) >= $cooldown) {
        $alertMsg = "⚠️ <b>WATER QUALITY CRITICAL ALERT!</b>\n";
        $alertMsg .= "System: <b>" . htmlspecialchars($settings['app_name']) . "</b>\n";
        $alertMsg .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
        $alertMsg .= "📊 <b>Sensor Readings:</b>\n";
        $alertMsg .= "• TDS: <b>{$tds} ppm</b> (Limit: {$settings['thresholds']['tds_max']})\n";
        $alertMsg .= "• Turbidity: <b>{$turbidity} NTU</b> (Limit: {$settings['thresholds']['turbidity_max']})\n";
        $alertMsg .= "• pH: <b>{$ph}</b> (Safe: {$settings['thresholds']['ph_min']} - {$settings['thresholds']['ph_max']})\n";
        $alertMsg .= "• Temperature: <b>{$temp} °C</b>\n\n";
        $alertMsg .= "🚨 <b>Issues Detected:</b>\n";
        foreach ($evaluation['issues'] as $issue) {
            $alertMsg .= " - " . htmlspecialchars($issue) . "\n";
        }
        $alertMsg .= "\n🚰 <b>Solenoid Valve Status:</b> " . ($currentValveState ? "OPEN (Flow ON)" : "EMERGENCY SHUTOFF (CLOSED)");

        if (send_telegram_alert($alertMsg, $settings)) {
            $settings['telegram']['last_alert_time'] = $now;
            save_settings($settings);
        }
    }
}

// Construct Log Record
$record = [
    'id' => time() . '_' . rand(100, 999),
    'timestamp' => date('Y-m-d H:i:s'),
    'epoch' => $now,
    'tds' => $tds,
    'turbidity' => $turbidity,
    'temperature' => $temp,
    'ph' => $ph,
    'score' => $evaluation['score'],
    'status' => $evaluation['status'],
    'is_bad' => $evaluation['is_bad'],
    'issues' => $evaluation['issues'],
    'valve_state' => $currentValveState,
    'valve_mode' => $valveMode
];

// Append record to data.json
append_sensor_record($record);

// Check if EEPROM reset was requested via Web App
$resetEepromPending = !empty($deviceState['reset_eeprom_pending']);

// Update device state
update_device_state([
    'valve_state' => $currentValveState,
    'valve_mode' => $valveMode,
    'last_ping' => date('Y-m-d H:i:s'),
    'ip_address' => $deviceIp,
    'rssi' => $rssi,
    'free_ram' => $freeRam,
    'cpu_temp' => $cpuTemp,
    'uptime_sec' => $uptime,
    'reset_eeprom_pending' => false // Consumed by ESP32 response
]);

// Return response to ESP32
echo json_encode([
    'success' => true,
    'valve_state' => $currentValveState, // 1 = SV ON (Relay LOW), 0 = SV OFF (Relay HIGH)
    'valve_mode' => $valveMode,
    'reset_eeprom' => $resetEepromPending,
    'server_time' => $now
]);
