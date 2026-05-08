<?php declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';
$pdo = db();
$token = preg_replace('/[^A-Za-z0-9]/', '', $_GET['token'] ?? '');
$stmt = $pdo->prepare('SELECT t.id token_id,t.used_at,p.id participant_id,p.name participant_name,p.email,p.project_id,p.institution_id,p.estate,i.name institution_name
FROM invitation_tokens t
JOIN participants p ON p.id=t.participant_id
JOIN institutions i ON i.id=p.institution_id
WHERE t.token=? LIMIT 1');
$stmt->execute([$token]);
$ctx = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ctx) { http_response_code(404); echo 'Token inválido'; exit; }

$qzStmt = $pdo->prepare("SELECT * FROM questionnaires WHERE institution_id=? AND project_id=? AND status='published' ORDER BY id DESC LIMIT 1");
$qzStmt->execute([(int)$ctx['institution_id'], (int)$ctx['project_id']]);
$questionnaire = $qzStmt->fetch(PDO::FETCH_ASSOC);
$questions = [];
if ($questionnaire) {
  $qStmt = $pdo->prepare('SELECT id,question_text,q_order FROM questionnaire_questions WHERE questionnaire_id=? AND estate=? ORDER BY q_order,id');
  $qStmt->execute([(int)$questionnaire['id'], (string)$ctx['estate']]);
  $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
}
$msg=''; $err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!empty($ctx['used_at'])) $err='Este token ya fue utilizado.';
  elseif (!$questionnaire) $err='No hay cuestionario publicado.';
  elseif (count($questions)===0) $err='No hay preguntas para tu estamento.';
  else {
    $answers=[]; foreach($questions as $q){ $v=(int)($_POST['q'.$q['id']] ?? 0); if($v<1||$v>5){$err='Debes responder todas las preguntas'; break;} $answers[$q['id']]=$v; }
    $comment = trim((string)($_POST['comment'] ?? ''));
    if (!$err) {
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO responses(token_id,questionnaire_id,participant_email,estate,comment,submitted_at) VALUES (?,?,?,?,?,?)')->execute([(int)$ctx['token_id'], (int)$questionnaire['id'], (string)$ctx['email'], (string)$ctx['estate'], $comment !== '' ? $comment : null, date('c')]);
      $responseId = (int)$pdo->lastInsertId();
      $ins = $pdo->prepare('INSERT INTO questionnaire_response_answers(response_id,questionnaire_question_id,value) VALUES (?,?,?)');
      foreach($answers as $qid=>$val) $ins->execute([$responseId,$qid,$val]);
      $pdo->prepare('UPDATE invitation_tokens SET used_at=? WHERE id=?')->execute([date('c'), (int)$ctx['token_id']]);
      $pdo->prepare('UPDATE participants SET responded_at=? WHERE id=?')->execute([date('c'), (int)$ctx['participant_id']]);
      $pdo->commit();
      $msg='Gracias, tu respuesta fue registrada correctamente.';
      $ctx['used_at'] = date('c');
    }
  }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Encuesta 360</title><style>
:root{--bg:#eef2f7;--bg-grad-1:#f8fbff;--bg-grad-2:#d9e3f2;--card:#fff;--line:#d9e0ea;--text:#0f172a;--muted:#64748b;--brand:#4f46e5;--brand2:#2563eb;--ok:#065f46;--okbg:#ecfdf5;--err:#991b1b;--errbg:#fef2f2}
[data-theme="dark"]{--bg:#0b1220;--bg-grad-1:#0a1633;--bg-grad-2:#3e4a5f;--card:#111827;--line:#334155;--text:#e2e8f0;--muted:#94a3b8;--brand:#6366f1;--brand2:#3b82f6;--ok:#86efac;--okbg:#052e1f;--err:#fca5a5;--errbg:#3f1111}
*{box-sizing:border-box}html,body{min-height:100%}body{margin:0;font-family:Inter,Segoe UI,sans-serif;background:linear-gradient(180deg,var(--bg-grad-1) 0%, var(--bg) 35%, var(--bg-grad-2) 100%);background-attachment:fixed;color:var(--text)}
.wrap{max-width:980px;margin:24px auto;padding:0 16px}.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.toggle{border:1px solid var(--line);background:var(--card);color:var(--text);padding:8px 12px;border-radius:999px;cursor:pointer}
.box{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:24px;box-shadow:0 8px 25px #0000000d}
h1{margin:0 0 8px;font-size:32px}.muted{color:var(--muted)}.q{margin:14px 0;padding:14px;border:1px solid var(--line);border-radius:12px}
.scale{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.scale label{display:inline-flex;align-items:center;gap:6px;padding:8px 10px;border:1px solid var(--line);border-radius:999px;cursor:pointer}
textarea{width:100%;min-height:110px;padding:12px;border:1px solid var(--line);border-radius:12px;background:transparent;color:var(--text)}
button[type='submit']{margin-top:14px;background:linear-gradient(90deg,var(--brand),var(--brand2));color:#fff;border:none;padding:12px 18px;border-radius:12px;font-weight:700;cursor:pointer}
.msg{padding:10px 12px;border-radius:10px;margin:12px 0}.ok{color:var(--ok);background:var(--okbg)}.err{color:var(--err);background:var(--errbg)}
</style></head><body><div class='wrap'><div class='top'><small class='muted'>Plataforma de Diagnóstico Institucional</small><button class='toggle' type='button' onclick='toggleTheme()'>🌗 Tema</button></div><div class='box'><h1><?= htmlspecialchars((string)$ctx['estate']) ?></h1><p class='muted'><?= htmlspecialchars((string)($questionnaire['name'] ?? 'Cuestionario')) ?> · <?= htmlspecialchars((string)$ctx['institution_name']) ?></p><p>Participante: <strong><?= htmlspecialchars((string)$ctx['participant_name'].' ('.$ctx['email'].')') ?></strong></p>
<?php if ($msg): ?><p class='msg ok'><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class='msg err'><?= htmlspecialchars($err) ?></p><?php endif; ?>
<?php if (!empty($ctx['used_at'])): ?><p class='msg ok'>Esta encuesta ya fue respondida el <?= htmlspecialchars((string)$ctx['used_at']) ?>.</p>
<?php elseif (!$questionnaire): ?><p class='err'>Encuesta no disponible.</p>
<?php else: ?><form method='post'><?php foreach($questions as $q): ?><div class='q'><p><strong><?= (int)$q['q_order'] ?>.</strong> <?= htmlspecialchars((string)$q['question_text']) ?></p><div class='scale'><?php for($v=1;$v<=5;$v++): ?><label><input type='radio' name='q<?= (int)$q['id'] ?>' value='<?= $v ?>' required> <?= $v ?></label><?php endfor; ?></div></div><?php endforeach; ?><p class='muted'><small>1 Muy en desacuerdo · 2 En desacuerdo · 3 Neutro · 4 De acuerdo · 5 Muy de acuerdo</small></p><?php if(!empty($questionnaire['enable_comments'])): ?><div class='q'><label>Comentario opcional</label><textarea name='comment' rows='4' placeholder='Escribe aquí tu comentario (opcional)'></textarea></div><?php endif; ?><button type='submit'>Enviar respuestas</button></form><?php endif; ?></div></div><script>const key='survey_theme';const pref=localStorage.getItem(key);if(pref)document.documentElement.setAttribute('data-theme',pref);function toggleTheme(){const c=document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',c);localStorage.setItem(key,c);}</script></body></html>
