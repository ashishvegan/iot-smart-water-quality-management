<?php
/**
 * CSV Data Exporter
 * IoT Smart Water Quality Management System
 */

require_once __DIR__ . '/includes/functions.php';

// Check authentication
require_login();

$records = read_json_file(DATA_FILE, []);

// Sort latest first
usort($records, function($a, $b) {
    $tA = isset($a['epoch']) ? $a['epoch'] : strtotime($a['timestamp']);
    $tB = isset($b['epoch']) ? $b['epoch'] : strtotime($b['timestamp']);
    return $tB - $tA;
});

$filename = 'AquaSense_Water_Records_' . date('d-m-Y_h-i-s_A') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header Columns
fputcsv($output, [
    'Record ID',
    'Timestamp',
    'TDS (ppm)',
    'Turbidity (NTU)',
    'Temperature (°C)',
    'pH Level',
    'Quality Score (%)',
    'Quality Status',
    'Solenoid Valve Relay 1',
    'Control Mode',
    'Detected Anomalies / Issues'
]);

foreach ($records as $r) {
    $valveText = !empty($r['valve_state']) ? 'OPEN (Flow Enabled)' : 'CLOSED (Shutoff)';
    $issuesText = !empty($r['issues']) && is_array($r['issues']) ? implode(' | ', $r['issues']) : 'Normal';
    
    $rId = isset($r['id']) ? $r['id'] : '';
    $rTime = format_datetime(isset($r['epoch']) ? $r['epoch'] : (isset($r['timestamp']) ? $r['timestamp'] : time()));
    $rTds = isset($r['tds']) ? $r['tds'] : '';
    $rTurb = isset($r['turbidity']) ? $r['turbidity'] : '';
    $rTemp = isset($r['temperature']) ? $r['temperature'] : '';
    $rPh = isset($r['ph']) ? $r['ph'] : '';
    $rScore = isset($r['score']) ? $r['score'] : '';
    $rStatus = isset($r['status']) ? strtoupper($r['status']) : 'NORMAL';
    $rMode = isset($r['valve_mode']) ? strtoupper($r['valve_mode']) : 'AUTO';

    fputcsv($output, [
        $rId,
        $rTime,
        $rTds,
        $rTurb,
        $rTemp,
        $rPh,
        $rScore,
        $rStatus,
        $valveText,
        $rMode,
        $issuesText
    ]);
}

fclose($output);
exit;
