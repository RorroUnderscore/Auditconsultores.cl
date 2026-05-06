<?php declare(strict_types=1);
session_start();
require_once __DIR__ . '/../lib/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: /admin'); exit; }
$pdo = db();

if (isset($_GET['logout'])) { session_destroy(); header('Location: /admin'); exit; }

$estates = ['Directivos','Docentes','Apoderados','Paradocentes'];
$tab = (string)($_GET['tab'] ?? 'datos');
$selectedInstitutionId = isset($_GET['institution_id']) ? (int)$_GET['institution_id'] : 0;
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'create_institution') {
      $pdo->prepare('INSERT INTO institutions(name, code, rbd, region, commune, address_line, email, phone, dependency, status) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
        trim((string)$_POST['name']), trim((string)$_POST['code']), trim((string)$_POST['rbd']), trim((string)$_POST['region']), trim((string)$_POST['commune']),
        trim((string)$_POST['address_line']), trim((string)$_POST['email']), trim((string)$_POST['phone']), trim((string)$_POST['dependency']), (string)($_POST['status'] ?? 'active')
      ]);
    } elseif ($action === 'update_institution') {
      $pdo->prepare('UPDATE institutions SET name=?, code=?, rbd=?, region=?, commune=?, address_line=?, email=?, phone=?, dependency=?, status=? WHERE id=?')->execute([
        trim((string)$_POST['name']), trim((string)$_POST['code']), trim((string)$_POST['rbd']), trim((string)$_POST['region']), trim((string)$_POST['commune']),
        trim((string)$_POST['address_line']), trim((string)$_POST['email']), trim((string)$_POST['phone']), trim((string)$_POST['dependency']), (string)($_POST['status'] ?? 'active'), (int)$_POST['institution_id']
      ]);
    } elseif ($action === 'delete_institution') {
      $pdo->prepare('DELETE FROM institutions WHERE id=?')->execute([(int)$_POST['institution_id']]);
    } elseif ($action === 'create_contact') {
      $pdo->prepare('INSERT INTO institution_contacts(institution_id, full_name, role_title, email, phone, is_primary) VALUES (?,?,?,?,?,?)')->execute([
        (int)$_POST['institution_id'], trim((string)$_POST['full_name']), trim((string)$_POST['role_title']), trim((string)$_POST['email']), trim((string)$_POST['phone']), isset($_POST['is_primary']) ? 1 : 0
      ]);
    } elseif ($action === 'create_participant') {
      $fullName = trim((string)$_POST['full_name']);
      $institutionId = (int)$_POST['institution_id'];
      $projectId = resolveProjectId($pdo, $institutionId);
      $pdo->prepare('INSERT INTO participants(institution_id, project_id, estate, name, email) VALUES (?,?,?,?,?)')->execute([
        $institutionId, $projectId, (string)$_POST['estate'], $fullName, trim((string)$_POST['email'])
      ]);
    } elseif ($action === 'delete_participant') {
      $pdo->prepare('DELETE FROM participants WHERE id=?')->execute([(int)$_POST['participant_id']]);
    } elseif ($action === 'save_template') {
      $institutionId = (int)$_POST['institution_id'];
      $type = (string)$_POST['template_type'];
      $subject = trim((string)$_POST['subject']);
      $body = trim((string)$_POST['body']);
      $pdo->prepare('INSERT INTO communication_templates(institution_id, template_type, subject, body, updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE subject=VALUES(subject), body=VALUES(body), updated_at=VALUES(updated_at)')->execute([$institutionId, $type, $subject, $body, date('c')]);
    }
  } catch (Throwable $e) {
    error_log('[DASHBOARD_ERROR] ' . $e->getMessage());
    $_SESSION['flash_error'] = 'No se pudo guardar la acción. Revisa proyecto por defecto/BD y campos obligatorios.';
  }

  $redirect = '/admin/dashboard.php';
  if (!empty($_POST['institution_id'])) $redirect .= '?institution_id=' . (int)$_POST['institution_id'] . '&tab=' . urlencode((string)($_POST['tab'] ?? 'datos'));
  header('Location: ' . $redirect);
  exit;
}

$institutions = $pdo->query('SELECT * FROM institutions ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$selectedInstitution = null;
$contacts = [];
$participants = [];
$participantCounts = ['Directivos'=>0,'Docentes'=>0,'Apoderados'=>0,'Paradocentes'=>0];
$templateDefaults = [
  'formal' => ['subject'=>'Invitación a Diagnóstico Institucional', 'body'=>"Estimado/a [NOMBRE],\n\nLe invitamos a responder el diagnóstico institucional de [INSTITUCION].\n\nPuede ingresar en: [LINK]\n\nAtentamente,\nEquipo de Diagnóstico"],
  'amigable' => ['subject'=>'¡Te invitamos a participar!', 'body'=>"Hola [NOMBRE],\n\nQueremos invitarte a responder el diagnóstico de [INSTITUCION].\n\nTu enlace es: [LINK]\n\n¡Gracias por participar!"],
  'recordatorio' => ['subject'=>'Recordatorio: encuesta pendiente', 'body'=>"Hola [NOMBRE],\n\nTe recordamos que aún puedes responder el diagnóstico de [INSTITUCION].\n\nIngresa aquí: [LINK]\n\nGracias."]
];
$templates = $templateDefaults;

if ($selectedInstitutionId > 0) {
  $stmt = $pdo->prepare('SELECT * FROM institutions WHERE id=? LIMIT 1');
  $stmt->execute([$selectedInstitutionId]);
  $selectedInstitution = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($selectedInstitution) {
    $cStmt = $pdo->prepare('SELECT * FROM institution_contacts WHERE institution_id=? ORDER BY is_primary DESC, id DESC');
    $cStmt->execute([$selectedInstitutionId]);
    $contacts = $cStmt->fetchAll(PDO::FETCH_ASSOC);

    $pStmt = $pdo->prepare('SELECT * FROM participants WHERE institution_id=? ORDER BY estate, id DESC');
    $pStmt->execute([$selectedInstitutionId]);
    $participants = $pStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($participants as $p) { if(isset($participantCounts[$p['estate']])) $participantCounts[$p['estate']]++; }

    $tStmt = $pdo->prepare('SELECT template_type, subject, body FROM communication_templates WHERE institution_id=?');
    $tStmt->execute([$selectedInstitutionId]);
    foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $tpl) {
      $templates[$tpl['template_type']] = ['subject'=>(string)$tpl['subject'], 'body'=>(string)$tpl['body']];
    }
  }
}

function activeTab(string $tab, string $current): string { return $tab === $current ? 'nav-item active' : 'nav-item'; }
function resolveProjectId(PDO $pdo, int $institutionId): int {
  $stmt = $pdo->prepare('SELECT id FROM projects WHERE institution_id=? ORDER BY id ASC LIMIT 1');
  $stmt->execute([$institutionId]);
  $id = $stmt->fetchColumn();
  if ($id) return (int)$id;
  $pdo->prepare('INSERT INTO projects(institution_id,name) VALUES (?,?)')->execute([$institutionId, 'Proyecto Base']);
  return (int)$pdo->lastInsertId();
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sistema de Diagnóstico</title>
<style>
:root{--bg:#f2f4f8;--sidebar:#1f1d5a;--sidebar2:#2b2781;--accent:#4f46e5;--accent2:#22a3e5;--card:#fff;--line:#dfe3ea;--text:#1f2937}
*{box-sizing:border-box} body{margin:0;font-family:Segoe UI,sans-serif;background:var(--bg);color:var(--text)}
.layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}.side{background:linear-gradient(180deg,#1b1753,#24206f);color:#c7cdfc;padding:12px;display:flex;flex-direction:column}
.logo{color:#9fb1ff;letter-spacing:1px;font-weight:700;margin:8px 6px 16px}.project-card{background:#3a30b6;border-radius:14px;padding:14px;color:#fff;margin-bottom:10px}
.project-card .badge{background:#2dd483;color:#093d21;padding:2px 8px;border-radius:999px;font-weight:700;font-size:12px}
.new-btn{border:1px dashed #5864d8;padding:10px;border-radius:10px;text-align:center;color:#93a0ff;margin-bottom:10px;text-decoration:none;display:block}
.nav{border-top:1px solid rgba(255,255,255,.15);padding-top:8px;flex:1}.nav-item{display:block;color:#aeb7ff;text-decoration:none;padding:10px 10px;border-radius:10px;margin:2px 0}
.nav-item.active{background:#3f36c0;color:#fff;font-weight:700}.side-footer{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.kpi{background:#2d2a7a;border-radius:10px;padding:10px;text-align:center;color:#9ea9ff}
.main{padding:20px}.topbar{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #d8dce6;padding-bottom:10px}.breadcrumb{color:#8892a6}.status{background:#c6f1d7;color:#12633d;border-radius:999px;padding:4px 10px;font-weight:700;font-size:12px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}.card{background:var(--card);border:1px solid var(--line);border-radius:18px;overflow:hidden}.card h3{margin:0;padding:16px 18px;border-bottom:1px solid #edf0f5}.card-body{padding:16px}
input,select,textarea,button{font:inherit}input,select,textarea{width:100%;padding:11px;border:1px solid #d7dce6;border-radius:11px;background:#fff;margin-top:6px}label{font-weight:600;color:#556277}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px}.btn{background:#4f46e5;color:#fff;border:none;padding:11px 16px;border-radius:12px;cursor:pointer;font-weight:700}.btn.gray{background:#eef0f5;color:#374151}.btn.danger{background:#ef4444}
.chips{display:flex;gap:8px;flex-wrap:wrap}.chip{padding:8px 12px;border:1px solid #d7dce6;border-radius:999px;background:#f3f4f6;color:#4b5563}.chip.active{background:#1da0e7;color:#fff;border-color:#1da0e7}
table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e6e9f0;text-align:left}.empty{border:2px dashed #d8dde8;border-radius:12px;padding:30px;text-align:center;color:#94a3b8}
.alert{padding:12px;border-radius:10px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;margin:12px 0}
</style></head><body>
<div class='layout'><aside class='side'>
  <div class='logo'>SISTEMA DE DIAGNOSTICO</div>
  <div class='project-card'>
    <strong><?= $selectedInstitution ? htmlspecialchars((string)$selectedInstitution['name']) : 'Selecciona una institución' ?></strong><br>
    <small><?= $selectedInstitution ? 'P1' : 'Hub principal' ?></small> <?= $selectedInstitution ? '<span class="badge">En curso</span>' : '' ?>
  </div>
  <?php if($selectedInstitution): ?>
    <a class='new-btn' href='/admin/dashboard.php'>← Volver</a>
    <nav class='nav'>
      <a class='<?= activeTab('datos',$tab) ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=datos'>Datos Institucionales</a>
      <a class='<?= activeTab('cuestionarios',$tab) ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios'>Cuestionarios</a>
      <a class='<?= activeTab('participantes',$tab) ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=participantes'>Participantes</a>
      <a class='<?= activeTab('comunicaciones',$tab) ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=comunicaciones'>Comunicaciones</a>
      <a class='<?= activeTab('participacion',$tab) ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=participacion'>Participación</a>
      <a class='<?= activeTab('resultados',$tab) ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=resultados'>Resultados</a>
      <a class='<?= activeTab('entregable',$tab) ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=entregable'>Entregable</a>
      <a class='<?= activeTab('benchmarking',$tab) ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=benchmarking'>Benchmarking</a>
      <a class='nav-item' href='?logout=1'>Cerrar sesión</a>
    </nav>
  <?php else: ?>
    <div class='empty' style='margin-top:8px'>Selecciona una institución para habilitar módulos</div>
    <div style='margin-top:auto'><a class='new-btn' href='?logout=1'>Cerrar sesión</a></div>
  <?php endif; ?>
  <div class='side-footer'><div class='kpi'><strong>1</strong><br>Proyectos</div><div class='kpi'><strong><?= array_sum($participantCounts) ?></strong><br>Particip.</div><div class='kpi'><strong><?= count($institutions) ?></strong><br>Inst.</div></div>
</aside>

<main class='main'><div class='topbar'><div><span class='breadcrumb'>Convivencia Escolar /</span> <strong><?= $selectedInstitution ? htmlspecialchars((string)$selectedInstitution['name']) : 'Hub Instituciones' ?></strong> <span class='status'>En curso</span></div><div>Administrador</div></div>
<?php if($flashError): ?><div class='alert'><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<?php if(!$selectedInstitution): ?>
  <div class='grid2' style='margin-top:16px'>
    <section class='card'><h3>Crear Institución</h3><div class='card-body'>
      <form method='post'>
        <input type='hidden' name='action' value='create_institution'>
        <label>Nombre</label><input name='name' required>
        <div class='row'><div><label>Código</label><input name='code'></div><div><label>RBD</label><input name='rbd'></div></div>
        <div class='row'><div><label>Comuna</label><input name='commune'></div><div><label>Región</label><input name='region'></div></div>
        <div class='row'><div><label>Mail</label><input name='email'></div><div><label>Teléfono</label><input name='phone'></div></div>
        <button class='btn'>Guardar</button>
      </form></div></section>
    <section class='card'><h3>Instituciones</h3><div class='card-body'><?php foreach($institutions as $i): ?><div style='padding:10px;border:1px solid #e6e9f0;border-radius:12px;margin-bottom:8px'><strong><?= htmlspecialchars((string)$i['name']) ?></strong><br><small><?= htmlspecialchars((string)($i['commune']??'')) ?> · <?= htmlspecialchars((string)($i['region']??'')) ?></small><div style='margin-top:8px;display:flex;gap:8px'><a class='btn gray' style='text-decoration:none' href='?institution_id=<?= (int)$i['id'] ?>&tab=datos'>Abrir menú</a><form method='post' onsubmit='return confirm("¿Eliminar?")'><input type='hidden' name='action' value='delete_institution'><input type='hidden' name='institution_id' value='<?= (int)$i['id'] ?>'><button class='btn danger'>Eliminar</button></form></div></div><?php endforeach; ?></div></section>
  </div>
<?php else: ?>
  <?php if($tab==='datos'): ?>
    <section class='card' style='margin-top:16px'><h3>Datos de la Institución</h3><div class='card-body'><form method='post'><input type='hidden' name='action' value='update_institution'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='datos'><div class='row'><div><label>Nombre</label><input name='name' value='<?= htmlspecialchars((string)$selectedInstitution['name']) ?>'></div><div><label>Calle</label><input name='address_line' value='<?= htmlspecialchars((string)($selectedInstitution['address_line']??'')) ?>'></div></div><div class='row'><div><label>Comuna</label><input name='commune' value='<?= htmlspecialchars((string)($selectedInstitution['commune']??'')) ?>'></div><div><label>Región</label><input name='region' value='<?= htmlspecialchars((string)($selectedInstitution['region']??'')) ?>'></div></div><div class='row'><div><label>Email</label><input name='email' value='<?= htmlspecialchars((string)($selectedInstitution['email']??'')) ?>'></div><div><label>Teléfono</label><input name='phone' value='<?= htmlspecialchars((string)($selectedInstitution['phone']??'')) ?>'></div></div><button class='btn'>Guardar</button></form></div></section>
  <?php elseif($tab==='cuestionarios'): ?><section class='card' style='margin-top:16px'><h3>Cuestionarios</h3><div class='card-body'><div class='empty'>Sección temporalmente vacía. Aquí haremos cambios del constructor.</div></div></section>
  <?php elseif($tab==='participantes'): ?><section class='card' style='margin-top:16px'><h3>Participantes</h3><div class='card-body'><div class='chips'><?php foreach($estates as $e): ?><span class='chip'><?= $e ?> (<?= (int)$participantCounts[$e] ?>)</span><?php endforeach; ?></div><form method='post' style='margin-top:10px'><input type='hidden' name='action' value='create_participant'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'><div class='row'><div><label>Nombre</label><input name='full_name' required></div><div><label>Email</label><input name='email' required></div></div><label>Estamento</label><select name='estate'><?php foreach($estates as $e): ?><option><?= $e ?></option><?php endforeach; ?></select><button class='btn'>+ Agregar</button></form><table><thead><tr><th>Nombre</th><th>Mail</th><th>Estamento</th><th>Acciones</th></tr></thead><tbody><?php foreach($participants as $p): ?><tr><td><?= htmlspecialchars((string)$p['name']) ?></td><td><?= htmlspecialchars((string)$p['email']) ?></td><td><?= htmlspecialchars((string)$p['estate']) ?></td><td><form method='post'><input type='hidden' name='action' value='delete_participant'><input type='hidden' name='participant_id' value='<?= (int)$p['id'] ?>'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'><button class='btn danger'>Eliminar</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
  <?php elseif($tab==='comunicaciones'): ?>
    <section class='card' style='margin-top:16px'><h3>Plantillas de Carta</h3><div class='card-body'>
      <div class='chips'><span class='chip active'>Formal</span><span class='chip'>Amigable</span><span class='chip'>Recordatorio</span></div>
      <p style='margin-top:10px;color:#64748b'>Correo emisor previsto: <strong>infodiagnosticos@auditconsultores.cl</strong>. Variables soportadas: [NOMBRE], [INSTITUCION], [LINK].</p>
    </div></section>
    <div class='grid2' style='margin-top:14px'>
      <?php foreach(['formal'=>'Formal','amigable'=>'Amigable','recordatorio'=>'Recordatorio'] as $type=>$label): ?>
      <section class='card'><h3><?= $label ?></h3><div class='card-body'>
        <form method='post'>
          <input type='hidden' name='action' value='save_template'>
          <input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'>
          <input type='hidden' name='tab' value='comunicaciones'>
          <input type='hidden' name='template_type' value='<?= $type ?>'>
          <label>Asunto</label><input name='subject' value='<?= htmlspecialchars((string)($templates[$type]['subject'] ?? '')) ?>' required>
          <label>Cuerpo</label><textarea name='body' rows='8' required><?= htmlspecialchars((string)($templates[$type]['body'] ?? '')) ?></textarea>
          <button class='btn'>Guardar plantilla</button>
        </form>
      </div></section>
      <?php endforeach; ?>
    </div>
  <?php elseif($tab==='participacion'): ?><section class='card' style='margin-top:16px'><h3>Participación</h3><div class='card-body'><div class='empty'>Gráficos se conectarán con respuestas reales.</div></div></section>
  <?php elseif($tab==='resultados'): ?><section class='card' style='margin-top:16px'><h3>Resultados</h3><div class='card-body'><div class='empty'>Sin respuestas aún.</div></div></section>
  <?php elseif($tab==='entregable'): ?><section class='card' style='margin-top:16px'><h3>Entregable</h3><div class='card-body'><div class='empty'>Módulo en construcción.</div></div></section>
  <?php elseif($tab==='benchmarking'): ?><section class='card' style='margin-top:16px'><h3>Benchmarking</h3><div class='card-body'><div class='empty'>Necesitas al menos 2 proyectos con cuestionarios cargados.</div></div></section>
  <?php endif; ?>
<?php endif; ?>
</main></div></body></html>
