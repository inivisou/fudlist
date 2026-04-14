#!/bin/bash
# ============================================================================
# SCRIPT DE CREACIÓN DE ESTRUCTURA - LINUX/MACOS
# Proyecto: f00dlist (Generador de Menús Semanales)
# ============================================================================

project_name="f00dlist"

echo -e "\033[0;36mCreando estructura de proyecto en: $project_name\033[0m"

# Crear carpeta raíz
mkdir -p "$project_name"
cd "$project_name" || exit

# ============================================================================
# 1. CARPETAS PRINCIPALES
# ============================================================================
folders=(
    "config"
    "includes"
    "classes"
    "api"
    "admin"
    "assets/css"
    "assets/js"
    "assets/img/icons"
    "pdf"
    "tests"
)

for folder in "${folders[@]}"; do
    mkdir -p "$folder"
    echo -e "  \033[0;90m+ Carpeta: $folder\033[0m"
done

# ============================================================================
# 2. ARCHIVOS PHP (Configuración y Núcleo)
# ============================================================================
php_files=(
    "config/config.php"
    "config/db.php"
    "includes/auth.php"
    "includes/functions.php"
    "includes/header.php"
    "includes/footer.php"
    "includes/sidebar.php"
    "classes/User.php"
    "classes/Menu.php"
    "classes/MenuGenerator.php"
    "classes/Recipe.php"
    "classes/Ingredient.php"
    "classes/Tool.php"
    "classes/Permission.php"
    "api/add_to_effective.php"
    "api/remove_from_effective.php"
    "api/toggle_ingredient.php"
    "api/toggle_favorite.php"
    "api/generate_menu.php"
    "admin/index.php"
    "admin/users.php"
    "admin/roles.php"
    "admin/tools.php"
    "admin/ingredients.php"
    "admin/recipes.php"
    "pdf/generate_list.php"
    "index.php"
    "login.php"
    "register.php"
    "perfil.php"
    "favoritos.php"
    "receta.php"
    "logout.php"
)

for file in "${php_files[@]}"; do
    touch "$file"
    echo -e "  \033[0;32m+ Archivo: $file\033[0m"
done

# ============================================================================
# 3. ARCHIVOS CSS
# ============================================================================
css_files=(
    "assets/css/style.css"
    "assets/css/responsive.css"
    "assets/css/caloric.css"
)

for file in "${css_files[@]}"; do
    touch "$file"
    echo -e "  \033[0;34m+ Archivo: $file\033[0m"
done

# ============================================================================
# 4. ARCHIVOS JAVASCRIPT
# ============================================================================
js_files=(
    "assets/js/main.js"
    "assets/js/menu.js"
    "assets/js/inventory.js"
)

for file in "${js_files[@]}"; do
    touch "$file"
    echo -e "  \033[0;33m+ Archivo: $file\033[0m"
done

# ============================================================================
# 5. ARCHIVOS DE CONFIGURACIÓN Y OTROS
# ============================================================================
other_files=(
    ".htaccess"
    ".gitignore"
    "README.md"
    "docker-compose.yaml"
    "composer.json"
    "assets/img/logo.png"
    "assets/img/icons/icon-192.png"
    "assets/img/icons/icon-512.png"
)

for file in "${other_files[@]}"; do
    # Crear directorios intermedios si no existen (solo si hay directorio)
    dir=$(dirname "$file")
    if [ -n "$dir" ] && [ ! -d "$dir" ]; then
        mkdir -p "$dir"
    fi
    touch "$file"
    echo -e "  \033[0;35m+ Archivo: $file\033[0m"
done

# ============================================================================
# FIN
# ============================================================================
cd ..
echo -e "\n\033[0;32m¡Estructura creada con éxito en la carpeta '$project_name'!\033[0m"
echo -e "\033[0;36mAhora puedes abrir la carpeta en tu editor de código.\033[0m"