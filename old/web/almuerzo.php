<?php
/**
 * index.php
 *
 * Lee el fichero almuerzos.csv y muestra su contenido en una tabla
 * estilizada con Bootstrap. Cada fila tiene un color distinto (paleta
 * de 9 colores que se repite cada 7 filas).
 */

header('Content-Type: text/html; charset=utf-8');

$csvFile = __DIR__ . '/almuerzos.csv';

if (!file_exists($csvFile)) {
    die('<p>⚠️ No se encontró <code>almuerzos.csv</code> en el directorio.</p>');
}

// Leemos todo el CSV, usando fgetcsv para manejar correctamente las comillas
$rows = [];
if (($handle = fopen($csvFile, "r")) !== false) {
    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        $rows[] = $data;
    }
    fclose($handle);
}

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
          rel="stylesheet">

    <style>
        /* Paletas de colores – 3 bloques de 7 filas cada uno */
        .palette-0 { background-color: #d4edda; }   /* verde claro */
        .palette-1 { background-color: #c3e6cb; }
        .palette-2 { background-color: #b1dfbb; }
        .palette-3 { background-color: #fff3cd; }   /* amarillo pálido */
        .palette-4 { background-color: #ffeeba; }
        .palette-5 { background-color: #ffe8a1; }
        .palette-6 { background-color: #d1ecf1; }   /* azul muy claro */
        .palette-7 { background-color: #bee5eb; }
        .palette-8 { background-color: #abdde5; }
        td a { text-decoration: none; color: #0d6efd; }
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
        foreach ($rows as $index => $row) {
            $block = intdiv($index, 7);
            $positionInBlock = $index % 7;
            $shadeIndex = ($positionInBlock % 3) + ($block * 3);
            $shadeClass = 'palette-' . ($shadeIndex % 9);
            
            echo '<tr class="' . $shadeClass . '">';
            foreach ($row as $key => $cell) {
                // Si es la columna "Enlace", la mostramos como <a>
                if (strtolower($headers[$key]) === 'enlace') {
                    $cellEscaped = htmlspecialchars($cell, ENT_QUOTES, 'UTF-8');
                    echo '<td><a href="' . $cellEscaped . '" target="_blank">Ver receta</a></td>';
                } else {
                    echo '<td>' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>';
                }
            }
            echo '</tr>';
        }
        ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
