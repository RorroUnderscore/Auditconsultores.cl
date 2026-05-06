<?php declare(strict_types=1);
session_start();
require_once __DIR__ . '/../lib/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: /admin'); exit; }
$pdo = db();

if (isset($_GET['logout'])) { session_destroy(); header('Location: /admin'); exit; }

$estates = ['Directivos','Docentes','Apoderados','Paradocentes'];
$tab = (string)($_GET['tab'] ?? 'datos');
$selectedInstitutionId = isset($_GET['institution_id']) ? (int)$_GET['institution_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

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
    $pdo->prepare('INSERT INTO participants(institution_id, project_id, estate, name, email) VALUES (?,?,?,?,?)')->execute([
      (int)$_POST['institution_id'], 1, (string)$_POST['estate'], $fullName, trim((string)$_POST['email'])
    ]);
  } elseif ($action === 'delete_participant') {
    $pdo->prepare('DELETE FROM participants WHERE id=?')->execute([(int)$_POST['participant_id']]);
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
  }
}

function activeTab(string $tab, string $current): string { return $tab === $current ? 'nav-item active' : 'nav-item'; }
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sistema de Diagnóstico</title>
<style>
:root{--bg:#f2f4f8;--sidebar:#1f1d5a;--sidebar2:#2b2781;--accent:#4f46e5;--accent2:#22a3e5;--card:#fff;--line:#dfe3ea;--text:#1f2937}
*{box-sizing:border-box} body{margin:0;font-family:Segoe UI,sans-serif;background:var(--bg);color:var(--text)}
.layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
.side{background:linear-gradient(180deg,#1b1753,#24206f);color:#c7cdfc;padding:12px;display:flex;flex-direction:column}
.logo{color:#9fb1ff;letter-spacing:1px;font-weight:700;margin:8px 6px 16px}
.project-card{background:#3a30b6;border-radius:14px;padding:14px;color:#fff;margin-bottom:10px}
.project-card .badge{background:#2dd483;color:#093d21;padding:2px 8px;border-radius:999px;font-weight:700;font-size:12px}
.new-btn{border:1px dashed #5864d8;padding:10px;border-radius:10px;text-align:center;color:#93a0ff;margin-bottom:10px}
.nav{border-top:1px solid rgba(255,255,255,.15);padding-top:8px;flex:1}
.nav-item{display:block;color:#aeb7ff;text-decoration:none;padding:10px 10px;border-radius:10px;margin:2px 0}
.nav-item.active{background:#3f36c0;color:#fff;font-weight:700}
.side-footer{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.kpi{background:#2d2a7a;border-radius:10px;padding:10px;text-align:center;color:#9ea9ff}
.main{padding:20px}
.topbar{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #d8dce6;padding-bottom:10px}
.breadcrumb{color:#8892a6}.status{background:#c6f1d7;color:#12633d;border-radius:999px;padding:4px 10px;font-weight:700;font-size:12px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;overflow:hidden}
.card h3{margin:0;padding:16px 18px;border-bottom:1px solid #edf0f5}
.card-body{padding:16px}
input,select,textarea,button{font:inherit}
input,select,textarea{width:100%;padding:11px;border:1px solid #d7dce6;border-radius:11px;background:#fff;margin-top:6px}
label{font-weight:600;color:#556277}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px}
.btn{background:#4f46e5;color:#fff;border:none;padding:11px 16px;border-radius:12px;cursor:pointer;font-weight:700}
.btn.gray{background:#eef0f5;color:#374151}.btn.danger{background:#ef4444}.chips{display:flex;gap:8px;flex-wrap:wrap}
.chip{padding:8px 12px;border:1px solid #d7dce6;border-radius:999px;background:#f3f4f6;color:#4b5563}
.chip.active{background:#1da0e7;color:#fff;border-color:#1da0e7}
table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e6e9f0;text-align:left}
.empty{border:2px dashed #d8dde8;border-radius:12px;padding:30px;text-align:center;color:#94a3b8}
</style></head><body>
<div class='layout'>
  <aside class='side'>
    <div class='logo'>SISTEMA DE DIAGNOSTICO</div>
    <div class='project-card'><strong><?= htmlspecialchars((string)($selectedInstitution['name'] ?? 'Convivencia Escolar')) ?></strong><br><small>P1</small> <span class='badge'>En curso</span></div>
    <div class='new-btn'>+ Nuevo Proyecto</div>
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
    <div class='side-footer'>
      <div class='kpi'><strong>1</strong><br>Proyectos</div>
      <div class='kpi'><strong><?= array_sum($participantCounts) ?></strong><br>Particip.</div>
      <div class='kpi'><strong><?= count($institutions) ?></strong><br>Inst.</div>
    </div>
  </aside>

  <main class='main'>
    <div class='topbar'><div><span class='breadcrumb'>Convivencia Escolar /</span> <strong><?= $selectedInstitution ? htmlspecialchars((string)$selectedInstitution['name']) : 'Hub Instituciones' ?></strong> <span class='status'>En curso</span></div><div>Administrador</div></div>

    <?php if(!$selectedInstitution): ?>
      <div class='grid2' style='margin-top:16px'>
        <section class='card'>
          <h3>Crear Institución</h3>
          <div class='card-body'>
            <form method='post'>
              <input type='hidden' name='action' value='create_institution'>
              <label>Nombre</label><input name='name' required>
              <div class='row'><div><label>Código</label><input name='code'></div><div><label>RBD</label><input name='rbd'></div></div>
              <div class='row'><div><label>Comuna</label><input name='commune'></div><div><label>Región</label><input name='region'></div></div>
              <div class='row'><div><label>Mail</label><input name='email'></div><div><label>Teléfono</label><input name='phone'></div></div>
              <button class='btn'>Guardar</button>
            </form>
          </div>
        </section>
        <section class='card'>
          <h3>Instituciones</h3>
          <div class='card-body'>
            <?php foreach($institutions as $i): ?>
              <div style='padding:10px;border:1px solid #e6e9f0;border-radius:12px;margin-bottom:8px'>
                <strong><?= htmlspecialchars((string)$i['name']) ?></strong><br><small><?= htmlspecialchars((string)($i['commune']??'')) ?> · <?= htmlspecialchars((string)($i['region']??'')) ?></small>
                <div style='margin-top:8px;display:flex;gap:8px'>
                  <a class='btn gray' style='text-decoration:none' href='?institution_id=<?= (int)$i['id'] ?>&tab=datos'>Abrir menú</a>
                  <form method='post' onsubmit='return confirm("¿Eliminar?")'>
                    <input type='hidden' name='action' value='delete_institution'><input type='hidden' name='institution_id' value='<?= (int)$i['id'] ?>'>
                    <button class='btn danger'>Eliminar</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    <?php else: ?>
      <?php if($tab==='datos'): ?>
        <section class='card' style='margin-top:16px'><h3>Datos de la Institución</h3><div class='card-body'>
          <form method='post'>
            <input type='hidden' name='action' value='update_institution'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='datos'>
            <div class='row'><div><label>Nombre</label><input name='name' value='<?= htmlspecialchars((string)$selectedInstitution['name']) ?>'></div><div><label>Calle</label><input name='address_line' value='<?= htmlspecialchars((string)($selectedInstitution['address_line']??'')) ?>'></div></div>
            <div class='row'><div><label>Comuna</label><input name='commune' value='<?= htmlspecialchars((string)($selectedInstitution['commune']??'')) ?>'></div><div><label>Región</label><input name='region' value='<?= htmlspecialchars((string)($selectedInstitution['region']??'')) ?>'></div></div>
            <div class='row'><div><label>Email</label><input name='email' value='<?= htmlspecialchars((string)($selectedInstitution['email']??'')) ?>'></div><div><label>Teléfono</label><input name='phone' value='<?= htmlspecialchars((string)($selectedInstitution['phone']??'')) ?>'></div></div>
            <div class='row'><div><label>Dependencia</label><input name='dependency' value='<?= htmlspecialchars((string)($selectedInstitution['dependency']??'')) ?>'></div><div><label>Estado</label><select name='status'><option value='active'>Activa</option><option value='inactive' <?= (($selectedInstitution['status']??'')==='inactive'?'selected':'') ?>>Inactiva</option></select></div></div>
            <button class='btn'>Guardar</button>
          </form>
        </div></section>

        <section class='card' style='margin-top:14px'><h3>Responsable del Estudio</h3><div class='card-body'>
          <form method='post'>
            <input type='hidden' name='action' value='create_contact'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='datos'>
            <div class='row'><div><label>Nombre</label><input name='full_name' required></div><div><label>Cargo</label><input name='role_title'></div></div>
            <div class='row'><div><label>Mail</label><input name='email'></div><div><label>Teléfono</label><input name='phone'></div></div>
            <label><input type='checkbox' name='is_primary' style='width:auto'> Contacto principal</label>
            <button class='btn'>Agregar</button>
          </form>
          <?php foreach($contacts as $c): ?><div style='border-top:1px solid #e6e9f0;padding-top:8px;margin-top:8px'><?= htmlspecialchars((string)$c['full_name']) ?><?= ((int)$c['is_primary']===1?' ⭐':'') ?> · <?= htmlspecialchars((string)($c['email']??'')) ?></div><?php endforeach; ?>
        </div></section>

      <?php elseif($tab==='cuestionarios'): ?>
        <section class='card' style='margin-top:16px'><h3>Cuestionarios</h3><div class='card-body'><div class='empty'>Sección temporalmente vacía. Aquí haremos los cambios del constructor de encuestas.</div></div></section>

      <?php elseif($tab==='participantes'): ?>
        <section class='card' style='margin-top:16px'><h3>Participantes</h3><div class='card-body'>
          <div class='chips'><?php foreach($estates as $e): ?><span class='chip'><?= $e ?> (<?= (int)$participantCounts[$e] ?>)</span><?php endforeach; ?></div>
          <form method='post' style='margin-top:10px'>
            <input type='hidden' name='action' value='create_participant'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'>
            <div class='row'><div><label>Nombre</label><input name='full_name' required></div><div><label>Email</label><input name='email' required></div></div>
            <label>Estamento</label><select name='estate'><?php foreach($estates as $e): ?><option><?= $e ?></option><?php endforeach; ?></select>
            <button class='btn'>+ Agregar</button>
          </form>
          <table><thead><tr><th>Nombre</th><th>Mail</th><th>Estamento</th><th>Acciones</th></tr></thead><tbody><?php foreach($participants as $p): ?><tr><td><?= htmlspecialchars((string)$p['name']) ?></td><td><?= htmlspecialchars((string)$p['email']) ?></td><td><?= htmlspecialchars((string)$p['estate']) ?></td><td><form method='post'><input type='hidden' name='action' value='delete_participant'><input type='hidden' name='participant_id' value='<?= (int)$p['id'] ?>'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'><button class='btn danger'>Eliminar</button></form></td></tr><?php endforeach; ?></tbody></table>
        </div></section>

      <?php elseif($tab==='comunicaciones'): ?>
        <section class='card' style='margin-top:16px'><h3>Plantillas de Carta</h3><div class='card-body'><div class='chips'><span class='chip active'>Formal</span><span class='chip'>Amigable</span><span class='chip'>Recordatorio</span></div></div></section>
        <section class='card' style='margin-top:14px'><h3>Carta de Invitación</h3><div class='card-body'><textarea rows='8'>Estimado/a Participante:

Por medio de la presente, le invitamos a participar en el Estudio de [Nombre Institución].

Acceda al cuestionario en:
https://forms.colegio.cl/directivos-2025

Sus respuestas son confidenciales.

Atentamente,
Equipo de Gestión Institucional</textarea></div></section>

      <?php elseif($tab==='participacion'): ?>
        <div class='grid2' style='margin-top:16px'><?php foreach($estates as $e): ?><section class='card'><div class='card-body' style='text-align:center'><h2 style='color:#4f46e5;margin:0'>0%</h2><strong><?= $e ?></strong><div>0/<?= (int)$participantCounts[$e] ?></div></div></section><?php endforeach; ?></div>
        <section class='card' style='margin-top:14px'><h3>Participación por Estamento</h3><div class='card-body'><div class='empty'>Gráfico se conectará con respuestas reales en la siguiente iteración.</div></div></section>

      <?php elseif($tab==='resultados'): ?>
        <section class='card' style='margin-top:16px'><h3>Respuestas por Estamento</h3><div class='card-body'><div class='chips'><?php foreach($estates as $e): ?><span class='chip'><?= $e ?></span><?php endforeach; ?></div><div class='empty' style='margin-top:10px'>Sin respuestas aún.</div></div></section>

      <?php elseif($tab==='entregable'): ?>
        <section class='card' style='margin-top:16px'><h3>Vista Previa del Reporte</h3><div class='card-body'><div class='empty'>Módulo de entregable en construcción (PDF, historial, firma).</div></div></section>

      <?php elseif($tab==='benchmarking'): ?>
        <section class='card' style='margin-top:16px'><h3>Benchmarking entre Proyectos</h3><div class='card-body'><div class='empty'>Necesitas al menos 2 proyectos con cuestionarios cargados.</div></div></section>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>
</body></html>
