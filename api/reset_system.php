<?php
/**
 * System Reset API
 * Endpoint: POST /api/reset_system.php
 * Clears data.json records and signals ESP32 to clear EEPROM & enter Setup Mode
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

// Perform system reset
reset_system_data();

echo json_encode([
    'success' => true,
    'message' => 'All sensor records deleted. Reset command sent to ESP32 to clear EEPROM and enter Setup Mode on next connection.'
]);
