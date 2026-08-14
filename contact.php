<?php
declare(strict_types=1);

// TEMP DEBUG — quitar en cuanto esté diagnosticado
if (($_GET['debug'] ?? '') === 'evogo2026') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function back_to(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

$configFile = __DIR__ . '/mail-config.php';
if (!is_file($configFile)) {
    error_log('contact.php: falta mail-config.php');
    back_to('/error.html');
}
$config = require $configFile;

// Honeypot: los bots suelen rellenar cualquier campo que encuentren.
if (!empty($_POST['_honey'])) {
    back_to('/gracias.html');
}

// Filtro anti-bot por tiempo: un envío en menos de 3s de cargar la página es sospechoso.
$ts = (float) ($_POST['_ts'] ?? 0);
$nowMs = microtime(true) * 1000;
if ($ts <= 0 || ($nowMs - $ts) < 3000) {
    back_to('/gracias.html');
}

function truncate(string $value, int $maxLen): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLen) : substr($value, 0, $maxLen);
}

function field(string $key, int $maxLen = 300): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
    return truncate($value, $maxLen);
}

$nombre = field('Nombre', 150);
$empresa = field('Empresa', 150);
$email = field('Email', 200);
$telefono = field('Teléfono', 50);
$mensaje = trim((string) ($_POST['Mensaje'] ?? ''));
$mensaje = truncate($mensaje, 5000);

if ($nombre === '' || $mensaje === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back_to('/#contacto');
}

$resumen = "Nombre: {$nombre}\n"
    . "Empresa: " . ($empresa !== '' ? $empresa : '-') . "\n"
    . "Email: {$email}\n"
    . "Teléfono: " . ($telefono !== '' ? $telefono : '-') . "\n\n"
    . "Mensaje:\n{$mensaje}\n";

try {
    // 1) Notificación interna a Madinfor, con "responder a" apuntando al cliente.
    $internal = new PHPMailer(true);
    $internal->isSMTP();
    $internal->Host = $config['host'];
    $internal->Port = $config['port'];
    $internal->SMTPSecure = $config['secure'];
    $internal->SMTPAuth = true;
    $internal->Username = $config['username'];
    $internal->Password = $config['password'];
    $internal->CharSet = 'UTF-8';
    $internal->setFrom($config['from_email'], $config['from_name']);
    $internal->addAddress($config['notify_to']);
    $internal->addReplyTo($email, $nombre);
    $internal->Subject = 'Nuevo mensaje de contacto desde evogo.app';
    $internal->isHTML(false);
    $internal->Body = $resumen;
    $internal->send();

    // 2) Copia de confirmación para el cliente interesado.
    $client = new PHPMailer(true);
    $client->isSMTP();
    $client->Host = $config['host'];
    $client->Port = $config['port'];
    $client->SMTPSecure = $config['secure'];
    $client->SMTPAuth = true;
    $client->Username = $config['username'];
    $client->Password = $config['password'];
    $client->CharSet = 'UTF-8';
    $client->setFrom($config['from_email'], $config['from_name']);
    $client->addAddress($email, $nombre);
    $client->addReplyTo($config['notify_to'], $config['from_name']);
    $client->Subject = 'Hemos recibido tu mensaje - EvoGo';
    $client->isHTML(false);
    $client->Body = "Hola {$nombre},\n\nGracias por contactar con EvoGo. Hemos recibido tu mensaje y te responderemos lo antes posible.\n\n"
        . "Copia de lo que nos enviaste:\n\n{$resumen}\n--\nEvoGo · Madinfor S.L.U.";
    $client->send();
} catch (PHPMailerException $e) {
    error_log('contact.php mail error: ' . $e->getMessage());
    back_to('/error.html');
}

back_to('/gracias.html');
