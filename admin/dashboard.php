<?php declare(strict_types=1);
session_start();
require_once __DIR__ . '/../lib/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: /admin'); exit; }

$pdo = db();
$estates = ['Directivos','Docentes','Apoderados','Paradocentes'];

if (isset($_GET['logout'])) { session_destroy(); header('Location: /admin'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'create_institution') {
    $pdo->prepare('INSERT INTO institutions(name) VALUES (?)')->execute([trim((string)$_POST['name'])]);
  } elseif ($action === 'create_project') {
    $pdo->prepare('INSERT INTO projects(institution_id,name) VALUES (?,?)')->execute([(int)$_POST['institution_id'], trim((string)$_POST['name'])]);
  } elseif ($action === 'create_survey') {
    $pdo->prepare('INSERT INTO surveys(project_id,name) VALUES (?,?)')->execute([(int)$_POST['project_id'], trim((string)$_POST['name'])]);
  } elseif ($action === 'create_form') {
    $pdo->prepare('INSERT INTO forms(survey_id,estate,status) VALUES (?,?,?)')->execute([(int)$_POST['survey_id'], (string)$_POST['estate'], (string)$_POST['status']]);
  } elseif ($action === 'create_question') {
    $pdo->prepare('INSERT INTO questions(form_id,text,q_order,required) VALUES (?,?,?,1)')->execute([(int)$_POST['form_id'], trim((string)$_POST['text']), (int)$_POST['q_order']]);
  } elseif ($action === 'create_participant') {
    $pdo->prepare('INSERT INTO participants(institution_id,project_id,estate,name,email) VALUES (?,?,?,?,?)')->execute([(int)$_POST['institution_id'], (int)$_POST['project_id'], (string)$_POST['estate'], trim((string)$_POST['name']), trim((string)$_POST['email'])]);
  } elseif ($action === 'generate_token') {
    $participantId = (int)$_POST['participant_id'];
    $formId = (int)$_POST['form_id'];
    $token = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO invitation_tokens(participant_id,form_id,token) VALUES (?,?,?)')->execute([$participantId,$formId,$token]);
  }
  header('Location: /admin/dashboard.php'); exit;
}

$institutions = $pdo->query('SELECT * FROM institutions ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$projects = $pdo->query('SELECT p.*, i.name institution_name FROM projects p JOIN institutions i ON i.id=p.institution_id ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC);
$surveys = $pdo->query('SELECT s.*, p.name project_name FROM surveys s JOIN projects p ON p.id=s.project_id ORDER BY s.id DESC')->fetchAll(PDO::FETCH_ASSOC);
$forms = $pdo->query('SELECT f.*, s.name survey_name FROM forms f JOIN surveys s ON s.id=f.survey_id ORDER BY f.id DESC')->fetchAll(PDO::FETCH_ASSOC);
$participants = $pdo->query('SELECT * FROM participants ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$tokens = $pdo->query('SELECT t.*, p.email, p.name, f.estate FROM invitation_tokens t JOIN participants p ON p.id=t.participant_id JOIN forms f ON f.id=t.form_id ORDER BY t.id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard</title><style>body{font-family:Segoe UI,sans-serif;background:#f8fafc;padding:24px;color:#0f172a}.box{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin:10px 0}input,select,button{padding:8px;border:1px solid #cbd5e1;border-radius:8px}button{background:#00C9A7;border:none;font-weight:700}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #e2e8f0;padding:6px;font-size:13px}.top{display:flex;justify-content:space-between}</style></head><body>
<div class='top'><h1>Dashboard Administrativo</h1><a href='?logout=1'>Cerrar sesión</a></div>
<div class='grid'>
<div class='box'><h3>Institución</h3><form method='post'><input type='hidden' name='action' value='create_institution'><input name='name' placeholder='Nombre institución' required><button>Crear</button></form></div>
<div class='box'><h3>Proyecto</h3><form method='post'><input type='hidden' name='action' value='create_project'><select name='institution_id' required><?php foreach($institutions as $i): ?><option value='<?= $i['id'] ?>'><?= htmlspecialchars($i['name']) ?></option><?php endforeach; ?></select><input name='name' placeholder='Nombre proyecto' required><button>Crear</button></form></div>
<div class='box'><h3>Encuesta</h3><form method='post'><input type='hidden' name='action' value='create_survey'><select name='project_id' required><?php foreach($projects as $p): ?><option value='<?= $p['id'] ?>'><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?></select><input name='name' placeholder='Nombre encuesta' required><button>Crear</button></form></div>
<div class='box'><h3>Formulario por estamento</h3><form method='post'><input type='hidden' name='action' value='create_form'><select name='survey_id' required><?php foreach($surveys as $s): ?><option value='<?= $s['id'] ?>'><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select><select name='estate'><?php foreach($estates as $e): ?><option><?= $e ?></option><?php endforeach; ?></select><select name='status'><option value='draft'>draft</option><option value='published'>published</option><option value='closed'>closed</option></select><button>Crear</button></form></div>
<div class='box'><h3>Pregunta Likert</h3><form method='post'><input type='hidden' name='action' value='create_question'><select name='form_id' required><?php foreach($forms as $f): ?><option value='<?= $f['id'] ?>'><?= htmlspecialchars($f['survey_name'].' · '.$f['estate']) ?></option><?php endforeach; ?></select><input name='text' placeholder='Texto pregunta' required><input name='q_order' type='number' value='1' min='1'><button>Crear</button></form></div>
<div class='box'><h3>Participante</h3><form method='post'><input type='hidden' name='action' value='create_participant'><select name='institution_id' required><?php foreach($institutions as $i): ?><option value='<?= $i['id'] ?>'><?= htmlspecialchars($i['name']) ?></option><?php endforeach; ?></select><select name='project_id' required><?php foreach($projects as $p): ?><option value='<?= $p['id'] ?>'><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?></select><select name='estate'><?php foreach($estates as $e): ?><option><?= $e ?></option><?php endforeach; ?></select><input name='name' placeholder='Nombre'><input name='email' type='email' placeholder='Correo'><button>Crear</button></form></div>
</div>
<div class='box'><h3>Generar token</h3><form method='post'><input type='hidden' name='action' value='generate_token'><select name='participant_id'><?php foreach($participants as $p): ?><option value='<?= $p['id'] ?>'><?= htmlspecialchars($p['name'].' · '.$p['email']) ?></option><?php endforeach; ?></select><select name='form_id'><?php foreach($forms as $f): ?><option value='<?= $f['id'] ?>'><?= htmlspecialchars($f['survey_name'].' · '.$f['estate']) ?></option><?php endforeach; ?></select><button>Generar</button></form></div>
<div class='box'><h3>Tokens generados</h3><table><tr><th>Participante</th><th>Estamento</th><th>Link</th><th>Usado</th></tr><?php foreach($tokens as $t): ?><tr><td><?= htmlspecialchars($t['name'].' · '.$t['email']) ?></td><td><?= htmlspecialchars($t['estate']) ?></td><td><a href='/survey/<?= urlencode((string)$t['token']) ?>' target='_blank'>/survey/<?= htmlspecialchars((string)$t['token']) ?></a></td><td><?= $t['used_at'] ? 'Sí' : 'No' ?></td></tr><?php endforeach; ?></table></div>
</body></html>
