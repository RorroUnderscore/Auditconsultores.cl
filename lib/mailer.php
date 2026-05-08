<?php declare(strict_types=1);

function renderTemplateText(string $text, array $vars): string {
    return strtr($text, $vars);
}

function sendMailFromDiagnosticos(string $to, string $subject, string $body): bool {
    $from = 'infodiagnosticos@auditconsultores.cl';
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: AuditConsultores Diagnósticos <' . $from . '>';
    $headers[] = 'Reply-To: ' . $from;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return mail($to, $encodedSubject, $body, implode("\r\n", $headers));
}
