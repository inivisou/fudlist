<?php
// ingredientes.php - CRUD de ingredientes globales

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
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($path, $json);
}

$ingredientesData = load_json($ingredientesFile);
$ingredientes = $ingredientesData['ingredientes'] ?? [];

// ===============================
// ELIMINAR INGREDIENTE
// ===============================
if (isset($_GET['delete'])) {
    $nombre = $_GET['delete'];

    $ingredientes = array_filter($ingredientes, function($ing) use ($nombre) {
        return $ing['nombre'] !== $nombre;
    });

    save_json($ingredientesFile, ['ingredientes' => array_values($ingredientes)]);
    header("Location: ingredientes.php");
    exit;
}

// ===============================
// GUARDAR CAMBIOS
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombres = $_POST['nombre'] ?? [];
    $calorias = $_POST['calorias'] ?? [];
    $supermercados = $_POST['supermercado'] ?? [];

    $newList = [];

    for ($i = 0; $i < count($nombres); $i++) {
        $n = trim($nombres[$i]);
        if ($n === '') continue;

        $newList[] = [
            'nombre' => $n,
            'calorias_x_100g' => $calorias[$i] !== '' ? (int)$calorias[$i] : null,
            'supermercado' => trim($supermercados[$i]) ?: 'General'
        ];
    }

    save_json($ingredientesFile, ['ingredientes' => $newList]);

    header("Location: ingredientes.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingredientes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    Ingredientes globales
</header>

<div class="container">

    <div class="card">
        <h2>Listado de ingredientes</h2>

        <form method="post">

            <table style="width:100%; border-collapse:collapse; font-size:0.95rem;">
                <thead>
                    <tr style="border-bottom:1px solid #e5e7eb;">
                        <th style="padding:0.5rem;">Nombre</th>
                        <th style="padding:0.5rem;">Calorías</th>
                        <th style="padding:0.5rem;">Supermercado</th>
                        <th style="padding:0.5rem; text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-ingredientes">

                <?php foreach ($ingredientes as $ing): ?>
                    <tr>
                        <td style="padding:0.5rem;">
                            <input type="text" name="nombre[]" value="<?= htmlspecialchars($ing['nombre']) ?>">
                        </td>
                        <td style="padding:0.5rem;">
                            <input type="number" name="calorias[]" value="<?= htmlspecialchars((string)$ing['calorias_x_100g']) ?>">
                        </td>
                        <td style="padding:0.5rem;">
                            <input type="text" name="supermercado[]" value="<?= htmlspecialchars($ing['supermercado']) ?>">
                        </td>
                        <td style="padding:0.5rem; text-align:right;">
                            <a href="ingredientes.php?delete=<?= urlencode($ing['nombre']) ?>"
                               class="btn-danger"
                               style="padding:0.3rem 0.7rem; font-size:0.85rem;"
                               onclick="return confirm('¿Eliminar ingrediente?');">
                                Eliminar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>

            <button type="button" class="btn-secondary" style="margin-top:1rem;"
                    onclick="addRow()">
                + Añadir ingrediente
            </button>

            <br><br>

            <button type="submit" class="btn-primary" style="padding:0.8rem 1.5rem;">
                Guardar cambios
            </button>

        </form>
    </div>

    <a href="index.php" class="btn-secondary" style="text-decoration:none; padding:0.6rem 1rem;">
        ← Volver a platos
    </a>

</div>

<script>
function addRow() {
    const tbody = document.getElementById("tabla-ingredientes");

    const tr = document.createElement("tr");

    tr.innerHTML = `
        <td style="padding:0.5rem;">
            <input type="text" name="nombre[]" value="">
        </td>
        <td style="padding:0.5rem;">
            <input type="number" name="calorias[]" value="">
        </td>
        <td style="padding:0.5rem;">
            <input type="text" name="supermercado[]" value="General">
        </td>
        <td style="padding:0.5rem; text-align:right;">
            <span style="color:#9ca3af;">Nuevo</span>
        </td>
    `;

    tbody.appendChild(tr);
}
</script>

</body>
</html>
