<?php
// eliminar_ingrediente.php - Elimina un ingrediente global

ini_set('display_errors', 1);
error_reporting(E_ALL);

$dataDir = __DIR__ . '/../data/';
$ingredientesFile = $dataDir . 'ingredientes.json';

function load_json($path) {
    if (!file_exists($path)) return null;
    $content = file_get_contents($path);
    if ($content === false || trim($content) === '') return null;
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    return $data;
}

function save_json($path, $data) {
    if (!is_writable($path)) {
        die("❌ ERROR: No tengo permisos para escribir en: $path");
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($path, $json);
}

$nombre = $_GET['nombre'] ?? null;

if ($nombre === null) {
    header("Location: ingredientes.php");
    exit;
}

// ===============================
// CARGAR INGREDIENTES
// ===============================
$data = load_json($ingredientesFile);
$ingredientes = $data['ingredientes'] ?? [];

// ===============================
// ELIMINAR INGREDIENTE
// ===============================
$ingredientes = array_filter($ingredientes, function($ing) use ($nombre) {
    return $ing['nombre'] !== $nombre;
});

// ===============================
// GUARDAR
// ===============================
save_json($ingredientesFile, ['ingredientes' => array_values($ingredientes)]);

// ===============================
// REDIRIGIR
// ===============================
header("Location: ingredientes.php");
exit;
