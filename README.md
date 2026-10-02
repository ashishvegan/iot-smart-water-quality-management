# AquaSense IoT - Smart Water Quality Management & Automatic SV Valve Control

[![IoT](https://img.shields.io/badge/IoT-ESP32%2030--Pin-0284c7.svg)](https://github.com/ashishvegan/iot-smart-water-quality-management)
[![Sensors](https://img.shields.io/badge/Sensors-TDS%20%7C%20Turbidity%20%7C%20pH%20%7C%20DS18B20-0ea5e9.svg)]()
[![Actuator](https://img.shields.io/badge/Actuator-5V%20Relay%20SV%20Active%20LOW-10b981.svg)]()
[![Backend](https://img.shields.io/badge/Backend-PHP%20%2B%20JSON%20Database-38bdf8.svg)]()
[![Frontend](https://img.shields.io/badge/Frontend-Tailwind%20%2B%20DaisyUI%20Water%20Theme-0284c7.svg)]()

AquaSense IoT is a complete end-to-end IoT Smart Water Quality Monitoring and Automatic Solenoid Valve Control System. It continuously analyzes physical and biochemical properties of water (TDS, Turbidity, pH, and Temperature) using an ESP32 microcontroller, drives an I2C 16x2 LCD, and streams telemetry every 10 seconds to a modern water-themed web application with real-time graphs, emergency audio-visual alerts, Telegram bot notifications, and automated valve shut-off protection.

---

## 🌊 Key Features

1. **Clean & Responsive Water-Themed UI:**
   - Designed with an Oceanic Aqua palette, animated water wave ripple effects, glassmorphic metric cards, and responsive layout powered by Tailwind CSS & DaisyUI.
2. **Real-time 10-Second Telemetry:**
   - Live continuous readings of **TDS (ppm)**, **Turbidity (NTU)**, **pH level (0-14)**, and **Water Temperature (°C)**.
3. **1-Hour Real-time Line Graph:**
   - Multi-dataset interactive Chart.js line graph displaying the last 1 hour of telemetry with 10-second real-time streaming updates and individual sensor filters.
4. **Solenoid Valve Relay 1 Control (Automatic + Manual):**
   - Active LOW relay logic: `LOW = SV ON (Flow Enabled)`.
   - **Auto Mode:** Automatically shuts off the Solenoid Valve if TDS, Turbidity, or pH breaches configured safe thresholds, and reopens when water returns to safe limits.
   - **Manual Mode:** Instant manual toggle switch on the dashboard.
5. **Emergency Contamination Alerting:**
   - Shifts UI to high-visibility **Emergency Alert Theme** (crimson pulsating ambient glow).
   - Generates procedural audio alarm siren using the browser's Web Audio API with a quick Mute/Unmute toggle.
   - Dispatches instant Telegram alert notifications to the operator's phone via Telegram Bot API with detailed readings and issues detected.
6. **Configurable Settings & Branding:**
   - Custom Application Title and Footer Copyright text.
   - Custom Logo upload with instant image preview.
   - Configurable safe threshold ranges for all 4 sensors.
   - Telegram Bot API token & Chat ID with an in-browser "Test Alert" button.
   - Target Wi-Fi SSID and Password synchronization for the ESP32.
7. **ESP32 Hardware Diagnostics (Requirement #14):**
   - Live telemetry of ESP32 CPU Temperature (°C), Free Heap RAM (KB), RAM usage percentage, Wi-Fi RSSI signal strength (dBm), and system uptime.
8. **Setup Mode & Remote EEPROM Erase (Requirement #36, #37, #38):**
   - On first boot or reset, ESP32 enters **Setup Mode**, starting an access point (`AquaSense-Setup` / `12345678`) and displaying the credentials on the 16x2 LCD.
   - Web App provides a **Factory Reset Button** that purges `data.json` logs and commands the ESP32 to clear its EEPROM and return to Setup Mode.
9. **Sensor Records & CSV Export:**
   - Dedicated records history page with status filtering and pagination (10, 20, 50, 100 rows per page, latest records first).
   - Instant 1-click **CSV Data Export** (`export.php`).
10. **Secure Authentication:**
    - Operator Login and Registration system backed by `users.json` with `password_hash` encryption.
    - `users.json` is protected and ignored in `.gitignore`.
11. **Timezone & DateTime Standardization (Requirement #21):**
    - Configured for **Asia/Kolkata (+05:30)** across the entire system.
    - All telemetry records, historical logs, live system clocks, Telegram alerts, Chart.js timestamps, and CSV export files consistently use the **`DD-MM-YYYY` and `HH:mm AM/PM`** format (e.g. `02-10-2026 03:09 PM`).

---

## ⚡ Hardware Pinout & Wiring (ESP32 30-Pin)

| Component | ESP32 Pin | Expansion Rail | Function / Notes |
| :--- | :--- | :--- | :--- |
| **TDS Sensor (A)** | **GPIO 34** | 5V / GND | ADC1 Channel 6 (Analog TDS) |
| **Turbidity Sensor (A)** | **GPIO 35** | 5V / GND | ADC1 Channel 7 (Analog Turbidity) |
| **pH Sensor (Po)** | **GPIO 32** | 5V / GND | ADC1 Channel 4 (Analog pH) |
| **DS18B20 Temp (Data)** | **GPIO 4** | 3.3V or 5V / GND | OneWire Digital (Requires 4.7kΩ pullup) |
| **16x2 LCD I2C (SDA)** | **GPIO 21** | 5V / GND | Hardware I2C SDA |
| **16x2 LCD I2C (SCL)** | **GPIO 22** | 5V / GND | Hardware I2C SCL |
| **Relay 1 (Solenoid Valve)**| **GPIO 26** | 5V / GND | Digital Output. **Active LOW** (LOW = SV ON) |

> ⚠️ **Important:** All analog sensors must use **ADC1 pins (GPIO 32-39)** because ESP32's ADC2 channels are disabled when Wi-Fi is active.

---

## 🚀 Getting Started

### 1. Running the Web Application
No external database or complicated installation required. Ensure PHP 5.6+ or 7.x+ or 8.x is installed:

```bash
# Start PHP built-in web server from the project directory
php -S 0.0.0.0:8000
```
Then navigate to:
```
http://localhost:8000
```

- **Default Administrator Credentials:**
  - **Username:** `admin`
  - **Password:** `admin123`

### 2. Flashing the ESP32 Code
1. Open [`esp32/esp32_water_monitor.ino`](esp32/esp32_water_monitor.ino) in the Arduino IDE.
2. In **Tools -> Manage Libraries...**, install:
   - `LiquidCrystal_I2C`
   - `OneWire`
   - `DallasTemperature`
3. Select board: **ESP32 Dev Module** (or DOIT ESP32 DEVKIT V1).
4. Update the default Wi-Fi SSID and Web Server IP in the sketch or use the onboard **Setup Mode** / Web App Settings.
5. Upload the code to your ESP32.

---

## 📂 Project Structure

```
├── api/
│   ├── control_valve.php   # Manual & Auto valve actuation endpoint
│   ├── get_latest.php      # 10s telemetry fetcher & 1-hour chart series
│   ├── reset_system.php    # Factory reset & EEPROM wipe trigger
│   ├── simulator.php       # Live test sensor injector (Normal vs Bad water)
│   ├── telemetry.php       # Ingests ESP32 POST data & evaluates thresholds
│   └── test_telegram.php   # Test Telegram notification dispatcher
├── assets/
│   ├── css/
│   │   └── style.css       # Water theme, wave animations & alert styles
│   ├── images/
│   │   └── logo.svg        # Modern vector SVG water droplet brand logo
│   └── js/
│       ├── audio.js        # Web Audio procedural siren sound synthesizer
│       └── dashboard.js    # Chart.js 1h graphs, 10s polling, alert theme
├── data/
│   ├── data.json           # Time-series sensor logs
│   ├── device_state.json   # Valve state, heartbeat & ESP32 diagnostics
│   ├── settings.json       # Thresholds, Telegram & Wi-Fi settings
│   └── users.json          # User accounts (ignored in .gitignore)
├── esp32/
│   ├── esp32_water_monitor.ino # Full Arduino C++ code with EEPROM & LCD
│   └── WIRING_GUIDE.md     # Detailed pinout & electrical connection diagrams
├── includes/
│   ├── config.php          # Paths, defaults and constants
│   ├── footer.php          # Bottom footer & animated water ripple
│   ├── functions.php       # Thread-safe JSON DB, auth & WQI calculation
│   └── header.php          # Navbar, CDN scripts, and user session menu
├── export.php              # CSV data exporter
├── history.php             # Sensor records with status filtering & pagination
├── index.php               # Main real-time live dashboard
├── login.php               # User login page
├── logout.php              # Session sign-out handler
├── register.php            # Operator registration page
├── settings.php            # System configuration & ESP32 hardware health
├── .gitignore              # Ignores users.json and system files
└── README.md               # Complete project documentation
```

---

## 🛡️ License & Credits
Developed for **Smart Water Quality Management System © 2026**.  
GitHub Repository: [https://github.com/ashishvegan/iot-smart-water-quality-management](https://github.com/ashishvegan/iot-smart-water-quality-management)
