<?php
/**
 * index.php
 *
 * Lee el fichero almuerzos.csv y muestra su contenido en una tabla
 * estilizada con Bootstrap (cargado vía CDN). Cada fila tiene una
 * tonalidad distinta: los colores se repiten cada 7 filas y cambian
 * de paleta después de cada bloque de 7.
 */

header('Content-Type: text/html; charset=utf-8');

// Ruta del CSV (en el mismo directorio)
$csvFile = __DIR__ . '/almuerzos.csv';

// Si el archivo no existe mostramos un mensaje sencillo
if (!file_exists($csvFile)) {
    die('<p>⚠️ No se encontró <code>almuerzos.csv</code> en el directorio.</p>');
}

// Leemos todo el CSV
$rows = array_map('str_getcsv', file($csvFile));

// La primera fila contiene los encabezados
$headers = array_shift($rows);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Plan de comidas</title>

    <!-- Bootstrap 5 vía CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-ENjdO4Dr2bkBIFxQpeoYz1H+KkU5/2eZr2K5G8t5M5Vd5JvF5Vx3G5g5g5g5g5g5"
          crossorigin="anonymous">

    <style>
        /* Paletas de colores – 3 bloques de 7 filas cada uno */
        .palette-0 { background-color: #d4edda; }   /* verde claro */
        .palette-1 { background-color: #c3e6cb; }   /* verde más claro */
        .palette-2 { background-color: #b1dfbb; }   /* verde intermedio */

        .palette-3 { background-color: #fff3cd; }   /* amarillo pálido */
        .palette-4 { background-color: #ffeeba; }   /* amarillo medio */
        .palette-5 { background-color: #ffe8a1; }   /* amarillo más claro */

        .palette-6 { background-color: #d1ecf1; }   /* azul muy claro */
        .palette-7 { background-color: #bee5eb; }   /* azul claro */
        .palette-8 { background-color: #abdde5; }   /* azul medio */
    </style>
</head>
<body class="bg-light py-4">

<div class="container">
    <h1 class="mb-4">Plan de comidas</h1>

    <table class="table table-bordered table-hover">
        <thead class="table-dark">
        <tr>
            <?php foreach ($headers as $head): ?>
                <th><?= htmlspecialchars($head, ENT_QUOTES, 'UTF-8') ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php
        // Recorremos las filas del CSV
        foreach ($rows as $index => $row) {
            // $index empieza en 0 → fila 1 del CSV corresponde a $index = 0
            // Cada bloque de 7 filas cambia de paleta (0,1,2 → 3,4,5 → 6,7,8 …)
            $block      = intdiv($index, 7);               // 0,1,2,...
            $positionInBlock = $index % 7;                 // 0‑6 dentro del bloque
            // Dentro del bloque usamos 3 tonalidades alternadas
            $shadeIndex = ($positionInBlock % 3) + ($block * 3);
            // Limitar a los estilos definidos (max 8)
            $shadeClass = 'palette-' . ($shadeIndex % 9);
            echo '<tr class="' . $shadeClass . '">';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        ?>
        </tbody>
    </table>
</div>

<!-- Bootstrap JS (opcional, solo si quieres componentes interactivos) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+6q5W5Y5V5V5V5V5V5V5V5V5V5V5"
        crossorigin="anonymous"></script>
</body>
</html>