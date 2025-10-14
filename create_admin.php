<?php
/**
 * create_admin.php
 * ----------------
 * Secure bootstrap page to create an admin user.
 *
 * Rules:
 *  - If there are currently NO admins in the database, this page allows anyone to create the FIRST admin.
 *  - If there IS at least one admin, only a logged-in admin can create additional admins.
 *
 * Security:
 *  - CSRF token on POST
 *  - Prepared statements
 *  - password_hash() with PASSWORD_DEFAULT
 */

declare(strict_types=1);

// --- Bootstrap: DB + session ---
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// -----------------------------
// Helpers
// -----------------------------

/**
 * Generate a base62-ish UID of given length (e.g., 26 chars for your `uid` column).
 * - Avoids ambiguous characters and keeps it URL-safe.
 */
function generate_uid(int $len = 26): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'; // no 0/O/I/l
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $idx = random_int(0, $max);
        $out .= $alphabet[$idx];
    }
    return $out;
}

/**
 * Return number of users with role 'admin'.
 */
function admin_count(mysqli $db): int {
    $sql = "SELECT COUNT(*) FROM users WHERE role='admin'";
    $res = $db->query($sql);
    if (!$res) return 0;
    [$count] = $res->fetch_row();
    return (int)$count;
}

/**
 * Is the current session logged in as admin?
 */
function is_logged_in_admin(): bool {
    return isset($_SESSION['user_id'], $_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Create a CSRF token if needed and return it.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_create_admin'])) {
        $_SESSION['csrf_create_admin'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_create_admin'];
}

/**
 * Validate incoming CSRF token.
 */
function check_csrf(?string $token): bool {
    return is_string($token) && hash_equals($_SESSION['csrf_create_admin'] ?? '', $token);
}

// -----------------------------
// Gatekeeping logic
// -----------------------------

$existing_admins = admin_count($mysqli);

/**
 * Access policy:
 *  - If there are zero admins, allow access (initial bootstrap).
 *  - If there is >= 1 admin, require the viewer to be a logged-in admin.
 */
if ($existing_admins > 0 && !is_logged_in_admin()) {
    http_response_code(403);
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Forbidden</title></head><body>";
    echo "<h1>403 Forbidden</h1>";
    echo "<p>An admin already exists. You must be logged in as an admin to create another admin.</p>";
    echo "</body></html>";
    exit;
}

// -----------------------------
// Handle POST (create admin)
// -----------------------------
$errors = [];
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Invalid request token. Please reload the page and try again.';
    }

    // Gather & trim inputs
    $email = trim((string)($_POST['email'] ?? ''));
    $name  = trim((string)($_POST['name'] ?? ''));
    $pass1 = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password_confirm'] ?? '');

    // Basic validation
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($name === '') {
        $errors[] = 'Please enter a display name.';
    }
    if (strlen($pass1) < 8) { // sensible minimum
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($pass1 !== $pass2) {
        $errors[] = 'Passwords do not match.';
    }

    // Ensure email not already used
    if (!$errors) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'That email is already in use.';
        }
        $stmt->close();
    }

    // Insert user
    if (!$errors) {
        $uid  = generate_uid(26);                 // fits your `uid` CHAR(26)
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        $role = 'admin';                           // must match your enum exactly
        $status = 'active';                        // matches your enum('active','invited','disabled')
        $contractor_id = null;                     // admins typically not tied to a contractor

        $stmt = $mysqli->prepare("
            INSERT INTO users (uid, email, name, password_hash, role, contractor_id, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        // Use i (int) for contractor_id but allow NULL via bind_param with 'i' and set to null using set_null
        // However, mysqli bind_param doesn't support NULL directly; use 's' and pass null? Better: set to NULL via PHP type and use 'i' with null + ->bind_param still sends 0.
        // We'll explicitly bind and call $stmt->bind_param with types "sssssis" and set contractor_id to NULL by calling $stmt->bind_param then $stmt->send_long_data? Simpler: use "sssssis" and pass NULL -> PHP will cast to 0. To truly insert NULL, we can use NULL in SQL: VALUES (?, ?, ?, ?, ?, NULL, ?, NOW(), NOW())
        $stmt->close();

        // Re-prepare with actual NULL for contractor_id to avoid 0 default.
        $stmt = $mysqli->prepare("
            INSERT INTO users (uid, email, name, password_hash, role, contractor_id, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NULL, ?, NOW(), NOW())
        ");
        $stmt->bind_param('ssssss', $uid, $email, $name, $hash, $role, $status);

        if ($stmt->execute()) {
            $success_msg = 'Admin user created successfully.';
            // Optional: If no admins existed before, you might auto-login the new admin:
            if ($existing_admins === 0) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['role']    = 'admin';
            }
            // Rotate CSRF to prevent resubmission
            unset($_SESSION['csrf_create_admin']);
        } else {
            $errors[] = 'Database error creating user: ' . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }
}

// -----------------------------
// Render HTML
// -----------------------------
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Create Admin User</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 2rem; background:#f7f7fb; color:#222; }
    .wrap { max-width: 620px; margin: 0 auto; background: #fff; padding: 1.5rem 2rem; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
    h1 { margin-top: 0; font-size: 1.5rem; }
    .note { background:#f0f5ff; border:1px solid #d6e2ff; padding:.75rem 1rem; border-radius:8px; margin-bottom:1rem; }
    .ok { background:#e9f9ee; border:1px solid #c6f0d3; color:#0f5132; }
    .err { background:#fde7e7; border:1px solid #f5c2c7; color:#842029; }
    form label { display:block; margin:.5rem 0 .25rem; font-weight:600; }
    input[type="text"], input[type="email"], input[type="password"] {
      width:100%; padding:.6rem .75rem; border:1px solid #cfd3d7; border-radius:8px; font-size:1rem; background:#fff;
    }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .actions { margin-top:1rem; display:flex; gap:.75rem; }
    button {
      padding:.6rem 1rem; border:0; border-radius:8px; font-weight:700; cursor:pointer;
      background:#2f6fed; color:#fff;
    }
    .muted { color:#666; font-size:.95rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Create Admin User</h1>

    <?php if ($existing_admins === 0): ?>
      <div class="note">
        <strong>Initial Setup:</strong> No admins found. Create the <em>first</em> admin user now.
      </div>
    <?php else: ?>
      <div class="note">
        <strong>Admin Required:</strong> At least one admin already exists. You are
        <?= is_logged_in_admin() ? '<strong>logged in as admin</strong>.' : '<strong>not an admin</strong>.' ?>
      </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
      <div class="note ok"><?= htmlspecialchars($success_msg) ?></div>
      <p class="muted">You can now <a href="./">return to the app</a> or open the <a href="./admin/">admin panel</a>.</p>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="note err">
        <strong>Unable to create admin:</strong>
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>" />
      <label for="name">Display Name</label>
      <input id="name" name="name" type="text" required placeholder="e.g., Clint Freeman" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"/>

      <label for="email">Email (used to log in)</label>
      <input id="email" name="email" type="email" required placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>

      <div class="grid">
        <div>
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required placeholder="At least 8 characters"/>
        </div>
        <div>
          <label for="password_confirm">Confirm Password</label>
          <input id="password_confirm" name="password_confirm" type="password" required placeholder="Re-enter password"/>
        </div>
      </div>

      <div class="actions">
        <button type="submit">Create Admin</button>
        <a href="./" class="muted" style="align-self:center; text-decoration:none;">Cancel</a>
      </div>
    </form>

    <p class="muted" style="margin-top:1rem;">
      This form always creates users with role <code>admin</code> and status <code>active</code>.
      The <code>contractor_id</code> is left <em>NULL</em> for admin users.
    </p>
  </div>
</body>
</html>
