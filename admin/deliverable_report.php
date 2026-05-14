<?php declare(strict_types=1);
session_start();
require_once __DIR__ . '/../lib/db.php';
if (!isset($_SESSION['admin_id'])) { header('Location: /admin'); exit; }
$pdo = db();
$institutionId = (int)($_GET['institution_id'] ?? 0);
if ($institutionId <= 0) { http_response_code(400); echo 'institution_id requerido'; exit; }
$inst = $pdo->prepare('SELECT name FROM institutions WHERE id=?'); $inst->execute([$institutionId]);
$institution = $inst->fetchColumn() ?: 'Institución';
$sql = "SELECT qq.estate, COALESCE(NULLIF(TRIM(qc.name),''),'Sin dimensión') dimension_name, qra.value, COUNT(*) qty
        FROM questionnaire_response_answers qra
        JOIN responses r ON r.id=qra.response_id
        JOIN questionnaire_questions qq ON qq.id=qra.questionnaire_question_id
        JOIN questionnaires q ON q.id=qq.questionnaire_id
        LEFT JOIN question_categories qc ON qc.id=qq.category_id
        WHERE q.institution_id=?
        GROUP BY qq.estate, COALESCE(NULLIF(TRIM(qc.name),''),'Sin dimensión'), qra.value
        ORDER BY qq.estate, dimension_name";
$st = $pdo->prepare($sql); $st->execute([$institutionId]);
$rows=[];
foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
  $e=(string)$r['estate']; $d=(string)$r['dimension_name']; $v=(int)$r['value']; $q=(int)$r['qty'];
  if(!isset($rows[$e])) $rows[$e]=[];
  if(!isset($rows[$e][$d])) $rows[$e][$d]=['sum'=>0,'total'=>0];
  $rows[$e][$d]['sum'] += $v*$q; $rows[$e][$d]['total'] += $q;
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><title>Informe - <?= htmlspecialchars((string)$institution) ?></title>
<style>
body{font-family:Arial,sans-serif;color:#1f2937;margin:28px} h1,h2,h3{margin:0}
.top{display:flex;justify-content:space-between;font-size:12px;margin-bottom:18px}.page{page-break-after:always}
.cover{min-height:88vh;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}
.table{width:100%;border-collapse:collapse;margin-top:12px}.table th,.table td{border:1px solid #d6dbe7;padding:8px;font-size:13px}.table th{background:#f2f5ff;text-align:left}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#eef2ff;color:#4338ca;margin:4px;font-size:12px}
.small{color:#6b7280;font-size:12px}
</style></head><body>
<div class="top"><div><?= date('d/m/y, H:i') ?></div><div>Informe - Convivencia Escolar</div></div>
<section class="cover page">
  <div class="small" style="letter-spacing:2px">SDI - INFORME INSTITUCIONAL CONFIDENCIAL</div>
  <h1 style="font-size:52px;margin:18px 0">Estudio de Convivencia Escolar</h1>
  <div style="font-size:28px;color:#4b5563"><?= htmlspecialchars((string)$institution) ?></div>
  <div class="small" style="margin-top:20px"><?= date('d \d\e F \d\e Y') ?></div>
  <div style="margin-top:12px"><?php foreach(array_keys($rows) as $e): ?><span class="badge"><?= htmlspecialchars($e) ?></span><?php endforeach; ?></div>
</section>
<section class="page">
  <h2>Índice</h2>
  <table class="table"><tr><th>Sección</th><th>Página</th></tr>
    <tr><td>1. Introducción y Metodología</td><td>2</td></tr>
    <?php $p=3; foreach(array_keys($rows) as $e): ?><tr><td><?= htmlspecialchars('Resultados - '.$e) ?></td><td><?= $p++ ?></td></tr><?php endforeach; ?>
    <tr><td>Conclusiones</td><td><?= $p ?></td></tr>
  </table>
  <h3 style="margin-top:22px">Introducción y Metodología</h3>
  <p>El presente informe consolida los resultados por estamento y dimensión del estudio aplicado en la institución.</p>
</section>
<?php foreach($rows as $estate=>$dims): $estateSum=0;$estateTotal=0; foreach($dims as $vals){$estateSum+=$vals['sum'];$estateTotal+=$vals['total'];} $estatePct=$estateTotal>0?round((($estateSum/$estateTotal)/5)*100):0; ?>
<section class="page">
  <h2>Resultados - <?= htmlspecialchars($estate) ?></h2>
  <p>Promedio general del estamento: <strong><?= (int)$estatePct ?>%</strong></p>
  <table class="table"><tr><th>#</th><th>Dimensión</th><th>Puntaje</th><th>Nivel</th><th>Interpretación</th></tr>
  <?php $i=1; foreach($dims as $d=>$vals): $avg=$vals['total']>0?($vals['sum']/$vals['total']):0; $pct=(int)round(($avg/5)*100); $nivel=$pct>=80?'Alto':($pct>=60?'Medio':'Bajo'); $interp=$pct>=80?'Fortaleza':($pct>=60?'En desarrollo':'Requiere atención'); ?>
    <tr><td><?= $i++ ?></td><td><?= htmlspecialchars($d) ?></td><td><?= $pct ?>%</td><td><?= $nivel ?></td><td><?= $interp ?></td></tr>
  <?php endforeach; ?></table>
</section>
<?php endforeach; ?>
<section>
  <h2>Conclusiones</h2>
  <p>Los resultados muestran fortalezas y oportunidades de mejora diferenciadas por estamento. Se recomienda priorizar dimensiones con nivel Bajo y Medio en planes de acción institucional.</p>
  <p class="small">Generado: <?= date('d-m-Y H:i:s') ?> · SDI v3.0</p>
</section>
<script>window.print();</script>
</body></html>
