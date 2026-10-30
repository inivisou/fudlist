# Puesta en Producción de f00dlist

Este documento recoge los pasos necesarios para preparar el proyecto f00dlist en un servidor de producción: cómo usar el script de creación de estructura, qué hace, qué hay que preparar en la base de datos y cómo crear los usuarios necesarios para FTP/SFTP y para MariaDB/MySQL.

---

## 1. Qué hace el script create_structure

En este proyecto existen scripts de arranque para crear la estructura base de carpetas y archivos:

- php/estructuraFiles/create_structure.ps1
- php/estructuraFiles/create_structure.sh
- php/web/create_structure.ps1

Estos scripts:
- crean la carpeta principal del proyecto llamada f00dlist,
- generan carpetas como config, includes, classes, api, admin, assets, pdf, tests,
- crean archivos PHP, CSS, JS y de configuración vacíos,
- sirven para iniciar un proyecto de forma rápida.

Importante:
- no crean la base de datos,
- no crean usuarios de FTP/SFTP,
- no crean usuarios de MariaDB/MySQL,
- no suben nada al servidor ni configuran Apache/Nginx.

Son solo el punto de partida del proyecto.

---

## 2. Cómo usar create_structure

### Opción A: Windows PowerShell

Desde la carpeta donde está el script:

```powershell
cd php\estructuraFiles
.\create_structure.ps1
```

### Opción B: Linux / macOS

```bash
cd php/estructuraFiles
bash create_structure.sh
```

### Opción C: Versión alternativa en php/web

```powershell
cd php\web
.\create_structure.ps1
```

Resultado esperado:
- se crea una carpeta f00dlist con la estructura base,
- dentro de esa carpeta aparecerán los archivos iniciales vacíos o de plantilla,
- después hay que copiar o subir el código real del proyecto y configurar la aplicación.

---

## 3. Preparación del servidor de producción

Antes de subir la aplicación, el servidor debe tener instalado lo siguiente:

- PHP 8.x
- MariaDB o MySQL
- Apache o Nginx
- SSH/SFTP para subir archivos
- Certificado SSL si se quiere acceso HTTPS

Recomendación:
- usar un usuario dedicado para el sitio web,
- no ejecutar la aplicación con el usuario root,
- proteger las credenciales de la base de datos.

---

## 4. Crear el usuario de FTP/SFTP para el proyecto

### Opción recomendada: SFTP con SSH

En un servidor Linux, el proceso suele ser este:

```bash
sudo adduser fudlistapp
sudo usermod -aG www-data fudlistapp
sudo passwd fudlistapp
```

Luego, el directorio del proyecto debe pertenecer al usuario adecuado y al grupo del servidor web:

```bash
sudo mkdir -p /var/www/f00dlist
sudo chown -R fudlistapp:www-data /var/www/f00dlist
sudo chmod -R 755 /var/www/f00dlist
```

Si el hosting lo gestiona desde un panel (cPanel, Plesk, DirectAdmin, etc.), se debe:

1. crear un usuario FTP/SFTP,
2. asignar ese usuario al directorio del sitio,
3. dar permisos de escritura únicamente donde sea necesario,
4. evitar usar el usuario root.

> En producción, lo ideal es usar SFTP en vez de FTP tradicional, porque es más seguro.

---

## 5. Crear la base de datos y el usuario de base de datos

El proyecto usa MariaDB/MySQL y el script inicial está en:

- php/bbdd/00_ScriptInicial.txt

### 5.1 Crear la base de datos

Accede a MariaDB/MySQL:

```bash
mysql -u root -p
```

Y ejecuta:

```sql
CREATE DATABASE IF NOT EXISTS inivi_f00dlist
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

### 5.2 Crear un usuario dedicado para la aplicación

```sql
CREATE USER 'inivi_tXU5o0w'@'localhost' IDENTIFIED BY 'CAMBIA_ESTA_CONTRASEÑA_POR_UNA_FUERTE';
GRANT ALL PRIVILEGES ON inivi_f00dlist.* TO 'inivi_tXU5o0w'@'localhost';
FLUSH PRIVILEGES;
```

Si la aplicación y la base de datos están en servidores distintos, usa la IP del servidor web o el comodín `%` con precaución:

```sql
CREATE USER 'inivi_tXU5o0w'@'192.168.1.10' IDENTIFIED BY 'CAMBIA_ESTA_CONTRASEÑA_POR_UNA_FUERTE';
GRANT ALL PRIVILEGES ON inivi_f00dlist.* TO 'inivi_tXU5o0w'@'192.168.1.10';
FLUSH PRIVILEGES;
```

> Nunca uses credenciales por defecto en producción. Cambia el usuario, la contraseña y la base de datos por valores únicos.

---

## 6. Importar el script SQL inicial

Una vez creada la base de datos, importa el script:

```bash
mysql -u inivi_tXU5o0w -p inivi_f00dlist < php/bbdd/00_ScriptInicial.txt
```

Este script:
- crea todas las tablas necesarias,
- inserta roles y permisos,
- crea usuarios iniciales,
- carga datos de ejemplo como ingredientes, platos, herramientas y recetas.

> En producción conviene revisar si quieres mantener los datos de ejemplo o si prefieres una instalación limpia con tus propios datos.

---

## 7. Configurar variables de entorno (.env)

**IMPORTANTE**: El proyecto **NO** usa credenciales hardcodeadas en config.php. Todas las credenciales sensibles se leen desde **variables de entorno**. El repositorio incluye un archivo `.env.example` como plantilla.

### 7.1 Crear el archivo .env en el servidor

En el directorio raíz del proyecto (donde está config.php), crea un archivo `.env` basado en `.env.example`:

```bash
cp .env.example .env
```

### 7.2 Editar .env con los valores reales del servidor

```bash
# Plantilla de variables de entorno para f00dlist
# Copiar a .env y rellenar con los valores reales del servidor.
# El archivo .env NUNCA debe subirse al repositorio (ver .gitignore).

# Base de datos MariaDB/MySQL
DB_HOST=localhost
DB_NAME=inivi_f00dlist
DB_USER=inivi_tXU5o0w
F00DLIST_DB_PASS=TU_CONTRASEÑA_REAL_SEGURA

# Ruta base de la app (ej: / para raíz, /f00dlist/ para subdirectorio)
F00DLIST_BASE_PATH=/

# Debug (true solo en desarrollo; false en producción)
F00DLIST_DEBUG=false
```

### 7.3 Variables requeridas

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `DB_HOST` | Host de la base de datos | `localhost` |
| `DB_NAME` | Nombre de la base de datos | `inivi_f00dlist` |
| `DB_USER` | Usuario de la base de datos | `inivi_tXU5o0w` |
| `F00DLIST_DB_PASS` | **Contraseña real de la BD** (nunca en el repo) | `password_seguro_123` |
| `F00DLIST_BASE_PATH` | Ruta base si la app está en subdirectorio | `/` o `/f00dlist/` |
| `F00DLIST_DEBUG` | Modo debug (false en producción) | `false` |

> **CRÍTICO**: El archivo `.env` **NUNCA** debe subirse al repositorio git. Está incluido en `.gitignore`. En el servidor de producción, créalo manualmente o usa el sistema de variables de entorno de tu proveedor (cPanel, Plesk, Docker, Kubernetes, etc.).

### 7.4 Cómo se leen las variables en config.php

El archivo `config/config.php` usa `getenv()` para leer estas variables:

```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'inivi_f00dlist');
define('DB_USER', getenv('DB_USER') ?: 'inivi_tXU5o0w');
define('DB_PASS', getenv('F00DLIST_DB_PASS') ?: '');
define('BASE_URL', $protocol . '://' . $host . $basePath);
define('DEBUG_MODE', filter_var(getenv('F00DLIST_DEBUG'), FILTER_VALIDATE_BOOLEAN));
```

---

## 8. Configurar la aplicación (config.php)

El archivo `php/web/f00dlist/config/config.php` ya está preparado para leer las variables de entorno. **No necesitas editarlo** si has configurado correctamente el `.env`.

Solo verifica que estas constantes se derivan correctamente:

```php
// Se derivan automáticamente de las variables de entorno
define('BASE_URL', 'https://tu-dominio.com/');  // Se construye desde $_SERVER + F00DLIST_BASE_PATH
define('DEBUG_MODE', false);                     // Desde F00DLIST_DEBUG
```

### Recomendaciones de configuración

- usar HTTPS en producción,
- activar logs de errores del servidor,
- desactivar el modo debug (`F00DLIST_DEBUG=false`),
- revisar la ruta base si el proyecto está en un subdirectorio (`F00DLIST_BASE_PATH`).

---

## 9. Permisos de archivos en el servidor

Para un entorno Linux con Apache o Nginx, conviene dejar los permisos así:

```bash
sudo find /var/www/f00dlist -type d -exec chmod 755 {} \;
sudo find /var/www/f00dlist -type f -exec chmod 644 {} \;
sudo chmod 600 /var/www/f00dlist/config/config.php
sudo chmod 600 /var/www/f00dlist/config/db.php
sudo chmod 600 /var/www/f00dlist/.env          # ¡Proteger el archivo .env!
sudo chown -R www-data:www-data /var/www/f00dlist
```

Si el proyecto se aloja con un usuario dedicado para subir archivos, se puede usar:

```bash
sudo chown -R fudlistapp:www-data /var/www/f00dlist
```

Y luego permitir que el servidor web lea los archivos.

---

## 10. Configurar el servidor web

### Apache (ejemplo básico)

```apache
<VirtualHost *:80>
    ServerName tu-dominio.com
    DocumentRoot /var/www/f00dlist

    <Directory /var/www/f00dlist>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Luego habilita el sitio y reinicia Apache:

```bash
sudo a2ensite tu-dominio.com.conf
sudo systemctl restart apache2
```

### Nginx (ejemplo básico)

El bloque de servidor debe apuntar al directorio del proyecto y ejecutar PHP con PHP-FPM.

---

## 11. Activar HTTPS

Una vez funcionando la web en HTTP, conviene poner HTTPS:
- instalar Certbot,
- obtener un certificado para el dominio,
- redirigir HTTP a HTTPS,
- actualizar BASE_URL a https://... (se hace automáticamente al detectar HTTPS en `$_SERVER`)

---

## 12. Pasos finales de validación

Una vez subido el proyecto y configurada la base de datos:
1. abrir la URL del sitio,
2. comprobar que la aplicación carga sin errores,
3. entrar con el usuario administrador inicial,
4. cambiar la contraseña del usuario admin,
5. revisar que los datos de ejemplo son correctos o eliminarlos si no se desean,
6. configurar copias de seguridad periódicas de la base de datos.

---

## 13. Recomendaciones de seguridad importantes

- cambiar todas las contraseñas por defecto,
- usar un usuario de base de datos con permisos mínimos,
- no dejar el modo debug activado (`F00DLIST_DEBUG=false`),
- proteger el archivo de configuración y **el archivo .env** (chmod 600),
- mantener copias de seguridad de la base de datos,
- usar HTTPS y actualizar PHP/MariaDB periódicamente.

---

## 14. Actualizaciones recientes en el proyecto

### Seguridad mejorada
- Todas las credenciales de base de datos se han migrado a variables de entorno en config/config.php (archivo .env)
- DEBUG_MODE se ha desactivado por defecto en producción
- Se han implementado cabeceras de seguridad HTTP (CSP, X-Frame-Options, etc.)

### Mejoras en la generación de menús
- Algoritmo de fuerza bruta para pescado corregido para cumplir con la frecuencia mínima
- Validación de pertenencia de ingredientes al menú implementada
- Soporte para categorías dietéticas libres en ingredientes

### Mejoras en la interfaz de administración
- Roles y permisos más granulares (admin, colaborador)
- Validación de longitud de nombres completos
- Mensajes genéricos para evitar enumeración de cuentas

---

## Resumen rápido

Para poner f00dlist en producción, necesitas:

1. crear el usuario FTP/SFTP para subir los archivos,
2. crear la base de datos y un usuario dedicado en MariaDB/MySQL,
3. importar el script SQL inicial,
4. **crear y configurar el archivo .env con las variables de entorno**,
5. subir la aplicación al servidor,
6. configurar Apache/Nginx y HTTPS,
7. revisar permisos y seguridad (incluyendo chmod 600 en .env).