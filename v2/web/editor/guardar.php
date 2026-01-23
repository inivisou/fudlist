<?php
// guardar.php - Guarda plato + receta + ingredientes nuevos

ini_set('display_errors', 1);
error_reporting(E_ALL);

$dataDir = __DIR__ . '/../data/';

$platosFile       = $dataDir . 'platos.json';
$recetasFile      = $dataDir . 'recetas.json';
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

// ===============================
// CARGAR JSON EXISTENTES
// ===============================
$platosData       = load_json($platosFile);
$recetasData      = load_json($recetasFile);
$ingredientesData = load_json($ingredientesFile);

$platos       = $platosData['platos'] ?? [];
$recetas      = $recetasData['recetas'] ?? [];
$ingredientes = $ingredientesData['ingredientes'] ?? [];

// ===============================
// SI ES PETICIÓN AJAX PARA CREAR INGREDIENTE
// ===============================
if (isset($_GET['action']) && $_GET['action'] === 'add_ingredient') {
    $input = json_decode(file_get_contents("php://input"), true);
    $nombre = trim($input['nombre'] ?? '');

    if ($nombre !== '') {
        // Comprobar si ya existe
        foreach ($ingredientes as $ing) {
            if (strcasecmp($ing['nombre'], $nombre) === 0) {
                exit(json_encode(['status' => 'exists']));
            }
        }

        // Crear ingrediente genérico
        $ingredientes[] = [
            'nombre' => $nombre,
            'calorias_x_100g' => null,
            'supermercado' => 'General'
        ];

        // Guardar
        save_json($ingredientesFile, ['ingredientes' => $ingredientes]);

        exit(json_encode(['status' => 'ok']));
    }

    exit(json_encode(['status' => 'error']));
}

// ===============================
// PROCESAR FORMULARIO NORMAL
// ===============================

$idPlato  = $_POST['id_plato'] ?? null;
$idReceta = $_POST['id_receta'] ?? null;

// ===============================
// RECONSTRUIR PLATO
// ===============================
$plato = [
    'id'        => $idPlato ? (int)$idPlato : null,
    'nombre'    => trim($_POST['nombre'] ?? ''),
    'comida'    => isset($_POST['comida']),
    'cena'      => isset($_POST['cena']),
    'tipo'      => [],
    'para_eme'  => isset($_POST['para_eme']),
    'para_cris' => isset($_POST['para_cris']),
    'id_receta' => $idReceta ? (int)$idReceta : null
];

// Tipos dinámicos
if (isset($_POST['tipos']) && is_array($_POST['tipos'])) {
    foreach ($_POST['tipos'] as $t) {
        $t = trim($t);
        if ($t !== '') $plato['tipo'][] = $t;
    }
}

// ===============================
// RECONSTRUIR RECETA
// ===============================
$receta = [
    'id'            => $idReceta ? (int)$idReceta : null,
    'titulo_html'   => trim($_POST['titulo_html'] ?? ''),
    'subtitulo_html'=> trim($_POST['subtitulo_html'] ?? ''),
    'texto_html'    => trim($_POST['texto_html'] ?? ''),
    'ingredientes'  => [],
    'herramientas'  => [],
    'enlace'        => trim($_POST['enlace'] ?? '')
];

// Ingredientes dinámicos
if (isset($_POST['ingredientes']) && is_array($_POST['ingredientes'])) {
    foreach ($_POST['ingredientes'] as $ing) {
        $ing = trim($ing);
        if ($ing !== '') $receta['ingredientes'][] = $ing;
    }
}

// Herramientas dinámicas
if (isset($_POST['herramientas']) && is_array($_POST['herramientas'])) {
    foreach ($_POST['herramientas'] as $h) {
        $h = trim($h);
        if ($h !== '') $receta['herramientas'][] = $h;
    }
}

// ===============================
// ASIGNAR ID NUEVO SI ES NECESARIO
// ===============================
if (!$plato['id']) {
    $max = 0;
    foreach ($platos as $p) {
        if ($p['id'] > $max) $max = $p['id'];
    }
    $plato['id'] = $max + 1;
}

if (!$receta['id']) {
    $max = 0;
    foreach ($recetas as $r) {
        if ($r['id'] > $max) $max = $r['id'];
    }
    $receta['id'] = $max + 1;
}

// Vincular plato → receta
$plato['id_receta'] = $receta['id'];

// ===============================
// GUARDAR O ACTUALIZAR PLATO
// ===============================
$found = false;
foreach ($platos as &$p) {
    if ($p['id'] === $plato['id']) {
        $p = $plato;
        $found = true;
        break;
    }
}
if (!$found) {
    $platos[] = $plato;
}

// ===============================
// GUARDAR O ACTUALIZAR RECETA
// ===============================
$found = false;
foreach ($recetas as &$r) {
    if ($r['id'] === $receta['id']) {
        $r = $receta;
        $found = true;
        break;
    }
}
if (!$found) {
    $recetas[] = $receta;
}

// ===============================
// GUARDAR JSON
// ===============================
save_json($platosFile, ['platos' => $platos]);
save_json($recetasFile, ['recetas' => $recetas]);

// ingredientes.json ya se guarda arriba si se crean nuevos

// ===============================
// REDIRIGIR
// ===============================
header("Location: index.php");
exit;
