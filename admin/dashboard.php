<?php declare(strict_types=1);
session_start();
require_once __DIR__ . '/../lib/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: /admin'); exit; }

$pdo = db();
if (isset($_GET['logout'])) { session_destroy(); header('Location: /admin'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create_institution') {
    $stmt = $pdo->prepare('INSERT INTO institutions(name, code, rbd, region, commune, address_line, email, phone, dependency, status) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
      trim((string)$_POST['name']), trim((string)$_POST['code']), trim((string)$_POST['rbd']),
      trim((string)$_POST['region']), trim((string)$_POST['commune']), trim((string)$_POST['address_line']),
      trim((string)$_POST['email']), trim((string)$_POST['phone']), trim((string)$_POST['dependency']),
      (string)($_POST['status'] ?? 'active')
    ]);
  } elseif ($action === 'update_institution') {
    $stmt = $pdo->prepare('UPDATE institutions SET name=?, code=?, rbd=?, region=?, commune=?, address_line=?, email=?, phone=?, dependency=?, status=? WHERE id=?');
    $stmt->execute([
      trim((string)$_POST['name']), trim((string)$_POST['code']), trim((string)$_POST['rbd']),
      trim((string)$_POST['region']), trim((string)$_POST['commune']), trim((string)$_POST['address_line']),
      trim((string)$_POST['email']), trim((string)$_POST['phone']), trim((string)$_POST['dependency']),
      (string)($_POST['status'] ?? 'active'), (int)$_POST['institution_id']
    ]);
  } elseif ($action === 'delete_institution') {
    $pdo->prepare('DELETE FROM institutions WHERE id=?')->execute([(int)$_POST['institution_id']]);
  } elseif ($action === 'create_contact') {
    $stmt = $pdo->prepare('INSERT INTO institution_contacts(institution_id, full_name, role_title, email, phone, is_primary) VALUES (?,?,?,?,?,?)');
    $stmt->execute([(int)$_POST['institution_id'], trim((string)$_POST['full_name']), trim((string)$_POST['role_title']), trim((string)$_POST['email']), trim((string)$_POST['phone']), isset($_POST['is_primary']) ? 1 : 0]);
  } elseif ($action === 'delete_contact') {
    $pdo->prepare('DELETE FROM institution_contacts WHERE id=?')->execute([(int)$_POST['contact_id']]);
  }

  $redirect = '/admin/dashboard.php';
  if (!empty($_POST['redirect_institution_id'])) $redirect .= '?institution_id=' . (int)$_POST['redirect_institution_id'];
  header('Location: ' . $redirect);
  exit;
}

$institutions = $pdo->query('SELECT * FROM institutions ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$selectedInstitutionId = isset($_GET['institution_id']) ? (int)$_GET['institution_id'] : 0;
$selectedInstitution = null;
$contacts = [];

if ($selectedInstitutionId > 0) {
  $stmt = $pdo->prepare('SELECT * FROM institutions WHERE id=? LIMIT 1');
  $stmt->execute([$selectedInstitutionId]);
  $selectedInstitution = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($selectedInstitution) {
    $cStmt = $pdo->prepare('SELECT * FROM institution_contacts WHERE institution_id=? ORDER BY is_primary DESC, id DESC');
    $cStmt->execute([$selectedInstitutionId]);
    $contacts = $cStmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard Instituciones</title>
<style>
body{font-family:Segoe UI,sans-serif;background:#f8fafc;padding:20px;color:#0f172a}
.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.box{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px}
.card{border:1px solid #e2e8f0;border-radius:12px;padding:12px;background:#fff}
input,select,button{padding:8px;border-radius:8px;border:1px solid #cbd5e1;width:100%;margin:5px 0}
button{background:#00C9A7;border:none;font-weight:700;color:#0A1628;cursor:pointer}
.btn-danger{background:#ef4444;color:#fff}
.row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.small{font-size:12px;color:#64748b}
.actions{display:flex;gap:8px}
.actions a{display:inline-block;padding:6px 10px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#0f172a}
</style></head><body>
<div class='top'><h1>Hub de Instituciones</h1><div><a href='/admin/dashboard.php'>Inicio</a> · <a href='?logout=1'>Cerrar sesión</a></div></div>

<div class='grid'>
  <div class='box'>
    <h3>Crear institución</h3>
    <form method='post'>
      <input type='hidden' name='action' value='create_institution'>
      <input name='name' placeholder='Nombre institución' required>
      <div class='row'><input name='code' placeholder='Código interno'><input name='rbd' placeholder='RBD'></div>
      <div class='row'><input name='region' placeholder='Región'><input name='commune' placeholder='Comuna'></div>
      <input name='address_line' placeholder='Dirección'>
      <div class='row'><input name='email' placeholder='Email'><input name='phone' placeholder='Teléfono'></div>
      <div class='row'><input name='dependency' placeholder='Dependencia'><select name='status'><option value='active'>Activa</option><option value='inactive'>Inactiva</option></select></div>
      <button>Guardar institución</button>
    </form>
  </div>

  <div class='box'>
    <h3>Instituciones registradas</h3>
    <div class='cards'>
      <?php foreach($institutions as $i): ?>
      <div class='card'>
        <strong><?= htmlspecialchars((string)$i['name']) ?></strong>
        <div class='small'><?= htmlspecialchars((string)($i['commune'] ?? '')) ?> · <?= htmlspecialchars((string)($i['region'] ?? '')) ?></div>
        <div class='small'><?= htmlspecialchars((string)($i['email'] ?? '')) ?></div>
        <div class='actions' style='margin-top:8px'>
          <a href='?institution_id=<?= (int)$i['id'] ?>'>Abrir menú</a>
          <form method='post' onsubmit='return confirm("¿Eliminar institución?")' style='flex:1'>
            <input type='hidden' name='action' value='delete_institution'>
            <input type='hidden' name='institution_id' value='<?= (int)$i['id'] ?>'>
            <button class='btn-danger'>Eliminar</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if($selectedInstitution): ?>
<div class='box' style='margin-top:16px'>
  <h2>Menú institución: <?= htmlspecialchars((string)$selectedInstitution['name']) ?></h2>
  <p class='small'>Edición de ficha institucional + contactos (base para replicar el mockup).</p>
  <div class='grid'>
    <div>
      <h3>Editar ficha</h3>
      <form method='post'>
        <input type='hidden' name='action' value='update_institution'>
        <input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'>
        <input type='hidden' name='redirect_institution_id' value='<?= (int)$selectedInstitution['id'] ?>'>
        <input name='name' value='<?= htmlspecialchars((string)$selectedInstitution['name']) ?>' required>
        <div class='row'><input name='code' value='<?= htmlspecialchars((string)($selectedInstitution['code'] ?? '')) ?>' placeholder='Código'><input name='rbd' value='<?= htmlspecialchars((string)($selectedInstitution['rbd'] ?? '')) ?>' placeholder='RBD'></div>
        <div class='row'><input name='region' value='<?= htmlspecialchars((string)($selectedInstitution['region'] ?? '')) ?>' placeholder='Región'><input name='commune' value='<?= htmlspecialchars((string)($selectedInstitution['commune'] ?? '')) ?>' placeholder='Comuna'></div>
        <input name='address_line' value='<?= htmlspecialchars((string)($selectedInstitution['address_line'] ?? '')) ?>' placeholder='Dirección'>
        <div class='row'><input name='email' value='<?= htmlspecialchars((string)($selectedInstitution['email'] ?? '')) ?>' placeholder='Email'><input name='phone' value='<?= htmlspecialchars((string)($selectedInstitution['phone'] ?? '')) ?>' placeholder='Teléfono'></div>
        <div class='row'><input name='dependency' value='<?= htmlspecialchars((string)($selectedInstitution['dependency'] ?? '')) ?>' placeholder='Dependencia'><select name='status'><option value='active' <?= (($selectedInstitution['status'] ?? 'active')==='active'?'selected':'') ?>>Activa</option><option value='inactive' <?= (($selectedInstitution['status'] ?? '')==='inactive'?'selected':'') ?>>Inactiva</option></select></div>
        <button>Actualizar institución</button>
      </form>
    </div>
    <div>
      <h3>Contactos institución</h3>
      <form method='post'>
        <input type='hidden' name='action' value='create_contact'>
        <input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'>
        <input type='hidden' name='redirect_institution_id' value='<?= (int)$selectedInstitution['id'] ?>'>
        <input name='full_name' placeholder='Nombre contacto' required>
        <input name='role_title' placeholder='Cargo'>
        <input name='email' placeholder='Email'>
        <input name='phone' placeholder='Teléfono'>
        <label><input type='checkbox' name='is_primary' style='width:auto'> Contacto principal</label>
        <button>Agregar contacto</button>
      </form>
      <hr>
      <?php foreach($contacts as $c): ?>
      <div class='card'>
        <strong><?= htmlspecialchars((string)$c['full_name']) ?></strong> <?= (int)$c['is_primary']===1 ? '⭐' : '' ?><br>
        <span class='small'><?= htmlspecialchars((string)($c['role_title'] ?? '')) ?> · <?= htmlspecialchars((string)($c['email'] ?? '')) ?></span>
        <form method='post' onsubmit='return confirm("¿Eliminar contacto?")'>
          <input type='hidden' name='action' value='delete_contact'>
          <input type='hidden' name='contact_id' value='<?= (int)$c['id'] ?>'>
          <input type='hidden' name='redirect_institution_id' value='<?= (int)$selectedInstitution['id'] ?>'>
          <button class='btn-danger'>Eliminar contacto</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>
</body></html>
