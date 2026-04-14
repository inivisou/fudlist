<?php
/**
 * PDF/GENERATE_LIST.PHP
 * =====================
 * Generación de la Lista de la Compra para imprimir/guardar como PDF.
 * Usa CSS @media print para formatear la salida sin librerías externas.
 */

require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../classes/Menu.php';

// 1. Verificar autenticación
requireLogin();

$userId = getCurrentUserId();
$menuId = (int)($_GET['menu_id'] ?? 0);

// Si no se pasa menu_id, usamos el menú actual
if ($menuId <= 0) {
    $menuActual = Menu::getActualForUser($userId);
    if ($menuActual) {
        $menuId = $menuActual->getId();
    } else {
        die("No hay un menú activo para generar lista.");
    }
}

// 2. Verificar que el menú pertenece al usuario
$menu = new Menu($menuId);
if (!$menu->getId() || $menu->getUsuarioCreadorId() != $userId) {
    die("Menú no encontrado o no autorizado.");
}

// 3. Obtener ingredientes del menú efectivo
$sql = "SELECT i.id, i.nombre, i.supermercado, SUM(ri.cantidad) as total_cantidad, ri.unidad
        FROM menu_dias md
        JOIN platos p ON md.id_plato = p.id
        JOIN recetas r ON p.id = r.id_plato
        JOIN recetas_ingredientes ri ON r.id = ri.id_receta
        JOIN ingredientes i ON ri.id_ingrediente = i.id
        WHERE md.id_menu = ? AND i.activo = 1
        GROUP BY i.id, i.nombre, i.supermercado, ri.unidad
        ORDER BY i.supermercado, i.nombre";

$rawIngredients = fetchAll($sql, [$menuId]);

// 4. Obtener ingredientes ya comprados
$compradosSql = "SELECT id_ingrediente FROM ingredientes_comprados WHERE id_menu = ? AND comprado = 1";
$comprados = fetchAll($compradosSql, [$menuId]);
$compradosIds = array_column($comprados, 'id_ingrediente');

$listaFinal = [];
foreach ($rawIngredients as $ing) {
    $ing['comprado'] = in_array($ing['id'], $compradosIds);
    $listaFinal[] = $ing;
}

// 5. Obtener datos del menú para el encabezado
$comensales = $menu->getComensalesData();
$dias = $menu->getDiasData();
$numDias = count($dias);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de la Compra - f00dlist</title>
    <style>
        /* Estilos generales para pantalla */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 20px;
        }
        .header h1 { margin: 0; color: #2c3e50; }
        .header p { margin: 5px 0; color: #7f8c8d; }
        
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) { background-color: #f9f9f9; }
        
        .comprado {
            text-decoration: line-through;
            color: #95a5a6;
            background-color: #f0f0f0 !important;
        }
        
        .btn-print {
            display: block;
            width: 200px;
            margin: 30px auto;
            padding: 15px;
            background: #27ae60;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            border: none;
        }
        .btn-print:hover { background: #219150; }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
        }

        /* ESTILOS ESPECÍFICOS PARA IMPRESIÓN / PDF */
        @media print {
            body {
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .btn-print, .back-link {
                display: none !important;
            }
            .header {
                border-bottom: 2px solid #000;
            }
            th {
                background-color: #ddd !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            tr:nth-child(even) {
                background-color: #f2f2f2 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .info-box {
                border: 1px solid #ccc;
                background: none;
            }
            .comprado {
                background-color: #fff !important;
                color: #999;
            }
            /* Forzar salto de página si es necesario */
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

<a href="<?= url('index.php') ?>" class="back-link">← Volver al Dashboard</a>

<div class="header">
    <h1>Lista de la Compra</h1>
    <p><strong>Generado:</strong> <?= date('d/m/Y H:i') ?></p>
    <p><strong>Menú para:</strong> <?= $numDias ?> días</p>
</div>

<div class="info-box">
    <strong>Comensales:</strong> 
    <?php 
    if (empty($comensales)) {
        echo "Nadie seleccionado";
    } else {
        echo implode(", ", array_map(function($u) { return $u->getNombreCompleto() ?: $u->getUsername(); }, $comensales));
    }
    ?>
</div>

<?php if (empty($listaFinal)): ?>
    <p style="text-align: center; font-size: 1.2rem; color: #7f8c8d; padding: 40px;">
        No hay ingredientes pendientes en el menú efectivo.
    </p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th style="width: 50%;">Ingrediente</th>
                <th style="width: 20%;">Cantidad</th>
                <th style="width: 25%;">Supermercado</th>
                <th style="width: 5%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $currentSuper = '';
            foreach ($listaFinal as $ing): 
                if ($currentSuper !== $ing['supermercado']):
                    $currentSuper = $ing['supermercado'];
            ?>
                <tr style="background-color: #e8f6f3 !important; font-weight: bold;">
                    <td colspan="4" style="padding: 8px; border-bottom: 2px solid #3498db;">
                        🛒 <?= sanitize($currentSuper) ?>
                    </td>
                </tr>
            <?php endif; ?>
                <tr class="<?= $ing['comprado'] ? 'comprado' : '' ?>">
                    <td><?= sanitize($ing['nombre']) ?></td>
                    <td><?= number_format($ing['total_cantidad'], 0) ?> <?= sanitize($ing['unidad']) ?></td>
                    <td><?= sanitize($ing['supermercado']) ?></td>
                    <td style="text-align: center;">
                        <?= $ing['comprado'] ? '✅' : '⬜' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>

</body>
</html>