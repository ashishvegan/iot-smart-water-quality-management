<?php
/**
 * User Login Page
 * IoT Smart Water Quality Management System
 */

require_once __DIR__ . '/includes/functions.php';

// If already logged in, go straight to dashboard
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$settings = get_settings();
$appName = isset($settings['app_name']) ? $settings['app_name'] : 'AquaSense IoT';
$logoPath = !empty($settings['logo_path']) ? $settings['logo_path'] : 'assets/images/logo.svg';

$error = '';
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (authenticate_user($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - <?= htmlspecialchars($appName) ?></title>
  <!-- Tailwind CSS & DaisyUI CDN -->
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.10.2/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Custom Water Theme Styles -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-950 text-slate-100 flex items-center justify-center min-h-screen p-4 relative overflow-hidden">
  
  <!-- Dynamic Ambient Water Glow -->
  <div class="water-ambient-bg"></div>

  <div class="w-full max-w-md relative z-10">
    
    <!-- Branding Header -->
    <div class="text-center mb-8">
      <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-tr from-sky-600 to-cyan-400 p-2.5 shadow-xl shadow-sky-500/30 mb-4 animate-bounce">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($appName) ?> Logo" class="w-full h-full object-contain">
      </div>
      <h1 class="text-3xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-sky-400 via-cyan-300 to-blue-200">
        <?= htmlspecialchars($appName) ?>
      </h1>
      <p class="text-xs text-slate-400 mt-1 uppercase tracking-wider font-mono">
        Smart Water Quality & Valve Control Dashboard
      </p>
    </div>

    <!-- Login Card -->
    <div class="water-card p-8 border-sky-500/30">
      
      <h2 class="text-xl font-bold text-white mb-2">Sign In to Dashboard</h2>
      <p class="text-xs text-slate-400 mb-6">Enter your authorized credentials to access telemetry</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error text-xs py-2.5 mb-5 shadow-lg border border-rose-500/40 font-medium">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($msg === 'registered'): ?>
        <div class="alert alert-success text-xs py-2.5 mb-5 shadow-lg border border-emerald-500/40 font-medium">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <span>Account created successfully! Please sign in below.</span>
        </div>
      <?php elseif ($msg === 'logged_out'): ?>
        <div class="alert alert-info text-xs py-2.5 mb-5 shadow-lg border border-sky-500/40 font-medium">
          <span>You have been signed out safely.</span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" class="space-y-4">
        
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Username</span></label>
          <div class="relative">
            <input type="text" name="username" placeholder="admin" value="admin" class="input input-bordered input-sm w-full bg-slate-900 border-slate-700 pl-9 font-medium" required autofocus>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
        </div>

        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Password</span></label>
          <div class="relative">
            <input type="password" id="loginPassword" name="password" placeholder="••••••••" value="admin123" class="input input-bordered input-sm w-full bg-slate-900 border-slate-700 pl-9 font-mono" required>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
        </div>

        <div class="p-2.5 rounded-lg bg-sky-950/40 border border-sky-800/40 text-[11px] text-sky-300 flex items-center justify-between font-mono">
          <span>Default credentials:</span>
          <strong>admin / admin123</strong>
        </div>

        <button type="submit" class="btn btn-info btn-sm w-full font-bold shadow-lg shadow-sky-500/25 mt-2">
          Sign In
        </button>
      </form>

      <div class="divider my-5 text-xs text-slate-600 font-mono">OR</div>

      <div class="text-center text-xs text-slate-400">
        Need an operator account? 
        <a href="register.php" class="text-sky-400 font-semibold hover:underline">Create Account</a>
      </div>

    </div>

    <!-- Footer Copyright -->
    <div class="text-center mt-6 text-[11px] text-slate-500">
      <?= htmlspecialchars(isset($settings['footer_text']) ? $settings['footer_text'] : 'AquaSense IoT © 2026') ?>
    </div>

  </div>

</body>
</html>
