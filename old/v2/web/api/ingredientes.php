<?php
header('Content-Type: application/json');

// Leer cuerpo POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validación básica
if (!$data || !isset($data['ingredientes']) || !is_array($data['ingredientes'])) {
    echo json_encode([
        "error" => "Formato inválido. Se esperaba { ingredientes: [] }"
    ]);
    exit;
}

// Cargar ingredientes.json
$ingredientes_json = json_decode(file_get_contents(__DIR__ . '/../data/ingredientes.json'), true);
$catalogo = $ingredientes_json['ingredientes'];

// Resolver supermercados
$resultado = [];

foreach ($data['ingredientes'] as $nombre) {
    $nombre = trim($nombre);

    // Buscar en catálogo
    $encontrado = null;
    foreach ($catalogo as $ing) {
        if ($ing['nombre'] === $nombre) {
            $encontrado = $ing;
            break;
        }
    }

    if ($encontrado) {
        $resultado[] = [
            "nombre" => $nombre,
            "supermercado" => $encontrado['supermercado']
        ];
    } else {
        // Ingrediente desconocido
        $resultado[] = [
            "nombre" => $nombre,
            "supermercado" => "Desconocido"
        ];
    }
}

// Ordenar por supermercado y nombre
usort($resultado, function($a, $b) {
    if ($a['supermercado'] === $b['supermercado']) {
        return strcmp($a['nombre'], $b['nombre']);
    }
    return strcmp($a['supermercado'], $b['supermercado']);
});

// Devolver JSON
echo json_encode([
    "ingredientes" => $resultado
]);
