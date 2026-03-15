<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FormBuilder — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="assets/css/app.css">
  <style>
    /* Login page specific */
    #login-page { display: block; }
    #app-root { display: block; }
  </style>
</head>
<body style="background:#f0f2f5;">

<!-- Login Page -->
<div id="login-page" class="login-page">
  <div class="login-card">
    <div class="login-logo">Form<span>Builder</span></div>
    <div class="login-tagline">Admin Dashboard — Sign in to continue</div>

    <div id="login-error" class="alert alert-error hidden">
      <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/></svg>
      <span id="login-error-msg"></span>
    </div>

    <div class="form-group">
      <label class="form-label">Username or Email</label>
      <input type="text" class="form-control" id="login-username" placeholder="admin" autocomplete="username">
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input type="password" class="form-control" id="login-password" placeholder="••••••••" autocomplete="current-password">
    </div>

    <button class="btn btn-accent" style="width:100%;padding:13px" id="login-btn" onclick="doLogin()">
      Sign In
    </button>

    <p class="text-center text-muted text-xs mt" style="margin-top:24px">
      Default: admin / Admin@123<br>
      <em>Change after first login</em>
    </p>
  </div>
</div>

<div id="app-root"></div>

<script>
 
  window.APP_BASE = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') ?>';
</script>
<script src="assets/js/app.js"></script>
</body>
</html>