<?php
/**
 * Relay 1 (Solenoid Valve) Control API
 * Endpoint: POST /api/control_valve.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$settings = get_settings();
$deviceState = get_device_state();

$newMode = isset($data['mode']) ? strtolower(trim($data['mode'])) : null;
$newValveState = isset($data['valve_state']) ? intval($data['valve_state']) : null;

if ($newMode && in_array($newMode, ['auto', 'manual'])) {
    $settings['valve_control']['mode'] = $newMode;
    $deviceState['valve_mode'] = $newMode;
}

if ($newValveState !== null) {
    $stateVal = $newValveState ? 1 : 0;
    $settings['valve_control']['manual_state'] = $stateVal;
    $deviceState['valve_state'] = $stateVal;
}

save_settings($settings);
update_device_state($deviceState);

echo json_encode([
    'success' => true,
    'message' => 'Valve state updated successfully.',
    'valve_mode' => $deviceState['valve_mode'],
    'valve_state' => $deviceState['valve_state']
]);
