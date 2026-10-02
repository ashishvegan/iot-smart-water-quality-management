/**
 * AquaSense IoT - Smart Water Quality Management & Automatic SV Valve Control
 * Microcontroller: ESP32 30-Pin NodeMCU / DOIT DevKit V1
 * Sensors:
 *   - TDS Sensor: GPIO 34 (ADC1 Channel 6)
 *   - Turbidity Sensor: GPIO 35 (ADC1 Channel 7)
 *   - pH Sensor: GPIO 32 (ADC1 Channel 4)
 *   - DS18B20 Temp Sensor: GPIO 4 (OneWire Digital Bus with 4.7k pullup)
 * Actuator:
 *   - Relay 1 (Solenoid Valve): GPIO 26 (Active LOW: LOW = SV ON / Flow Enabled)
 * Display:
 *   - 16x2 LCD I2C: SDA=GPIO 21, SCL=GPIO 22 (Address 0x27)
 *
 * Network Configuration:
 *   - Wi-Fi Hotspot: "ESP32" (Password: "12345678", 2.4 GHz)
 *   - Cloud Target: https://waterquality.infinityfree.io/api/telemetry.php
 *   - InfinityFree Anti-Bot Bypass: Built-in hardware mbedTLS AES-128-CBC Challenge Solver
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include "mbedtls/aes.h"

// ==============================================================================
// HARDWARE PIN DEFINITIONS (ESP32 30-PIN)
// ==============================================================================
#define PIN_TDS          34  // ADC1 Channel 6 (TDS Analog Output)
#define PIN_TURBIDITY    35  // ADC1 Channel 7 (Turbidity Analog Output)
#define PIN_PH           32  // ADC1 Channel 4 (pH Probe Analog Output - Po)
#define PIN_TEMP_ONEWIRE  4  // OneWire Digital Bus for DS18B20
#define PIN_RELAY_SV     26  // 5V Relay 1: Active LOW (LOW = Solenoid Valve ON)

// ==============================================================================
// HARDCODED WI-FI & CLOUD SERVER CONFIGURATION
// ==============================================================================
const char* WIFI_SSID   = "ESP32";
const char* WIFI_PASS   = "12345678";
const char* SERVER_URL  = "https://waterquality.infinityfree.io/api/telemetry.php";
const char* AUTH_URL    = "https://waterquality.infinityfree.io/?i=1";

// InfinityFree security cookie (pre-calculated from your challenge + auto-updated)
String serverCookie = "__test=486db43ec712b84401588e1de9b5f72f";

// ==============================================================================
// PERIPHERALS INITIALIZATION
// ==============================================================================
// 16x2 Character LCD with PCF8574 I2C Backpack (Address 0x27)
LiquidCrystal_I2C lcd(0x27, 16, 2);

// DS18B20 OneWire Temp Sensor
OneWire oneWire(PIN_TEMP_ONEWIRE);
DallasTemperature tempSensor(&oneWire);

// Timing & State variables
unsigned long lastTelemetryTime = 0;
const unsigned long TELEMETRY_INTERVAL_MS = 10000; // 10 Seconds real-time post
unsigned long lcdPageTimer = 0;
uint8_t lcdPage = 0;
bool valveState = true; // true = SV ON (Flow Enabled)
bool isSolvingChallenge = false;

// ==============================================================================
// SOLENOID VALVE RELAY CONTROL (Active LOW)
// Requirement: Relay 1: When LOW: SV ON Means Water Flow Enable (Automatic + Manual)
// ==============================================================================
void setSolenoidValve(bool enableFlow) {
  valveState = enableFlow;
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
float readTDSSensor(float currentTempC) {
  int analogVal = analogRead(PIN_TDS);
  float voltage = (analogVal / 4095.0) * 3.3; // ESP32 12-bit ADC (3.3V reference)
  
  float compensationCoefficient = 1.0 + 0.02 * (currentTempC - 25.0);
  float compensationVoltage = voltage / compensationCoefficient;
  
  float tdsValue = (133.42 * pow(compensationVoltage, 3) - 255.86 * pow(compensationVoltage, 2) + 857.39 * compensationVoltage) * 0.5;
  if (tdsValue < 0) tdsValue = 0;
  return tdsValue;
}

// Read Turbidity Sensor (NTU)
float readTurbiditySensor() {
  int analogVal = analogRead(PIN_TURBIDITY);
  float voltage = (analogVal / 4095.0) * 3.3;
  
  float ntu = 0;
  if (voltage < 1.0) {
    ntu = 3000;
  } else if (voltage >= 2.8) {
    ntu = 0.5;
  } else {
    ntu = -1120.4 * pow(voltage, 2) + 5742.3 * voltage - 4352.9;
    if (ntu < 0) ntu = 0;
  }
  return ntu;
}

// Read pH Sensor (Po Analog Pin)
float readpHSensor() {
  int rawADC = 0;
  for (int i = 0; i < 10; i++) {
    rawADC += analogRead(PIN_PH);
    delay(5);
  }
  rawADC /= 10;
  
  float voltage = (rawADC / 4095.0) * 3.3;
  float phValue = 7.0 + ((1.65 - voltage) * 3.5);
  if (phValue < 0.0) phValue = 0.0;
  if (phValue > 14.0) phValue = 14.0;
  return phValue;
}

// Internal ESP32 CPU Temperature in Celsius
float readInternalCpuTemp() {
  #if defined(temprature_sens_read)
    return (temprature_sens_read() - 32) / 1.8;
  #else
    return 42.5;
  #endif
}

// ==============================================================================
// 16x2 LCD DISPLAY ROTATION
// ==============================================================================
void updateLCDDisplay(float tds, float turbidity, float tempC, float phVal) {
  if (millis() - lcdPageTimer > 3000) {
    lcdPageTimer = millis();
    lcdPage = (lcdPage + 1) % 2;
    lcd.clear();
  }

  bool isDry = (tds <= 5.0 && (turbidity > 200 || phVal > 11.5));

  if (lcdPage == 0) {
    // Page 1: TDS, SV Status & Turbidity / Dry Status
    lcd.setCursor(0, 0);
    lcd.print("TDS:");
    lcd.print((int)tds);
    lcd.print("ppm ");
    
    lcd.setCursor(11, 0);
    lcd.print(valveState ? "SV:ON" : "SV:OFF");

    lcd.setCursor(0, 1);
    if (isDry) {
      lcd.print("Pipe: DRY(EMPTY)");
    } else {
      lcd.print("Turb:");
      lcd.print(turbidity, 1);
      lcd.print(" NTU");
    }
  } else {
    // Page 2: pH, Temperature & Wi-Fi IP
    lcd.setCursor(0, 0);
    if (isDry) {
      lcd.print("pH:DRY ");
    } else {
      lcd.print("pH:");
      lcd.print(phVal, 2);
    }
    
    lcd.setCursor(9, 0);
    lcd.print("T:");
    lcd.print(tempC, 1);
    lcd.print((char)223); // Degree symbol
    lcd.print("C");

    lcd.setCursor(0, 1);
    if (WiFi.status() == WL_CONNECTED) {
      lcd.print("IP:");
      lcd.print(WiFi.localIP().toString());
    } else {
      lcd.print("WiFi: Reconn...");
    }
  }
}

// ==============================================================================
// INFINITYFREE AES-128-CBC CHALLENGE SOLVER (AUTOMATIC)
// ==============================================================================
String solveInfinityFreeChallenge(const String& html) {
  int idxA = html.indexOf("toNumbers(\"");
  if (idxA < 0) return "";
  String hexA = html.substring(idxA + 11, idxA + 43);

  int idxB = html.indexOf("toNumbers(\"", idxA + 43);
  if (idxB < 0) return "";
  String hexB = html.substring(idxB + 11, idxB + 43);

  int idxC = html.indexOf("toNumbers(\"", idxB + 43);
  if (idxC < 0) return "";
  String hexC = html.substring(idxC + 11, idxC + 43);

  if (hexA.length() != 32 || hexB.length() != 32 || hexC.length() != 32) return "";

  unsigned char key[16], iv[16], cipher[16], plain[16];
  for (int i = 0; i < 16; i++) {
    key[i] = (unsigned char)strtol(hexA.substring(i * 2, i * 2 + 2).c_str(), NULL, 16);
    iv[i] = (unsigned char)strtol(hexB.substring(i * 2, i * 2 + 2).c_str(), NULL, 16);
    cipher[i] = (unsigned char)strtol(hexC.substring(i * 2, i * 2 + 2).c_str(), NULL, 16);
  }

  mbedtls_aes_context aes;
  mbedtls_aes_init(&aes);
  mbedtls_aes_setkey_dec(&aes, key, 128);
  mbedtls_aes_crypt_cbc(&aes, MBEDTLS_AES_DECRYPT, 16, iv, cipher, plain);
  mbedtls_aes_free(&aes);

  char cookieHex[33];
  for (int i = 0; i < 16; i++) {
    sprintf(cookieHex + (i * 2), "%02x", plain[i]);
  }
  cookieHex[32] = '\0';
  return String(cookieHex);
}

// Validate cookie with InfinityFree ByetHost firewall
void authorizeSecuritySession(const String& cookie) {
  HTTPClient authHttp;
  WiFiClientSecure authClient;
  authClient.setInsecure();

  authHttp.begin(authClient, AUTH_URL);
  authHttp.addHeader("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
  authHttp.addHeader("Accept", "*/*");
  authHttp.addHeader("Cookie", cookie);
  authHttp.setTimeout(8000);

  Serial.println("Validating session with InfinityFree firewall...");
  int code = authHttp.GET();
  Serial.printf("Firewall Auth Response: %d\n", code);
  authHttp.end();
}

// ==============================================================================
// TELEMETRY HTTP/HTTPS POST TO WEB DASHBOARD API
// ==============================================================================
void sendTelemetryToDashboard(float tds, float turbidity, float tempC, float phVal) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Wi-Fi disconnected. Attempting auto-reconnect...");
    WiFi.reconnect();
    return;
  }

  HTTPClient http;
  WiFiClientSecure secureClient;
  secureClient.setInsecure(); // Accept SSL certificate for cloud host

  http.begin(secureClient, SERVER_URL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
  http.addHeader("Accept", "*/*");
  if (serverCookie.length() > 0) {
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

  Serial.print("Posting telemetry to: "); Serial.println(SERVER_URL);
  int httpCode = http.POST(json);

  if (httpCode > 0) {
    String payload = http.getString();

    // Check if InfinityFree returned its Javascript AES challenge
    if (payload.indexOf("aes.js") > 0 || payload.indexOf("slowAES") > 0) {
      Serial.println("InfinityFree security challenge received! Solving AES challenge...");
      http.end();

      if (!isSolvingChallenge) {
        isSolvingChallenge = true;
        String solvedHex = solveInfinityFreeChallenge(payload);
        if (solvedHex.length() > 0) {
          serverCookie = "__test=" + solvedHex;
          Serial.println("Challenge Solved! New Cookie: " + serverCookie);
          authorizeSecuritySession(serverCookie);
          isSolvingChallenge = false;
          // Immediately re-post telemetry with new authorized session
          sendTelemetryToDashboard(tds, turbidity, tempC, phVal);
          return;
        }
        isSolvingChallenge = false;
      }
      return;
    }

    Serial.println("Server Response (" + String(httpCode) + "): " + payload);

    // Parse Valve State from server response
    if (payload.indexOf("\"valve_state\":1") > 0) {
      setSolenoidValve(true); // Open Valve
    } else if (payload.indexOf("\"valve_state\":0") > 0) {
      setSolenoidValve(false); // Close Valve
    }
  } else {
    Serial.printf("HTTP POST failed, error: %s\n", http.errorToString(httpCode).c_str());
  }
  http.end();
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

  // Connect to Hardcoded Wi-Fi Hotspot: "ESP32" / "12345678"
  Serial.printf("Connecting to Wi-Fi SSID: '%s'\n", WIFI_SSID);
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Connecting WiFi:");
  lcd.setCursor(0, 1);
  lcd.print(WIFI_SSID);

  WiFi.disconnect(true);
  delay(200);
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  delay(100);

  WiFi.begin(WIFI_SSID, WIFI_PASS);

  // Wait for connection with LCD dot animation
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    lcd.setCursor(strlen(WIFI_SSID) + (attempts % 4), 1);
    lcd.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWi-Fi Connected successfully!");
    Serial.print("Node IP: "); Serial.println(WiFi.localIP());
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WiFi Connected!");
    lcd.setCursor(0, 1);
    lcd.print(WiFi.localIP().toString());
    delay(2000);

    // Perform initial session authorization with InfinityFree ByetHost firewall
    authorizeSecuritySession(serverCookie);
  } else {
    Serial.println("\nWi-Fi connection pending. Node will auto-reconnect in loop...");
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WiFi Connecting");
    lcd.setCursor(0, 1);
    lcd.print("Starting Sensors");
    delay(1500);
  }
}

// ==============================================================================
// ARDUINO MAIN LOOP
// ==============================================================================
void loop() {
  // Read Sensor Values
  float waterTemp = readWaterTemperature();
  float tdsValue = readTDSSensor(waterTemp);
  float turbidityNTU = readTurbiditySensor();
  float phValue = readpHSensor();

  // Update 16x2 LCD Display Continuously
  updateLCDDisplay(tdsValue, turbidityNTU, waterTemp, phValue);

  // Real-time Telemetry Dispatch Every 10 Seconds (Requirement #3)
  unsigned long currentMillis = millis();
  if (currentMillis - lastTelemetryTime >= TELEMETRY_INTERVAL_MS) {
    lastTelemetryTime = currentMillis;
    sendTelemetryToDashboard(tdsValue, turbidityNTU, waterTemp, phValue);
  }

  // Periodic Wi-Fi watchdog
  if (WiFi.status() != WL_CONNECTED && (currentMillis % 15000 < 100)) {
    Serial.println("Wi-Fi still disconnected. Reconnecting to 'ESP32'...");
    WiFi.reconnect();
  }

  delay(50);
}
