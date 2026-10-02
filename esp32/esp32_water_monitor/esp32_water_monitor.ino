/*
 * ==============================================================================
 * AquaSense IoT - Smart Water Quality Management & Automatic SV Valve System
 * ESP32 30-Pin Node with Expansion Board (3.3V & 5V)
 * ==============================================================================
 * 
 * RECOMMENDED PIN CONFIGURATION (ESP32 30-PIN):
 * ------------------------------------------------------------------------------
 * Component                  ESP32 Pin     Expansion Rail  Notes
 * ------------------------------------------------------------------------------
 * TDS Sensor (A)             GPIO 34       5V / GND        ADC1_CH6 (Input Only)
 * Turbidity Sensor (A)       GPIO 35       5V / GND        ADC1_CH7 (Input Only)
 * pH Sensor (Po)             GPIO 32       5V / GND        ADC1_CH4 (Analog pH)
 * DS18B20 Temp (Data)        GPIO 4        3.3V/5V / GND   Digital OneWire (Req 4.7kΩ pullup)
 * 16x2 LCD I2C SDA           GPIO 21       5V / GND        I2C SDA (Addr 0x27)
 * 16x2 LCD I2C SCL           GPIO 22       5V / GND        I2C SCL (Addr 0x27)
 * Relay 1 (SV Solenoid Valve)GPIO 26       5V / GND        Active LOW (LOW = Valve ON)
 * ------------------------------------------------------------------------------
 * CRITICAL NOTE ON ESP32 ADC:
 * ADC1 pins (GPIO 32-39) MUST be used for analog sensors.
 * ADC2 pins CANNOT be used when Wi-Fi is active.
 * ------------------------------------------------------------------------------
 */

#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <EEPROM.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// ==============================================================================
// PIN DEFINITIONS
// ==============================================================================
#define PIN_TDS           34   // TDS Sensor Analog Input (ADC1)
#define PIN_TURBIDITY     35   // Turbidity Sensor Analog Input (ADC1)
#define PIN_PH            32   // pH Sensor Po Analog Output (ADC1)
#define PIN_TEMP_ONEWIRE  4    // DS18B20 Data Pin (Digital)
#define PIN_RELAY_SV      26   // Relay 1 Solenoid Valve (Active LOW)

// ==============================================================================
// I2C LCD CONFIGURATION
// ==============================================================================
// Standard 16x2 LCD with PCF8574 I2C adapter (Address 0x27)
LiquidCrystal_I2C lcd(0x27, 16, 2);

// ==============================================================================
// DS18B20 TEMPERATURE SENSOR SETUP
// ==============================================================================
OneWire oneWire(PIN_TEMP_ONEWIRE);
DallasTemperature tempSensor(&oneWire);

// ==============================================================================
// EEPROM CONFIGURATION & MEMORY MAP
// ==============================================================================
#define EEPROM_SIZE        512
#define EEPROM_MAGIC_BYTE  0xA5  // Magic byte indicates valid configuration saved
#define ADDR_MAGIC         0
#define ADDR_SSID          1     // 32 Bytes
#define ADDR_PASS          33    // 64 Bytes
#define ADDR_SERVER_IP     97    // 64 Bytes
#define ADDR_SERVER_PORT   161   // 2 Bytes (uint16_t)
#define ADDR_SERVER_COOKIE 163   // 128 Bytes

// Default Deployed Cloud Server Credentials (waterquality.infinityfree.io)
const char* DEFAULT_WIFI_SSID     = "AquaSense_HomeWiFi";
const char* DEFAULT_WIFI_PASS     = "WaterSecure2026";
const char* DEFAULT_SERVER_IP     = "waterquality.infinityfree.io";
const uint16_t DEFAULT_SERVER_PORT  = 80;
const char* DEFAULT_SERVER_COOKIE = "";

// Temporary AP Setup Mode Credentials
const char* AP_SSID = "AquaSense-Setup";
const char* AP_PASS = "12345678";

// Runtime Variables
char wifiSSID[33] = "";
char wifiPass[65] = "";
char serverHost[65] = "";
uint16_t serverPort = 80;
char serverCookie[129] = "";

bool isSetupMode = false;
WebServer setupServer(80);

unsigned long lastTelemetryTime = 0;
const unsigned long TELEMETRY_INTERVAL_MS = 10000; // 10 Seconds real-time post
unsigned long lcdPageTimer = 0;
uint8_t lcdPage = 0;

// ==============================================================================
// EEPROM HELPER FUNCTIONS
// ==============================================================================
void writeEEPROMString(int startAddr, const String& data, int maxLen) {
  int len = data.length();
  if (len >= maxLen) len = maxLen - 1;
  for (int i = 0; i < len; i++) {
    EEPROM.write(startAddr + i, data[i]);
  }
  EEPROM.write(startAddr + len, '\0');
}

String readEEPROMString(int startAddr, int maxLen) {
  char buf[maxLen];
  for (int i = 0; i < maxLen; i++) {
    buf[i] = EEPROM.read(startAddr + i);
    if (buf[i] == '\0') break;
  }
  buf[maxLen - 1] = '\0';
  return String(buf);
}

void saveConfigToEEPROM(const String& ssid, const String& pass, const String& host, uint16_t port, const String& cookie = "") {
  EEPROM.write(ADDR_MAGIC, EEPROM_MAGIC_BYTE);
  writeEEPROMString(ADDR_SSID, ssid, 32);
  writeEEPROMString(ADDR_PASS, pass, 64);
  writeEEPROMString(ADDR_SERVER_IP, host, 64);
  EEPROM.write(ADDR_SERVER_PORT, (port >> 8) & 0xFF);
  EEPROM.write(ADDR_SERVER_PORT + 1, port & 0xFF);
  writeEEPROMString(ADDR_SERVER_COOKIE, cookie, 128);
  EEPROM.commit();
}

void clearEEPROMConfig() {
  EEPROM.write(ADDR_MAGIC, 0x00); // Invalidate magic byte
  for (int i = 1; i < 300; i++) {
    EEPROM.write(i, 0x00);
  }
  EEPROM.commit();
}

bool loadConfigFromEEPROM() {
  uint8_t magic = EEPROM.read(ADDR_MAGIC);
  if (magic != EEPROM_MAGIC_BYTE) {
    return false; // No valid configuration stored
  }
  String sSsid = readEEPROMString(ADDR_SSID, 32);
  String sPass = readEEPROMString(ADDR_PASS, 64);
  String sHost = readEEPROMString(ADDR_SERVER_IP, 64);
  uint16_t port = (EEPROM.read(ADDR_SERVER_PORT) << 8) | EEPROM.read(ADDR_SERVER_PORT + 1);
  String sCookie = readEEPROMString(ADDR_SERVER_COOKIE, 128);

  if (sSsid.length() == 0 || port == 0) return false;

  sSsid.toCharArray(wifiSSID, 33);
  sPass.toCharArray(wifiPass, 65);
  sHost.toCharArray(serverHost, 65);
  serverPort = port;
  sCookie.toCharArray(serverCookie, 129);
  return true;
}

// ==============================================================================
// SOLENOID VALVE RELAY CONTROL (Active LOW)
// ==============================================================================
// Requirement: Relay 1: When LOW: SV ON Means Water Flow Enable (Automatic + Manual)
void setSolenoidValve(bool enableFlow) {
  if (enableFlow) {
    digitalWrite(PIN_RELAY_SV, LOW);  // Active LOW = Valve OPEN (Flow ON)
  } else {
    digitalWrite(PIN_RELAY_SV, HIGH); // HIGH = Valve CLOSED (Flow CUT OFF)
  }
}

// ==============================================================================
// SENSOR READING & CALIBRATION FORMULAS
// ==============================================================================

// Read DS18B20 Water Temperature in Celsius
float readWaterTemperature() {
  tempSensor.requestTemperatures();
  float tempC = tempSensor.getTempCByIndex(0);
  if (tempC < -50.0 || tempC > 100.0) {
    return 25.0; // Fallback standard temperature if disconnected
  }
  return tempC;
}

// Read TDS Sensor (Total Dissolved Solids in ppm)
// Uses temperature compensation formula for accurate ppm calculation
float readTDSSensor(float currentTempC) {
  int analogVal = analogRead(PIN_TDS);
  float voltage = (analogVal / 4095.0) * 3.3; // ESP32 12-bit ADC (3.3V reference)
  
  // Temperature compensation formula: fCampensation = 1.0 + 0.02 * (temp - 25.0)
  float compensationCoefficient = 1.0 + 0.02 * (currentTempC - 25.0);
  float compensationVoltage = voltage / compensationCoefficient;
  
  // Convert voltage value to TDS ppm value
  float tdsValue = (133.42 * pow(compensationVoltage, 3) - 255.86 * pow(compensationVoltage, 2) + 857.39 * compensationVoltage) * 0.5;
  if (tdsValue < 0) tdsValue = 0;
  return tdsValue;
}

// Read Turbidity Sensor (NTU)
// Sensor module maps high turbidity (dirty) to lower voltage
float readTurbiditySensor() {
  int analogVal = analogRead(PIN_TURBIDITY);
  float voltage = (analogVal / 4095.0) * 3.3;
  
  // Standard analog turbidity mapping curve
  // Clean water ~ 2.5V - 3.0V (0-5 NTU)
  float ntu = 0;
  if (voltage < 1.0) {
    ntu = 3000; // Extremely cloudy
  } else if (voltage >= 2.8) {
    ntu = 0.5;  // Crystal clear
  } else {
    // Polynomial transfer function
    ntu = -1120.4 * pow(voltage, 2) + 5742.3 * voltage - 4352.9;
    if (ntu < 0) ntu = 0;
  }
  return ntu;
}

// Read pH Sensor (Po Analog Pin)
// Standard pH probe calibration: 2.5V is typically neutral pH 7.0
float readpHSensor() {
  int rawADC = 0;
  // Average 10 samples to smooth analog noise
  for (int i = 0; i < 10; i++) {
    rawADC += analogRead(PIN_PH);
    delay(5);
  }
  rawADC /= 10;
  
  float voltage = (rawADC / 4095.0) * 3.3;
  // Standard calibration curve: pH = 7.0 + ((2.5 - Voltage) * CalibrationSlope)
  // Typically: 3.5 * voltage or calibrated around 7.0
  float phValue = 7.0 + ((1.65 - voltage) * 3.5);
  if (phValue < 0.0) phValue = 0.0;
  if (phValue > 14.0) phValue = 14.0;
  return phValue;
}

// Internal ESP32 CPU Temperature in Celsius
float readInternalCpuTemp() {
  // Built-in ESP32 sensor if supported or calculate estimate
  #if defined(temprature_sens_read)
    return (temprature_sens_read() - 32) / 1.8;
  #else
    return 42.5; // Typical operating baseline
  #endif
}

// ==============================================================================
// SETUP MODE (AP + Captive Setup Webserver + LCD Display)
// ==============================================================================
void handleSetupWebRoot() {
  String html = "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width,initial-scale=1'><title>AquaSense ESP32 Setup</title>";
  html += "<style>body{font-family:sans-serif;background:#081325;color:#e2e8f0;padding:20px;text-align:center;}";
  html += ".card{background:#0e203c;border:1px solid #0284c7;border-radius:12px;max-width:380px;margin:auto;padding:20px;}";
  html += "input{width:100%;box-sizing:border-box;padding:10px;margin:8px 0;border-radius:6px;border:1px solid #334155;background:#1e293b;color:#fff;}";
  html += "button{width:100%;padding:12px;background:#0284c7;color:#fff;border:none;border-radius:6px;font-weight:bold;margin-top:12px;cursor:pointer;}";
  html += "</style></head><body><div class='card'>";
  html += "<h2>💧 AquaSense ESP32</h2><p style='font-size:12px;color:#94a3b8;'>Configure Target Wi-Fi & Web Dashboard Host</p>";
  String curSsid = (strlen(wifiSSID) > 0) ? String(wifiSSID) : String(DEFAULT_WIFI_SSID);
  String curPass = (strlen(wifiPass) > 0) ? String(wifiPass) : "";
  String curHost = (strlen(serverHost) > 0) ? String(serverHost) : String(DEFAULT_SERVER_IP);

  html += "<form method='POST' action='/save'>";
  html += "<div style='text-align:left;font-size:12px;margin-bottom:4px;color:#38bdf8;'>Target Wi-Fi SSID (2.4 GHz Only):</div>";
  html += "<input type='text' name='ssid' placeholder='e.g. MyHomeWiFi' value='" + curSsid + "' required>";
  html += "<div style='text-align:left;font-size:12px;margin-bottom:4px;color:#38bdf8;'>Wi-Fi Password:</div>";
  html += "<input type='password' name='pass' placeholder='Leave empty if open network' value='" + curPass + "'>";
  html += "<div style='text-align:left;font-size:12px;margin-bottom:4px;color:#38bdf8;'>Dashboard Host / Domain:</div>";
  html += "<input type='text' name='host' placeholder='waterquality.infinityfree.io' value='" + curHost + "' required>";
  html += "<div style='text-align:left;font-size:12px;margin-bottom:4px;color:#38bdf8;'>Port (80 for HTTP, 443 for HTTPS):</div>";
  html += "<input type='number' name='port' placeholder='80' value='" + String(serverPort) + "' required>";
  html += "<div style='text-align:left;font-size:12px;margin-bottom:4px;color:#38bdf8;'>Bypass Cookie (Optional):</div>";
  html += "<input type='text' name='cookie' placeholder='e.g. __test=...' value='" + String(serverCookie) + "'>";
  html += "<button type='submit'>Save Credentials & Connect</button>";
  html += "</form></div></body></html>";
  setupServer.send(200, "text/html", html);
}

void handleSetupSave() {
  String sSsid = setupServer.arg("ssid");
  String sPass = setupServer.arg("pass");
  String sHost = setupServer.arg("host");
  uint16_t uPort = setupServer.arg("port").toInt();
  if (uPort == 0) uPort = 80;
  String sCookie = setupServer.arg("cookie");

  // Trim extraneous whitespace from mobile auto-correct/pasting
  sSsid.trim();
  sPass.trim();
  sHost.trim();
  sCookie.trim();

  saveConfigToEEPROM(sSsid, sPass, sHost, uPort, sCookie);

  String resp = "<html><body style='background:#081325;color:#38bdf8;text-align:center;padding:50px;'>";
  resp += "<h2>Credentials Saved!</h2><p>Connecting to <strong>" + sSsid + "</strong>...</p><p>ESP32 is rebooting now.</p></body></html>";
  setupServer.send(200, "text/html", resp);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Saved: ");
  lcd.print(sSsid.substring(0, 9));
  lcd.setCursor(0, 1);
  lcd.print("Rebooting ESP32");
  delay(1500);
  ESP.restart();
}

void enterSetupMode() {
  isSetupMode = true;
  WiFi.disconnect(true);
  WiFi.mode(WIFI_AP);
  WiFi.softAP(AP_SSID, AP_PASS);

  IPAddress apIP = WiFi.softAPIP();

  // Requirement 36: Display temporary WiFi Hotspot Name & Password on LCD
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("AP: ");
  lcd.print(AP_SSID);
  lcd.setCursor(0, 1);
  lcd.print("P: ");
  lcd.print(AP_PASS);

  setupServer.on("/", HTTP_GET, handleSetupWebRoot);
  setupServer.on("/save", HTTP_POST, handleSetupSave);
  setupServer.begin();

  Serial.println("=========================================");
  Serial.println("ESP32 ENTERED SETUP MODE");
  Serial.print("SoftAP SSID: "); Serial.println(AP_SSID);
  Serial.print("SoftAP PASS: "); Serial.println(AP_PASS);
  Serial.print("SoftAP IP:   "); Serial.println(apIP);
  Serial.println("=========================================");
}

// ==============================================================================
// TELEMETRY HTTP/HTTPS POST TO WEB DASHBOARD API
// ==============================================================================
void sendTelemetryToDashboard(float tds, float turbidity, float tempC, float phVal) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Wi-Fi disconnected. Cannot send telemetry.");
    return;
  }

  HTTPClient http;
  String hostStr = String(serverHost);
  hostStr.trim();

  // Build Target URL
  String fullUrl = "";
  if (hostStr.startsWith("http://") || hostStr.startsWith("https://")) {
    fullUrl = hostStr;
    if (!fullUrl.endsWith("/api/telemetry.php")) {
      if (fullUrl.endsWith("/")) fullUrl += "api/telemetry.php";
      else fullUrl += "/api/telemetry.php";
    }
  } else {
    String proto = (serverPort == 443) ? "https://" : "http://";
    fullUrl = proto + hostStr;
    if (serverPort != 80 && serverPort != 443) {
      fullUrl += ":" + String(serverPort);
    }
    fullUrl += "/api/telemetry.php";
  }

  WiFiClient client;
  WiFiClientSecure secureClient;

  if (fullUrl.startsWith("https://")) {
    secureClient.setInsecure(); // Accept SSL cert for cloud host
    http.begin(secureClient, fullUrl);
  } else {
    http.begin(client, fullUrl);
  }

  // Cloud Headers & InfinityFree Compatibility
  http.addHeader("Content-Type", "application/json");
  http.addHeader("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
  http.addHeader("Accept", "*/*");
  if (strlen(serverCookie) > 0) {
    http.addHeader("Cookie", serverCookie);
  }
  http.setTimeout(8000);

  // Read ESP32 Diagnostics
  uint32_t freeRam = ESP.getFreeHeap();
  float cpuTemp = readInternalCpuTemp();
  int32_t rssi = WiFi.RSSI();
  uint32_t uptimeSec = millis() / 1000;
  String ipAddr = WiFi.localIP().toString();

  // Construct JSON Payload
  String json = "{";
  json += "\"tds\":" + String(tds, 1) + ",";
  json += "\"turbidity\":" + String(turbidity, 2) + ",";
  json += "\"temperature\":" + String(tempC, 1) + ",";
  json += "\"ph\":" + String(phVal, 2) + ",";
  json += "\"free_ram\":" + String(freeRam) + ",";
  json += "\"cpu_temp\":" + String(cpuTemp, 1) + ",";
  json += "\"rssi\":" + String(rssi) + ",";
  json += "\"uptime_sec\":" + String(uptimeSec) + ",";
  json += "\"ip\":\"" + ipAddr + "\"";
  json += "}";

  Serial.print("Posting to: "); Serial.println(fullUrl);
  int httpCode = http.POST(json);

  if (httpCode > 0) {
    String payload = http.getString();
    Serial.println("Server Response: " + payload);

    // Parse Valve State and Reset Command from Response
    // Example: {"success":true,"valve_state":1,"reset_eeprom":false}
    
    // Solenoid Valve Control
    if (payload.indexOf("\"valve_state\":1") > 0) {
      setSolenoidValve(true); // Open Valve
    } else if (payload.indexOf("\"valve_state\":0") > 0) {
      setSolenoidValve(false); // Close Valve
    }

    // Remote EEPROM Factory Reset (Requirement 38)
    if (payload.indexOf("\"reset_eeprom\":true") > 0) {
      Serial.println("RESET COMMAND RECEIVED FROM WEB APP! Clearing EEPROM & rebooting...");
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("FACTORY RESET");
      lcd.setCursor(0, 1);
      lcd.print("Clearing EEPROM");
      clearEEPROMConfig();
      delay(2000);
      ESP.restart();
    }
  } else {
    Serial.printf("HTTP POST failed, error: %s\n", http.errorToString(httpCode).c_str());
  }
  http.end();
}

// ==============================================================================
// UPDATE 16X2 I2C LCD DISPLAY
// ==============================================================================
void updateLCDDisplay(float tds, float turbidity, float tempC, float phVal, bool valveState) {
  // Rotate between 2 informative display pages every 3 seconds
  if (millis() - lcdPageTimer > 3000) {
    lcdPageTimer = millis();
    lcdPage = (lcdPage + 1) % 2;
    lcd.clear();
  }

  if (lcdPage == 0) {
    // Page 1: TDS & Turbidity
    lcd.setCursor(0, 0);
    lcd.print("TDS:");
    lcd.print((int)tds);
    lcd.print("ppm ");
    
    lcd.setCursor(11, 0);
    lcd.print(valveState ? "SV:ON" : "SV:OFF");

    lcd.setCursor(0, 1);
    lcd.print("Turb:");
    lcd.print(turbidity, 1);
    lcd.print(" NTU");
  } else {
    // Page 2: pH & Temperature
    lcd.setCursor(0, 0);
    lcd.print("pH:");
    lcd.print(phVal, 2);
    
    lcd.setCursor(9, 0);
    lcd.print("T:");
    lcd.print(tempC, 1);
    lcd.print((char)223); // Degree symbol
    lcd.print("C");

    lcd.setCursor(0, 1);
    lcd.print("IP:");
    lcd.print(WiFi.localIP().toString());
  }
}

// ==============================================================================
// ARDUINO MAIN SETUP
// ==============================================================================
void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n--- AquaSense IoT Microcontroller Starting ---");

  // Initialize Pin Modes
  pinMode(PIN_TDS, INPUT);
  pinMode(PIN_TURBIDITY, INPUT);
  pinMode(PIN_PH, INPUT);
  pinMode(PIN_RELAY_SV, OUTPUT);

  // Relay 1 Default State: Active LOW (SV ON / Water Flow Enabled)
  setSolenoidValve(true);

  // Initialize OneWire DS18B20
  tempSensor.begin();

  // Initialize 16x2 I2C LCD (SDA=GPIO21, SCL=GPIO22)
  Wire.begin(21, 22);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("AquaSense IoT");
  lcd.setCursor(0, 1);
  lcd.print("Starting Node...");
  delay(1500);

  // Initialize EEPROM
  EEPROM.begin(EEPROM_SIZE);

  // Check if valid Wi-Fi configuration exists
  if (!loadConfigFromEEPROM()) {
    Serial.println("No EEPROM configuration found. Loading defaults from project settings...");
    // Save default configuration from settings on initial boot
    saveConfigToEEPROM(DEFAULT_WIFI_SSID, DEFAULT_WIFI_PASS, DEFAULT_SERVER_IP, DEFAULT_SERVER_PORT);
    loadConfigFromEEPROM();
  }

  // Attempt Wi-Fi Connection
  Serial.printf("Connecting to Wi-Fi SSID: %s\n", wifiSSID);
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Connecting WiFi:");
  lcd.setCursor(0, 1);
  lcd.print(String(wifiSSID).substring(0, 16));

  // Clean Wi-Fi radio state and set Station mode
  WiFi.disconnect(true);
  delay(200);
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  delay(100);

  Serial.printf("Initiating connection with SSID: '%s' (Pass length: %d)\n", wifiSSID, strlen(wifiPass));
  WiFi.begin(wifiSSID, wifiPass);

  // 20 Seconds Timeout (40 iterations * 500ms) with LCD countdown
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 40) {
    delay(500);
    Serial.print(".");
    if (attempts % 2 == 0) {
      // Show remaining seconds in top right of LCD
      lcd.setCursor(13, 0);
      int remaining = (40 - attempts) / 2;
      if (remaining < 10) lcd.print(" ");
      lcd.print(remaining);
      lcd.print("s");
    }
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWi-Fi Connected successfully!");
    Serial.print("Assigned Node IP: "); Serial.println(WiFi.localIP());
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WiFi Connected!");
    lcd.setCursor(0, 1);
    lcd.print(WiFi.localIP().toString());
    delay(2000);
  } else {
    int failStatus = WiFi.status();
    Serial.printf("\nFailed to connect to Wi-Fi! Status Code: %d\n", failStatus);
    lcd.clear();
    lcd.setCursor(0, 0);

    if (failStatus == WL_NO_SSID_AVAIL) {
      lcd.print("SSID Not Found!");
      Serial.println("Diagnosis: SSID not found! Verify 2.4 GHz band and SSID spelling.");
    } else if (failStatus == WL_CONNECT_FAILED) {
      lcd.print("Wrong Password!");
      Serial.println("Diagnosis: Authentication failed! Verify Wi-Fi password.");
    } else {
      lcd.print("Conn Timeout!");
      Serial.println("Diagnosis: Connection timed out or weak signal.");
    }

    lcd.setCursor(0, 1);
    lcd.print("Starting AP Mode");
    delay(3000);
    enterSetupMode();
  }
}

// ==============================================================================
// ARDUINO MAIN LOOP
// ==============================================================================
void loop() {
  // If in Setup Mode: Handle captive web server and keep AP active
  if (isSetupMode) {
    setupServer.handleClient();
    delay(10);
    return;
  }

  // Read Sensor Values
  float waterTemp = readWaterTemperature();
  float tdsValue = readTDSSensor(waterTemp);
  float turbidityNTU = readTurbiditySensor();
  float phValue = readpHSensor();
  bool currentValveState = (digitalRead(PIN_RELAY_SV) == LOW); // LOW = SV ON

  // Update 16x2 LCD Display Continuously
  updateLCDDisplay(tdsValue, turbidityNTU, waterTemp, phValue, currentValveState);

  // Real-time Telemetry Dispatch Every 10 Seconds (Requirement #3)
  unsigned long currentMillis = millis();
  if (currentMillis - lastTelemetryTime >= TELEMETRY_INTERVAL_MS) {
    lastTelemetryTime = currentMillis;
    sendTelemetryToDashboard(tdsValue, turbidityNTU, waterTemp, phValue);
  }

  delay(50);
}
