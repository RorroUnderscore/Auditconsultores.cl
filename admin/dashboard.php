<?php declare(strict_types=1);
session_start();
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
if (!isset($_SESSION['admin_id'])) { header('Location: /admin'); exit; }
$pdo = db();

if (isset($_GET['logout'])) { session_destroy(); header('Location: /admin'); exit; }

$estates = ['Directivos','Docentes','Apoderados','Paradocentes'];
$tab = (string)($_GET['tab'] ?? 'datos');
$activeTemplate = (string)($_GET['tpl'] ?? 'formal');
$estateFilter = (string)($_GET['estate'] ?? 'Directivos');
$templateEstateFilter = trim((string)($_GET['tpl_estate'] ?? ''));
$questionnaireEstateFilter = trim((string)($_GET['q_estate'] ?? ''));
$questionnaireMode = (string)($_GET['qmode'] ?? '');
$questionnaireNew = (string)($_GET['qnew'] ?? '') === '1';
$resultView = (string)($_GET['rview'] ?? 'charts');
$resultEstate = (string)($_GET['rest'] ?? 'Directivos');
$estateManageMode = (string)($_GET['emode'] ?? '');
$addMode = isset($_GET['add']) ? (int)$_GET['add'] === 1 : false;
$selectedInstitutionId = isset($_GET['institution_id']) ? (int)$_GET['institution_id'] : 0;
if (!isset($_SESSION['q_builder_context_institution_id'])) $_SESSION['q_builder_context_institution_id'] = 0;
if ($selectedInstitutionId > 0 && (int)$_SESSION['q_builder_context_institution_id'] !== $selectedInstitutionId) {
  $_SESSION['q_builder'] = ['name' => '', 'source_template_id' => null, 'status' => 'draft', 'enable_comments' => 0, 'estates' => [], 'questions' => []];
  $_SESSION['q_builder_context_institution_id'] = $selectedInstitutionId;
}

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
      $institutionId = (int)$_POST['institution_id'];
      $pdo->prepare('UPDATE institutions SET name=?, code=?, rbd=?, region=?, commune=?, address_line=?, email=?, phone=?, dependency=?, status=? WHERE id=?')->execute([
        trim((string)$_POST['name']), trim((string)$_POST['code']), trim((string)$_POST['rbd']), trim((string)$_POST['region']), trim((string)$_POST['commune']),
        trim((string)$_POST['address_line']), trim((string)$_POST['email']), trim((string)$_POST['phone']), trim((string)$_POST['dependency']), (string)($_POST['status'] ?? 'active'), $institutionId
      ]);
      $rName = trim((string)($_POST['responsible_name'] ?? ''));
      $rLast = trim((string)($_POST['responsible_last_name'] ?? ''));
      $rEmail = trim((string)($_POST['responsible_email'] ?? ''));
      $rPhone = trim((string)($_POST['responsible_phone'] ?? ''));
      $rFull = trim($rName . ' ' . $rLast);
      $sel = $pdo->prepare('SELECT id FROM institution_contacts WHERE institution_id=? AND is_primary=1 ORDER BY id ASC LIMIT 1');
      $sel->execute([$institutionId]);
      $contactId = $sel->fetchColumn();
      if ($rFull !== '' || $rEmail !== '' || $rPhone !== '') {
        if ($contactId) $pdo->prepare('UPDATE institution_contacts SET full_name=?, email=?, phone=?, role_title=?, is_primary=1 WHERE id=?')->execute([$rFull !== '' ? $rFull : 'Responsable', $rEmail, $rPhone, 'Responsable del Estudio', (int)$contactId]);
        else $pdo->prepare('INSERT INTO institution_contacts(institution_id, full_name, role_title, email, phone, is_primary) VALUES (?,?,?,?,?,1)')->execute([$institutionId, $rFull !== '' ? $rFull : 'Responsable', 'Responsable del Estudio', $rEmail, $rPhone]);
      }
    } elseif ($action === 'delete_institution') {
      $institutionId = (int)$_POST['institution_id'];
      $confirmText = trim((string)($_POST['confirm_delete_text'] ?? ''));
      if ($confirmText !== 'ELIMINAR') throw new RuntimeException('Debes escribir ELIMINAR para confirmar el borrado total de la institución.');
      $pdo->beginTransaction();
      $projectIdsStmt = $pdo->prepare('SELECT id FROM projects WHERE institution_id=?'); $projectIdsStmt->execute([$institutionId]);
      $projectIds = array_map('intval', $projectIdsStmt->fetchAll(PDO::FETCH_COLUMN));
      $participantIdsStmt = $pdo->prepare('SELECT id FROM participants WHERE institution_id=?'); $participantIdsStmt->execute([$institutionId]);
      $participantIds = array_map('intval', $participantIdsStmt->fetchAll(PDO::FETCH_COLUMN));
      if (count($participantIds) > 0) {
        $inP = implode(',', array_fill(0, count($participantIds), '?'));
        $tokenStmt = $pdo->prepare('SELECT id FROM invitation_tokens WHERE participant_id IN (' . $inP . ')');
        $tokenStmt->execute($participantIds);
        $tokenIds = array_map('intval', $tokenStmt->fetchAll(PDO::FETCH_COLUMN));
        if (count($tokenIds) > 0) {
          $inT = implode(',', array_fill(0, count($tokenIds), '?'));
          $pdo->prepare('DELETE FROM questionnaire_response_answers WHERE response_id IN (SELECT id FROM responses WHERE token_id IN (' . $inT . '))')->execute($tokenIds);
          $pdo->prepare('DELETE FROM responses WHERE token_id IN (' . $inT . ')')->execute($tokenIds);
          $pdo->prepare('DELETE FROM invitation_tokens WHERE id IN (' . $inT . ')')->execute($tokenIds);
        }
      }
      if (count($projectIds) > 0) {
        $inPr = implode(',', array_fill(0, count($projectIds), '?'));
        if (tableExists($pdo, 'surveys')) { $surveyIdsStmt = $pdo->prepare('SELECT id FROM surveys WHERE project_id IN (' . $inPr . ')'); }
        $surveyIdsStmt->execute($projectIds);
        $surveyIds = array_map('intval', $surveyIdsStmt->fetchAll(PDO::FETCH_COLUMN));
        if (count($surveyIds) > 0) {
          $inS = implode(',', array_fill(0, count($surveyIds), '?'));
          if (tableExists($pdo, 'forms')) { $formIdsStmt = $pdo->prepare('SELECT id FROM forms WHERE survey_id IN (' . $inS . ')'); }
          $formIdsStmt->execute($surveyIds);
          $formIds = array_map('intval', $formIdsStmt->fetchAll(PDO::FETCH_COLUMN));
          if (count($formIds) > 0) {
            $inF = implode(',', array_fill(0, count($formIds), '?'));
            if (tableExists($pdo, 'questions')) { $questionIdsStmt = $pdo->prepare('SELECT id FROM questions WHERE form_id IN (' . $inF . ')'); }
            $questionIdsStmt->execute($formIds);
            $questionIds = array_map('intval', $questionIdsStmt->fetchAll(PDO::FETCH_COLUMN));
            if (count($questionIds) > 0) {
              $inQ = implode(',', array_fill(0, count($questionIds), '?'));
              if (tableExists($pdo, 'response_answers')) { $pdo->prepare('DELETE FROM response_answers WHERE question_id IN (' . $inQ . ')')->execute($questionIds); }
            }
            if (tableExists($pdo, 'invitation_tokens')) { $pdo->prepare('DELETE FROM invitation_tokens WHERE form_id IN (' . $inF . ')')->execute($formIds); }
            $pdo->prepare('DELETE FROM questions WHERE form_id IN (' . $inF . ')')->execute($formIds);
            $pdo->prepare('DELETE FROM forms WHERE id IN (' . $inF . ')')->execute($formIds);
          }
          $pdo->prepare('DELETE FROM surveys WHERE id IN (' . $inS . ')')->execute($surveyIds);
        }
        $pdo->prepare('DELETE FROM participants WHERE project_id IN (' . $inPr . ')')->execute($projectIds);
        $pdo->prepare('DELETE FROM questionnaires WHERE project_id IN (' . $inPr . ')')->execute($projectIds);
        if (tableExists($pdo, 'reports')) $pdo->prepare('DELETE FROM reports WHERE project_id IN (' . $inPr . ')')->execute($projectIds);
        $pdo->prepare('DELETE FROM projects WHERE id IN (' . $inPr . ')')->execute($projectIds);
      }
      $pdo->prepare('DELETE FROM communication_templates WHERE institution_id=?')->execute([$institutionId]);
      $pdo->prepare('DELETE FROM institution_estates WHERE institution_id=?')->execute([$institutionId]);
      $pdo->prepare('DELETE FROM institution_contacts WHERE institution_id=?')->execute([$institutionId]);
      $pdo->prepare('DELETE FROM question_categories WHERE institution_id=?')->execute([$institutionId]);
      $pdo->prepare('DELETE FROM institutions WHERE id=?')->execute([$institutionId]);
      $pdo->commit();
    } elseif ($action === 'create_contact') {
      $pdo->prepare('INSERT INTO institution_contacts(institution_id, full_name, role_title, email, phone, is_primary) VALUES (?,?,?,?,?,?)')->execute([
        (int)$_POST['institution_id'], trim((string)$_POST['full_name']), trim((string)$_POST['role_title']), trim((string)$_POST['email']), trim((string)$_POST['phone']), isset($_POST['is_primary']) ? 1 : 0
      ]);
    } elseif ($action === 'create_participant') {
      $institutionId = (int)$_POST['institution_id'];
      $projectId = resolveProjectId($pdo, $institutionId);
      $email = trim((string)$_POST['email']);
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Correo inválido');
      if (!hasInstitutionQuestionnaire($pdo, $institutionId, $projectId)) throw new RuntimeException('Debes tener un cuestionario con preguntas antes de registrar participantes.');
      $pdo->prepare('INSERT INTO participants(institution_id, project_id, estate, name, last_name, email) VALUES (?,?,?,?,?,?)')->execute([
        $institutionId, $projectId, (string)$_POST['estate'], trim((string)$_POST['name']), trim((string)$_POST['last_name']), $email
      ]);
    } elseif ($action === 'create_estate') {
      $institutionId = (int)$_POST['institution_id'];
      $name = trim((string)$_POST['estate_name']);
      if ($name === '') throw new RuntimeException('Nombre de estamento requerido');
      $countStmt = $pdo->prepare('SELECT COUNT(*) FROM institution_estates WHERE institution_id=?');
      $countStmt->execute([$institutionId]);
      if ((int)$countStmt->fetchColumn() >= 10) throw new RuntimeException('Máximo 10 estamentos por institución');
      $pdo->prepare('INSERT INTO institution_estates(institution_id,name,created_at) VALUES (?,?,?)')->execute([$institutionId, $name, date('c')]);
    } elseif ($action === 'rename_estate') {
      $institutionId = (int)$_POST['institution_id']; $old = trim((string)$_POST['old_estate']); $new = trim((string)$_POST['new_estate']);
      if ($new === '') throw new RuntimeException('Nuevo nombre requerido');
      $pdo->prepare('UPDATE institution_estates SET name=? WHERE institution_id=? AND name=?')->execute([$new, $institutionId, $old]);
      $pdo->prepare('UPDATE participants SET estate=? WHERE institution_id=? AND estate=?')->execute([$new, $institutionId, $old]);
      $pdo->prepare('UPDATE questionnaire_questions SET estate=? WHERE estate=?')->execute([$new, $old]);
    } elseif ($action === 'delete_estate') {
      $institutionId = (int)$_POST['institution_id']; $name = trim((string)$_POST['estate_name']);
      $pdo->prepare('DELETE FROM institution_estates WHERE institution_id=? AND name=?')->execute([$institutionId, $name]);
    } elseif ($action === 'delete_participant') {
      $pdo->prepare('DELETE FROM participants WHERE id=?')->execute([(int)$_POST['participant_id']]);
    } elseif ($action === 'send_email') {
      $participantId = (int)$_POST['participant_id'];
      $ctxP = $pdo->prepare('SELECT institution_id, project_id FROM participants WHERE id=? LIMIT 1'); $ctxP->execute([$participantId]); $prCtx=$ctxP->fetch(PDO::FETCH_ASSOC); if(!$prCtx || !hasInstitutionQuestionnaire($pdo,(int)$prCtx['institution_id'],(int)$prCtx['project_id'])) throw new RuntimeException('No hay cuestionario activo con preguntas para enviar encuestas.');
      if (dispatchParticipantEmail($pdo, $participantId, 'formal')) {
        $pdo->prepare("UPDATE participants SET email_delivery_status='sent', email_sent_at=? WHERE id=?")->execute([date('c'), $participantId]);
      } else { throw new RuntimeException('No se pudo enviar correo'); }
    } elseif ($action === 'resend_email') {
      $participantId = (int)$_POST['participant_id'];
      $ctxP = $pdo->prepare('SELECT institution_id, project_id FROM participants WHERE id=? LIMIT 1'); $ctxP->execute([$participantId]); $prCtx=$ctxP->fetch(PDO::FETCH_ASSOC); if(!$prCtx || !hasInstitutionQuestionnaire($pdo,(int)$prCtx['institution_id'],(int)$prCtx['project_id'])) throw new RuntimeException('No hay cuestionario activo con preguntas para enviar encuestas.');
      if (dispatchParticipantEmail($pdo, $participantId, 'formal')) {
        $pdo->prepare("UPDATE participants SET email_delivery_status='sent', email_sent_at=? WHERE id=?")->execute([date('c'), $participantId]);
      } else { throw new RuntimeException('No se pudo reenviar correo'); }
    } elseif ($action === 'send_reminder') {
      $participantId = (int)$_POST['participant_id'];
      $ctxP = $pdo->prepare('SELECT institution_id, project_id FROM participants WHERE id=? LIMIT 1'); $ctxP->execute([$participantId]); $prCtx=$ctxP->fetch(PDO::FETCH_ASSOC); if(!$prCtx || !hasInstitutionQuestionnaire($pdo,(int)$prCtx['institution_id'],(int)$prCtx['project_id'])) throw new RuntimeException('No hay cuestionario activo con preguntas para enviar encuestas.');
      if (dispatchParticipantEmail($pdo, $participantId, 'recordatorio')) {
        $pdo->prepare("UPDATE participants SET email_delivery_status='reminded', reminder_sent_at=? WHERE id=?")->execute([date('c'), $participantId]);
      } else { throw new RuntimeException('No se pudo enviar recordatorio'); }
    } elseif ($action === 'send_pending_bulk') {
      $institutionId=(int)$_POST['institution_id'];
      $estate=(string)$_POST['estate'];
      $q=$pdo->prepare("SELECT id FROM participants WHERE institution_id=? AND estate=? AND (email_delivery_status IS NULL OR email_delivery_status='pending')");
      $q->execute([$institutionId,$estate]);
      foreach($q->fetchAll(PDO::FETCH_COLUMN) as $pid){
        $pid=(int)$pid;
        if (dispatchParticipantEmail($pdo, $pid, 'formal')) {
          $pdo->prepare("UPDATE participants SET email_delivery_status='sent', email_sent_at=? WHERE id=?")->execute([date('c'), $pid]);
        }
      }
    } elseif ($action === 'send_unanswered_reminders') {
      $institutionId=(int)$_POST['institution_id']; $estate=(string)$_POST['estate'];
      $q=$pdo->prepare("SELECT p.id FROM participants p WHERE p.institution_id=? AND p.estate=? AND p.email_delivery_status='sent' AND (p.responded_at IS NULL) AND EXISTS(SELECT 1 FROM invitation_tokens t WHERE t.participant_id=p.id AND t.used_at IS NULL)");
      $q->execute([$institutionId,$estate]);
      foreach($q->fetchAll(PDO::FETCH_COLUMN) as $pid){ $pid=(int)$pid; if (dispatchParticipantEmail($pdo, $pid, 'recordatorio')) $pdo->prepare("UPDATE participants SET email_delivery_status='reminded', reminder_sent_at=? WHERE id=?")->execute([date('c'), $pid]); }
    } elseif ($action === 'save_template') {
      $institutionId = (int)$_POST['institution_id'];
      $type = (string)$_POST['template_type'];
      $subject = trim((string)$_POST['subject']);
      $body = trim((string)$_POST['body']);
      $estate = (string)($_POST['estate'] ?? 'General');
      $sel = $pdo->prepare('SELECT id FROM communication_templates WHERE institution_id=? AND template_type=? AND estate=? LIMIT 1');
      $sel->execute([$institutionId, $type, $estate]);
      $id = $sel->fetchColumn();
      if ($id) {
        $pdo->prepare('UPDATE communication_templates SET subject=?, body=?, updated_at=?, is_approved=0, approved_at=NULL WHERE id=?')->execute([$subject, $body, date('c'), (int)$id]);
      } else {
        $pdo->prepare('INSERT INTO communication_templates(institution_id, template_type, estate, subject, body, updated_at, is_approved, approved_at) VALUES (?,?,?,?,?,?,0,NULL)')->execute([$institutionId, $type, $estate, $subject, $body, date('c')]);
      }
    } elseif ($action === 'approve_template') {
      $institutionId = (int)$_POST['institution_id'];
      $type = (string)$_POST['template_type'];
      $estate = (string)($_POST['estate'] ?? 'General');
      $pdo->prepare('UPDATE communication_templates SET is_approved=1, approved_at=? WHERE institution_id=? AND template_type=? AND estate=?')->execute([date('c'), $institutionId, $type, $estate]);
    } elseif ($action === 'qtpl_reset') {
      $_SESSION['qtpl_builder'] = ['template_id' => null, 'name' => '', 'estates' => [], 'questions' => []];
    } elseif ($action === 'qtpl_add_estate') {
      $name = trim((string)($_POST['estate_name'] ?? ''));
      $current = $_SESSION['qtpl_builder']['estates'] ?? [];
      if ($name === '') throw new RuntimeException('Nombre de estamento requerido');
      if (in_array($name, $current, true)) throw new RuntimeException('El estamento ya existe en esta plantilla');
      $current[] = $name;
      $_SESSION['qtpl_builder']['estates'] = $current;
      $_SESSION['qtpl_builder']['questions'][$name] = $_SESSION['qtpl_builder']['questions'][$name] ?? [];
    } elseif ($action === 'qtpl_delete_estate') {
      $name = trim((string)($_POST['estate_name'] ?? ''));
      $_SESSION['qtpl_builder']['estates'] = array_values(array_filter($_SESSION['qtpl_builder']['estates'] ?? [], fn($e)=>$e!==$name));
      unset($_SESSION['qtpl_builder']['questions'][$name]);
    } elseif ($action === 'qtpl_rename_estate') {
      $old = trim((string)($_POST['old_estate_name'] ?? ''));
      $new = trim((string)($_POST['new_estate_name'] ?? ''));
      $current = $_SESSION['qtpl_builder']['estates'] ?? [];
      if ($old === '' || $new === '') throw new RuntimeException('Debes indicar el estamento actual y el nuevo nombre');
      if (!in_array($old, $current, true)) throw new RuntimeException('El estamento a renombrar no existe en la plantilla');
      if (in_array($new, $current, true) && $new !== $old) throw new RuntimeException('Ya existe otro estamento con ese nombre');
      foreach ($current as &$estateName) if ($estateName === $old) $estateName = $new;
      unset($estateName);
      $_SESSION['qtpl_builder']['estates'] = array_values($current);
      if ($old !== $new) {
        $_SESSION['qtpl_builder']['questions'][$new] = $_SESSION['qtpl_builder']['questions'][$old] ?? [];
        unset($_SESSION['qtpl_builder']['questions'][$old]);
      }
    } elseif ($action === 'qtpl_draft_sync') {
      $raw = (string)($_POST['draft_json'] ?? '');
      $draft = json_decode($raw, true);
      if (is_array($draft)) {
        $_SESSION['qtpl_builder']['name'] = trim((string)($draft['name'] ?? ($_SESSION['qtpl_builder']['name'] ?? '')));
        $_SESSION['qtpl_builder']['estates'] = array_values(array_map('strval', $draft['estates'] ?? ($_SESSION['qtpl_builder']['estates'] ?? [])));
        if (is_array($draft['questions'] ?? null)) { foreach ($draft['questions'] as $e=>$rows) $_SESSION['qtpl_builder']['questions'][(string)$e] = is_array($rows) ? $rows : []; }
      }
      header('Content-Type: application/json'); echo json_encode(['ok'=>1]); exit;
    } elseif ($action === 'q_draft_sync') {
      $raw = (string)($_POST['draft_json'] ?? '');
      $draft = json_decode($raw, true);
      if (is_array($draft)) {
        $_SESSION['q_builder']['name'] = trim((string)($draft['name'] ?? ($_SESSION['q_builder']['name'] ?? '')));
        $_SESSION['q_builder']['estates'] = array_values(array_map('strval', $draft['estates'] ?? ($_SESSION['q_builder']['estates'] ?? [])));
        if (is_array($draft['questions'] ?? null)) { foreach ($draft['questions'] as $e=>$rows) $_SESSION['q_builder']['questions'][(string)$e] = is_array($rows) ? $rows : []; }
      }
      header('Content-Type: application/json'); echo json_encode(['ok'=>1]); exit;
    } elseif ($action === 'qtpl_set_name') {
      $_SESSION['qtpl_builder']['name'] = trim((string)($_POST['template_name'] ?? ''));
    } elseif ($action === 'qtpl_add_question') {
      $estate = (string)$_POST['estate']; $text = trim((string)$_POST['question_text']); $cat = trim((string)($_POST['question_category'] ?? ''));
      if ($text !== '' && in_array($estate, $_SESSION['qtpl_builder']['estates'] ?? [], true)) $_SESSION['qtpl_builder']['questions'][$estate][] = ['text'=>$text,'category'=>$cat];
    } elseif ($action === 'qtpl_delete_question') {
      $estate = (string)$_POST['estate']; $idx = (int)$_POST['idx'];
      if (isset($_SESSION['qtpl_builder']['questions'][$estate][$idx])) array_splice($_SESSION['qtpl_builder']['questions'][$estate], $idx, 1);
    } elseif ($action === 'qtpl_update_question') {
      $estate = (string)$_POST['estate']; $idx = (int)$_POST['idx']; $text = trim((string)$_POST['question_text']);
      if ($text !== '' && isset($_SESSION['qtpl_builder']['questions'][$estate][$idx])) $_SESSION['qtpl_builder']['questions'][$estate][$idx] = ['text'=>$text,'category'=>trim((string)($_POST['question_category'] ?? ''))];
    } elseif ($action === 'qtpl_move_question') {
      $estate = (string)$_POST['estate']; $idx = (int)$_POST['idx']; $dir = (string)$_POST['direction'];
      $list = $_SESSION['qtpl_builder']['questions'][$estate] ?? []; $newIdx = $dir === 'up' ? $idx - 1 : $idx + 1;
      if (isset($list[$idx], $list[$newIdx])) { $tmp = $list[$idx]; $list[$idx] = $list[$newIdx]; $list[$newIdx] = $tmp; $_SESSION['qtpl_builder']['questions'][$estate] = array_values($list); }
    } elseif ($action === 'qtpl_inherit_questions') {
      $fromEstate = (string)$_POST['from_estate']; $toEstate = (string)$_POST['to_estate'];
      if (in_array($fromEstate, $_SESSION['qtpl_builder']['estates'] ?? [], true) && in_array($toEstate, $_SESSION['qtpl_builder']['estates'] ?? [], true) && count($_SESSION['qtpl_builder']['questions'][$toEstate] ?? []) === 0) $_SESSION['qtpl_builder']['questions'][$toEstate] = $_SESSION['qtpl_builder']['questions'][$fromEstate] ?? [];
    } elseif ($action === 'qtpl_save') {
      $builder = $_SESSION['qtpl_builder'] ?? []; $name = trim((string)($builder['name'] ?? '')); $questionsByEstate = $builder['questions'] ?? [];
      $name = trim((string)($_POST['template_name'] ?? $name));
      $_SESSION['qtpl_builder']['name'] = $name;
      if ($name === '') throw new RuntimeException('Nombre requerido');
      $tplEstates = $_SESSION['qtpl_builder']['estates'] ?? [];
      if (count($tplEstates) < 1) throw new RuntimeException('Debes crear al menos un estamento para la plantilla');
      foreach ($tplEstates as $e) if (count($questionsByEstate[$e] ?? []) < 1) throw new RuntimeException("El estamento {$e} debe tener al menos una pregunta");
      $pdo->beginTransaction();
      $tplId = (int)($_SESSION['qtpl_builder']['template_id'] ?? 0);
      if ($tplId > 0) {
        $pdo->prepare('UPDATE questionnaire_templates SET name=?, updated_at=? WHERE id=?')->execute([$name, date('c'), $tplId]);
        $pdo->prepare('DELETE FROM questionnaire_template_questions WHERE template_id=?')->execute([$tplId]);
      } else {
        $pdo->prepare('INSERT INTO questionnaire_templates(name, created_at, updated_at) VALUES (?,?,?)')->execute([$name, date('c'), date('c')]);
        $tplId = (int)$pdo->lastInsertId();
      }
      $ins = $pdo->prepare('INSERT INTO questionnaire_template_questions(template_id, estate, question_text, q_order) VALUES (?,?,?,?)');
      foreach ($tplEstates as $estate) foreach (($questionsByEstate[$estate] ?? []) as $i => $qText) { $txt=is_array($qText)?trim((string)($qText['text']??'')):(string)$qText; $cat=is_array($qText)?trim((string)($qText['category']??'')):''; $ins->execute([$tplId, $estate, json_encode(['text'=>$txt,'category'=>$cat], JSON_UNESCAPED_UNICODE), $i + 1]); }
      $pdo->commit();
      $_SESSION['qtpl_builder'] = ['template_id' => null, 'name' => '', 'estates' => [], 'questions' => []];
    } elseif ($action === 'q_reset') {
      $_SESSION['q_builder'] = ['name' => '', 'source_template_id' => null, 'status' => 'draft', 'enable_comments' => 0, 'estates' => [], 'questions' => []];
    } elseif ($action === 'q_load_template') {
      $templateId = (int)$_POST['template_id'];
      $institutionId = (int)$_POST['institution_id'];
      $projectId = resolveProjectId($pdo, $institutionId);
      $stmt = $pdo->prepare('SELECT id, name FROM questionnaire_templates WHERE id=? LIMIT 1'); $stmt->execute([$templateId]); $tpl = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$tpl) throw new RuntimeException('Plantilla no encontrada');
      $qs = []; $templateEstates = [];
      $qStmt = $pdo->prepare('SELECT estate, question_text FROM questionnaire_template_questions WHERE template_id=? ORDER BY estate, q_order ASC'); $qStmt->execute([$templateId]);
      foreach ($qStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $estateName = (string)$row['estate'];
        if (!isset($qs[$estateName])) { $qs[$estateName] = []; $templateEstates[] = $estateName; }
        $raw = (string)$row['question_text'];
        $cat = ''; $qText = $raw;
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) { $qText = (string)($decoded['text'] ?? ''); $cat = (string)($decoded['category'] ?? ''); }
        elseif (preg_match('/^\[(.*?)\]\s*(.*)$/', $raw, $m)) { $cat = (string)$m[1]; $qText = (string)$m[2]; }
        $qs[$estateName][] = ['text' => $qText, 'category' => $cat];
      }
      if (count($templateEstates) < 1) throw new RuntimeException('La plantilla no contiene estamentos configurados');
      $pdo->beginTransaction();
      $pidsStmt = $pdo->prepare('SELECT id FROM participants WHERE institution_id=? AND project_id=?');
      $pidsStmt->execute([$institutionId, $projectId]);
      $pids = array_map('intval', $pidsStmt->fetchAll(PDO::FETCH_COLUMN));
      if (count($pids) > 0) {
        $in = implode(',', array_fill(0, count($pids), '?'));
        $tokStmt = $pdo->prepare('SELECT id FROM invitation_tokens WHERE participant_id IN (' . $in . ')');
        $tokStmt->execute($pids);
        $tokenIds = array_map('intval', $tokStmt->fetchAll(PDO::FETCH_COLUMN));
        if (count($tokenIds) > 0) {
          $inTok = implode(',', array_fill(0, count($tokenIds), '?'));
          $pdo->prepare('DELETE FROM questionnaire_response_answers WHERE response_id IN (SELECT id FROM responses WHERE token_id IN (' . $inTok . '))')->execute($tokenIds);
          $pdo->prepare('DELETE FROM responses WHERE token_id IN (' . $inTok . ')')->execute($tokenIds);
          $pdo->prepare('DELETE FROM invitation_tokens WHERE id IN (' . $inTok . ')')->execute($tokenIds);
        }
        $pdo->prepare('DELETE FROM participants WHERE id IN (' . $in . ')')->execute($pids);
      }
      $pdo->prepare('DELETE FROM institution_estates WHERE institution_id=?')->execute([$institutionId]);
      $insEstate = $pdo->prepare('INSERT INTO institution_estates(institution_id, name, created_at) VALUES (?,?,?)');
      foreach ($templateEstates as $estateName) $insEstate->execute([$institutionId, $estateName, date('c')]);
      $pdo->commit();
      $_SESSION['q_builder'] = ['name' => (string)$tpl['name'], 'source_template_id' => $templateId, 'status' => 'draft', 'enable_comments' => 0, 'estates' => $templateEstates, 'questions' => $qs]; $_SESSION['q_builder_context_institution_id'] = $institutionId;
    } elseif ($action === 'q_set_comments') {
      $_SESSION['q_builder']['enable_comments'] = isset($_POST['enable_comments']) ? 1 : 0;
    } elseif ($action === 'q_add_estate') {
      $name = trim((string)($_POST['estate_name'] ?? ''));
      $current = $_SESSION['q_builder']['estates'] ?? [];
      if ($name === '') throw new RuntimeException('Nombre de estamento requerido');
      if (in_array($name, $current, true)) throw new RuntimeException('El estamento ya existe en este cuestionario');
      $current[] = $name;
      $_SESSION['q_builder']['estates'] = $current;
      $_SESSION['q_builder']['questions'][$name] = $_SESSION['q_builder']['questions'][$name] ?? [];
      $institutionId = (int)($_POST['institution_id'] ?? 0);
      if ($institutionId > 0) $existsEstate=$pdo->prepare('SELECT id FROM institution_estates WHERE institution_id=? AND name=? LIMIT 1'); $existsEstate->execute([$institutionId,$name]); if(!$existsEstate->fetchColumn()) $pdo->prepare('INSERT INTO institution_estates(institution_id,name,created_at) VALUES (?,?,?)')->execute([$institutionId, $name, date('c')]);
    } elseif ($action === 'q_rename_estate') {
      $old = trim((string)($_POST['old_estate_name'] ?? ''));
      $new = trim((string)($_POST['new_estate_name'] ?? ''));
      $current = $_SESSION['q_builder']['estates'] ?? [];
      if ($old === '' || $new === '') throw new RuntimeException('Debes indicar el estamento actual y el nuevo nombre');
      if (!in_array($old, $current, true)) throw new RuntimeException('El estamento a renombrar no existe en el cuestionario');
      if (in_array($new, $current, true) && $new !== $old) throw new RuntimeException('Ya existe otro estamento con ese nombre');
      foreach ($current as &$estateName) if ($estateName === $old) $estateName = $new;
      unset($estateName);
      $_SESSION['q_builder']['estates'] = array_values($current);
      if ($old !== $new) {
        $_SESSION['q_builder']['questions'][$new] = $_SESSION['q_builder']['questions'][$old] ?? [];
        unset($_SESSION['q_builder']['questions'][$old]);
        $institutionId = (int)($_POST['institution_id'] ?? 0);
        if ($institutionId > 0) {
          $pdo->prepare('UPDATE institution_estates SET name=? WHERE institution_id=? AND name=?')->execute([$new,$institutionId,$old]);
          $pdo->prepare('UPDATE participants SET estate=? WHERE institution_id=? AND estate=?')->execute([$new,$institutionId,$old]);
          $pdo->prepare('UPDATE communication_templates SET estate=? WHERE institution_id=? AND estate=?')->execute([$new,$institutionId,$old]);
        }
      }
    } elseif ($action === 'q_delete_estate') {
      $name = trim((string)($_POST['estate_name'] ?? ''));
      $_SESSION['q_builder']['estates'] = array_values(array_filter($_SESSION['q_builder']['estates'] ?? [], fn($e)=>$e!==$name));
      unset($_SESSION['q_builder']['questions'][$name]);
      $institutionId = (int)($_POST['institution_id'] ?? 0);
      if ($institutionId > 0) {
        $projectId = resolveProjectId($pdo, $institutionId);
        $pdo->prepare('DELETE FROM participants WHERE institution_id=? AND project_id=? AND estate=?')->execute([$institutionId,$projectId,$name]);
        $pdo->prepare('DELETE FROM communication_templates WHERE institution_id=? AND estate=?')->execute([$institutionId,$name]);
        $pdo->prepare('DELETE FROM institution_estates WHERE institution_id=? AND name=?')->execute([$institutionId,$name]);
      }
    } elseif ($action === 'q_add_question') {
      $estate = (string)$_POST['estate']; $text = trim((string)$_POST['question_text']); $category = trim((string)($_POST['question_category'] ?? ''));
      if ($text !== '' && in_array($estate, $_SESSION['q_builder']['estates'] ?? [], true)) $_SESSION['q_builder']['questions'][$estate][] = ['text'=>$text,'category'=>$category];
    } elseif ($action === 'q_delete_question') {
      $estate = (string)$_POST['estate']; $idx = (int)$_POST['idx'];
      if (isset($_SESSION['q_builder']['questions'][$estate][$idx])) array_splice($_SESSION['q_builder']['questions'][$estate], $idx, 1);
    } elseif ($action === 'q_update_question') {
      $estate = (string)$_POST['estate']; $idx = (int)$_POST['idx']; $text = trim((string)$_POST['question_text']); $category = trim((string)($_POST['question_category'] ?? ''));
      if ($text !== '' && isset($_SESSION['q_builder']['questions'][$estate][$idx])) $_SESSION['q_builder']['questions'][$estate][$idx] = ['text'=>$text,'category'=>$category];
    } elseif ($action === 'q_move_question') {
      $estate = (string)$_POST['estate']; $idx = (int)$_POST['idx']; $dir = (string)$_POST['direction']; $list = $_SESSION['q_builder']['questions'][$estate] ?? [];
      $newIdx = $dir === 'up' ? $idx - 1 : $idx + 1; if (isset($list[$idx], $list[$newIdx])) { $tmp = $list[$idx]; $list[$idx] = $list[$newIdx]; $list[$newIdx] = $tmp; $_SESSION['q_builder']['questions'][$estate] = array_values($list); }
    } elseif ($action === 'q_inherit_questions') {
      $fromEstate = (string)$_POST['from_estate']; $toEstate = (string)$_POST['to_estate'];
      if (in_array($fromEstate, $_SESSION['q_builder']['estates'] ?? [], true) && in_array($toEstate, $_SESSION['q_builder']['estates'] ?? [], true) && count($_SESSION['q_builder']['questions'][$toEstate] ?? []) === 0) $_SESSION['q_builder']['questions'][$toEstate] = $_SESSION['q_builder']['questions'][$fromEstate] ?? [];
    } elseif ($action === 'q_save' || $action === 'q_publish') {
      $builder = $_SESSION['q_builder']; if (isset($_POST['enable_comments'])) $builder['enable_comments'] = (int)$_POST['enable_comments'] === 1 || $_POST['enable_comments'] === 'on' ? 1 : 0; $name = trim((string)($builder['name'] ?? 'Cuestionario '.date('Y-m-d H:i')));
      $builderEstates = $builder['estates'] ?? $estates;
      $total = 0; foreach ($builderEstates as $e) $total += count($builder['questions'][$e] ?? []); if ($total < 1) throw new RuntimeException('Debe incluir preguntas');
      $institutionId = (int)$_POST['institution_id']; $_SESSION['q_builder_context_institution_id'] = $institutionId; $projectId = resolveProjectId($pdo, $institutionId); $status = $action === 'q_publish' ? 'published' : 'draft';
      $pdo->beginTransaction();
      if ($status === 'published') $pdo->prepare("UPDATE questionnaires SET status='closed', updated_at=? WHERE institution_id=? AND project_id=? AND status='published'")->execute([date('c'), $institutionId, $projectId]);
      $pdo->prepare('INSERT INTO questionnaires(institution_id, project_id, name, source_template_id, status, enable_comments, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$institutionId, $projectId, $name, $builder['source_template_id'], $status, (int)($builder['enable_comments'] ?? 0), date('c'), date('c')]);
      $qid = (int)$pdo->lastInsertId(); $ins = $pdo->prepare('INSERT INTO questionnaire_questions(questionnaire_id, estate, question_text, q_order) VALUES (?,?,?,?)');
      foreach ($builderEstates as $estate) foreach (($builder['questions'][$estate] ?? []) as $i => $qRow) { $qText = is_array($qRow)?(string)($qRow['text']??''):(string)$qRow; $cat = is_array($qRow)?trim((string)($qRow['category']??'')):''; $catId=null; if($cat!==''){ $cSel=$pdo->prepare('SELECT id FROM question_categories WHERE institution_id=? AND name=? LIMIT 1'); $cSel->execute([$institutionId,$cat]); $catId=$cSel->fetchColumn(); if(!$catId){ $pdo->prepare('INSERT INTO question_categories(institution_id,name,created_at) VALUES (?,?,?)')->execute([$institutionId,$cat,date('c')]); $catId=(int)$pdo->lastInsertId(); } } $ins->execute([$qid, $estate, $qText, $i + 1]); if($catId){ $pdo->prepare('UPDATE questionnaire_questions SET category_id=? WHERE id=?')->execute([(int)$catId,(int)$pdo->lastInsertId()]); } }
      $pdo->commit(); $_SESSION['q_builder'] = ['name' => '', 'source_template_id' => null, 'status' => 'draft', 'enable_comments' => 0, 'estates' => [], 'questions' => []];
    } elseif ($action === 'q_discard_all') {
      $institutionId = (int)$_POST['institution_id']; $projectId = resolveProjectId($pdo, $institutionId);
      $pidsStmt=$pdo->prepare('SELECT id FROM participants WHERE institution_id=? AND project_id=?'); $pidsStmt->execute([$institutionId,$projectId]); $pids=array_map('intval',$pidsStmt->fetchAll(PDO::FETCH_COLUMN));
      if(count($pids)>0){ $in=implode(',',array_fill(0,count($pids),'?')); $tokStmt=$pdo->prepare('SELECT id FROM invitation_tokens WHERE participant_id IN (' . $in . ')'); $tokStmt->execute($pids); $tokIds=array_map('intval',$tokStmt->fetchAll(PDO::FETCH_COLUMN)); if(count($tokIds)>0){ $inTok=implode(',',array_fill(0,count($tokIds),'?')); $pdo->prepare('DELETE FROM questionnaire_response_answers WHERE response_id IN (SELECT id FROM responses WHERE token_id IN (' . $inTok . '))')->execute($tokIds); $pdo->prepare('DELETE FROM responses WHERE token_id IN (' . $inTok . ')')->execute($tokIds); $pdo->prepare('DELETE FROM invitation_tokens WHERE id IN (' . $inTok . ')')->execute($tokIds);} }
      $pdo->prepare('DELETE FROM questionnaires WHERE institution_id=? AND project_id=?')->execute([$institutionId, $projectId]);
      $pdo->prepare('DELETE FROM participants WHERE institution_id=? AND project_id=?')->execute([$institutionId, $projectId]);
      $pdo->prepare('DELETE FROM communication_templates WHERE institution_id=?')->execute([$institutionId]);
      $pdo->prepare('DELETE FROM institution_estates WHERE institution_id=?')->execute([$institutionId]);
      $_SESSION['q_builder'] = ['name' => '', 'source_template_id' => null, 'status' => 'draft', 'enable_comments' => 0, 'estates' => [], 'questions' => []];
    } elseif ($action === 'qtpl_delete_template') {
      $templateId = (int)$_POST['template_id'];
      $pdo->beginTransaction();
      $pdo->prepare('UPDATE questionnaires SET source_template_id=NULL WHERE source_template_id=?')->execute([$templateId]);
      $pdo->prepare('DELETE FROM questionnaire_template_questions WHERE template_id=?')->execute([$templateId]);
      $pdo->prepare('DELETE FROM questionnaire_templates WHERE id=?')->execute([$templateId]);
      $pdo->commit();
      $currentTplBuilderName = (string)($_SESSION['qtpl_builder']['name'] ?? '');
      if ($currentTplBuilderName !== '' && isset($_GET['template_id']) && (int)$_GET['template_id'] === $templateId) $_SESSION['qtpl_builder'] = ['template_id' => null, 'name' => '', 'estates' => [], 'questions' => []];
    } elseif ($action === 'export_results_excel') {
      $institutionId = (int)$_POST['institution_id'];
      exportResultsExcel($pdo, $institutionId);
      exit;
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[DASHBOARD_ERROR] ' . $e->getMessage());
    $_SESSION['flash_error'] = $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo guardar la acción.';
  }

  $redirect = '/admin/dashboard.php';
  if ($action === 'delete_institution') {
    $redirect = '/admin/dashboard.php?tab=datos';
  } elseif (!empty($_POST['institution_id'])) {
    $redirect .= '?institution_id=' . (int)$_POST['institution_id'] . '&tab=' . urlencode((string)($_POST['tab'] ?? 'datos'));
    if (!empty($_POST['tpl'])) $redirect .= '&tpl=' . urlencode((string)$_POST['tpl']);
    if (!empty($_POST['estate'])) $redirect .= '&estate=' . urlencode((string)$_POST['estate']);
    if (!empty($_POST['emode'])) $redirect .= '&emode=' . urlencode((string)$_POST['emode']);
    if (!empty($_POST['qmode'])) $redirect .= '&qmode=' . urlencode((string)$_POST['qmode']);
    if (!empty($_POST['tpl_estate'])) $redirect .= '&tpl_estate=' . urlencode((string)$_POST['tpl_estate']);
    if (!empty($_POST['q_estate'])) $redirect .= '&q_estate=' . urlencode((string)$_POST['q_estate']);
    if (($action === 'q_save' || $action === 'q_publish') && (string)($_POST['tab'] ?? '') === 'cuestionarios') $redirect = '/admin/dashboard.php?institution_id=' . (int)$_POST['institution_id'] . '&tab=cuestionarios&qmode=institution_editor';
  }
  header('Location: ' . $redirect);
  exit;
}
if (!isset($_SESSION['qtpl_builder'])) $_SESSION['qtpl_builder'] = ['template_id' => null, 'name' => '', 'estates' => [], 'questions' => []];
$qtplBuilder = $_SESSION['qtpl_builder'];

if ($tab === 'cuestionarios' && $questionnaireMode === 'create_template' && !isset($_GET['template_id']) && (string)($_GET['new'] ?? '') === '1') {
  $_SESSION['qtpl_builder'] = ['template_id' => null, 'name' => '', 'estates' => [], 'questions' => []];
  $qtplBuilder = $_SESSION['qtpl_builder'];
}
if ($tab === 'cuestionarios' && $questionnaireMode === 'create_template' && isset($_GET['template_id'])) {
  $templateId = (int)$_GET['template_id'];
  $t = $pdo->prepare('SELECT id,name FROM questionnaire_templates WHERE id=? LIMIT 1'); $t->execute([$templateId]); $tpl = $t->fetch(PDO::FETCH_ASSOC);
  if ($tpl) {
    $qs=[]; $tEstates=[];
    $q = $pdo->prepare('SELECT estate,question_text FROM questionnaire_template_questions WHERE template_id=? ORDER BY estate,q_order,id'); $q->execute([$templateId]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){ $e=(string)$row['estate']; if(!isset($qs[$e])){$qs[$e]=[]; $tEstates[]=$e;} $decoded=decodeTemplateQuestion((string)$row['question_text']); $qs[$e][]=['text'=>(string)$decoded['text'],'category'=>(string)$decoded['category']]; }
    $_SESSION['qtpl_builder']=['template_id'=>(int)$tpl['id'],'name'=>(string)$tpl['name'],'estates'=>$tEstates,'questions'=>$qs]; $qtplBuilder=$_SESSION['qtpl_builder'];
  }
}
if (!isset($_SESSION['q_builder'])) $_SESSION['q_builder'] = ['name' => '', 'source_template_id' => null, 'status' => 'draft', 'enable_comments' => 0, 'estates' => [], 'questions' => []];
$qBuilder = $_SESSION['q_builder'];

$institutions = $pdo->query('SELECT * FROM institutions ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$selectedInstitution = null;
$contacts = [];
$participants = [];
$participantCounts = array_fill_keys($estates, 0);
$templateDefaults = [
  'formal' => ['subject'=>'Invitación a Diagnóstico Institucional', 'body'=>"Estimado/a [NOMBRE],\n\nLe invitamos a responder el diagnóstico institucional de [INSTITUCION].\n\nPuede ingresar en: [LINK]\n\nAtentamente,\nEquipo de Diagnóstico"],
  'recordatorio' => ['subject'=>'Recordatorio: encuesta pendiente', 'body'=>"Hola [NOMBRE],\n\nTe recordamos que aún puedes responder el diagnóstico de [INSTITUCION].\n\nIngresa aquí: [LINK]\n\nGracias.", 'is_approved'=>0]
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

    $pStmt = $pdo->prepare("SELECT p.*, EXISTS(SELECT 1 FROM invitation_tokens t WHERE t.participant_id=p.id AND t.used_at IS NOT NULL) AS has_used_token FROM participants p WHERE p.institution_id=? ORDER BY p.estate, p.id DESC");
    $pStmt->execute([$selectedInstitutionId]);
    $allParticipants = $pStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allParticipants as $p) { if(isset($participantCounts[$p['estate']])) $participantCounts[$p['estate']]++; }
    $participants = array_values(array_filter($allParticipants, fn($p) => ($p['estate'] ?? '') === $estateFilter));

    $eStmt = $pdo->prepare('SELECT name FROM institution_estates WHERE institution_id=? ORDER BY id ASC');
    $eStmt->execute([$selectedInstitutionId]);
    $dbEstates = array_map(fn($r)=>(string)$r['name'], $eStmt->fetchAll(PDO::FETCH_ASSOC));
    if ($dbEstates) { $estates = $dbEstates; $estateFilter = in_array($estateFilter, $estates, true) ? $estateFilter : $estates[0]; $participantCounts = array_fill_keys($estates, 0); foreach ($allParticipants as $p) if(isset($participantCounts[$p['estate']])) $participantCounts[$p['estate']]++; $participants = array_values(array_filter($allParticipants, fn($p) => ($p['estate'] ?? '') === $estateFilter)); }
    else { $estates = []; $participants = []; $participantCounts = []; }

    $tStmt = $pdo->prepare('SELECT template_type, estate, subject, body, is_approved FROM communication_templates WHERE institution_id=?');
    $tStmt->execute([$selectedInstitutionId]);
    foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $tpl) {
      if (($tpl['estate'] ?? '') === $estateFilter) $templates[$tpl['template_type']] = ['subject'=>(string)$tpl['subject'], 'body'=>(string)$tpl['body'], 'is_approved'=>(int)($tpl['is_approved'] ?? 0)];
    }
  }
}

$responsible = ['name'=>'','last_name'=>'','email'=>'','phone'=>''];
if (!empty($contacts)) {
  $primary = null; foreach ($contacts as $c) { if ((int)($c['is_primary'] ?? 0) === 1) { $primary = $c; break; } }
  if (!$primary) $primary = $contacts[0];
  $full = trim((string)($primary['full_name'] ?? '')); $parts = preg_split('/\s+/', $full, 2);
  $responsible['name'] = $parts[0] ?? '';
  $responsible['last_name'] = $parts[1] ?? '';
  $responsible['email'] = (string)($primary['email'] ?? '');
  $responsible['phone'] = (string)($primary['phone'] ?? '');
}

$existingQuestionnaire = null;
if ($selectedInstitutionId > 0) {
  $projectId = resolveProjectId($pdo, $selectedInstitutionId);
  $qst = $pdo->prepare('SELECT q.*, (SELECT COUNT(*) FROM questionnaire_questions qq WHERE qq.questionnaire_id=q.id) AS q_count FROM questionnaires q WHERE q.institution_id=? AND q.project_id=? ORDER BY q_count DESC, q.id DESC LIMIT 1');
  $qst->execute([$selectedInstitutionId, $projectId]);
  $existingQuestionnaire = $qst->fetch(PDO::FETCH_ASSOC) ?: null;
  if ($existingQuestionnaire) {
    $qq = $pdo->prepare('SELECT estate, question_text FROM questionnaire_questions WHERE questionnaire_id=? ORDER BY q_order ASC, id ASC');
    $qq->execute([(int)$existingQuestionnaire['id']]);
    $byEstate = []; $qEstates=[];
    foreach ($qq->fetchAll(PDO::FETCH_ASSOC) as $row) { $e=(string)$row['estate']; if(!isset($byEstate[$e])){ $byEstate[$e]=[]; $qEstates[]=$e; } $byEstate[$e][] = (string)$row['question_text']; }
    $hasAny = array_sum(array_map('count', $byEstate)) > 0;
    if ($tab === 'cuestionarios' && in_array($questionnaireMode, ['', 'institution_editor'], true) && $hasAny) {
      $questionnaireMode = 'institution_editor';
      $_SESSION['q_builder'] = ['name' => (string)$existingQuestionnaire['name'], 'source_template_id' => $existingQuestionnaire['source_template_id'] ?? null, 'status' => (string)$existingQuestionnaire['status'], 'enable_comments' => (int)($existingQuestionnaire['enable_comments'] ?? 0), 'estates' => $qEstates, 'questions' => $byEstate];
      $_SESSION['q_builder_context_institution_id'] = $selectedInstitutionId;
      $qBuilder = $_SESSION['q_builder'];
    }
  }
}

if ($tab === 'cuestionarios' && $questionnaireMode === 'scratch' && $questionnaireNew) {
  $_SESSION['q_builder'] = ['name' => '', 'source_template_id' => null, 'status' => 'draft', 'enable_comments' => 0, 'estates' => [], 'questions' => []];
  $qBuilder = $_SESSION['q_builder'];
}
if ($tab === 'cuestionarios' && $questionnaireMode === '') {
  $sum=0; foreach (($qBuilder['estates'] ?? []) as $e) $sum += count($qBuilder['questions'][$e] ?? []);
  if ($sum > 0) $questionnaireMode = 'institution_editor';
}
if ($questionnaireMode === 'use_template') {
  $sum = 0; foreach (($qBuilder['estates'] ?? []) as $e) $sum += count($qBuilder['questions'][$e] ?? []);
  if ($sum > 0) $questionnaireMode = 'institution_editor';
}




function tableExists(PDO $pdo, string $table): bool {
  try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
      $st = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
      $st->execute([$table]);
      return (bool)$st->fetchColumn();
    }
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

function hasInstitutionQuestionnaire(PDO $pdo, int $institutionId, int $projectId): bool {
  $st = $pdo->prepare('SELECT COUNT(*) FROM questionnaires q JOIN questionnaire_questions qq ON qq.questionnaire_id=q.id WHERE q.institution_id=? AND q.project_id=?');
  $st->execute([$institutionId, $projectId]);
  return (int)$st->fetchColumn() > 0;
}

function dispatchParticipantEmail(PDO $pdo, int $participantId, string $templateType): bool {
  $stmt = $pdo->prepare('SELECT p.*, i.name AS institution_name FROM participants p JOIN institutions i ON i.id=p.institution_id WHERE p.id=? LIMIT 1');
  $stmt->execute([$participantId]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$p || empty($p['email'])) return false;

  $tplStmt = $pdo->prepare('SELECT subject, body, is_approved FROM communication_templates WHERE institution_id=? AND template_type=? AND estate=? LIMIT 1');
  $tplStmt->execute([(int)$p['institution_id'], $templateType, (string)$p['estate']]);
  $tpl = $tplStmt->fetch(PDO::FETCH_ASSOC);
  if (!$tpl) return false;
  if ((int)($tpl['is_approved'] ?? 0) !== 1) return false;

  $token = getOrCreateParticipantToken($pdo, (int)$p['id'], (int)$p['project_id'], (string)$p['estate']);
  $baseUrl = appConfig()['app_url'] ?? 'https://auditconsultores.cl';
  $link = rtrim((string)$baseUrl, '/') . '/survey.php?token=' . urlencode($token);
  $fullName = trim(((string)($p['name'] ?? '')) . ' ' . ((string)($p['last_name'] ?? '')));
  $vars = ['[NOMBRE]' => $fullName !== '' ? $fullName : 'Participante', '[INSTITUCION]' => (string)($p['institution_name'] ?? ''), '[LINK]' => $link];
  $subject = renderTemplateText((string)$tpl['subject'], $vars);
  $body = renderTemplateText((string)$tpl['body'], $vars);
  return sendMailFromDiagnosticos((string)$p['email'], $subject, $body);
}

function getOrCreateParticipantToken(PDO $pdo, int $participantId, int $projectId, string $estate): string {
  $sel = $pdo->prepare('SELECT token FROM invitation_tokens WHERE participant_id=? ORDER BY id DESC LIMIT 1');
  $sel->execute([$participantId]);
  $existing = $sel->fetchColumn();
  if ($existing) return (string)$existing;

  $surveyId = ensureSurveyForProject($pdo, $projectId);
  $formId = ensureFormForEstate($pdo, $surveyId, $estate);
  $token = bin2hex(random_bytes(16));
  $pdo->prepare('INSERT INTO invitation_tokens(participant_id,form_id,token,used_at) VALUES (?,?,?,NULL)')->execute([$participantId, $formId, $token]);
  return $token;
}

function ensureSurveyForProject(PDO $pdo, int $projectId): int {
  $s = $pdo->prepare('SELECT id FROM surveys WHERE project_id=? ORDER BY id DESC LIMIT 1');
  $s->execute([$projectId]);
  $id = $s->fetchColumn();
  if ($id) return (int)$id;
  $pdo->prepare('INSERT INTO surveys(project_id,name) VALUES (?,?)')->execute([$projectId, 'Encuesta Institucional']);
  return (int)$pdo->lastInsertId();
}

function ensureFormForEstate(PDO $pdo, int $surveyId, string $estate): int {
  $f = $pdo->prepare('SELECT id FROM forms WHERE survey_id=? AND estate=? ORDER BY id DESC LIMIT 1');
  $f->execute([$surveyId, $estate]);
  $id = $f->fetchColumn();
  if ($id) return (int)$id;
  $pdo->prepare("INSERT INTO forms(survey_id,estate,status) VALUES (?,?, 'published')")->execute([$surveyId, $estate]);
  return (int)$pdo->lastInsertId();
}

function xmlCell(string $value): string { return '<Cell><Data ss:Type="String">' . htmlspecialchars($value, ENT_XML1) . '</Data></Cell>'; }
function getInstitutionEstates(PDO $pdo, int $institutionId): array {
  $stmt = $pdo->prepare('SELECT name FROM institution_estates WHERE institution_id=? ORDER BY id ASC');
  $stmt->execute([$institutionId]);
  $rows = array_map(fn($r)=>(string)$r['name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
  return $rows ?: ['Directivos','Docentes','Apoderados','Paradocentes'];
}
function initEstateQuestionMap(array $estates): array {
  $out = [];
  foreach ($estates as $e) $out[(string)$e] = [];
  return $out;
}
function decodeTemplateQuestion($q): array {
  if (is_array($q)) return ['text' => (string)($q['text'] ?? ''), 'category' => (string)($q['category'] ?? '')];
  $raw = trim((string)$q);
  if ($raw === '') return ['text' => '', 'category' => ''];
  $decoded = json_decode($raw, true);
  if (is_string($decoded)) $decoded = json_decode($decoded, true);
  if (is_array($decoded)) return ['text' => (string)($decoded['text'] ?? ''), 'category' => (string)($decoded['category'] ?? '')];
  if (preg_match('/^\[(.*?)\]\s*(.*)$/', $raw, $m)) return ['text' => (string)$m[2], 'category' => (string)$m[1]];
  return ['text' => $raw, 'category' => ''];
}
function qText($q): string { $d = decodeTemplateQuestion($q); return (string)$d['text']; }
function qCategory($q): string { $d = decodeTemplateQuestion($q); return (string)$d['category']; }
function estateColorByIndex(int $i): string {
  $palette = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#eab308','#06b6d4','#22c55e'];
  return $palette[$i % count($palette)];
}

function exportResultsExcel(PDO $pdo, int $institutionId): void {
  $inst = $pdo->prepare('SELECT * FROM institutions WHERE id=?'); $inst->execute([$institutionId]); $institution = $inst->fetch(PDO::FETCH_ASSOC) ?: ['name'=>'Institución'];
  $projectId = resolveProjectId($pdo, $institutionId);
  $participantsStmt = $pdo->prepare("SELECT p.*, EXISTS(SELECT 1 FROM invitation_tokens t WHERE t.participant_id=p.id AND t.used_at IS NOT NULL) has_used_token FROM participants p WHERE p.institution_id=? ORDER BY p.estate, p.last_name, p.name");
  $participantsStmt->execute([$institutionId]); $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
  $qSel = $pdo->prepare("SELECT q.id, q.name, q.status, q.updated_at, COUNT(r.id) AS resp_count
    FROM questionnaires q
    LEFT JOIN responses r ON r.questionnaire_id=q.id
    WHERE q.institution_id=? AND q.project_id=?
    GROUP BY q.id, q.name, q.status, q.updated_at
    ORDER BY (q.status='published') DESC, resp_count DESC, q.id DESC
    LIMIT 1");
  $qSel->execute([$institutionId, $projectId]); $activeQ = $qSel->fetch(PDO::FETCH_ASSOC);
  $qid = (int)($activeQ['id'] ?? 0);
  $estates = getInstitutionEstates($pdo, $institutionId);
  $xml = '<?xml version="1.0"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
  $xml .= '<Styles><Style ss:ID="h"><Font ss:Bold="1"/><Interior ss:Color="#E8EEFF" ss:Pattern="Solid"/></Style><Style ss:ID="th"><Font ss:Bold="1"/><Interior ss:Color="#F3F4F6" ss:Pattern="Solid"/></Style></Styles>';
  $xml .= '<Worksheet ss:Name="Resumen"><Table>';
  $xml .= '<Row><Cell ss:StyleID="h"><Data ss:Type="String">Resumen de Exportación</Data></Cell></Row><Row></Row>';
  $xml .= '<Row>'.xmlCell('Proyecto').xmlCell((string)$projectId).'</Row><Row>'.xmlCell('Institución').xmlCell((string)$institution['name']).'</Row><Row>'.xmlCell('Cuestionario').xmlCell((string)($activeQ['name'] ?? 'N/A')).'</Row><Row>'.xmlCell('Estado').xmlCell((string)($activeQ['status'] ?? 'N/A')).'</Row><Row>'.xmlCell('Última actualización').xmlCell((string)($activeQ['updated_at'] ?? 'N/A')).'</Row><Row>'.xmlCell('Exportado').xmlCell(date('c')).'</Row><Row>'.xmlCell('Participantes').xmlCell((string)count($participants)).'</Row>';
  $xml .= '</Table></Worksheet>';
  foreach($estates as $estate){ $xml .= '<Worksheet ss:Name="'.htmlspecialchars($estate, ENT_XML1).'"><Table>';
    if ($qid === 0) { $xml .= '<Row>'.xmlCell('Sin cuestionario activo').'</Row></Table></Worksheet>'; continue; }
    $questions = $pdo->prepare("SELECT qq.id, qq.question_text, qc.name category_name FROM questionnaire_questions qq LEFT JOIN question_categories qc ON qc.id=qq.category_id WHERE qq.questionnaire_id=? AND qq.estate=? ORDER BY qq.q_order, qq.id");
    $questions->execute([$qid, $estate]); $qRows = $questions->fetchAll(PDO::FETCH_ASSOC);
    foreach($qRows as $idx=>$q){ $xml .= '<Row><Cell ss:StyleID="h"><Data ss:Type="String">Pregunta '.($idx+1).'</Data></Cell>'.xmlCell((string)$q['question_text']).xmlCell('Categoría: '.(string)($q['category_name'] ?? 'Sin categoría')).'</Row><Row><Cell ss:StyleID="th"><Data ss:Type="String">Participante</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">Correo</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">Respuesta</Data></Cell></Row>';
      $ans = $pdo->prepare("SELECT p.name,p.last_name,p.email,qra.value FROM questionnaire_response_answers qra JOIN responses r ON r.id=qra.response_id JOIN invitation_tokens t ON t.id=r.token_id JOIN participants p ON p.id=t.participant_id WHERE r.questionnaire_id=? AND qra.questionnaire_question_id=? ORDER BY p.last_name,p.name");
      $ans->execute([$qid,(int)$q['id']]); $counts=[1=>0,2=>0,3=>0,4=>0,5=>0];
      foreach($ans->fetchAll(PDO::FETCH_ASSOC) as $a){ $v=(int)$a['value']; if(isset($counts[$v]))$counts[$v]++; $xml.='<Row>'.xmlCell(trim(($a['name']??'').' '.($a['last_name']??''))).xmlCell((string)$a['email']).xmlCell((string)$v).'</Row>'; }
      $xml .= '<Row><Cell ss:StyleID="th"><Data ss:Type="String">Resumen</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">1</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">2</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">3</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">4</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">5</Data></Cell></Row>';
      $xml .= '<Row>'.xmlCell('Resultados').xmlCell((string)$counts[1]).xmlCell((string)$counts[2]).xmlCell((string)$counts[3]).xmlCell((string)$counts[4]).xmlCell((string)$counts[5]).'</Row><Row></Row>'; }
    $comments = $pdo->prepare("SELECT r.comment FROM responses r WHERE r.questionnaire_id=? AND r.estate=? AND r.comment IS NOT NULL AND TRIM(r.comment)<>''");
    $comments->execute([$qid,$estate]); $cRows=$comments->fetchAll(PDO::FETCH_COLUMN);
    if ($cRows){ $xml .= '<Row><Cell ss:StyleID="h"><Data ss:Type="String">Comentarios</Data></Cell></Row>'; foreach($cRows as $c){ $xml .= '<Row>'.xmlCell((string)$c).'</Row>'; } }
    $xml .= '</Table></Worksheet>'; }
  $xml .= '<Worksheet ss:Name="Participantes"><Table><Row><Cell ss:StyleID="th"><Data ss:Type="String">Estamento</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">Nombre</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">Apellido</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">Mail</Data></Cell><Cell ss:StyleID="th"><Data ss:Type="String">Estado cuestionario</Data></Cell></Row>';
  foreach($participants as $p){ $status=(!empty($p['responded_at']) || (int)$p['has_used_token']===1)?'Contestado':'No contestado'; $xml.='<Row>'.xmlCell((string)$p['estate']).xmlCell((string)$p['name']).xmlCell((string)($p['last_name']??'')).xmlCell((string)$p['email']).xmlCell($status).'</Row>'; }
  $xml .= '</Table></Worksheet></Workbook>';
  header('Content-Type: application/xml; charset=UTF-8'); header('Content-Disposition: attachment; filename="resultados_'.$institutionId.'_'.date('Ymd_His').'.xml"'); echo $xml;
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
.chips{display:flex;gap:8px;flex-wrap:wrap}.chip{padding:8px 12px;border:1px solid #d7dce6;border-radius:999px;background:#f3f4f6;color:#4b5563;text-decoration:none}.chip.active{background:#1da0e7;color:#fff;border-color:#1da0e7}.status-pill{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;color:#fff}.status-pill.ok{background:#16a34a}.status-pill.no{background:#ef4444}
table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e6e9f0;text-align:left}.empty{border:2px dashed #d8dde8;border-radius:12px;padding:30px;text-align:center;color:#94a3b8}
.alert{padding:12px;border-radius:10px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;margin:12px 0}
.q-hub{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:8px}
.q-group{border:1px solid #e5e7eb;border-radius:14px;padding:14px;background:#fafbff}
.q-group h4{margin:0 0 10px;color:#1f2a44}
.q-group p{margin:0 0 12px;color:#64748b;font-size:14px}
.q-actions{display:flex;flex-wrap:wrap;gap:8px}
.q-actions .chip{background:#fff}
.editor-shell{border:1px solid #dbe1ee;border-radius:14px;padding:12px;background:#fcfdff}
.field-help{color:#64748b;font-size:13px}
.kpi-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.estate-kpi{border:1px solid #e5e7eb;border-radius:16px;padding:18px;background:#fff}
.estate-kpi .pct{font-size:44px;font-weight:800;line-height:1;margin-bottom:4px}
.bar{height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden}.bar span{display:block;height:100%}
.chart{border:1px solid #e5e7eb;border-radius:16px;padding:14px;background:#fff;margin-top:14px}
.chart-row{display:grid;grid-template-columns:120px 1fr 70px;gap:12px;align-items:center;margin:10px 0}
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
    <section class='card'><h3>Instituciones</h3><div class='card-body'><?php foreach($institutions as $i): ?><div style='padding:10px;border:1px solid #e6e9f0;border-radius:12px;margin-bottom:8px'><strong><?= htmlspecialchars((string)$i['name']) ?></strong><br><small><?= htmlspecialchars((string)($i['commune']??'')) ?> · <?= htmlspecialchars((string)($i['region']??'')) ?></small><div style='margin-top:8px;display:flex;gap:8px'><a class='btn gray' style='text-decoration:none' href='?institution_id=<?= (int)$i['id'] ?>&tab=datos'>Abrir menú</a><form method='post' onsubmit='const msg="¿Seguro que quieres eliminar esta institución? Se borrarán TODOS los registros asociados (cuestionarios, participantes, comunicaciones, respuestas y resultados). Escribe ELIMINAR para confirmar."; const v=prompt(msg,""); if(v===null)return false; this.confirm_delete_text.value=v.trim(); return this.confirm_delete_text.value==="ELIMINAR";'><input type='hidden' name='action' value='delete_institution'><input type='hidden' name='institution_id' value='<?= (int)$i['id'] ?>'><input type='hidden' name='confirm_delete_text' value=''><button class='btn danger'>Eliminar</button></form></div></div><?php endforeach; ?></div></section>
  </div>
<?php else: ?>
  <?php if($tab==='datos'): ?>
    <section class='card' style='margin-top:16px'><h3>Datos de la Institución</h3><div class='card-body'><form method='post'><input type='hidden' name='action' value='update_institution'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='datos'><div class='row'><div><label>Nombre</label><input name='name' value='<?= htmlspecialchars((string)$selectedInstitution['name']) ?>'></div><div><label>Calle</label><input name='address_line' value='<?= htmlspecialchars((string)($selectedInstitution['address_line']??'')) ?>'></div></div><div class='row'><div><label>Comuna</label><input name='commune' value='<?= htmlspecialchars((string)($selectedInstitution['commune']??'')) ?>'></div><div><label>Región</label><input name='region' value='<?= htmlspecialchars((string)($selectedInstitution['region']??'')) ?>'></div></div><div class='row'><div><label>Email Institución</label><input name='email' value='<?= htmlspecialchars((string)($selectedInstitution['email']??'')) ?>'></div><div><label>Teléfono Institución</label><input name='phone' value='<?= htmlspecialchars((string)($selectedInstitution['phone']??'')) ?>'></div></div><h3 style='margin-top:14px'>Responsable del Estudio</h3><div class='row'><div><label>Nombre</label><input name='responsible_name' value='<?= htmlspecialchars((string)($responsible['name'] ?? '')) ?>'></div><div><label>Apellidos</label><input name='responsible_last_name' value='<?= htmlspecialchars((string)($responsible['last_name'] ?? '')) ?>'></div></div><div class='row'><div><label>Mail</label><input name='responsible_email' value='<?= htmlspecialchars((string)($responsible['email'] ?? '')) ?>'></div><div><label>Teléfono</label><input name='responsible_phone' value='<?= htmlspecialchars((string)($responsible['phone'] ?? '')) ?>'></div></div><button class='btn'>Guardar</button></form></div></section>
  <?php elseif($tab==='cuestionarios'): ?>
    <section class='card' style='margin-top:16px'><h3>Cuestionarios</h3><div class='card-body'>
      <?php if($questionnaireMode===''): ?>
        <div class='q-hub'>
          <div class='q-group'>
            <h4>Administrar plantillas</h4>
            <p>Crea y mantén plantillas reutilizables para futuros cuestionarios.</p>
            <div class='q-actions'>
              <a class='chip active' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios&qmode=create_template&new=1'>Crear plantilla</a>
              <a class='chip' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios&qmode=edit_template'>Modificar plantilla</a>
              <a class='chip' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios&qmode=delete_template'>Eliminar plantilla</a>
            </div>
          </div>
          <div class='q-group'>
            <h4>Cuestionario de institución</h4>
            <p>Usa una plantilla como base o comienza un cuestionario desde cero.</p>
            <div class='q-actions'>
              <a class='chip' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios&qmode=use_template'>Usar plantilla</a>
              <a class='chip' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios&qmode=scratch&qnew=1'>Comenzar desde cero</a>
            </div>
          </div>
        </div>
      <?php elseif($questionnaireMode==='edit_template' || $questionnaireMode==='delete_template'): ?>
        <a class='btn gray' style='text-decoration:none' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios'>← Volver</a>
        <?php $allTemplates = $pdo->query('SELECT id, name FROM questionnaire_templates ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC); ?>
        <div style='margin-top:10px'><?php foreach($allTemplates as $tpl): ?>
          <?php if($questionnaireMode==='edit_template'): ?><a class='chip' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios&qmode=create_template&template_id=<?= (int)$tpl['id'] ?>'>Modificar: <?= htmlspecialchars((string)$tpl['name']) ?></a>
          <?php else: ?><form method='post' style='display:inline-block;margin:0 8px 8px 0' onsubmit='return confirm("¿Eliminar plantilla y todas sus preguntas asociadas? Esta acción no se puede deshacer.")'><input type='hidden' name='action' value='qtpl_delete_template'><input type='hidden' name='template_id' value='<?= (int)$tpl['id'] ?>'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='delete_template'><button class='btn danger'>Eliminar: <?= htmlspecialchars((string)$tpl['name']) ?></button></form><?php endif; ?>
        <?php endforeach; ?></div>
      <?php elseif($questionnaireMode==='create_template'): ?>
        <?php $templateEstates = $qtplBuilder['estates'] ?? []; $activeTplEstate = $templateEstateFilter; if (!in_array($activeTplEstate, $templateEstates, true)) $activeTplEstate = $templateEstates[0] ?? ''; $questions = $activeTplEstate !== '' ? ($qtplBuilder['questions'][$activeTplEstate] ?? []) : []; $tplCategories=[]; foreach(($qtplBuilder['questions'] ?? []) as $estateQs){ foreach(($estateQs ?? []) as $row){ $c=trim(qCategory($row)); if($c!=='' && !in_array($c,$tplCategories,true)) $tplCategories[]=$c; }} ?>
        <div style='display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap'>
          <a class='btn gray' style='text-decoration:none' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios'>← Volver</a>
          <form method='post'><input type='hidden' name='action' value='qtpl_reset'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' ><button class='btn gray'>Limpiar borrador</button></form>
        </div>
        <form method='post' style='margin-top:10px'>
          <input type='hidden' name='action' value='qtpl_save'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' >
          <label>Nombre de la plantilla</label>
          <div style='display:flex;gap:8px'><input data-qtpl-name='1' name='template_name' required value='<?= htmlspecialchars((string)($qtplBuilder['name'] ?? '')) ?>' placeholder='Ej: Encuesta Convivencia Escolar'><button class='btn'>Guardar plantilla</button></div>
        </form>

        <div style='margin-top:14px;border:1px solid #e6e9f0;border-radius:12px;padding:12px'>
          <form method='post' style='display:grid;grid-template-columns:1fr auto;gap:8px'>
            <input type='hidden' name='action' value='qtpl_add_estate'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' >
            <input name='estate_name' required placeholder='Crear estamento (ej: Asistentes de aula)'><button class='btn'>Agregar estamento</button>
          </form>
          <?php if(count($templateEstates)===0): ?><div class='empty' style='margin-top:10px'>Esta plantilla parte vacía. Crea al menos un estamento para agregar preguntas.</div><?php endif; ?>
        </div>

        <?php if(count($templateEstates)>0): ?>
          <div class='chips' style='margin:14px 0'><?php foreach($templateEstates as $e): ?><a class='chip <?= $activeTplEstate===$e?'active':'' ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios&qmode=create_template&tpl_estate=<?= urlencode($e) ?>'><?= htmlspecialchars($e) ?></a><?php endforeach; ?></div>
          <div style='display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:10px'>
            <form method='post' style='display:grid;grid-template-columns:1fr auto;gap:8px'>
              <input type='hidden' name='action' value='qtpl_rename_estate'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' ><input type='hidden' name='old_estate_name' value='<?= htmlspecialchars($activeTplEstate) ?>'>
              <input name='new_estate_name' required value='<?= htmlspecialchars($activeTplEstate) ?>'><button class='btn gray'>Renombrar estamento</button>
            </form>
            <form method='post' onsubmit='return confirm("¿Eliminar estamento y sus preguntas de esta plantilla?")'><input type='hidden' name='action' value='qtpl_delete_estate'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' ><input type='hidden' name='estate_name' value='<?= htmlspecialchars($activeTplEstate) ?>'><button class='btn danger'>Eliminar estamento</button></form>
          </div>

          <?php foreach($questions as $idx=>$q): ?>
            <div style='border:1px solid #e6e9f0;border-radius:12px;padding:10px;margin-bottom:8px'>
              <form method='post' style='display:grid;grid-template-columns:1fr 280px;gap:8px'>
                <input type='hidden' name='action' value='qtpl_update_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' ><input type='hidden' name='estate' value='<?= htmlspecialchars($activeTplEstate) ?>'><input type='hidden' name='idx' value='<?= (int)$idx ?>'>
                <input data-qtpl-qtext='1' name='question_text' value='<?= htmlspecialchars(qText($q)) ?>'>
                <input data-qtpl-qcat='1' name='question_category' placeholder='Categoría' value='<?= htmlspecialchars(qCategory($q)) ?>'>
                
              </form>
              <div style='display:flex;gap:6px;flex-wrap:wrap;margin-top:8px'>
                <form method='post'><input type='hidden' name='action' value='qtpl_delete_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' ><input type='hidden' name='estate' value='<?= htmlspecialchars($activeTplEstate) ?>'><input type='hidden' name='idx' value='<?= (int)$idx ?>'><button class='btn danger'>Eliminar</button></form>
                <form method='post'><input type='hidden' name='action' value='qtpl_move_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' ><input type='hidden' name='estate' value='<?= htmlspecialchars($activeTplEstate) ?>'><input type='hidden' name='idx' value='<?= (int)$idx ?>'><input type='hidden' name='direction' value='up'><button class='btn gray' <?= $idx===0?'disabled':'' ?>>Subir</button></form>
                <form method='post'><input type='hidden' name='action' value='qtpl_move_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' ><input type='hidden' name='estate' value='<?= htmlspecialchars($activeTplEstate) ?>'><input type='hidden' name='idx' value='<?= (int)$idx ?>'><input type='hidden' name='direction' value='down'><button class='btn gray' <?= $idx===count($questions)-1?'disabled':'' ?>>Bajar</button></form>
              </div>
            </div>
          <?php endforeach; ?>
          <form method='post'><input type='hidden' name='action' value='qtpl_add_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='tpl_estate' value='<?= htmlspecialchars($activeTplEstate) ?>' ><input type='hidden' name='estate' value='<?= htmlspecialchars($activeTplEstate) ?>'><label>Agregar pregunta</label><div style='display:grid;grid-template-columns:1fr 280px auto;gap:8px'><input name='question_text' placeholder='Escribe la pregunta' required><input name='question_category' placeholder='Categoría'><button class='btn'>Guardar</button></div></form>
          <?php if(count($questions)===0 && count($templateEstates)>1): ?><form method='post' style='margin-top:8px'><input type='hidden' name='action' value='qtpl_inherit_questions'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='create_template'><input type='hidden' name='to_estate' value='<?= htmlspecialchars($activeTplEstate) ?>'><div style='display:grid;grid-template-columns:1fr auto;gap:8px'><select name='from_estate'><?php foreach($templateEstates as $e): if($e!==$activeTplEstate): ?><option value='<?= htmlspecialchars($e) ?>'><?= htmlspecialchars($e) ?></option><?php endif; endforeach; ?></select><button class='btn gray'>Heredar preguntas</button></div></form><?php endif; ?>
  
        <?php endif; ?>
<?php elseif($questionnaireMode==='use_template'): ?>
        <a class='btn gray' style='text-decoration:none' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios'>← Volver</a>
        <?php $allTemplates = $pdo->query('SELECT id, name FROM questionnaire_templates ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC); ?>
        <div style='margin-top:10px'>
          <?php foreach($allTemplates as $tpl): ?>
            <form method='post' style='display:inline-block;margin:0 8px 8px 0' onsubmit='return confirm("Se eliminarán participantes y estamentos actuales de la institución. ¿Continuar?")'>
              <input type='hidden' name='action' value='q_load_template'><input type='hidden' name='template_id' value='<?= (int)$tpl['id'] ?>'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'>
              <button class='chip'><?= htmlspecialchars((string)$tpl['name']) ?></button>
            </form>
          <?php endforeach; ?>
        </div>
        <?php if(($qBuilder['name'] ?? '') !== ''): ?>
          <form method='post'><input type='hidden' name='action' value='q_set_comments'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'><label><input type='checkbox' name='enable_comments' <?= !empty($qBuilder['enable_comments'])?'checked':'' ?>> Habilitar comentarios</label> <button class='btn gray'>Guardar switch</button></form>
          <?php $qEstates = $qBuilder['estates'] ?? []; $estateFilter = $questionnaireEstateFilter!==''?$questionnaireEstateFilter:$estateFilter; if(!in_array($estateFilter,$qEstates,true)) $estateFilter = $qEstates[0] ?? ''; $questions = $estateFilter!=='' ? ($qBuilder['questions'][$estateFilter] ?? []) : []; ?>
          <div class='chips' style='margin:14px 0'><?php foreach(($qBuilder['estates'] ?? []) as $e): ?><a class='chip <?= $estateFilter===$e?'active':'' ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios&qmode=use_template&q_estate=<?= urlencode($e) ?>'><?= htmlspecialchars($e) ?></a><?php endforeach; ?></div>
          <div style='display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:10px'><form method='post' style='display:grid;grid-template-columns:1fr auto;gap:8px'><input type='hidden' name='action' value='q_rename_estate'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'><input type='hidden' name='q_estate' value='<?= htmlspecialchars($estateFilter) ?>'><input type='hidden' name='old_estate_name' value='<?= htmlspecialchars($estateFilter) ?>'><input name='new_estate_name' required value='<?= htmlspecialchars($estateFilter) ?>'><button class='btn gray'>Renombrar estamento</button></form><form method='post' onsubmit='return confirm("¿Eliminar estamento y sus preguntas de este cuestionario?")'><input type='hidden' name='action' value='q_delete_estate'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'><input type='hidden' name='q_estate' value='<?= htmlspecialchars($estateFilter) ?>'><input type='hidden' name='estate_name' value='<?= htmlspecialchars($estateFilter) ?>'><button class='btn danger'>Eliminar estamento</button></form></div><form method='post' style='display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:10px'><input type='hidden' name='action' value='q_add_estate'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'><input type='hidden' name='q_estate' value='<?= htmlspecialchars($estateFilter) ?>'><input name='estate_name' placeholder='Crear estamento' required><button class='btn'>Agregar estamento</button></form><?php if(count($questions)===0): ?><form method='post'><input type='hidden' name='action' value='q_inherit_questions'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'><input type='hidden' name='to_estate' value='<?= htmlspecialchars($estateFilter) ?>'><select name='from_estate'><?php foreach($estates as $e): if($e!==$estateFilter): ?><option><?= $e ?></option><?php endif; endforeach; ?></select><button class='btn gray'>Heredar preguntas</button></form><?php endif; ?>
          <?php foreach($questions as $idx=>$q): ?><div style='border:1px solid #e6e9f0;border-radius:12px;padding:10px;margin-bottom:8px'><form method='post' style='display:grid;grid-template-columns:1fr 220px;gap:8px'><input type='hidden' name='action' value='q_update_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><input type='hidden' name='idx' value='<?= (int)$idx ?>'><input data-q-qtext='1' name='question_text' value='<?= htmlspecialchars(qText($q)) ?>'><input data-q-qcat='1' name='question_category' placeholder='Categoría' value='<?= htmlspecialchars(qCategory($q)) ?>'></form></div><?php endforeach; ?>
          <form method='post'><input type='hidden' name='action' value='q_add_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><label>Agregar pregunta</label><div style='display:grid;grid-template-columns:1fr 220px auto;gap:8px'><input name='question_text' required><input name='question_category' placeholder='Categoría'><button class='btn'>Guardar</button></div></form>
          <div style='margin-top:10px;display:flex;gap:8px'><form method='post'><input type='hidden' name='action' value='q_save'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'><button class='btn'>Guardar cuestionario</button></form><form method='post'><input type='hidden' name='action' value='q_publish'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='use_template'><button class='btn gray'>Publicar cuestionario</button></form></div>
        <?php endif; ?>
      <?php elseif($questionnaireMode==='scratch' || $questionnaireMode==='institution_editor'): ?>
        <form method='post' onsubmit='return confirm("¿Descartar todas las preguntas y volver al menú?")'><input type='hidden' name='action' value='q_discard_all'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value=''><button class='btn danger'>Descartar preguntas y volver al menú</button></form>
        <div class='chips' style='margin:14px 0'><?php $qEstates = $qBuilder['estates'] ?? []; $estateFilter = $questionnaireEstateFilter!==''?$questionnaireEstateFilter:$estateFilter; if(!in_array($estateFilter,$qEstates,true)) $estateFilter = $qEstates[0] ?? ''; foreach($qEstates as $e): ?><a class='chip <?= $estateFilter===$e?'active':'' ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=cuestionarios&qmode=scratch&q_estate=<?= urlencode($e) ?>'><?= htmlspecialchars($e) ?></a><?php endforeach; ?></div>
        <div class='editor-shell'>
        <?php $questions = $estateFilter!=='' ? ($qBuilder['questions'][$estateFilter] ?? []) : []; ?><form method='post' style='display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:10px'><input type='hidden' name='action' value='q_add_estate'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='q_estate' value='<?= htmlspecialchars($estateFilter) ?>'><input name='estate_name' placeholder='Crear estamento' required><button class='btn'>Agregar estamento</button></form><?php if($estateFilter!==''): ?><div style='display:grid;grid-template-columns:1fr auto;gap:8px;margin-bottom:10px'><form method='post' style='display:grid;grid-template-columns:1fr auto;gap:8px'><input type='hidden' name='action' value='q_rename_estate'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='q_estate' value='<?= htmlspecialchars($estateFilter) ?>'><input type='hidden' name='old_estate_name' value='<?= htmlspecialchars($estateFilter) ?>'><input name='new_estate_name' required value='<?= htmlspecialchars($estateFilter) ?>'><button class='btn gray'>Renombrar estamento</button></form><form method='post' onsubmit='return confirm("¿Eliminar estamento y sus preguntas de este cuestionario?")'><input type='hidden' name='action' value='q_delete_estate'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='q_estate' value='<?= htmlspecialchars($estateFilter) ?>'><input type='hidden' name='estate_name' value='<?= htmlspecialchars($estateFilter) ?>'><button class='btn danger'>Eliminar estamento</button></form></div><?php endif; if(count($questions)===0): ?><form method='post'><input type='hidden' name='action' value='q_inherit_questions'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='to_estate' value='<?= htmlspecialchars($estateFilter) ?>'><select name='from_estate'><?php foreach($estates as $e): if($e!==$estateFilter): ?><option><?= $e ?></option><?php endif; endforeach; ?></select><button class='btn gray'>Heredar preguntas</button></form><?php endif; ?>
        <?php foreach($questions as $idx=>$q): ?><div style='border:1px solid #e6e9f0;border-radius:12px;padding:10px;margin-bottom:8px'><form method='post' style='display:grid;grid-template-columns:1fr 220px;gap:8px'><input type='hidden' name='action' value='q_update_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><input type='hidden' name='idx' value='<?= (int)$idx ?>'><input data-q-qtext='1' name='question_text' value='<?= htmlspecialchars(qText($q)) ?>'><input data-q-qcat='1' name='question_category' placeholder='Categoría' value='<?= htmlspecialchars(qCategory($q)) ?>'></form><div style='display:flex;gap:6px;flex-wrap:wrap;margin-top:8px'><form method='post' onsubmit='return confirm("¿Eliminar pregunta?")'><input type='hidden' name='action' value='q_delete_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><input type='hidden' name='idx' value='<?= (int)$idx ?>'><button class='btn danger'>Eliminar</button></form><form method='post'><input type='hidden' name='action' value='q_move_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><input type='hidden' name='idx' value='<?= (int)$idx ?>'><input type='hidden' name='direction' value='up'><button class='btn gray' <?= $idx===0?'disabled':'' ?>>Subir</button></form><form method='post'><input type='hidden' name='action' value='q_move_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><input type='hidden' name='idx' value='<?= (int)$idx ?>'><input type='hidden' name='direction' value='down'><button class='btn gray' <?= $idx===count($questions)-1?'disabled':'' ?>>Bajar</button></form></div></div><?php endforeach; ?>
        <form method='post'><input type='hidden' name='action' value='q_add_question'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><label>Agregar pregunta (Likert 1 a 5)</label><div style='display:grid;grid-template-columns:1fr 220px auto;gap:8px'><input name='question_text' required><input name='question_category' placeholder='Categoría'><button class='btn'>Guardar</button></div></form>
        <div class='field-help' style='margin-top:10px'>Escala fija: 1 Muy en desacuerdo · 2 En desacuerdo · 3 Neutro · 4 De acuerdo · 5 Muy de acuerdo.</div>
        <div style='margin-top:10px;display:flex;gap:8px;align-items:center'><label><input type='checkbox' form='qsaveform' name='enable_comments' <?= !empty($qBuilder['enable_comments'])?'checked':'' ?>> Habilitar Comentarios</label><form id='qsaveform' method='post'><input type='hidden' name='action' value='q_save'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><button class='btn'>Guardar cuestionario</button></form><form method='post'><input type='hidden' name='action' value='q_publish'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><input type='hidden' name='tab' value='cuestionarios'><input type='hidden' name='qmode' value='scratch'><input type='hidden' name='enable_comments' value='<?= !empty($qBuilder['enable_comments'])?1:0 ?>'><button class='btn gray'>Publicar cuestionario</button></form></div>
        </div>
      <?php else: ?>
        <div class='empty'>Modo no reconocido.</div>
      <?php endif; ?>
    </div></section>
  <?php elseif($tab==='participantes'): ?>
    <?php $colors=[]; foreach(array_values($estates) as $i=>$e) $colors[$e]=estateColorByIndex($i); ?>
    <section class='card' style='margin-top:16px'><h3>Participantes - <?= htmlspecialchars($estateFilter) ?></h3><div class='card-body'>
      <?php if(empty($estates)): ?>
        <div class='empty' style='margin-bottom:10px'>No hay estamentos disponibles para cargar participantes. Primero crea o aplica un cuestionario con estamentos.</div>
      <?php else: ?>
      <div class='chips' style='margin-bottom:10px'>
        <?php foreach($estates as $e): ?><a class='chip <?= $estateFilter===$e?'active':'' ?>' style='<?= $estateFilter===$e?'background:'.$colors[$e].';border-color:'.$colors[$e].';color:#fff;':'' ?>' href='?institution_id=<?= (int)$selectedInstitution['id'] ?>&tab=participantes&estate=<?= urlencode($e) ?>'><?= $e ?> (<?= (int)($participantCounts[$e]??0) ?>)</a><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if(!empty($estates)): ?><div style='display:flex;justify-content:flex-end;gap:8px;margin-bottom:10px'>
        <form method='post'>
          <input type='hidden' name='action' value='send_pending_bulk'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'>
          <button class='btn gray'>Enviar pendientes</button>
        </form>
        <form method='post'>
          <input type='hidden' name='action' value='send_unanswered_reminders'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'>
          <button class='btn gray'>Recordar no contestados</button>
        </form>
        <a class='btn' href='?institution_id=<?= (int)$selectedInstitution['id'] ?>&tab=participantes&estate=<?= urlencode($estateFilter) ?>&add=1'>+ Agregar</a></div>
      <table>
        <thead><tr><th>Nombre</th><th>Apellido</th><th>Mail</th><th>Estado correo</th><th>Estado cuestionario</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php foreach($participants as $p): ?>
          <tr>
            <td><?= htmlspecialchars((string)$p['name']) ?></td>
            <td><?= htmlspecialchars((string)($p['last_name'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)$p['email']) ?></td>
            <td><?= (($p['email_delivery_status'] ?? 'pending')==='pending'?'No enviado':(($p['email_delivery_status']??'')==='sent'?'Enviado':'Recordatorio enviado')) ?></td>
            <td><?= (!empty($p['responded_at']) || (int)($p['has_used_token'] ?? 0) === 1) ? 'Contestado' : 'No contestado' ?></td>
            <td>
              <div style='display:flex;gap:6px;flex-wrap:wrap'>
                <form method='post'><input type='hidden' name='action' value='send_email'><input type='hidden' name='participant_id' value='<?= (int)$p['id'] ?>'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><button class='btn gray'>Enviar</button></form>
                <form method='post'><input type='hidden' name='action' value='resend_email'><input type='hidden' name='participant_id' value='<?= (int)$p['id'] ?>'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><button class='btn gray'>Reenviar</button></form>
                <form method='post'><input type='hidden' name='action' value='send_reminder'><input type='hidden' name='participant_id' value='<?= (int)$p['id'] ?>'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><button class='btn gray'>Recordatorio</button></form>
                <form method='post' onsubmit='return confirm("¿Eliminar?")'><input type='hidden' name='action' value='delete_participant'><input type='hidden' name='participant_id' value='<?= (int)$p['id'] ?>'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'><input type='hidden' name='tab' value='participantes'><input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'><button class='btn danger'>Eliminar</button></form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if($addMode): ?>
          <tr>
            <form method='post'>
              <td><input name='name' placeholder='Nombre' required></td>
              <td><input name='last_name' placeholder='Apellido' required></td>
              <td><input name='email' placeholder='mail@cl' required></td>
              <td>—</td><td>—</td>
              <td>
                <input type='hidden' name='action' value='create_participant'>
                <input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'>
                <input type='hidden' name='tab' value='participantes'>
                <input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'>
                <button class='btn'>OK</button>
                <a class='btn gray' href='?institution_id=<?= (int)$selectedInstitution['id'] ?>&tab=participantes&estate=<?= urlencode($estateFilter) ?>'>X</a>
              </td>
            </form>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div></section>
  <?php elseif($tab==='comunicaciones'): ?>

    <?php $currentTpl = in_array($activeTemplate, ['formal','recordatorio'], true) ? $activeTemplate : 'formal';
          $tplData = $templates[$currentTpl] ?? ['subject'=>'','body'=>'','is_approved'=>0];
          $isApproved = (int)($tplData['is_approved'] ?? 0) === 1; ?>
    <section class='card' style='margin-top:16px'><h3>Plantillas de Carta</h3><div class='card-body'>
      <div class='chips'>
        <a class='chip <?= $currentTpl==='formal'?'active':'' ?>' href='?institution_id=<?= (int)$selectedInstitution['id'] ?>&tab=comunicaciones&tpl=formal&estate=<?= urlencode($estateFilter) ?>'>Formal</a>
        <a class='chip <?= $currentTpl==='recordatorio'?'active':'' ?>' href='?institution_id=<?= (int)$selectedInstitution['id'] ?>&tab=comunicaciones&tpl=recordatorio&estate=<?= urlencode($estateFilter) ?>'>Recordatorio</a>
      </div>
      <div class='chips' style='margin-top:8px'><?php foreach($estates as $e): ?><a class='chip <?= $estateFilter===$e?'active':'' ?>' href='?institution_id=<?= (int)$selectedInstitution['id'] ?>&tab=comunicaciones&tpl=<?= urlencode($currentTpl) ?>&estate=<?= urlencode($e) ?>'><?= $e ?></a><?php endforeach; ?></div>
      <div style='margin-top:10px;display:flex;align-items:center;gap:10px'><span class='status-pill <?= $isApproved ? 'ok' : 'no' ?>'><?= $isApproved ? 'Aprobado' : 'No Aprobado' ?></span></div>
    </div></section>

    <section class='card' style='margin-top:14px'><h3>Carta de Invitación · <?= ucfirst($currentTpl) ?></h3><div class='card-body'>
      <div class='chips' style='margin-bottom:10px'>
        <button type='button' class='btn gray' onclick="insertToken('[NOMBRE]')">[NOMBRE]</button>
        <button type='button' class='btn gray' onclick="insertToken('[INSTITUCION]')">[INSTITUCION]</button>
        <button type='button' class='btn gray' onclick="insertToken('[LINK]')">[LINK]</button>
      </div>
      <form method='post'>
        <input type='hidden' name='action' value='save_template'>
        <input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'>
        <input type='hidden' name='tab' value='comunicaciones'>
        <input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'>
        <input type='hidden' name='template_type' value='<?= $currentTpl ?>'>
        <input type='hidden' name='tpl' value='<?= $currentTpl ?>'>
        <label>Asunto</label><input id='mail-subject' name='subject' value='<?= htmlspecialchars((string)($tplData['subject'] ?? '')) ?>' required>
        <label>Cuerpo</label><textarea id='mail-body' name='body' rows='10' required><?= htmlspecialchars((string)($tplData['body'] ?? '')) ?></textarea>
        <div style='display:flex;gap:10px;margin-top:10px'>
          <button class='btn'>Guardar</button>
      </form>
          <form method='post'>
            <input type='hidden' name='action' value='approve_template'>
            <input type='hidden' name='institution_id' value='<?= (int)$selectedInstitution['id'] ?>'>
            <input type='hidden' name='tab' value='comunicaciones'>
            <input type='hidden' name='estate' value='<?= htmlspecialchars($activeQEstate ?? $estateFilter) ?>'>
            <input type='hidden' name='template_type' value='<?= $currentTpl ?>'>
            <input type='hidden' name='tpl' value='<?= $currentTpl ?>'>
            <button class='btn gray'>Aprobar</button>
          </form>
        </div>
      </div>
    </section>
  <?php elseif($tab==='participacion'): ?>
    <?php
      $estateColors=[]; foreach(array_values($estates) as $i=>$e) $estateColors[$e]=estateColorByIndex($i);
      $estateStats=[];
      foreach($estates as $e){
        $total=(int)($participantCounts[$e]??0); $done=0;
        foreach($participants as $p) { /* filtered list, skip */ }
        if ($selectedInstitutionId > 0) {
          $tmp = $pdo->prepare("SELECT COUNT(*) FROM participants p WHERE p.institution_id=? AND p.estate=? AND (p.responded_at IS NOT NULL OR EXISTS(SELECT 1 FROM invitation_tokens t WHERE t.participant_id=p.id AND t.used_at IS NOT NULL))");
          $tmp->execute([(int)$selectedInstitutionId, $e]);
          $done = (int)$tmp->fetchColumn();
        }
        $pct=$total>0?(int)round(($done/$total)*100):0;
        $estateStats[$e]=['total'=>$total,'done'=>$done,'pct'=>$pct];
      }
    ?>
    <section class='card' style='margin-top:16px'><h3>Participación</h3><div class='card-body'>
      <div class='kpi-grid'>
        <?php foreach($estates as $e): $s=$estateStats[$e]; ?>
        <div class='estate-kpi'>
          <div class='pct' style='color:<?= $estateColors[$e] ?>'><?= $s['pct'] ?>%</div>
          <strong><?= $e ?></strong><div style='color:#64748b'><?= $s['done'] ?>/<?= $s['total'] ?></div>
          <div class='bar' style='margin-top:10px'><span style='width:<?= $s['pct'] ?>%;background:<?= $estateColors[$e] ?>'></span></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class='chart'>
        <h4 style='margin:0 0 8px'>Participación por Estamento</h4>
        <?php foreach($estates as $e): $s=$estateStats[$e]; ?>
          <div class='chart-row'><div><?= $e ?></div><div class='bar'><span style='width:<?= $s['pct'] ?>%;background:<?= $estateColors[$e] ?>'></span></div><div style='text-align:right'><?= $s['pct'] ?>%</div></div>
        <?php endforeach; ?>
      </div>
    </div></section>
  <?php elseif($tab==='resultados'): ?>
    <?php
      $resultEstate = in_array($resultEstate, $estates, true) ? $resultEstate : 'Directivos';
      $responsesByQuestion = [];
      if ($selectedInstitutionId > 0) {
        $sql = "SELECT qq.id question_id, qq.question_text, qc.name category_name, qra.value, COUNT(*) qty
                FROM questionnaire_response_answers qra
                JOIN responses r ON r.id=qra.response_id
                JOIN questionnaire_questions qq ON qq.id=qra.questionnaire_question_id
                JOIN questionnaires q ON q.id=qq.questionnaire_id
                LEFT JOIN question_categories qc ON qc.id=qq.category_id
                WHERE q.institution_id=? AND qq.estate=?
                GROUP BY qq.id, qq.question_text, qc.name, qra.value
                ORDER BY qq.id ASC, qra.value ASC";
        $st = $pdo->prepare($sql); $st->execute([(int)$selectedInstitutionId, $resultEstate]);
        foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row){
          $qid=(int)$row['question_id']; if(!isset($responsesByQuestion[$qid])) $responsesByQuestion[$qid]=['text'=>(string)$row['question_text'],'category'=>(string)($row['category_name'] ?? ''),'counts'=>[1=>0,2=>0,3=>0,4=>0,5=>0],'total'=>0];
          $v=(int)$row['value']; $qty=(int)$row['qty']; if(isset($responsesByQuestion[$qid]['counts'][$v])) $responsesByQuestion[$qid]['counts'][$v]+=$qty; $responsesByQuestion[$qid]['total']+=$qty;
        }
      }
    ?>
    <section class='card' style='margin-top:16px'><h3>Resultados</h3><div class='card-body'>
      <div class='chips'>
        <a class='chip <?= $resultView==='charts'?'active':'' ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=resultados&rview=charts&rest=<?= urlencode($resultEstate) ?>'>Gráficas</a>
        <a class='chip <?= $resultView==='db'?'active':'' ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=resultados&rview=db&rest=<?= urlencode($resultEstate) ?>'>Base de datos</a>
      </div>
      <?php if($resultView==='db'): ?>
        <div style='margin-top:16px'><form method='post'><input type='hidden' name='action' value='export_results_excel'><input type='hidden' name='institution_id' value='<?= (int)$selectedInstitutionId ?>'><button class='btn gray' type='submit'>Exportar Excel</button></form></div>
      <?php else: ?>
        <div class='chips' style='margin:14px 0'><?php foreach($estates as $e): ?><a class='chip <?= $resultEstate===$e?'active':'' ?>' href='?institution_id=<?= (int)$selectedInstitutionId ?>&tab=resultados&rview=charts&rest=<?= urlencode($e) ?>'><?= $e ?></a><?php endforeach; ?></div>
        <?php if(empty($responsesByQuestion)): ?><div class='empty'>Aún no hay respuestas para <?= htmlspecialchars($resultEstate) ?>.</div>
        <?php else: ?>
          <?php foreach($responsesByQuestion as $i=>$q): ?>
            <div class='chart' style='margin-bottom:12px'>
              <strong>Pregunta <?= (int)$i ?>:</strong> <?= htmlspecialchars($q['text']) ?> <?php if(!empty($q['category'])): ?><span class='chip' style='margin-left:8px'>Categoría: <?= htmlspecialchars($q['category']) ?></span><?php endif; ?>
              <div style='margin-top:10px'>
                <?php for($v=1;$v<=5;$v++): $pct=$q['total']>0?round(($q['counts'][$v]/$q['total'])*100):0; ?>
                  <div class='chart-row'><div><?= $v ?></div><div class='bar'><span style='width:<?= (int)$pct ?>%;background:#4f46e5'></span></div><div style='text-align:right'><?= (int)$pct ?>%</div></div>
                <?php endfor; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div></section>
  <?php elseif($tab==='entregable'): ?><section class='card' style='margin-top:16px'><h3>Entregable</h3><div class='card-body'><div class='empty'>Módulo en construcción.</div></div></section>
  <?php elseif($tab==='benchmarking'): ?><section class='card' style='margin-top:16px'><h3>Benchmarking</h3><div class='card-body'><div class='empty'>Necesitas al menos 2 proyectos con cuestionarios cargados.</div></div></section>
  <?php endif; ?>
<?php endif; ?>
</main></div>
<script>
(function(){
  const postDraft=(action,payload)=>{const fd=new FormData();fd.append('action',action);fd.append('institution_id','<?= (int)$selectedInstitutionId ?>');fd.append('tab','cuestionarios');fd.append('draft_json',JSON.stringify(payload));return fetch('/admin/dashboard.php',{method:'POST',body:fd,credentials:'same-origin'}).catch(()=>{});};
  const collectTpl=()=>{const name=(document.querySelector('[data-qtpl-name]')||{}).value||'';const estates=[...document.querySelectorAll("a.chip[href*='qmode=create_template&tpl_estate']")].map(a=>a.textContent.trim());const questions={};document.querySelectorAll("form input[name='action'][value='qtpl_update_question']").forEach(a=>{const row=a.closest('form');if(!row)return;const estate=(row.querySelector("input[name='estate']")||{}).value||'';const qt=row.querySelector("input[name='question_text']");const qc=row.querySelector("input[name='question_category']");if(!questions[estate])questions[estate]=[];questions[estate].push({text:qt?qt.value:'',category:qc?qc.value:''});});return {name,estates,questions};};
  const collectQ=()=>{let estates=[...document.querySelectorAll("a.chip[href*='q_estate=']")].map(a=>a.textContent.trim()); if(estates.length===0){ estates=[...new Set([...document.querySelectorAll("form input[name='action'][value='q_update_question']")].map(a=>((a.closest('form').querySelector("input[name='estate']")||{}).value||'').trim()).filter(Boolean))]; }const questions={};document.querySelectorAll("form input[name='action'][value='q_update_question']").forEach(a=>{const f=a.closest('form');const estate=(f.querySelector("input[name='estate']")||{}).value||'';const qt=(f.querySelector("input[name='question_text']")||{}).value||'';const qc=(f.querySelector("input[name='question_category']")||{}).value||'';if(!questions[estate])questions[estate]=[];questions[estate].push({text:qt,category:qc});});return {estates,questions};};
  let t1=null;document.querySelectorAll('[data-qtpl-name],[data-qtpl-qtext],[data-qtpl-qcat]').forEach(el=>el.addEventListener('input',()=>{clearTimeout(t1);t1=setTimeout(()=>postDraft('qtpl_draft_sync',collectTpl()),200);}));
  let t2=null;document.querySelectorAll('[data-q-qtext],[data-q-qcat]').forEach(el=>el.addEventListener('input',()=>{clearTimeout(t2);t2=setTimeout(()=>postDraft('q_draft_sync',collectQ()),200);}));
  document.querySelectorAll("form input[name='qmode'][value='create_template']").forEach(h=>{const f=h.closest('form');if(!f)return;f.addEventListener('submit',async()=>{clearTimeout(t1);await postDraft('qtpl_draft_sync',collectTpl());});});
  document.querySelectorAll("form input[name='qmode'][value='use_template'], form input[name='qmode'][value='scratch']").forEach(h=>{const f=h.closest('form');if(!f)return;f.addEventListener('submit',async()=>{clearTimeout(t2);await postDraft('q_draft_sync',collectQ());});});
})();
</script>
<script>function insertToken(token){var el=document.getElementById('mail-body');if(!el)return;var start=el.selectionStart||0;var end=el.selectionEnd||0;var txt=el.value||'';el.value=txt.slice(0,start)+token+txt.slice(end);el.focus();el.selectionStart=el.selectionEnd=start+token.length;}</script></body></html>

