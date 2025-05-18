<?php
// Este archivo maneja las descargas seguras
session_start();

// Constantes importantes
define('SECURE_ACCESS', true);
define('CONFIG_FILE', realpath(__DIR__ . '/../private/config.php'));
define('UPLOAD_DIR', realpath(__DIR__ . '/../private/uploads'));

// Cargar contraseña
$ADMIN_PASSWORD = '';
if (file_exists(CONFIG_FILE)) {
    include_once(CONFIG_FILE);
}

// Función para sanitizar entradas
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Verificar si se solicita un archivo
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header("HTTP/1.0 400 Bad Request");
    exit("Solicitud incorrecta");
}

$filename = sanitizeInput($_GET['file']);
$filepath = UPLOAD_DIR . '/' . $filename;

// Verificar que el archivo existe y no contiene inyecciones de ruta
if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    header("HTTP/1.0 403 Forbidden");
    exit("Acceso denegado");
}

// Verificar si el archivo requiere autenticación
$requireAuth = isset($_GET['auth']) && $_GET['auth'] === '1';

// Si requiere autenticación, verificar contraseña
if ($requireAuth) {
    if (!isset($_POST['password']) || $_POST['password'] !== $ADMIN_PASSWORD) {
        header("HTTP/1.0 401 Unauthorized");
        exit("Acceso no autorizado");
    }
}

// Verificar que el archivo existe y es legible
if (!file_exists($filepath) || !is_readable($filepath)) {
    header("HTTP/1.0 404 Not Found");
    exit("Archivo no encontrado");
}

// Preparar la descarga
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath) ?: 'application/octet-stream';
finfo_close($finfo);

// Enviar encabezados para descarga
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Enviar el archivo en trozos para manejar archivos grandes
$handle = fopen($filepath, 'rb');
while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}
fclose($handle);
exit;