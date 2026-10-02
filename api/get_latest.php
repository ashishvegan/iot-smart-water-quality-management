<?php
/**
 * Real-time Dashboard Telemetry Fetcher
 * Endpoint: GET /api/get_latest.php
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../includes/functions.php';

$settings = get_settings();
$deviceState = get_device_state();

// Get last 1 hour records (3600 seconds)
$recentRecords = get_recent_sensor_records(3600);

// Get latest record
$latestRecord = null;
if (!empty($recentRecords)) {
    $latestRecord = end($recentRecords);
} else {
    // If no records in last hour, check total records
    $allRecords = read_json_file(DATA_FILE, []);
    if (!empty($allRecords)) {
        $latestRecord = end($allRecords);
    }
}

// Fallback baseline if database is completely fresh
if (!$latestRecord) {
    $latestRecord = [
        'id' => 'init_1',
        'timestamp' => date('Y-m-d H:i:s'),
        'epoch' => time(),
        'tds' => 180.0,
        'turbidity' => 1.2,
        'temperature' => 24.5,
        'ph' => 7.2,
        'score' => 96,
        'status' => 'good',
        'is_bad' => false,
        'issues' => [],
        'valve_state' => 1,
        'valve_mode' => 'auto'
    ];
}

// Check device online status (heartbeat within 35 seconds)
$isOnline = false;
if (!empty($deviceState['last_ping'])) {
    $diff = time() - strtotime($deviceState['last_ping']);
    $isOnline = ($diff <= 35);
}

// Prepare 1-hour history for Chart.js
$chartData = [
    'labels' => [],
    'tds' => [],
    'turbidity' => [],
    'temperature' => [],
    'ph' => []
];

// If very many records, sample every Nth record to keep payload crisp
$step = max(1, floor(count($recentRecords) / 120));
$index = 0;

foreach ($recentRecords as $rec) {
    if ($index % $step === 0 || $index === count($recentRecords) - 1) {
        $timeLabel = date('H:i:s', isset($rec['epoch']) ? $rec['epoch'] : strtotime($rec['timestamp']));
        $chartData['labels'][] = $timeLabel;
        $chartData['tds'][] = floatval(isset($rec['tds']) ? $rec['tds'] : 0);
        $chartData['turbidity'][] = floatval(isset($rec['turbidity']) ? $rec['turbidity'] : 0);
        $chartData['temperature'][] = floatval(isset($rec['temperature']) ? $rec['temperature'] : 0);
        $chartData['ph'][] = floatval(isset($rec['ph']) ? $rec['ph'] : 0);
    }
    $index++;
}

echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'latest' => $latestRecord,
    'device' => [
        'valve_state' => intval(isset($deviceState['valve_state']) ? $deviceState['valve_state'] : 1),
        'valve_mode' => isset($deviceState['valve_mode']) ? $deviceState['valve_mode'] : 'auto',
        'is_online' => $isOnline,
        'last_ping' => isset($deviceState['last_ping']) ? $deviceState['last_ping'] : null,
        'cpu_temp' => floatval(isset($deviceState['cpu_temp']) ? $deviceState['cpu_temp'] : 42.0),
        'free_ram' => intval(isset($deviceState['free_ram']) ? $deviceState['free_ram'] : 184320),
        'total_ram' => intval(isset($deviceState['total_ram']) ? $deviceState['total_ram'] : 327680),
        'rssi' => intval(isset($deviceState['rssi']) ? $deviceState['rssi'] : -65),
        'uptime_sec' => intval(isset($deviceState['uptime_sec']) ? $deviceState['uptime_sec'] : 0),
        'ip_address' => isset($deviceState['ip_address']) ? $deviceState['ip_address'] : '192.168.1.150'
    ],
    'thresholds' => $settings['thresholds'],
    'chart_data' => $chartData,
    'app' => [
        'name' => isset($settings['app_name']) ? $settings['app_name'] : 'AquaSense IoT',
        'logo' => isset($settings['logo_path']) ? $settings['logo_path'] : 'assets/images/logo.svg',
        'footer' => isset($settings['footer_text']) ? $settings['footer_text'] : ''
    ]
]);
