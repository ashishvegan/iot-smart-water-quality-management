<?php
/**
 * Sensor Data Simulator & Seed Generator
 * Endpoint: POST/GET /api/simulator.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';

$action = 'readings';
if (isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif (isset($_POST['action'])) {
    $action = $_POST['action'];
}

if ($action === 'seed_history') {
    // Generate 60 records for the past 1 hour (1 per minute for demo)
    $records = [];
    $baseTime = time() - 3600;
    $settings = get_settings();
    
    for ($i = 0; $i <= 60; $i++) {
        $timestamp = $baseTime + ($i * 60);
        $tds = round(160 + sin($i / 5) * 45 + rand(-5, 5), 1);
        $turbidity = round(1.2 + cos($i / 6) * 0.8 + (rand(0, 30) / 100), 2);
        $temp = round(24.0 + sin($i / 10) * 2.5 + (rand(-3, 3) / 10), 1);
        $ph = round(7.2 + cos($i / 8) * 0.4 + (rand(-1, 1) / 10), 2);
        
        $eval = evaluate_water_quality([
            'tds' => $tds,
            'turbidity' => $turbidity,
            'temperature' => $temp,
            'ph' => $ph
        ], $settings['thresholds']);
        
        $records[] = [
            'id' => $timestamp . '_' . rand(100, 999),
            'timestamp' => date(DATETIME_FORMAT, $timestamp),
            'epoch' => $timestamp,
            'tds' => $tds,
            'turbidity' => $turbidity,
            'temperature' => $temp,
            'ph' => $ph,
            'score' => $eval['score'],
            'status' => $eval['status'],
            'is_bad' => $eval['is_bad'],
            'issues' => $eval['issues'],
            'valve_state' => 1,
            'valve_mode' => 'auto'
        ];
    }
    
    write_json_file(DATA_FILE, $records);
    update_device_state([
        'last_ping' => date(DATETIME_FORMAT),
        'rssi' => -58,
        'free_ram' => 192400,
        'cpu_temp' => 41.8,
        'uptime_sec' => 3600
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully seeded 60 data points for the past 1 hour.'
    ]);
    exit;
}

if ($action === 'simulate_reading') {
    $type = 'normal';
    if (isset($_GET['type'])) {
        $type = $_GET['type'];
    } elseif (isset($_POST['type'])) {
        $type = $_POST['type'];
    }
    $settings = get_settings();
    
    if ($type === 'bad') {
        // Bad water: high TDS, murky turbidity, acidic pH
        $tds = round(rand(620, 850), 1);
        $turbidity = round(rand(7, 15) + (rand(1, 9)/10), 2);
        $ph = round(rand(45, 58) / 10, 2);
        $temp = round(rand(25, 32) + (rand(1, 9)/10), 1);
    } elseif ($type === 'alkaline') {
        // High pH bad water
        $tds = round(rand(350, 480), 1);
        $turbidity = round(rand(2, 4), 2);
        $ph = round(rand(92, 105) / 10, 2);
        $temp = round(rand(24, 28), 1);
    } else {
        // Normal clean water
        $tds = round(rand(140, 260) + (rand(1, 9)/10), 1);
        $turbidity = round(rand(8, 22) / 10, 2);
        $ph = round(rand(71, 78) / 10, 2);
        $temp = round(rand(230, 265) / 10, 1);
    }
    
    // Dispatch to telemetry handler logic directly
    $eval = evaluate_water_quality([
        'tds' => $tds,
        'turbidity' => $turbidity,
        'temperature' => $temp,
        'ph' => $ph
    ], $settings['thresholds']);
    
    $valveState = 1;
    if ($settings['valve_control']['mode'] === 'auto') {
        if ($eval['is_bad'] && !empty($settings['valve_control']['auto_shutoff_on_bad'])) {
            $valveState = 0;
        }
    } else {
        $valveState = intval(isset($settings['valve_control']['manual_state']) ? $settings['valve_control']['manual_state'] : 1);
    }
    
    $rec = [
        'id' => time() . '_' . rand(100, 999),
        'timestamp' => date(DATETIME_FORMAT),
        'epoch' => time(),
        'tds' => $tds,
        'turbidity' => $turbidity,
        'temperature' => $temp,
        'ph' => $ph,
        'score' => $eval['score'],
        'status' => $eval['status'],
        'is_bad' => $eval['is_bad'],
        'issues' => $eval['issues'],
        'valve_state' => $valveState,
        'valve_mode' => $settings['valve_control']['mode']
    ];
    
    append_sensor_record($rec);
    $curDevState = get_device_state();
    $curUptime = isset($curDevState['uptime_sec']) ? intval($curDevState['uptime_sec']) : 0;
    update_device_state([
        'last_ping' => date(DATETIME_FORMAT),
        'valve_state' => $valveState,
        'valve_mode' => $settings['valve_control']['mode'],
        'cpu_temp' => round(rand(410, 440) / 10, 1),
        'free_ram' => rand(175000, 195000),
        'uptime_sec' => $curUptime + 10
    ]);
    
    echo json_encode([
        'success' => true,
        'record' => $rec,
        'evaluation' => $eval
    ]);
    exit;
}

echo json_encode([
    'usage' => [
        'Seed 1 hour graph' => '?action=seed_history',
        'Simulate Normal Water' => '?action=simulate_reading&type=normal',
        'Simulate Bad Water (Triggers Alarm)' => '?action=simulate_reading&type=bad'
    ]
]);
