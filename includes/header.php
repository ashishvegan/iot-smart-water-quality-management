<?php
/**
 * Header Component
 * IoT Smart Water Quality Management System
 */

require_once __DIR__ . '/functions.php';

$settings = get_settings();
$currentPage = basename($_SERVER['PHP_SELF']);
$appName = isset($settings['app_name']) ? $settings['app_name'] : 'AquaSense IoT';
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'user';
$logoPath = !empty($settings['logo_path']) ? $settings['logo_path'] : 'assets/images/logo.svg';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> - Real-time Water Monitoring & Solenoid Control</title>
  <meta name="description" content="IoT Smart Water Quality Management System with TDS, Turbidity, pH, Temperature monitoring and automatic Solenoid Valve shutoff.">
  
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($logoPath) ?>">

  <!-- Tailwind CSS & DaisyUI CDN -->
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.10.2/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Custom Water Theme Styles -->
  <link rel="stylesheet" href="assets/css/style.css">

  <!-- Audio Synthesizer -->
  <script src="assets/js/audio.js"></script>
</head>
<body class="bg-slate-950 text-slate-100 flex flex-col min-h-screen">
  <!-- Dynamic Ambient Water Glow -->
  <div class="water-ambient-bg"></div>

  <!-- Top Navigation Bar -->
  <header class="navbar-water sticky top-0 z-50 bg-slate-900/80 backdrop-blur-md border-b border-sky-900/40 transition-colors duration-500">
    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
      
      <!-- Logo & Brand -->
      <div class="flex items-center gap-3">
        <a href="index.php" class="flex items-center gap-3 group">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-600 to-cyan-400 p-1.5 shadow-lg shadow-sky-500/25 group-hover:scale-105 transition-transform">
            <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($appName) ?> Logo" class="w-full h-full object-contain">
          </div>
          <div>
            <span class="text-lg font-bold bg-clip-text text-transparent bg-gradient-to-r from-sky-400 via-cyan-300 to-blue-200">
              <?= htmlspecialchars($appName) ?>
            </span>
            <div class="text-[10px] text-sky-400/80 tracking-wider font-mono uppercase">IoT Water Control</div>
          </div>
        </a>
      </div>

      <!-- Main Navigation Links -->
      <nav class="hidden md:flex items-center gap-1 font-medium text-sm">
        <a href="index.php" class="px-3.5 py-2 rounded-lg transition-all <?= ($currentPage == 'index.php') ? 'bg-sky-500/20 text-sky-300 font-semibold border border-sky-500/30' : 'text-slate-300 hover:text-sky-300 hover:bg-slate-800/60' ?>">
          <span class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Dashboard
          </span>
        </a>
        <a href="history.php" class="px-3.5 py-2 rounded-lg transition-all <?= ($currentPage == 'history.php') ? 'bg-sky-500/20 text-sky-300 font-semibold border border-sky-500/30' : 'text-slate-300 hover:text-sky-300 hover:bg-slate-800/60' ?>">
          <span class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20v-6M6 20V10M18 20V4"/></svg>
            Sensor Records
          </span>
        </a>
        <a href="settings.php" class="px-3.5 py-2 rounded-lg transition-all <?= ($currentPage == 'settings.php') ? 'bg-sky-500/20 text-sky-300 font-semibold border border-sky-500/30' : 'text-slate-300 hover:text-sky-300 hover:bg-slate-800/60' ?>">
          <span class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Settings & Hardware
          </span>
        </a>
      </nav>

      <!-- Right Header Actions -->
      <div class="flex items-center gap-3">
        <!-- Audio Alarm Mute Toggle Button -->
        <button id="muteToggleBtn" type="button" class="btn btn-sm btn-ghost gap-1.5 text-xs text-slate-300 border border-slate-700 hover:border-sky-500" title="Toggle Alarm Sound">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
          <span class="hidden sm:inline">Mute Alert</span>
        </button>

        <?php if (is_logged_in()): ?>
          <!-- User Dropdown Menu -->
          <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar border border-sky-500/40 hover:border-sky-400">
              <div class="w-8 rounded-full bg-sky-900 text-sky-200 flex items-center justify-center font-bold text-sm">
                <?= strtoupper(substr($userName, 0, 1)) ?>
              </div>
            </div>
            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow-2xl bg-slate-900 rounded-box w-52 border border-slate-800 text-sm mt-3">
              <li class="menu-title px-4 py-1 text-xs text-sky-400 font-mono">
                <?= htmlspecialchars($userName) ?>
                <span class="badge badge-xs badge-info ml-1"><?= htmlspecialchars($userRole) ?></span>
              </li>
              <div class="divider my-1 opacity-20"></div>
              <li><a href="index.php">Dashboard</a></li>
              <li><a href="history.php">Sensor History</a></li>
              <li><a href="settings.php">System Settings</a></li>
              <div class="divider my-1 opacity-20"></div>
              <li><a href="logout.php" class="text-rose-400 hover:text-rose-300 hover:bg-rose-950/30">Logout</a></li>
            </ul>
          </div>
        <?php else: ?>
          <a href="login.php" class="btn btn-sm btn-info font-semibold shadow-md shadow-sky-500/20">Login</a>
        <?php endif; ?>

        <!-- Mobile Menu Hamburger -->
        <div class="dropdown dropdown-end md:hidden">
          <div tabindex="0" role="button" class="btn btn-ghost btn-sm btn-circle">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
          </div>
          <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow-2xl bg-slate-900 rounded-box w-52 border border-slate-800 text-sm mt-3">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="history.php">Sensor Records</a></li>
            <li><a href="settings.php">Settings & ESP32</a></li>
          </ul>
        </div>
      </div>

    </div>
  </header>

  <!-- Main Application Body Container -->
  <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 relative z-10">
