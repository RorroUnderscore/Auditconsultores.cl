<?php declare(strict_types=1);
$token = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['token'] ?? '');
$dataFile = __DIR__ . '/data/responses.json';
if (!is_file($dataFile)) file_put_contents($dataFile, json_encode(['responses'=>[]], JSON_PRETTY_PRINT));
$db = json_decode((string)file_get_contents($dataFile), true) ?: ['responses'=>[]];
$already = false;
foreach ($db['responses'] as $r) { if (($r['token'] ?? '') === $token) { $already = true; break; } }
$questions = ['El equipo directivo comunica objetivos claros.','Existe buen clima de convivencia escolar.','La coordinación pedagógica es efectiva.'];
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST' && !$already) {
  $answers=[]; $ok=true;
  foreach ($questions as $i=>$q){ $v=(int)($_POST['q'.$i] ?? 0); if($v<1||$v>5){$ok=false;} $answers[]=$v; }
  if($ok){ $db['responses'][]=['token'=>$token,'submittedAt'=>date('c'),'answers'=>$answers,'participantEmail'=>'maria@colegio.cl','estate'=>'Docentes']; file_put_contents($dataFile, json_encode($db, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); $already=true; $msg='Gracias, tu respuesta fue guardada.'; }
  else $msg='Debes responder todas las preguntas.';
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Encuesta</title><style>body{font-family:Segoe UI,sans-serif;background:#f8fafc;padding:24px}.box{max-width:900px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px}.q{margin:14px 0}label{margin-right:8px}.ok{color:#047857}.err{color:#b91c1c}</style></head><body><div class="box"><h1>Encuesta 360 · Docentes</h1><p>Token: <?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?></p>
<?php if ($already): ?><p class="ok">Ya existe una respuesta para este token. <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p><?php else: ?>
<form method="post"><?php foreach($questions as $i=>$q): ?><div class="q"><p><?= ($i+1) ?>. <?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></p><?php for($v=1;$v<=5;$v++): ?><label><input type="radio" name="q<?= $i ?>" value="<?= $v ?>" required> <?= $v ?></label><?php endfor; ?></div><?php endforeach; ?>
<button type="submit">Enviar respuestas</button></form>
<?php if($msg): ?><p class="err"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php endif; ?></div></body></html>
