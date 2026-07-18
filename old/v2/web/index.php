<?php
require_once __DIR__ . '/partials/header.php';

// ===============================
// SPINNER (se muestra siempre al entrar)
// ===============================
echo '<div id="spinner"><div class="spinner-icon"></div></div>';

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

// ===============================
// GENERADOR TENTATIVO REAL
// ===============================

function obtenerRecetaPorId($recetas_json, $id) {
    foreach ($recetas_json['recetas'] as $r) {
        if ($r['id'] == $id) return $r;
    }
    return null;
}

function platoValidoParaMomento($plato, $momento) {
    if ($momento === 'comida' && !$plato['comida']) return false;
    if ($momento === 'cena' && !$plato['cena']) return false;
    return true;
}

function tiposDelPlato($plato) {
    return isset($plato['tipo']) ? $plato['tipo'] : [];
}

function conflictoPesado($tipo1, $tipo2) {
    $pesados = ['pasta', 'fajitas', 'tortilla'];
    return in_array($tipo1, $pesados) && in_array($tipo2, $pesados);
}

function conflictoLigero($tipo1, $tipo2) {
    $ligeros = ['ensalada', 'crema'];
    return in_array($tipo1, $ligeros) && in_array($tipo2, $ligeros);
}

function tiposCompatibles($platoA, $platoB) {
    $tiposA = tiposDelPlato($platoA);
    $tiposB = tiposDelPlato($platoB);

    foreach ($tiposA as $a) {
        foreach ($tiposB as $b) {
            if ($a === $b) return false;
            if (conflictoPesado($a, $b)) return false;
            if (conflictoLigero($a, $b)) return false;
        }
    }
    return true;
}

function elegirPlato($candidatos, $tiposUsados, $platosUsados, $momento, $otroPlato = null) {
    shuffle($candidatos);

    foreach ($candidatos as $plato) {

        if (in_array($plato['id'], $platosUsados)) continue;

        if (!platoValidoParaMomento($plato, $momento)) continue;

        $tipos = tiposDelPlato($plato);
        $superaLimite = false;
        foreach ($tipos as $t) {
            if (isset($tiposUsados[$t]) && $tiposUsados[$t] >= 1) {
                $superaLimite = true;
                break;
            }
        }
        if ($superaLimite) continue;

        if ($otroPlato !== null && !tiposCompatibles($plato, $otroPlato)) continue;

        return $plato;
    }

    foreach ($candidatos as $plato) {
        if (!platoValidoParaMomento($plato, $momento)) continue;
        if ($otroPlato !== null && !tiposCompatibles($plato, $otroPlato)) continue;
        return $plato;
    }

    return $candidatos[array_rand($candidatos)];
}

$generado = false;
$menu_tentativo = [];

// ===============================
// NUEVO: generar 9 días por defecto
// ===============================
if (!isset($_POST['dias'])) {
    $_POST['dias'] = 9;
}

if (isset($_POST['dias'])) {
    $generado = true;

    $dias = intval($_POST['dias']);
    $excluir_herramientas = isset($_POST['excluir']) ? $_POST['excluir'] : [];

    $candidatos = [];

    foreach ($platos_json['platos'] as $plato) {
        $receta = obtenerRecetaPorId($recetas_json, $plato['id_receta']);
        if (!$receta) continue;

        $tiene_excluida = false;
        foreach ($receta['herramientas'] as $h) {
            if (in_array($h, $excluir_herramientas)) {
                $tiene_excluida = true;
                break;
            }
        }
        if ($tiene_excluida) continue;

        if (!$plato['para_eme'] && !$plato['para_cris']) continue;

        $candidatos[] = $plato;
    }

    if (count($candidatos) === 0) {
        $candidatos = $platos_json['platos'];
    }

    $tiposUsados = [];
    $platosUsados = [];

    for ($i = 1; $i <= $dias; $i++) {

        $comida = elegirPlato($candidatos, $tiposUsados, $platosUsados, 'comida');
        $tiposComida = tiposDelPlato($comida);
        foreach ($tiposComida as $t) {
            if (!isset($tiposUsados[$t])) $tiposUsados[$t] = 0;
            $tiposUsados[$t]++;
        }
        $platosUsados[] = $comida['id'];

        $cena = elegirPlato($candidatos, $tiposUsados, $platosUsados, 'cena', $comida);
        $tiposCena = tiposDelPlato($cena);
        foreach ($tiposCena as $t) {
            if (!isset($tiposUsados[$t])) $tiposUsados[$t] = 0;
            $tiposUsados[$t]++;
        }
        $platosUsados[] = $cena['id'];

        $menu_tentativo[$i] = [
            'comida' => $comida,
            'cena'   => $cena
        ];
    }
}

// ===============================
// OCULTAR SPINNER DESPUÉS DE GENERAR
// ===============================
echo "<script>document.getElementById('spinner').style.display='none';</script>";
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
        <input type="number" name="dias" min="1" max="14" value="<?php echo $_POST['dias']; ?>" required>

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
                            <a href="receta.php?id=<?php echo $datos['comida']['id_receta']; ?>" target="_blank">Ver receta</a>
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
                            <a href="receta.php?id=<?php echo $datos['cena']['id_receta']; ?>" target="_blank">Ver receta</a>
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
