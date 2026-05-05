<?php declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';
$pdo = db();
$token = preg_replace('/[^A-Za-z0-9]/', '', $_GET['token'] ?? '');
$stmt = $pdo->prepare('SELECT t.id token_id,t.used_at,p.name participant_name,p.email,p.estate,f.id form_id,f.status,s.name survey_name,pr.name project_name,i.name institution_name
FROM invitation_tokens t
JOIN participants p ON p.id=t.participant_id
JOIN forms f ON f.id=t.form_id
JOIN surveys s ON s.id=f.survey_id
JOIN projects pr ON pr.id=s.project_id
JOIN institutions i ON i.id=pr.institution_id
WHERE t.token=? LIMIT 1');
$stmt->execute([$token]);
$ctx = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ctx) { http_response_code(404); echo 'Token inválido'; exit; }

$qStmt = $pdo->prepare('SELECT id,text,q_order FROM questions WHERE form_id=? ORDER BY q_order,id');
$qStmt->execute([(int)$ctx['form_id']]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
$msg=''; $err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!empty($ctx['used_at'])) $err='Este token ya fue utilizado.';
  elseif ($ctx['status'] !== 'published') $err='La encuesta no está publicada.';
  else {
    $answers=[]; foreach($questions as $q){ $v=(int)($_POST['q'.$q['id']] ?? 0); if($v<1||$v>5){$err='Debes responder todas las preguntas'; break;} $answers[$q['id']]=$v; }
    if (!$err) {
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO responses(token_id,submitted_at) VALUES (?,?)')->execute([(int)$ctx['token_id'], date('c')]);
      $responseId = (int)$pdo->lastInsertId();
      $ins = $pdo->prepare('INSERT INTO response_answers(response_id,question_id,value) VALUES (?,?,?)');
      foreach($answers as $qid=>$val) $ins->execute([$responseId,$qid,$val]);
      $pdo->prepare('UPDATE invitation_tokens SET used_at=? WHERE id=?')->execute([date('c'), (int)$ctx['token_id']]);
      $pdo->commit();
      $msg='Gracias, tu respuesta fue registrada correctamente.';
      $ctx['used_at'] = date('c');
    }
  }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Encuesta 360</title><style>body{font-family:Segoe UI,sans-serif;background:#f8fafc;padding:24px}.box{max-width:920px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px}.q{margin:14px 0}.ok{color:#047857}.err{color:#b91c1c}label{margin-right:8px}</style></head><body><div class='box'><h1><?= htmlspecialchars((string)$ctx['survey_name']) ?> · <?= htmlspecialchars((string)$ctx['estate']) ?></h1><p><?= htmlspecialchars((string)$ctx['institution_name'].' / '.$ctx['project_name']) ?></p><p>Participante: <strong><?= htmlspecialchars((string)$ctx['participant_name'].' ('.$ctx['email'].')') ?></strong></p>
<?php if ($msg): ?><p class='ok'><?= htmlspecialchars($msg) ?></p><?php endif; ?>
<?php if ($err): ?><p class='err'><?= htmlspecialchars($err) ?></p><?php endif; ?>
<?php if (!empty($ctx['used_at'])): ?><p class='ok'>Esta encuesta ya fue respondida el <?= htmlspecialchars((string)$ctx['used_at']) ?>.</p>
<?php elseif ($ctx['status'] !== 'published'): ?><p class='err'>Encuesta no disponible.</p>
<?php else: ?><form method='post'><?php foreach($questions as $q): ?><div class='q'><p><?= (int)$q['q_order'] ?>. <?= htmlspecialchars((string)$q['text']) ?></p><?php for($v=1;$v<=5;$v++): ?><label><input type='radio' name='q<?= (int)$q['id'] ?>' value='<?= $v ?>' required> <?= $v ?></label><?php endfor; ?></div><?php endforeach; ?><button type='submit'>Enviar respuestas</button></form><?php endif; ?></div></body></html>
