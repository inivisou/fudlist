<?php
// eliminar_plato.php - Elimina un plato y su receta asociada

ini_set('display_errors', 1);
error_reporting(E_ALL);

$dataDir = __DIR__ . '/../data/';

$platosFile  = $dataDir . 'platos.json';
$recetasFile = $dataDir . 'recetas.json';

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

$id = $_GET['id'] ?? null;

if ($id === null) {
    header("Location: index.php");
    exit;
}

// ===============================
// CARGAR JSON
// ===============================
$platosData  = load_json($platosFile);
$recetasData = load_json($recetasFile);

$platos  = $platosData['platos'] ?? [];
$recetas = $recetasData['recetas'] ?? [];

// ===============================
// BUSCAR PLATO Y SU RECETA
// ===============================
$idReceta = null;

foreach ($platos as $p) {
    if ((string)$p['id'] === (string)$id) {
        $idReceta = $p['id_receta'] ?? null;
        break;
    }
}

// ===============================
// ELIMINAR PLATO
// ===============================
$platos = array_filter($platos, function($p) use ($id) {
    return (string)$p['id'] !== (string)$id;
});

// ===============================
// ELIMINAR RECETA ASOCIADA
// ===============================
if ($idReceta !== null) {
    $recetas = array_filter($recetas, function($r) use ($idReceta) {
        return (string)$r['id'] !== (string)$idReceta;
    });
}

// ===============================
// GUARDAR JSON
// ===============================
save_json($platosFile, ['platos' => array_values($platos)]);
save_json($recetasFile, ['recetas' => array_values($recetas)]);

// ===============================
// REDIRIGIR
// ===============================
header("Location: index.php");
exit;
