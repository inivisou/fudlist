<?php
require_once __DIR__ . '/partials/header.php';

// Cargar JSON
$platos_json = json_decode(file_get_contents(__DIR__ . '/data/platos.json'), true);
$recetas_json = json_decode(file_get_contents(__DIR__ . '/data/recetas.json'), true);

// Obtener herramientas únicas
$herramientas_unicas = [];
foreach ($recetas_json['recetas'] as $receta) {
    foreach ($receta['herramientas'] as $h) {
        if (!in_array($h, $herramientas_unicas)) {
            $herramientas_unicas[] = $h;
        }
    }
}
sort($herramientas_unicas);

// Procesar formulario
$generado = false;
$menu_tentativo = [];

if (isset($_POST['dias'])) {
    $generado = true;

    $dias = intval($_POST['dias']);
    $excluir_herramientas = isset($_POST['excluir']) ? $_POST['excluir'] : [];

    // Filtrar platos según herramientas excluidas
    $platos_filtrados = [];
    foreach ($platos_json['platos'] as $plato) {
        $id_receta = $plato['id_receta'];

        // Buscar receta
        $receta = null;
        foreach ($recetas_json['recetas'] as $r) {
            if ($r['id'] == $id_receta) {
                $receta = $r;
                break;
            }
        }

        if ($receta) {
            $tiene_excluida = false;
            foreach ($receta['herramientas'] as $h) {
                if (in_array($h, $excluir_herramientas)) {
                    $tiene_excluida = true;
                    break;
                }
            }
            if (!$tiene_excluida) {
                $platos_filtrados[] = $plato;
            }
        }
    }

    if (count($platos_filtrados) === 0) {
        $platos_filtrados = $platos_json['platos'];
    }

    for ($i = 1; $i <= $dias; $i++) {
        $menu_tentativo[$i] = [
            'comida' => $platos_filtrados[array_rand($platos_filtrados)],
            'cena'   => $platos_filtrados[array_rand($platos_filtrados)]
        ];
    }
}
?>

<main class="contenedor">

    <h1>Generador de Menús</h1>

    <form method="POST" class="formulario">
        <label>No contenga herramientas:</label>
        <select name="excluir[]" multiple>
            <?php foreach ($herramientas_unicas as $h): ?>
                <option value="<?php echo htmlspecialchars($h); ?>">
                    <?php echo htmlspecialchars($h); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Días:</label>
        <input type="number" name="dias" min="1" max="14" required>

        <button type="submit">Generar</button>
    </form>

    <?php if ($generado): ?>
        <h2>Menú Tentativo</h2>
        <table class="tabla-tentativo">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Comida</th>
                    <th>Para</th>
                    <th>Cena</th>
                    <th>Para</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menu_tentativo as $dia => $datos): ?>
                    <tr>
                        <td><?php echo $dia; ?></td>

                        <td class="clickable" data-tipo="comida" data-id="<?php echo $datos['comida']['id']; ?>">
                            <?php echo htmlspecialchars($datos['comida']['nombre']); ?>
                        </td>
                        <td>
                            <?php
                                $p = $datos['comida'];
                                $para = [];
                                if ($p['para_eme']) $para[] = "Eme";
                                if ($p['para_cris']) $para[] = "Cris";
                                echo implode(" / ", $para);
                            ?>
                        </td>

                        <td class="clickable" data-tipo="cena" data-id="<?php echo $datos['cena']['id']; ?>">
                            <?php echo htmlspecialchars($datos['cena']['nombre']); ?>
                        </td>
                        <td>
                            <?php
                                $p = $datos['cena'];
                                $para = [];
                                if ($p['para_eme']) $para[] = "Eme";
                                if ($p['para_cris']) $para[] = "Cris";
                                echo implode(" / ", $para);
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Menú Efectivo</h2>
    <table id="menu-efectivo" class="tabla-efectivo">
        <thead>
            <tr>
                <th>Día</th>
                <th>Comida</th>
                <th>Quitar</th>
                <th>Cena</th>
                <th>Quitar</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <h2>Ingredientes</h2>
    <div id="lista-ingredientes"></div>

    <button id="pdf-preview">Vista previa PDF</button>
    <button id="pdf-download">Descargar PDF</button>

</main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
