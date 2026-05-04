<?php declare(strict_types=1);
$email = $_POST['email'] ?? 'admin@auditconsultores.cl';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard</title>
<style>body{font-family:Segoe UI,sans-serif;background:#f8fafc;padding:24px;color:#0f172a}.box{max-width:900px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px}a.btn{display:inline-block;background:#00C9A7;color:#0A1628;padding:8px 14px;border-radius:999px;text-decoration:none;font-weight:700}</style></head><body><div class="box"><h1>Dashboard administrativo (base PHP)</h1><p>Sesión mock iniciada por: <strong><?= htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8') ?></strong></p>
<ul><li>Crear institución/proyecto/encuesta por estamento (siguiente iteración).</li><li>Gestión de participantes y tokens.</li><li>Resultados desde respuestas reales.</li></ul>
<p><a class="btn" href="/survey/demo-docente-token">Probar encuesta pública por token</a></p></div></body></html>
