# ESP32 30-Pin Hardware Wiring Guide & Architecture

## System Overview
- **Microcontroller:** ESP32 30-Pin Development Board (NodeMCU / DOIT DevKit V1)
- **Base Expansion:** ESP32 30-Pin Expansion Board (Dual 3.3V and 5V power rails)
- **Actuator:** 5V Relay Module (Active LOW: LOW = Solenoid Valve ON / Water Flow Enabled)
- **Display:** 16x2 Character LCD with PCF8574 I2C Adapter (Address `0x27`)

---

## ⚡ Crucial ESP32 Engineering Consideration: ADC1 vs ADC2
The ESP32 has two ADC units:
- **ADC1:** GPIO 32, 33, 34, 35, 36 (VP), 39 (VN)
- **ADC2:** GPIO 0, 2, 4, 12, 13, 14, 15, 25, 26, 27

> **CRITICAL RULE:** When Wi-Fi is enabled on the ESP32, the Wi-Fi driver uses the SAR ADC2 internally. Any attempt to read analog sensors on ADC2 pins will fail or return `0` / invalid readings.  
> **SOLUTION:** In this design, **all analog sensors (TDS, Turbidity, pH) are exclusively connected to ADC1 pins (GPIO 32, 34, 35)**.

---

## 📌 Complete Pin Assignment & Wiring Table

| Component | Sensor Pin / Terminal | ESP32 Pin | Expansion Board Rail | Notes |
| :--- | :--- | :--- | :--- | :--- |
| **TDS Sensor** | `-` (GND) | — | **GND** | Sensor Ground |
| | `+` (VCC) | — | **5V** (or 3.3V) | Sensor Power |
| | `A` (Analog) | **GPIO 34** | — | ADC1 Channel 6 (Input Only) |
| **Turbidity Sensor** | `-` (GND) | — | **GND** | Sensor Ground |
| | `+` (VCC) | — | **5V** | Sensor Power (5V recommended for full dynamic range) |
| | `A` (Analog) | **GPIO 35** | — | ADC1 Channel 7 (Input Only) |
| **pH Sensor (6-Pin)** | `G`, `G` (Ground) | — | **GND** | Connect both or either to common GND |
| | `V+` (VCC) | — | **5V** | Power (5V) |
| | `Po` (Analog pH) | **GPIO 32** | — | ADC1 Channel 4 (0-14 pH mapped analog voltage) |
| | `To` (Temp out) | *Unused* | — | Optional onboard thermistor |
| | `Do` (Digital out)| *Unused* | — | Optional limit trigger output |
| **DS18B20 Temp** | `GND` | — | **GND** | Sensor Ground |
| | `VCC` | — | **3.3V** or **5V** | Sensor Power |
| | `Data` | **GPIO 4** | — | Digital OneWire bus. **Must connect a 4.7kΩ pull-up resistor between VCC and Data.** |
| **16x2 LCD I2C** | `GND` | — | **GND** | LCD Ground |
| | `VCC` | — | **5V** | 5V rail gives crisp contrast on 16x2 LCD |
| | `SDA` | **GPIO 21** | — | ESP32 Hardware I2C SDA |
| | `SCL` | **GPIO 22** | — | ESP32 Hardware I2C SCL |
| **Relay Module (SV)** | `GND` | — | **GND** | Relay Ground |
| | `VCC` | — | **5V** | 5V rail for coil energization |
| | `IN1` (Signal) | **GPIO 26** | — | Digital Output. **Active LOW:** `LOW` = SV ON (Flow Enabled), `HIGH` = SV OFF (Flow Cut Off) |

---

## 💧 Solenoid Valve Electrical Connection
- **Relay COM (Common):** Connect to External 12V/24V DC Solenoid Power Supply (+)
- **Relay NO (Normally Open):** Connect to Solenoid Valve (+) lead
- **Solenoid Valve (-) lead:** Connect directly to External Power Supply (-) GND
- **Logic:**
  - `digitalWrite(26, LOW)`: Relay coil closes circuit -> Solenoid Valve energizes -> Water flows.
  - `digitalWrite(26, HIGH)`: Relay coil opens circuit -> Solenoid Valve de-energizes -> Water flow shuts off immediately.

---

## 🛠️ Wi-Fi Network & Cloud Configuration
1. **Direct Wi-Fi Hotspot Connection:**
   - The ESP32 is hardcoded to connect directly to your mobile personal hotspot or local Wi-Fi router:
     - **Wi-Fi SSID / Hotspot Name:** `ESP32`
     - **Wi-Fi Password:** `12345678`
     - **Band:** **2.4 GHz Only** *(Ensure "Maximize Compatibility" is enabled on iPhone, or AP Band is set to 2.4 GHz on Android)*.
   - On boot, the 16x2 LCD displays:
     ```
     Connecting WiFi:
     ESP32
     ```
   - When connected, the LCD displays:
     ```
     WiFi Connected!
     <Node IP Address>
     ```
2. **Live Cloud Server:**
   - Sends real-time telemetry every 10 seconds via HTTPS POST to:
     `https://waterquality.infinityfree.io/api/telemetry.php`
   - Uses `WiFiClientSecure` with `client.setInsecure()` and standard browser User-Agent headers.
   - Automatically reconnects in the background if the Wi-Fi hotspot signal drops.

---

## 📦 Required Arduino IDE Libraries
Install via **Tools -> Manage Libraries...**:
1. `LiquidCrystal_I2C` by Frank de Brabander
2. `OneWire` by Paul Stoffregen
3. `DallasTemperature` by Miles Burton
4. Standard ESP32 Board definitions (Espressif Systems)
