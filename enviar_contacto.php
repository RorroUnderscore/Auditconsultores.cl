<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Método no permitido.'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Solicitud inválida.'
    ]);
    exit;
}

function limpiarCampo($valor, int $max = 500): string {
    $valor = is_string($valor) ? trim($valor) : '';
    $valor = strip_tags($valor);
    $valor = str_replace(["\r", "\n"], ' ', $valor);

    if (function_exists('mb_substr')) {
        return mb_substr($valor, 0, $max, 'UTF-8');
    }

    return substr($valor, 0, $max);
}

function limpiarMensaje($valor, int $max = 3000): string {
    $valor = is_string($valor) ? trim($valor) : '';
    $valor = strip_tags($valor);
    $valor = str_replace("\r", '', $valor);

    if (function_exists('mb_substr')) {
        return mb_substr($valor, 0, $max, 'UTF-8');
    }

    return substr($valor, 0, $max);
}

$nombre = limpiarCampo($data['nombre'] ?? '', 120);
$institucion = limpiarCampo($data['institucion'] ?? '', 160);
$emailRaw = is_string($data['email'] ?? null) ? trim($data['email']) : '';
$email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
$servicio = limpiarCampo($data['servicio'] ?? '', 160);
$mensaje = limpiarMensaje($data['mensaje'] ?? '', 3000);

if ($nombre === '' || !$email) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Debes ingresar nombre y un correo válido.'
    ]);
    exit;
}

$para = 'info@auditconsultores.cl';
$copia = 'rodrigogodoyp@auditconsultores.cl';

$asunto = 'Nuevo mensaje desde auditconsultores.cl';

$cuerpo = "Se recibió un nuevo mensaje desde el formulario de contacto de auditconsultores.cl\n\n";
$cuerpo .= "Nombre: {$nombre}\n";
$cuerpo .= "Institución / Organización: " . ($institucion !== '' ? $institucion : 'No indicada') . "\n";
$cuerpo .= "Correo del contacto: {$email}\n";
$cuerpo .= "Servicio de interés: " . ($servicio !== '' ? $servicio : 'No especificado') . "\n\n";
$cuerpo .= "Mensaje:\n";
$cuerpo .= ($mensaje !== '' ? $mensaje : 'Sin mensaje adicional.') . "\n\n";
$cuerpo .= "Responder directamente a este correo debería responder al contacto original.\n";

$asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'From: AuditConsultores <info@auditconsultores.cl>';
$headers[] = 'Reply-To: ' . $nombre . ' <' . $email . '>';
$headers[] = 'Cc: Rodrigo Godoy <' . $copia . '>';
$headers[] = 'X-Mailer: PHP/' . phpversion();

$enviado = mail($para, $asuntoCodificado, $cuerpo, implode("\r\n", $headers));

if (!$enviado) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'El servidor no pudo enviar el correo.'
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Mensaje enviado correctamente.'
]);