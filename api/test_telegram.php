<?php
/**
 * Test Telegram Notification API
 * Endpoint: POST /api/test_telegram.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$settings = get_settings();

// Allow overriding with test credentials passed in request
$botToken = !empty($data['bot_token']) ? trim($data['bot_token']) : $settings['telegram']['bot_token'];
$chatId = !empty($data['chat_id']) ? trim($data['chat_id']) : $settings['telegram']['chat_id'];

if (empty($botToken) || empty($chatId)) {
    echo json_encode(['success' => false, 'error' => 'Bot Token and Chat ID are required to send a test message.']);
    exit;
}

$testSettings = $settings;
$testSettings['telegram']['enabled'] = true;
$testSettings['telegram']['bot_token'] = $botToken;
$testSettings['telegram']['chat_id'] = $chatId;

$message = "💧 <b>AquaSense IoT System</b>\n\n";
$message .= "✅ <b>Telegram Notification Test Successful!</b>\n";
$message .= "Timestamp: " . date(DATETIME_FORMAT) . " IST\n";
$message .= "Your bot is configured and ready to dispatch real-time water quality warnings and solenoid valve alerts.";

$result = send_telegram_alert($message, $testSettings);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Telegram test message sent successfully! Check your Telegram chat.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message via Telegram API. Check bot token and chat ID.']);
}
