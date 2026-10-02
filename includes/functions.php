<?php
/**
 * Core Helper Functions
 * IoT Smart Water Quality Management System
 */

require_once __DIR__ . '/config.php';

/**
 * Thread-safe read JSON file
 */
function read_json_file($filePath, $default = []) {
    if (!file_exists($filePath)) {
        return $default;
    }
    
    $fp = fopen($filePath, 'r');
    if (!$fp) {
        return $default;
    }
    
    flock($fp, LOCK_SH);
    $content = '';
    while (!feof($fp)) {
        $content .= fread($fp, 8192);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    
    if (empty(trim($content))) {
        return $default;
    }
    
    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

/**
 * Thread-safe write JSON file
 */
function write_json_file($filePath, $data) {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    
    $fp = fopen($filePath, 'c+');
    if (!$fp) {
        return false;
    }
    
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
    
    fclose($fp);
    return false;
}

/**
 * Get system settings with defaults merged
 */
function get_settings() {
    $defaults = get_default_settings();
    $saved = read_json_file(SETTINGS_FILE, []);
    
    // Deep merge defaults with saved settings
    return array_replace_recursive($defaults, $saved);
}

/**
 * Save system settings
 */
function save_settings($settings) {
    return write_json_file(SETTINGS_FILE, $settings);
}

/**
 * Get device state
 */
function get_device_state() {
    $defaults = get_default_device_state();
    $saved = read_json_file(STATE_FILE, []);
    return array_merge($defaults, $saved);
}

/**
 * Update device state
 */
function update_device_state($updates) {
    $current = get_device_state();
    $updated = array_merge($current, $updates);
    write_json_file(STATE_FILE, $updated);
    return $updated;
}

/**
 * Get all users
 */
function get_users() {
    $users = read_json_file(USERS_FILE, []);
    // If empty, create default admin user
    if (empty($users)) {
        $defaultAdmin = [
            'admin' => [
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'name' => 'Administrator',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        write_json_file(USERS_FILE, $defaultAdmin);
        return $defaultAdmin;
    }
    return $users;
}

/**
 * Register a new user
 */
function register_user($username, $password, $name = '') {
    $users = get_users();
    $username = strtolower(trim($username));
    
    if (empty($username) || empty($password)) {
        return ['success' => false, 'error' => 'Username and password cannot be empty.'];
    }
    
    if (isset($users[$username])) {
        return ['success' => false, 'error' => 'Username is already taken.'];
    }
    
    $users[$username] = [
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => count($users) === 0 ? 'admin' : 'user',
        'name' => !empty($name) ? trim($name) : ucfirst($username),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    if (write_json_file(USERS_FILE, $users)) {
        return ['success' => true];
    }
    return ['success' => false, 'error' => 'Failed to save user account.'];
}

/**
 * Authenticate user credentials
 */
function authenticate_user($username, $password) {
    $users = get_users();
    $username = strtolower(trim($username));
    
    if (isset($users[$username])) {
        if (password_verify($password, $users[$username]['password'])) {
            $_SESSION['user_id'] = $username;
            $_SESSION['user_name'] = $users[$username]['name'];
            $_SESSION['user_role'] = $users[$username]['role'];
            return true;
        }
    }
    return false;
}

/**
 * Check if current user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login or redirect
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Evaluate water quality against configured thresholds
 */
function evaluate_water_quality($data, $thresholds) {
    $issues = [];
    $isBad = false;
    $isWarning = false;
    
    $tds = floatval(isset($data['tds']) ? $data['tds'] : 0);
    $turbidity = floatval(isset($data['turbidity']) ? $data['turbidity'] : 0);
    $temp = floatval(isset($data['temperature']) ? $data['temperature'] : 25);
    $ph = floatval(isset($data['ph']) ? $data['ph'] : 7.0);
    
    $tdsMax = isset($thresholds['tds_max']) ? $thresholds['tds_max'] : 500;
    $tdsMin = isset($thresholds['tds_min']) ? $thresholds['tds_min'] : 50;
    $turbidityMax = isset($thresholds['turbidity_max']) ? $thresholds['turbidity_max'] : 5.0;
    $phMin = isset($thresholds['ph_min']) ? $thresholds['ph_min'] : 6.5;
    $phMax = isset($thresholds['ph_max']) ? $thresholds['ph_max'] : 8.5;
    $tempMin = isset($thresholds['temp_min']) ? $thresholds['temp_min'] : 15.0;
    $tempMax = isset($thresholds['temp_max']) ? $thresholds['temp_max'] : 35.0;

    // Check TDS
    if ($tds > $tdsMax) {
        $issues[] = "TDS ({$tds} ppm) exceeds safe maximum ({$tdsMax} ppm)";
        $isBad = true;
    } elseif ($tds < $tdsMin) {
        $issues[] = "TDS ({$tds} ppm) is too low demineralized (< {$tdsMin} ppm)";
        $isWarning = true;
    }
    
    // Check Turbidity
    if ($turbidity > $turbidityMax) {
        $issues[] = "Turbidity ({$turbidity} NTU) exceeds clarity limit ({$turbidityMax} NTU)";
        $isBad = true;
    }
    
    // Check pH
    if ($ph < $phMin) {
        $issues[] = "pH ({$ph}) is acidic (< {$phMin})";
        $isBad = true;
    } elseif ($ph > $phMax) {
        $issues[] = "pH ({$ph}) is alkaline (> {$phMax})";
        $isBad = true;
    }
    
    // Check Temperature
    if ($temp > $tempMax) {
        $issues[] = "Water temperature ({$temp}°C) is above normal limit";
        $isWarning = true;
    } elseif ($temp < $tempMin) {
        $issues[] = "Water temperature ({$temp}°C) is below minimum limit";
        $isWarning = true;
    }
    
    $status = 'good';
    if ($isBad) {
        $status = 'bad';
    } elseif ($isWarning) {
        $status = 'warning';
    }
    
    // Calculate simple Water Quality Score (0 - 100%)
    $score = 100;
    if ($tds > $thresholds['tds_max']) {
        $score -= min(40, (($tds - $thresholds['tds_max']) / 10));
    }
    if ($turbidity > $thresholds['turbidity_max']) {
        $score -= min(35, (($turbidity - $thresholds['turbidity_max']) * 8));
    }
    if ($ph < $thresholds['ph_min']) {
        $score -= min(35, (($thresholds['ph_min'] - $ph) * 20));
    } elseif ($ph > $thresholds['ph_max']) {
        $score -= min(35, (($ph - $thresholds['ph_max']) * 20));
    }
    $score = max(5, min(100, round($score)));
    
    return [
        'status' => $status,
        'is_bad' => $isBad,
        'issues' => $issues,
        'score' => $score
    ];
}

/**
 * Send Telegram Alert
 */
function send_telegram_alert($message, $settings) {
    if (empty($settings['telegram']['enabled']) || empty($settings['telegram']['bot_token']) || empty($settings['telegram']['chat_id'])) {
        return false;
    }
    
    $botToken = trim($settings['telegram']['bot_token']);
    $chatId = trim($settings['telegram']['chat_id']);
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $payload = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result !== false;
    } else {
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($payload),
                'timeout' => 6
            ]
        ];
        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        return $result !== false;
    }
}

/**
 * Append sensor record to data.json
 * Maintains the most recent 5,000 records to keep JSON fast and efficient
 */
function append_sensor_record($record) {
    $records = read_json_file(DATA_FILE, []);
    
    // Add unique ID and timestamp if missing
    if (!isset($record['id'])) {
        $record['id'] = time() . '_' . rand(100, 999);
    }
    if (!isset($record['timestamp'])) {
        $record['timestamp'] = date('Y-m-d H:i:s');
    }
    if (!isset($record['epoch'])) {
        $record['epoch'] = time();
    }
    
    // Prepend or append - we append to the array
    $records[] = $record;
    
    // Trim older records if exceeding 5000 records (approx 14 hours of 10s updates)
    if (count($records) > 5000) {
        $records = array_slice($records, -5000);
    }
    
    return write_json_file(DATA_FILE, $records);
}

/**
 * Get records for the last N seconds (e.g. 3600 for 1 hour)
 */
function get_recent_sensor_records($seconds = 3600) {
    $records = read_json_file(DATA_FILE, []);
    if (empty($records)) {
        return [];
    }
    
    $cutoff = time() - $seconds;
    $filtered = [];
    
    foreach ($records as $r) {
        $epoch = isset($r['epoch']) ? intval($r['epoch']) : strtotime($r['timestamp']);
        if ($epoch >= $cutoff) {
            $filtered[] = $r;
        }
    }
    
    return $filtered;
}

/**
 * Get paginated records (latest first)
 */
function get_paginated_records($page = 1, $limit = 20, $statusFilter = 'all') {
    $records = read_json_file(DATA_FILE, []);
    if (empty($records)) {
        return [
            'records' => [],
            'total' => 0,
            'page' => 1,
            'totalPages' => 1
        ];
    }
    
    // Filter if needed
    if ($statusFilter !== 'all') {
        $records = array_filter($records, function($item) use ($statusFilter) {
            return isset($item['status']) && $item['status'] === $statusFilter;
        });
    }
    
    // Sort latest first
    usort($records, function($a, $b) {
        $tA = isset($a['epoch']) ? $a['epoch'] : strtotime($a['timestamp']);
        $tB = isset($b['epoch']) ? $b['epoch'] : strtotime($b['timestamp']);
        return $tB - $tA;
    });
    
    $total = count($records);
    $limit = max(5, min(100, intval($limit)));
    $totalPages = max(1, ceil($total / $limit));
    $page = max(1, min($totalPages, intval($page)));
    
    $offset = ($page - 1) * $limit;
    $slice = array_slice($records, $offset, $limit);
    
    return [
        'records' => $slice,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => $totalPages
    ];
}

/**
 * Purge all records & flag EEPROM reset for ESP32
 */
function reset_system_data() {
    // 1. Wipe data.json
    write_json_file(DATA_FILE, []);
    
    // 2. Set reset_eeprom_pending flag on device state
    $state = get_device_state();
    $state['reset_eeprom_pending'] = true;
    write_json_file(STATE_FILE, $state);
    
    return true;
}
