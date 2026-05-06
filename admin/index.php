<?php declare(strict_types=1);
session_start();
require_once __DIR__ . '/../lib/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  try {
    $stmt = db()->prepare('SELECT id,password_hash FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($password, (string)$row['password_hash'])) {
      $_SESSION['admin_id'] = (int)$row['id'];
      $_SESSION['admin_email'] = $email;
      header('Location: /admin/dashboard.php');
      exit;
    }

    $error = 'Credenciales inválidas.';
  } catch (Throwable $e) {
    error_log('[ADMIN_LOGIN_ERROR] ' . $e->getMessage());
    $error = 'Error de configuración del servidor/BD. Revisa config/app.php y phpMyAdmin.';
  }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin AuditConsultores</title>
<style>body{font-family:Segoe UI,sans-serif;background:#0f172a;color:#e2e8f0;padding:24px}.card{max-width:720px;margin:auto;background:#1e293b;border-radius:14px;padding:20px}input,button{padding:10px;border-radius:8px;border:1px solid #475569}input{width:100%;margin:6px 0 12px;background:#0f172a;color:#fff}button{background:#00C9A7;color:#0A1628;font-weight:700;cursor:pointer}.hint{font-size:13px;color:#94a3b8}.err{color:#fca5a5}</style></head><body>
<div class="card"><h1>Acceso administrador</h1><p class="hint">Usuario: el que tengas en <code>config/app.php</code> &gt; <code>admin_seed.email</code>. Clave: <code>admin_seed.password</code>.</p>
<?php if($error): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post"><label>Correo</label><input name="email" type="email" required><label>Contraseña</label><input name="password" type="password" required><button type="submit">Ingresar</button></form>
</div></body></html>
