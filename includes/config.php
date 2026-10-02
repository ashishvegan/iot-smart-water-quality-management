<?php
/**
 * Global Configuration and Paths
 * IoT Smart Water Quality Management System
 */

@ini_set('always_populate_raw_post_data', -1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

// Start session safely if not started already
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// Define Base Paths
define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'data');
define('UPLOADS_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads');

// Ensure storage directories exist
if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0777, true);
}
if (!is_dir(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR, 0777, true);
}

// Data File Paths
define('DATA_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'data.json');
define('USERS_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'users.json');
define('SETTINGS_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'settings.json');
define('STATE_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'device_state.json');

// Default System Settings
function get_default_settings() {
    return [
        'app_name' => 'AquaSense IoT',
        'footer_text' => 'Smart Water Quality Monitoring & Automatic SV Valve Management System © 2026',
        'logo_path' => 'assets/images/logo.svg',
        
        // Sensor Safe Ranges
        'thresholds' => [
            'tds_min' => 50,      // ppm
            'tds_max' => 500,     // ppm (WHO/EPA recommends < 500 ppm for potable water)
            'turbidity_max' => 5, // NTU (WHO standard for drinking water is < 5 NTU, ideally < 1)
            'temp_min' => 15.0,   // °C
            'temp_max' => 35.0,   // °C
            'ph_min' => 6.5,      // Standard potable range 6.5 - 8.5
            'ph_max' => 8.5
        ],
        
        // Relay 1 (Solenoid Valve) Automation Rules
        'valve_control' => [
            'mode' => 'auto',            // 'auto' or 'manual'
            'manual_state' => 1,         // 1 = SV ON (Water Enabled), 0 = SV OFF
            'auto_shutoff_on_bad' => true, // Turn off SV when water quality is bad
            'auto_reopen_on_good' => true // Reopen SV when quality returns to normal
        ],
        
        // Telegram Bot Notifications
        'telegram' => [
            'enabled' => false,
            'bot_token' => '',
            'chat_id' => '',
            'last_alert_time' => 0,
            'cooldown_seconds' => 300   // 5 minutes between repeating alerts
        ],
        
        // WiFi Configuration sent to ESP32
        'wifi' => [
            'ssid' => 'AquaSense_HomeWiFi',
            'password' => 'WaterSecure2026',
            'server_ip' => '192.168.1.100', // Dashboard host IP for ESP32
            'server_port' => 80
        ]
    ];
}

// Default Device State
function get_default_device_state() {
    return [
        'valve_state' => 1,          // 1 = ON (Water Enabled - Active LOW on Relay), 0 = OFF
        'valve_mode' => 'auto',       // 'auto' or 'manual'
        'last_ping' => null,         // ISO Timestamp
        'ip_address' => '192.168.1.150',
        'rssi' => -65,
        'free_ram' => 184320,        // Bytes
        'total_ram' => 327680,       // ESP32 SRAM ~320KB
        'cpu_temp' => 42.5,          // °C
        'uptime_sec' => 0,
        'reset_eeprom_pending' => false
    ];
}
