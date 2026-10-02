<?php
/**
 * User Registration Page
 * IoT Smart Water Quality Management System
 */

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$settings = get_settings();
$appName = isset($settings['app_name']) ? $settings['app_name'] : 'AquaSense IoT';
$logoPath = !empty($settings['logo_path']) ? $settings['logo_path'] : 'assets/images/logo.svg';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $res = register_user($username, $password, $name);
        if ($res['success']) {
            header('Location: login.php?msg=registered');
            exit;
        } else {
            $error = isset($res['error']) ? $res['error'] : 'Registration failed.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - <?= htmlspecialchars($appName) ?></title>
  <!-- Tailwind CSS & DaisyUI CDN -->
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.10.2/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Custom Water Theme Styles -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-950 text-slate-100 flex items-center justify-center min-h-screen p-4 relative overflow-hidden">
  
  <div class="water-ambient-bg"></div>

  <div class="w-full max-w-md relative z-10">
    
    <!-- Branding Header -->
    <div class="text-center mb-6">
      <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-sky-600 to-cyan-400 p-2 shadow-xl shadow-sky-500/30 mb-3">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($appName) ?> Logo" class="w-full h-full object-contain">
      </div>
      <h1 class="text-2xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-sky-400 via-cyan-300 to-blue-200">
        <?= htmlspecialchars($appName) ?>
      </h1>
      <p class="text-xs text-slate-400 mt-1 uppercase tracking-wider font-mono">
        Operator Registration
      </p>
    </div>

    <!-- Register Card -->
    <div class="water-card p-8 border-sky-500/30">
      
      <h2 class="text-xl font-bold text-white mb-2">Create New Account</h2>
      <p class="text-xs text-slate-400 mb-6">Register an authorized account for system management</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error text-xs py-2.5 mb-5 shadow-lg border border-rose-500/40 font-medium">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="register.php" class="space-y-3.5">
        
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Full Name</span></label>
          <input type="text" name="name" placeholder="John Doe" class="input input-bordered input-sm w-full bg-slate-900 border-slate-700 font-medium">
        </div>

        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Username</span></label>
          <input type="text" name="username" placeholder="operator1" class="input input-bordered input-sm w-full bg-slate-900 border-slate-700 font-medium" required>
        </div>

        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Password (min 6 characters)</span></label>
          <input type="password" name="password" placeholder="••••••••" class="input input-bordered input-sm w-full bg-slate-900 border-slate-700 font-mono" required>
        </div>

        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs text-slate-300 font-semibold">Confirm Password</span></label>
          <input type="password" name="confirm_password" placeholder="••••••••" class="input input-bordered input-sm w-full bg-slate-900 border-slate-700 font-mono" required>
        </div>

        <button type="submit" class="btn btn-info btn-sm w-full font-bold shadow-lg shadow-sky-500/25 mt-4">
          Register Account
        </button>
      </form>

      <div class="divider my-5 text-xs text-slate-600 font-mono">OR</div>

      <div class="text-center text-xs text-slate-400">
        Already registered? 
        <a href="login.php" class="text-sky-400 font-semibold hover:underline">Sign In</a>
      </div>

    </div>

  </div>

</body>
</html>
