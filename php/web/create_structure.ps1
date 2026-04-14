# ============================================================================
# SCRIPT DE CREACIÓN DE ESTRUCTURA - WINDOWS (PowerShell)
# Proyecto: f00dlist (Generador de Menús Semanales)
# ============================================================================

$projectName = "f00dlist"

Write-Host "Creando estructura de proyecto en: $projectName" -ForegroundColor Cyan

# Crear carpeta raíz
New-Item -ItemType Directory -Force -Path $projectName | Out-Null
Set-Location $projectName

# ============================================================================
# 1. CARPETAS PRINCIPALES
# ============================================================================
$folders = @(
    "config",
    "includes",
    "classes",
    "api",
    "admin",
    "assets/css",
    "assets/js",
    "assets/img/icons",
    "pdf",
    "tests"
)

foreach ($folder in $folders) {
    New-Item -ItemType Directory -Force -Path $folder | Out-Null
    Write-Host "  + Carpeta: $folder" -ForegroundColor Gray
}

# ============================================================================
# 2. ARCHIVOS PHP (Configuración y Núcleo)
# ============================================================================
$phpFiles = @(
    "config/config.php",
    "config/db.php",
    "includes/auth.php",
    "includes/functions.php",
    "includes/header.php",
    "includes/footer.php",
    "includes/sidebar.php",
    "classes/User.php",
    "classes/Menu.php",
    "classes/MenuGenerator.php",
    "classes/Recipe.php",
    "classes/Ingredient.php",
    "classes/Tool.php",
    "classes/Permission.php",
    "api/add_to_effective.php",
    "api/remove_from_effective.php",
    "api/toggle_ingredient.php",
    "api/toggle_favorite.php",
    "api/generate_menu.php",
    "admin/index.php",
    "admin/users.php",
    "admin/roles.php",
    "admin/tools.php",
    "admin/ingredients.php",
    "admin/recipes.php",
    "pdf/generate_list.php",
    "index.php",
    "login.php",
    "register.php",
    "perfil.php",
    "favoritos.php",
    "receta.php",
    "logout.php"
)

foreach ($file in $phpFiles) {
    New-Item -ItemType File -Force -Path $file | Out-Null
    Write-Host "  + Archivo: $file" -ForegroundColor DarkGreen
}

# ============================================================================
# 3. ARCHIVOS CSS
# ============================================================================
$cssFiles = @(
    "assets/css/style.css",
    "assets/css/responsive.css",
    "assets/css/caloric.css"
)

foreach ($file in $cssFiles) {
    New-Item -ItemType File -Force -Path $file | Out-Null
    Write-Host "  + Archivo: $file" -ForegroundColor DarkBlue
}

# ============================================================================
# 4. ARCHIVOS JAVASCRIPT
# ============================================================================
$jsFiles = @(
    "assets/js/main.js",
    "assets/js/menu.js",
    "assets/js/inventory.js"
)

foreach ($file in $jsFiles) {
    New-Item -ItemType File -Force -Path $file | Out-Null
    Write-Host "  + Archivo: $file" -ForegroundColor DarkYellow
}

# ============================================================================
# 5. ARCHIVOS DE CONFIGURACIÓN Y OTROS
# ============================================================================
$otherFiles = @(
    ".htaccess",
    ".gitignore",
    "README.md",
    "docker-compose.yaml",
    "composer.json",
    "assets/img/logo.png",
    "assets/img/icons/icon-192.png",
    "assets/img/icons/icon-512.png"
)

foreach ($file in $otherFiles) {
    # Crear directorios intermedios si no existen (solo si hay directorio)
    $dir = Split-Path -Parent $file
    if ($dir -and -not (Test-Path $dir)) {
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
    }
    New-Item -ItemType File -Force -Path $file | Out-Null
    Write-Host "  + Archivo: $file" -ForegroundColor Magenta
}

# ============================================================================
# FIN
# ============================================================================
Set-Location ..
Write-Host "`n¡Estructura creada con éxito en la carpeta '$projectName'!" -ForegroundColor Green
Write-Host "Ahora puedes abrir la carpeta en tu editor de código." -ForegroundColor Cyan