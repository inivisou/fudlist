<?php
// index.php - Listado de platos

$dataDir = __DIR__ . '/../data/';

$platosFile = $dataDir . 'platos.json';
$recetasFile = $dataDir . 'recetas.json';

function load_json($path) {
    if (!file_exists($path)) return null;
    $content = file_get_contents($path);
    if ($content === false || trim($content) === '') return null;
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    return $data;
}

$platosData  = load_json($platosFile);
$recetasData = load_json($recetasFile);

$platos  = $platosData['platos'] ?? [];
$recetas = $recetasData['recetas'] ?? [];

// Indexar recetas por id para mostrar título rápido
$recetasById = [];
foreach ($recetas as $r) {
    if (isset($r['id'])) {
        $recetasById[$r['id']] = $r;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Platos - Editor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
    Editor de menú - Platos
</header>

<div class="container">
    <div class="card">
        <h2>Platos</h2>
        <div style="margin-bottom: 1rem;">
            <a href="editar.php" class="btn-primary" style="text-decoration:none; padding:0.6rem 1rem; display:inline-block;">
                + Añadir nuevo plato
            </a>
            <a href="ingredientes.php" class="btn-secondary" style="text-decoration:none; padding:0.6rem 1rem; display:inline-block; margin-left:0.5rem;">
                Gestionar ingredientes
            </a>
        </div>

        <?php if (empty($platos)): ?>
            <p>No hay platos todavía.</p>
        <?php else: ?>
            <table style="width:100%; border-collapse:collapse; font-size:0.95rem;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                        <th style="padding:0.5rem;">ID</th>
                        <th style="padding:0.5rem;">Nombre</th>
                        <th style="padding:0.5rem;">Comida</th>
                        <th style="padding:0.5rem;">Cena</th>
                        <th style="padding:0.5rem;">Receta</th>
                        <th style="padding:0.5rem; text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($platos as $plato): ?>
                    <?php
                        $id = $plato['id'] ?? null;
                        $nombre = $plato['nombre'] ?? '';
                        $comida = !empty($plato['comida']);
                        $cena   = !empty($plato['cena']);
                        $idReceta = $plato['id_receta'] ?? null;
                        $recetaTitulo = $idReceta && isset($recetasById[$idReceta])
                            ? ($recetasById[$idReceta]['titulo_html'] ?? '')
                            : '';
                    ?>
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:0.5rem;"><?= htmlspecialchars((string)$id) ?></td>
                        <td style="padding:0.5rem;"><?= htmlspecialchars($nombre) ?></td>
                        <td style="padding:0.5rem;"><?= $comida ? 'Sí' : 'No' ?></td>
                        <td style="padding:0.5rem;"><?= $cena ? 'Sí' : 'No' ?></td>
                        <td style="padding:0.5rem;"><?= htmlspecialchars($recetaTitulo) ?></td>
                        <td style="padding:0.5rem; text-align:right;">
                            <a href="editar.php?id=<?= urlencode((string)$id) ?>" class="btn-primary" style="text-decoration:none; padding:0.3rem 0.7rem; font-size:0.85rem;">
                                Editar
                            </a>
                            <a href="eliminar_plato.php?id=<?= urlencode((string)$id) ?>"
                               class="btn-danger"
                               style="text-decoration:none; padding:0.3rem 0.7rem; font-size:0.85rem; margin-left:0.3rem;"
                               onclick="return confirm('¿Seguro que quieres eliminar este plato?');">
                                Eliminar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
