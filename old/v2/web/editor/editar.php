<?php
// editar.php - Editor completo de plato + receta

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

$platosData       = load_json($platosFile);
$recetasData      = load_json($recetasFile);
$ingredientesData = load_json($ingredientesFile);

$platos       = $platosData['platos'] ?? [];
$recetas      = $recetasData['recetas'] ?? [];
$ingredientes = $ingredientesData['ingredientes'] ?? [];

$id = $_GET['id'] ?? null;

$plato = null;
$receta = null;

if ($id !== null) {
    foreach ($platos as $p) {
        if ((string)$p['id'] === (string)$id) {
            $plato = $p;
            break;
        }
    }

    if ($plato) {
        $idReceta = $plato['id_receta'] ?? null;
        foreach ($recetas as $r) {
            if ((string)$r['id'] === (string)$idReceta) {
                $receta = $r;
                break;
            }
        }
    }
}

// Si no existe, crear estructuras vacías
if (!$plato) {
    $plato = [
        'id' => null,
        'nombre' => '',
        'comida' => false,
        'cena' => false,
        'tipo' => [],
        'para_eme' => false,
        'para_cris' => false,
        'id_receta' => null
    ];
}

if (!$receta) {
    $receta = [
        'id' => null,
        'titulo_html' => '',
        'subtitulo_html' => '',
        'texto_html' => '',
        'ingredientes' => [],
        'herramientas' => [],
        'enlace' => ''
    ];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar plato</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/dynamic.js" defer></script>
</head>
<body>

<header>
    Editar plato
</header>

<div class="container">

    <form action="guardar.php" method="post">

        <input type="hidden" name="id_plato" value="<?= htmlspecialchars((string)$plato['id']) ?>">
        <input type="hidden" name="id_receta" value="<?= htmlspecialchars((string)$receta['id']) ?>">

        <!-- ============================
             SECCIÓN A: DATOS DEL PLATO
        ============================= -->
        <div class="card">
            <h2>Datos del plato</h2>

            <label>Nombre del plato</label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($plato['nombre']) ?>">

            <label>¿Es comida?</label>
            <input type="checkbox" name="comida" <?= $plato['comida'] ? 'checked' : '' ?>>

            <label>¿Es cena?</label>
            <input type="checkbox" name="cena" <?= $plato['cena'] ? 'checked' : '' ?>>

            <label>Tipos</label>
            <div id="tipos-container" class="dynamic-list"></div>
            <button type="button" class="btn-secondary" onclick="addDynamicItem('tipos-container')">+ Añadir tipo</button>

            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    <?php foreach ($plato['tipo'] as $t): ?>
                        addDynamicItem("tipos-container", "<?= htmlspecialchars($t) ?>");
                    <?php endforeach; ?>
                });
            </script>

            <br><br>

            <label>Para Eme</label>
            <input type="checkbox" name="para_eme" <?= $plato['para_eme'] ? 'checked' : '' ?>>

            <label>Para Cris</label>
            <input type="checkbox" name="para_cris" <?= $plato['para_cris'] ? 'checked' : '' ?>>

        </div>

        <!-- ============================
             SECCIÓN B: RECETA
        ============================= -->
        <div class="card">
            <h2>Receta asociada</h2>

            <label>Título HTML</label>
            <input type="text" name="titulo_html" value="<?= htmlspecialchars($receta['titulo_html']) ?>">

            <label>Subtítulo HTML</label>
            <input type="text" name="subtitulo_html" value="<?= htmlspecialchars($receta['subtitulo_html']) ?>">

            <label>Texto HTML</label>
            <textarea name="texto_html"><?= htmlspecialchars($receta['texto_html']) ?></textarea>

            <!-- INGREDIENTES -->
            <label>Ingredientes</label>
            <div id="ingredientes-container" class="dynamic-list"></div>
            <button type="button" class="btn-secondary" onclick="addDynamicItem('ingredientes-container')">+ Añadir ingrediente</button>

            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    <?php foreach ($receta['ingredientes'] as $ing): ?>
                        addDynamicItem("ingredientes-container", "<?= htmlspecialchars($ing) ?>");
                    <?php endforeach; ?>
                });
            </script>

            <!-- HERRAMIENTAS -->
            <label>Herramientas</label>
            <div id="herramientas-container" class="dynamic-list"></div>
            <button type="button" class="btn-secondary" onclick="addDynamicItem('herramientas-container')">+ Añadir herramienta</button>

            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    <?php foreach ($receta['herramientas'] as $h): ?>
                        addDynamicItem("herramientas-container", "<?= htmlspecialchars($h) ?>");
                    <?php endforeach; ?>
                });
            </script>

            <label>Enlace</label>
            <input type="text" name="enlace" value="<?= htmlspecialchars($receta['enlace']) ?>">

        </div>

        <!-- ============================
             BOTÓN GUARDAR
        ============================= -->
        <div class="card">
            <button type="submit" class="btn-primary" style="font-size:1rem; padding:0.8rem 1.5rem;">
                Guardar todo
            </button>
        </div>

    </form>

</div>

</body>
</html>
