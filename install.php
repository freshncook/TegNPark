<?php
// TagNPark one-time installer. Open https://your-site/tagnpark/install.php once, then delete this file.
define('TNP', 1);
$cfgFile = __DIR__ . '/api/config.php';
$schema = __DIR__ . '/api/schema.php';
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$done = null; $err = null;
$installed = file_exists($cfgFile);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$guessUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'example.com') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $host = trim($_POST['db_host'] ?? 'localhost');
  $name = trim($_POST['db_name'] ?? ''); $user = trim($_POST['db_user'] ?? ''); $pass = (string)($_POST['db_pass'] ?? '');
  $site = rtrim(trim($_POST['site'] ?? ''), '/');
  $aname = trim($_POST['a_name'] ?? ''); $aemail = strtolower(trim($_POST['a_email'] ?? '')); $apass = (string)($_POST['a_pass'] ?? '');
  if (!in_array($host, ['localhost', '127.0.0.1'], true)) $err = 'Database host must be localhost (the database on this same hosting account).';
  elseif (!$name || !$user) $err = 'Fill the database name and user.';
  elseif (!filter_var($aemail, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid admin email.';
  elseif (strlen($apass) < 10) $err = 'Admin password must be at least 10 characters.';
  elseif (!filter_var($site, FILTER_VALIDATE_URL)) $err = 'Enter the website address, e.g. https://ramakantrao.in/tagnpark';
  else {
    try {
      $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
      foreach (require $schema as $sql) $pdo->exec($sql);
      require __DIR__ . '/api/lib.php';
      $secret = b32_secret();
      $st = $pdo->prepare('INSERT INTO admins (name,email,pass_hash,role,totp_secret,created) VALUES (?,?,?,?,?,?)');
      $st->execute([$aname ?: 'Owner', $aemail, password_hash($apass, PASSWORD_DEFAULT), 'Super Admin', $secret, time()]);
      $cfg = [
        'DB_HOST' => $host, 'DB_NAME' => $name, 'DB_USER' => $user, 'DB_PASS' => $pass,
        'APP_KEY' => bin2hex(random_bytes(32)),
        'SITE_URL' => $site,
        // Fill these when you have the accounts (see SETUP guide). Empty = demo OTP 123456.
        'MSG91_AUTHKEY' => '', 'MSG91_OTP_TEMPLATE' => '', 'MSG91_ALERT_TEMPLATE' => '',
        'EXOTEL_SID' => '',
      ];
      $php = "<?php\n// TagNPark settings. Keep this file private. Never share APP_KEY.\nif (!defined('TNP')) { http_response_code(403); exit; }\nreturn " . var_export($cfg, true) . ";\n";
      if (file_put_contents($cfgFile, $php) === false) throw new Exception('Could not write api/config.php. Check folder permissions (755).');
      @chmod($cfgFile, 0640);
      foreach ([__DIR__ . '/api/private', __DIR__ . '/uploads/photos'] as $d) if (!is_dir($d)) @mkdir($d, 0755, true);
      $done = ['secret' => $secret, 'email' => $aemail, 'uri' => 'otpauth://totp/TagNPark:' . rawurlencode($aemail) . '?secret=' . $secret . '&issuer=TagNPark', 'site' => $site];
    } catch (Throwable $ex) {
      $err = 'Could not finish: ' . $ex->getMessage();
      if (isset($pdo)) { try { $pdo->exec('DELETE FROM admins WHERE email=' . $pdo->quote($aemail)); } catch (Throwable $x) {} }
    }
  }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TagNPark setup</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<style>
body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#F7F5F0;color:#0E1A2B;margin:0;padding:30px 16px}
.box{max-width:560px;margin:0 auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,.06)}
h1{margin:0 0 6px;font-size:24px} h2{font-size:16px;margin:24px 0 8px} p{color:#5B6678;line-height:1.5}
label{display:block;font-size:13px;font-weight:600;margin:12px 0 5px} input{width:100%;box-sizing:border-box;padding:11px;border:1.5px solid #E4E1D8;border-radius:10px;font-size:15px}
button,.btn{display:inline-block;margin-top:20px;background:#0E1A2B;color:#fff;border:0;border-radius:10px;padding:13px 20px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none}
.err{background:#FEE2E2;color:#B91C1C;padding:12px;border-radius:10px;margin-top:14px}
.ok{background:#DCFCE7;color:#15803D;padding:12px;border-radius:10px}
.hint{font-size:13px;color:#5B6678} code{background:#F1EFE9;padding:2px 6px;border-radius:5px;word-break:break-all}
#qr{margin:14px 0} #qr img,#qr svg{width:200px;height:200px}
</style></head><body><div class="box">
<?php if ($done): ?>
  <h1>✅ TagNPark is installed</h1>
  <p class="ok">Database tables created and your Super Admin account is ready.</p>
  <h2>Last step: set up two-step login</h2>
  <p>Open <b>Google Authenticator</b> (or Microsoft Authenticator) on your phone, tap <b>+</b> → <b>Scan a QR code</b>, and scan this:</p>
  <div id="qr"></div>
  <p class="hint">Can't scan? Choose "Enter a setup key" and type: <code><?= e($done['secret']) ?></code></p>
  <p><b>Save this key somewhere safe now.</b> It will not be shown again. Without it you cannot log in to the admin panel.</p>
  <h2>Then</h2>
  <p>1. Delete <code>install.php</code> from cPanel File Manager.<br>2. Open the admin panel and log in with <b><?= e($done['email']) ?></b>, your password, and the 6-digit code from the app.</p>
  <a class="btn" href="index.html#admin">Open admin panel</a>
  <script>try{var q=qrcode(0,'M');q.addData(<?= json_encode($done['uri']) ?>);q.make();document.getElementById('qr').innerHTML=q.createSvgTag({cellSize:5,margin:2});}catch(e){}</script>
<?php elseif ($installed): ?>
  <h1>Already installed</h1>
  <p>TagNPark is already set up on this site. For safety, please delete <code>install.php</code> from cPanel File Manager.</p>
  <a class="btn" href="index.html#admin">Open admin panel</a>
<?php else: ?>
  <h1>TagNPark setup</h1>
  <p>Fill this once. You need the MySQL database you created in cPanel (MySQL Databases).</p>
  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <h2>1. Database</h2>
    <label>Database host</label><input name="db_host" value="localhost">
    <label>Database name</label><input name="db_name" required placeholder="e.g. ramakant_tagnpark" value="<?= e($_POST['db_name'] ?? '') ?>">
    <label>Database user</label><input name="db_user" required placeholder="e.g. ramakant_tnpuser" value="<?= e($_POST['db_user'] ?? '') ?>">
    <label>Database password</label><input name="db_pass" type="password">
    <h2>2. Website address</h2>
    <label>Where TagNPark is installed</label><input name="site" required value="<?= e($_POST['site'] ?? $guessUrl) ?>">
    <p class="hint">QR stickers will point to this address, e.g. <?= e($guessUrl) ?>/?t=TNP-7Q2X9</p>
    <h2>3. Your admin account</h2>
    <label>Your name</label><input name="a_name" value="<?= e($_POST['a_name'] ?? '') ?>">
    <label>Admin email</label><input name="a_email" type="email" required value="<?= e($_POST['a_email'] ?? '') ?>">
    <label>Admin password (at least 10 characters)</label><input name="a_pass" type="password" required minlength="10">
    <button>Install TagNPark</button>
  </form>
<?php endif; ?>
</div></body></html>
