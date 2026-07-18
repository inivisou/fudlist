<?php
require_once __DIR__ . '/partials/header.php';

// Obtener ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Cargar recetas
$recetas_json = json_decode(file_get_contents(__DIR__ . '/data/recetas.json'), true);

// Buscar receta
$receta = null;
foreach ($recetas_json['recetas'] as $r) {
    if ($r['id'] === $id) {
        $receta = $r;
        break;
    }
}
?>

<main class="contenedor">

<?php if (!$receta): ?>

    <h1>Receta no encontrada</h1>
    <p>No existe ninguna receta con el ID proporcionado.</p>

<?php else: ?>

    <article class="receta">

        <h1><?php echo $receta['titulo_html']; ?></h1>
        <h2><?php echo $receta['subtitulo_html']; ?></h2>

        <section class="texto-receta">
            <?php echo $receta['texto_html']; ?>
        </section>

        <section class="ingredientes">
            <h3>Ingredientes</h3>
            <ul>
                <?php foreach ($receta['ingredientes'] as $ing): ?>
                    <li><?php echo htmlspecialchars($ing); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="herramientas">
            <h3>Herramientas necesarias</h3>
            <ul>
                <?php foreach ($receta['herramientas'] as $h): ?>
                    <li><?php echo htmlspecialchars($h); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="enlace">
            <h3>Enlace externo</h3>
            <p>
                <a href="<?php echo htmlspecialchars($receta['enlace']); ?>" target="_blank">
                    Ver receta completa
                </a>
            </p>
        </section>

    </article>

<?php endif; ?>

</main>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
