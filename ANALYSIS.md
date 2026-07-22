# ANALYSIS.md — Bitácora de decisiones del proyecto f00dlist

> Este fichero es una **bitácora viva**. Se actualiza conforme se toman decisiones nuevas que pueden modificar lo aquí reflejado. Cada bloque de decisión incluye el contexto que lo motivó para poder rastrear la evolución del proyecto.

---

## 1. QUÉ ES EL PROYECTO

**f00dlist** es una aplicación web para **generar y gestionar menús semanales** (comidas y cenas) de forma sencilla, accesible desde navegador y preparada para empaquetarse como app Android (WebView).

El usuario selecciona comensales y restricciones, pulsa **"Generar"** y la aplicación produce un menú que respeta reglas de separación entre platos, evita duplicados y permite gestionar la lista de la compra.

---

## 2. BITÁCORA DE DECISIONES TOMADAS

### Decisión 1 — Enfoque de generación: ya NO se usa YAML ni un LLM con prompt
- **Contexto original:** Los primeros prompts (`Prompt.txt`, `Prompt_platos_e_ingredientes.yaml.txt`) definían un flujo donde un LLM leía un catálogo en YAML y generaba un CSV al escribir exactamente `Genera`.
- **Decisión actual:** Se **descarta el fichero YAML y el enfoque de prompt/LLM**. La aplicación será una **página web normal**, con la lógica de generación de menús implementada en código (PHP) y los datos persistidos en base de datos.
- **Consecuencia:** `Prompt.txt` y `Prompt_platos_e_ingredientes.yaml.txt` quedan como documentación histórica en la raíz, pero no representan la arquitectura actual. El catálogo de platos/ingredientes ya no vive en YAML, vive en la BD.

### Decisión 2 — Backend en PHP (POO, PDO)
- PHP moderno, sin frameworks MVC pesados (no Laravel/Symfony).
- Acceso a datos con `PDO::prepare()` (prevención de SQL injection).
- Salida HTML escapada con `htmlspecialchars()` (prevención de XSS).
- Reflejado en `php/Prompt_php_version.txt` y en el script de estructura `php/estructuraFiles/create_structure.ps1`.

### Decisión 3 — Base de datos MariaDB/MySQL
- Motor elegido: **MariaDB/MySQL** (ya resuelto; no pendiente).
- Base de datos: `inivi_f00dlist` (utf8mb4).
- Esquema completo definido y creado en `php/bbdd/00_ScriptInicial.txt` (17 tablas: users, roles, permisos, platos, recetas, ingredientes, herramientas, menus, menu_dias, etc.).
- Datos de ejemplo incluidos (usuarios admin/Eme/Cris, platos, ingredientes, herramientas, recetas).

### Decisión 4 — Interfaz web "como una página normal"
- Frontend: HTML5 + CSS3 + JS Vanilla (sin jQuery, `fetch()` nativo).
- Diseño **responsive / Mobile First**.
- Botón **"Generar"** en la propia interfaz (no se espera un comando de texto `Genera`).
- Páginas previstas: `index.php` (dashboard), `login.php`, `register.php`, `perfil.php`, `receta.php`, `favoritos.php`, panel `admin/`.

### Decisión 5 — App móvil híbrida (Web-to-Android)
- La misma web se encapsula en un contenedor nativo (Capacitor o Cordova) para generar el APK.
- Requisitos: HTTPS, CORS configurado, diseño mobile-first.

### Decisión 6 — Automatización de la estructura
- Scripts `create_structure.ps1` / `create_structure.sh` (en `php/estructuraFiles/`) y `php/web/create_structure.ps1` generan carpetas y archivos vacíos de arranque (config, includes, classes, api, admin, assets, pdf, tests).
- Estos scripts **no** crean la BD ni usuarios; solo son punto de partida (ver `Puesta_en_Produccion.md`).

### Decisión 7 — Empaquetado de distribución
- Existe `php/web/f00dlist.zip` como artefacto de distribución/despliegue de la carpeta `php/web/f00dlist/`.

### Decisión 8 — `categoria` como texto libre con autocompletado (resuelve duda §13.3)
- **Contexto:** `MenuGenerator::checkDistance()` e `isPescado()` aplican reglas vía `strpos()` sobre `platos.categoria` (palabras clave: pasta, fajita, tortilla, crema/sopa, pescado). El seed deja la mayoría de `categoria` en NULL, así que esas reglas no se aplicaban.
- **Decisión:** `categoria` (en `platos` e `ingredientes`) se mantiene como **campo texto libre**, pero en los formularios de administración se añade un **`<datalist>` autocompletado** que sugiere los valores `DISTINCT` ya existentes en su propia tabla y permite escribir valores nuevos (que quedan guardados y pasan a sugerirse).
  - `admin/recipes.php`: datalist de `SELECT DISTINCT categoria FROM platos`.
  - `admin/ingredients.php`: datalist de `SELECT DISTINCT categoria FROM ingredientes`.
- **Consecuencia:** el generador sigue usando `strpos()` sobre el texto; en cuanto los platos lleven la palabra clave (p.ej. "pasta", "pescado") las reglas de distancia y la de pescado se activan. No se cambia la lógica de `MenuGenerator`, solo se asegura que el dato se rellene de forma consistente.
- **Pendiente de implementar:** añadir los `<datalist>` + `list=` en ambos formularios de admin y poblar `categoria` en el seed/platos existentes.

### Decisión 9 — Etiquetas dietéticas libres en ingredientes (resuelve duda §13.4)
- **Contexto:** `Recipe::isCompatibleWithDiet()` solo trataba `vegetariano` con IDs hardcodeados `[1,5,9]` (que no coinciden con el seed) y aceptaba siempre `vegan`/`celiaco`/`sin_lactosa`. El formulario de perfil ofrecía esas opciones pero no se aplicaban.
- **Decisión:** añadir a `ingredientes` un campo **`etiquetas` (texto libre)**, gestionado igual que `categoria` (§8): `<datalist>` con `SELECT DISTINCT etiquetas` en `admin/ingredients.php`, permitiendo valores nuevos (ej. `carne`, `pescado`, `lacteo`, `gluten`, `huevo`, `vegano`). Varias etiquetas separadas por comas/espacios dentro del mismo campo.
- **Lógica genérica en `Recipe::isCompatibleWithDiet($restriccion)`:** ya no usa IDs, sino `strpos()` sobre las etiquetas de los ingredientes de la receta:
  - `vegetariano` → incompatible si algún ingrediente tiene `carne` o `pescado`.
  - `vegan` → incompatible si tiene `carne`, `pescado`, `lacteo` u `huevo`.
  - `celiaco` (sin gluten) → incompatible si tiene `gluten`.
  - `sin_lactosa` → incompatible si tiene `lacteo`.
  - `normal` → siempre compatible.
- **Visualización:** al pintar el menú (tentativo y efectivo) se muestran las **etiquetas agregadas del plato** (unión de etiquetas de sus ingredientes) como *badges*, igual que el nivel calórico.
- **Filtrado:** al igual que con `categoria`, cualquier filtro por etiqueta se hará con `LIKE`/`strpos` sobre el texto (p.ej. buscador de platos por etiqueta en admin/catálogo).
- **Pendiente de implementar:** columna `etiquetas` en `ingredientes` (script SQL), datalist en `admin/ingredients.php`, reescritura de `isCompatibleWithDiet()`, agregación y pintado de badges en `index.php`/`receta.php`, y poblar etiquetas en el seed.

### Decisión 10 — Secretos y configuración por variables de entorno (resuelve duda §13.7 y §13.8)
- **Contexto:** `config/config.php` contenía credenciales reales de BD (`inivi_tXU5o0w` + contraseña) en el repositorio, `DEBUG_MODE=true` y `BASE_URL` hardcodeado a `/f00dlist/`. Riesgo de seguridad y falta de portabilidad.
- **Decisión:** migrar la configuración sensible a **variables de entorno** (`getenv()`) con *fallback* a valores por defecto seguros:
  - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` → desde env, fallback a `localhost`/`inivi_f00dlist`/usuario por defecto (sin contraseña real en el repo).
  - `DEBUG_MODE` → desde env (`F00DLIST_DEBUG`), default `false` en producción.
  - `BASE_URL` → derivado de `$_SERVER` (protocolo + host) y un `BASE_PATH` desde env (`F00DLIST_BASE_PATH`, default `/`), en lugar de hardcodear `/f00dlist/`.
- **Consecuencia:** el repositorio queda **sin secretos**. La contraseña real se elimina de `config.php` y se define solo en el servidor (panel/Apanel/env del hosting). Se añade `.env`/secretos a `.gitignore` por si se usa además un `.env.example` de plantilla.
- **Pendiente de implementar:** reescribir `config/config.php` con `getenv()`+fallbacks, rotar la contraseña expuesta en el servidor real, y documentar las variables en `Puesta_en_Produccion.md`.

### Decisión 11 — `classes/User.php` y `classes/Permission.php` como código muerto (resuelve duda §13.1)
- **Contexto:** existen pero no se usan; el RBAC y la gestión de usuario se hacen con funciones en `includes/auth.php` que consultan la BD directamente.
- **Decisión:** se **descartan** esas clases (no migrar). Se eliminan del repo para evitar confusión, o se dejan como `.bak` no cargados. La fuente de verdad es `auth.php` + `admin/users.php` + `api/admin_users.php`.

### Decisión 12 — Eliminar APIs huérfanas `generate_menu.php` y `toggle_favorite.php` (resuelve duda §13.2)
- **Contexto:** el menú se genera por POST directo en `index.php` (llamando a `MenuGenerator`) y el favorito de plato se gestiona en `receta.php`. Esos endpoints no se invocan.
- **Decisión:** se **eliminan** para no duplicar lógica ni dejar superficie de ataque sin mantenimiento. El listado de §9 (APIs) queda con los 5 endpoints reales.

### Decisión 13 — Conversión de unidades `unid` = 50 g se mantiene como limitación (resuelve duda §13.5)
- **Contexto:** `Ingredient::calculateCalories()` asume 50 g por unidad.
- **Decisión:** se **mantiene** la aproximación por ahora (no bloquea el MVP). Se anota como limitación conocida; en una fase futura se añadirá `peso_promedio` por ingrediente o un factor por tipo de unidad si se requiere precisión.

### Decisión 14 — Corregir enlace roto en `receta.php` (resuelve duda §13.6)
- **Contexto:** `receta.php` enlaza a `admin/editar_receta.php` que no existe.
- **Decisión:** se **corrige** el enlace a `admin/recipes.php?edit=ID` (el CRUD real). No se crea una página nueva.

### Decisión 15 — Descartar `includes/sidebar.php` (resuelve duda §13.9)
- **Contexto:** se crea vacío por los scripts de estructura pero no se usa; la navegación ya está en `includes/header.php`.
- **Decisión:** se **descarta** (eliminar o no cargar). La navegación única vive en el header.

---

## 3. ARQUITECTURA ACTUAL (resumen)

```
Navegador / App Android (WebView)
        │  HTTP + fetch()
        ▼
  PHP (POO, PDO)  ──►  MariaDB (inivi_f00dlist)
        │
   HTML/CSS/JS (responsive)
```

- **Catálogo:** platos, ingredientes, herramientas y recetas viven en tablas relacionales (ya no en YAML).
- **Generación:** algoritmo en PHP que aplica restricciones y produce el menú tentativo; el usuario lo confirma en el menú efectivo.
- **Persistencia:** menús actuales y favoritos, comensales, inventario ("ya en casa") y preferencias de usuario.

---

## 3.1. DOS MENÚS DIFERENCIADOS: TENTATIVO Y EFECTIVO

La generación produce dos vistas distintas (definidas en `php/Prompt_php_version.txt` y el esquema SQL):

### Menú Tentativo (generado)
- **Origen:** se genera al pulsar "Generar".
- **Estado:** efímero, no persistente; se resetea al generar de nuevo.
- **Restricciones:** cumple TODAS las reglas (no repetir, distancias mínimas, pescado, herramientas, ingredientes).
- **Formato de tabla:** `Nº | Comida | Para | Cena | Para`.
- **Columna "Para":** indica qué comensales pueden comer cada plato (exclusivo vs compartido).
- **Interacción:** click en celda → añade al menú efectivo (primer hueco libre). Sin checkboxes de edición.
- **Visualización:** nombre del plato + calorías totales + etiqueta de nivel calórico (clases CSS `.cal-bajo`, `.cal-medio`, `.cal-alto`).

### Menú Efectivo (persistente)
- **Origen:** se llena manualmente desde el tentativo o se edita.
- **Estado:** persistente en `menu_dias` (fuente de verdad para lista de la compra y PDF).
- **Restricciones:** el usuario puede romperlas manualmente (añadir repetidos, etc.).
- **Formato de tabla:** `Día | Comida | Quitar | Cena | Quitar | Herramientas del Día`.
- **Días:** números correlativos (1, 2, 3…), sin fechas.
- **Interacción:** checkboxes "Quitar" liberan hueco; se puede rellenar desde el tentativo.
- **Columna "Herramientas del Día":** resumen consolidado de herramientas de comida + cena.

### Flujo de generación
1. Selección de comensales (checkbox list).
2. Selección de herramientas a excluir.
3. Input "Días" (1 a total_platos/2).
4. El algoritmo aplica restricciones y genera el tentativo.
5. El efectivo se mantiene intacto hasta que el usuario lo modifique.

---

## 3.2. CATÁLOGOS: INGREDIENTES, RECETAS, CATEGORÍAS Y SUPERMERCADO

### Ingredientes (tabla `ingredientes`)
Catálogo maestro. Campos:
- `nombre` (único), `calorias_x_100g` (DECIMAL para cálculo nutricional), `supermercado` (agrupa la lista de la compra), `categoria` (p.ej. Carnes, Verduras, Pasta, Lácteos…), `activo`.
- Un solo registro por ingrediente; se reutiliza en todas las recetas que lo necesiten (regla del generador YAML original, ahora en BD).

### Recetas (tablas `recetas`, `recetas_ingredientes`, `recetas_herramientas`)
- `recetas`: definición (`titulo_html`, `subtitulo_html`, `texto_html`, `enlace`) ligada a un plato.
- `recetas_ingredientes`: relación N:M con `cantidad` y `unidad` explícitas por plato → base del cálculo de calorías y de la lista de la compra.
- `recetas_herramientas`: relación N:M con el catálogo de `herramientas` (Olla, Sartén, Horno, Termomix…) para el filtrado de generación y el resumen diario.

### Cálculo de calorías
- Fórmula: `SUM((cantidad * calorias_x_100g) / 100)` por receta; conversión automática si la unidad no es gramos.
- El plato lleva `nivel_calorico` (bajo/medio/alto) para la etiqueta visual.
- Manejo de NULL/0 en `calorias_x_100g`.

### Categorías y Supermercado
- `categoria` en ingredientes y platos permite filtrado y organización.
- `supermercado` en ingredientes ordena la **lista de la compra** agrupada por establecimiento (formato: `Día 1 – Comida • Ingrediente`).

### Inventario ("ya en casa")
- Tabla `ingredientes_comprados` por menú: marca ingredientes como comprados (persistente, AJAX).
- La lista de la compra y el PDF excluyen los marcados como comprados.

---

## 3.3. FAVORITOS (dos niveles)

El sistema admite favoritos tanto a nivel de **menú completo** como de **plato individual** (definido en el esquema SQL y `php/Prompt_php_version.txt`):

### Favoritos de menú (tabla `menus` + `menu_comensales` + `menu_dias`)
- Un menú generado puede guardarse como **favorito** con un nombre personalizado.
- Es una **copia profunda** de `menu_dias` y `menu_comensales` (no referencias).
- Permite tener **menús favoritos ilimitados**; se listan en "Mis Favoritos" (ver, cargar, eliminar, renombrar).
- Se diferencia del menú "actual" (único, efímero, se sobrescribe) por el campo `tipo` ENUM('actual','favorito').

### Favoritos de plato (tabla `platos_favoritos`)
- Un **usuario** puede marcar/desmarcar platos individuales como favoritos.
- Los platos favoritos tienen **mayor peso** en la generación aleatoria del menú tentativo.
- Se gestionan en el perfil (`perfil.php`) y en la vista de receta (`receta.php`, botón "Marcar como favorito").

---

## 4. REGLAS DE NEGOCIO (siempre activas)

- No inventar platos: solo se usan platos del catálogo (ahora en BD).
- Separación mínima entre tipos de plato:
  - ≥ 4 días entre pasta
  - ≥ 9 días entre fajitas
  - ≥ 5 días entre tortillas
  - ≥ 5 días entre cremas
  - Al menos un pescado cada 7 días
- No duplicar platos en el mismo menú (salvo necesidad extrema).
- Filtros: herramientas no disponibles, ingredientes a evitar, restricciones dietéticas de cualquier comensal.
- Lógica de comensales: prioridad exclusivo > compartido.
- Interfaz responsive (móvil y escritorio).

---

## 5. AUTENTICACIÓN, SESIONES Y SEGURIDAD

El código implementa un sistema completo de acceso (ver `includes/auth.php`, `login.php`, `register.php`, `logout.php`):

- **Registro y Login:** `password_hash()` con `PASSWORD_BCRYPT`; verificación con `password_verify()`. Sesión con `session_regenerate_id()` para evitar *session fixation*.
- **Sesión segura:** cookies `HttpOnly`, `Secure` (si HTTPS), `SameSite=Strict`, `gc_maxlifetime=3600` (1 h).
- **CSRF:** token por sesión validado en todos los POST (formularios y APIs).
- **SQL Injection:** todas las consultas vía PDO `prepare()`/`execute()` (patrón Singleton en `config/db.php`, `ATTR_EMULATE_PREPARES=false`).
- **XSS:** `htmlspecialchars()` en todas las salidas (`sanitize()` en `includes/functions.php`).
- **Acceso:** `requireLogin()` protege páginas internas; `requireAdmin()` las de administración.

---

## 6. CONTROL DE ROLES Y PERMISOS (RBAC)

Definido en `php/bbdd/00_ScriptInicial.txt` y gestionado en `admin/users.php` + `api/admin_users.php`:

- **Roles:** `admin` (acceso total), `user` (planificación), `colaborador` (gestión de catálogo: platos/ingredientes/herramientas).
- **Permisos** granulares: `crear_menu`, `manage_users`, `gestionar_herramientas`, `gestionar_recetas`, `ver_estadisticas`.
- **Helper:** `hasRole()`, `hasPermission()`, `isAdmin()`. El dashboard muestra panel de gestión si el usuario es `admin` o `colaborador`.
- **Comensales:** `getEligibleComensals()` excluye a los `admin` de la lista de comensales seleccionables.

---

## 7. PREFERENCIAS DE USUARIO (PERFIL)

`perfil.php` guarda en `preferencias_usuario` (clave/valor JSON) lo que alimenta al generador:

- `restriccion_dietetica`: `normal`, `vegetariano`, `vegan`, `celiaco` (sin gluten), `sin_lactosa`.
- `ingredientes_avitar`: lista de IDs de ingredientes a excluir.
- `platos_exclusivos`: platos que solo come ese usuario (lógica exclusivo > compartido).
- `platos_preferidos`: platos con prioridad en la generación aleatoria.

---

## 8. PANELES Y NAVEGACIÓN

- **Navbar responsive** (`includes/header.php`): menú hamburguesa en móvil, enlaces Dashboard / Favoritos / Perfil / Admin / Salir.
- **Admin** (`admin/index.php`): estadísticas (usuarios, platos, herramientas, ingredientes) y CRUD de Herramientas, Ingredientes, Recetas/Platos y Usuarios.
- **CRUD catálogo:** `admin/recipes.php` (plato + receta + ingredientes con cantidad/unidad + herramientas), `admin/ingredients.php`, `admin/tools.php`.
- **Soft delete:** ingredientes y herramientas se desactivan (`activo=0`), no se borran físicamente (FK `RESTRICT`).

---

## 9. APIS (AJAX / FETCH)

El frontend usa `fetch()` nativo (sin jQuery) contra `api/`:

- `add_to_effective.php` — añade plato al efectivo (primer hueco libre con `auto_find=1`).
- `remove_from_effective.php` — quita plato, deja hueco libre.
- `toggle_ingredient.php` — marca/desmarca "ya en casa" (`ingredientes_comprados`).
- `save_as_favorite.php` — guarda menú actual como favorito (copia profunda).
- `toggle_favorite.php` — marca/desmarca plato favorito del usuario.
- `admin_users.php` — cambia rol/estado de usuario (solo admin).

> Nota: `generate_menu.php` y `toggle_favorite.php` (Decisión 12) se eliminaron; el menú se genera por POST en `index.php` y el favorito de plato en `receta.php`.

---

## 10. LISTA DE LA COMPRA Y PDF

- **Origen:** solo el menú efectivo (`menu_dias` → `recetas_ingredientes` → `ingredientes`).
- **Lista dinámica** en `index.php`: agrupada por `supermercado`, con cantidad total, checkbox "Ya en casa" (AJAX persistente, tachado visual).
- **PDF:** `pdf/generate_list.php` usa **CSS `@media print`** + `window.print()` (sin librerías externas TCPDF/Dompdf en la implementación actual). Excluye ingredientes marcados como comprados y agrupa por supermercado.

---

## 11. DECISIONES PENDIENTES / ABIERTAS

- [ ] El algoritmo de generación usa selección aleatoria con prioridad de favoritos; definir si se quiere weighting más fino.
- [ ] Decidir si se mantienen los datos de ejemplo del script SQL o se parte de una instalación limpia.
- [ ] Confirmar Capacitor vs Cordova para el empaquetado Android.
- [ ] Implementar los "Pendiente de implementar" de las Decisiones 8, 9, 10, 16, 17, 18, 19, 20, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 36, 37, 38, 39, 40, 41, 42, 46 y 47 (datalists, etiquetas, rotar contraseña, PDO en favoritos, forcePescado, momento en add_to_effective, max() vacío, CSS mínimos, eliminar getPlato, HTMLPurifier, rate-limiting+cabeceras, mensajes genéricos+boolean, variables admin/recipes, datalist categoria, requireRole colaborador, escapar JS, unificar getTools, proteger último admin, mensaje genérico login/register, rate-limiting login/register, UX admin_users, requireRole+datalist ingredients, limitar nombre_completo, validar pertenencia ingrediente, requireRole tools, defensa AJAX, distinguir hueco vacío, limpiar functions, separar bootstrap).
- [x] **IMPLEMENTADAS en código (octava ronda):** Decisión 35 (header basename), Decisión 43 (env vars + cabeceras globales en config.php + .env.example/.gitignore), Decisión 44 (validación dieta en perfil.php), Decisión 45 (logout POST + CSRF en header.php/logout.php). **Pendiente crítico:** rotar la contraseña BD expuesta en el servidor de producción y definir las variables de entorno allí.

---

## 12. SIGUIENTE PASO

- [ ] Pulir el generador (dietas completas, pesos de unidad reales) y validar el flujo completo tentativo→efectivo→favorito→PDF sobre el esquema ya definido en `php/bbdd/00_ScriptInicial.txt`.

---

## 13. DUDA / PUNTOS A DISCUTIR (encontrados al revisar el código)

Al leer la implementación real (`php/web/f00dlist/`) he detectado las siguientes incógnitas o incoherencias que conviene acordar:

1. **Archivos de clase aparentemente no usados.** ✅ **RESUELTO en Decisión 11**: `classes/User.php` y `classes/Permission.php` se descartan (código muerto); la lógica vive en `auth.php`.
2. **APIs duplicadas / sin uso.** ✅ **RESUELTO en Decisión 12**: `api/generate_menu.php` y `api/toggle_favorite.php` se eliminan; el flujo real usa POST en `index.php` y `receta.php`.
3. **Reglas de distancia dependen de `categoria` rellena.** ✅ **RESUELTO en Decisión 8**: `categoria` se mantiene texto libre con `<datalist>` autocompletado en `admin/recipes.php` e `admin/ingredients.php` (lee `DISTINCT` de su tabla). El generador sigue con `strpos()`; basta poblar las palabras clave (pasta, fajita, tortilla, crema, pescado) en los platos.
4. **Dieta solo implementada a medias.** ✅ **RESUELTO en Decisión 9**: se añade campo `etiquetas` (texto libre + datalist) a `ingredientes` y `isCompatibleWithDiet()` se reescribe genérico por `strpos()` sobre etiquetas (`carne`, `pescado`, `lacteo`, `gluten`, `huevo`, `vegano`). Las etiquetas del plato se pintan como badges en el menú.
5. **Conversión de unidades para calorías.** ✅ **RESUELTO en Decisión 13**: se mantiene `unid`=50 g como limitación conocida (no bloquea MVP); pendiente `peso_promedio` futuro.
6. **`receta.php` enlaza a `admin/editar_receta.php`** que no existe. ✅ **RESUELTO en Decisión 14**: se corrige el enlace a `admin/recipes.php?edit=ID`.
7. **Credenciales de BD en `config/config.php`.** ✅ **RESUELTO en Decisión 10**: se migran a variables de entorno (`getenv()` + fallback), se elimina la contraseña real del repo y `DEBUG_MODE`/`BASE_PATH` también vienen de env.
8. **`BASE_URL` hardcodeado a `/f00dlist/`.** ✅ **RESUELTO en Decisión 10**: `BASE_URL` se deriva de `$_SERVER` + `F00DLIST_BASE_PATH` (env, default `/`), ya no hardcodeado.
9. **`sidebar.php`** se crea vacío por los scripts de estructura pero no se usa. ✅ **RESUELTO en Decisión 15**: se descarta; la navegación vive en `header.php`.

---

## 14. SEGUNDA RONDA — NUEVAS DUDA / MEJORAS (tras revisar index, perfil, favoritos, Menu, functions)

En esta segunda lectura en profundidad he detectado los siguientes puntos:

1. **Inyección SQL / inconsistencia en `favoritos.php` (acción "cargar").** Líns. 65-66 usan `$db->exec("DELETE FROM menu_dias WHERE id_menu = {$menuActual->getId()}")` con interpolación de variable en vez de sentencias preparadas. Aunque `getId()` es int (riesgo bajo), rompe el estándar PDO del resto de la app y es frágil. ¿Lo pasamos a `executeQuery()` con parámetros?
2. **`MenuGenerator::forcePescado()` solo fuerza UN pescado** aunque el menú sea de 14 días (la regla dice "≥1 pescado cada 7 días" → 2 pescados en 14). Bug de lógica: hace `return` tras el primero. ¿Corregimos el bucle para cumplir la frecuencia?
3. **`add_to_effective.php` con `auto_find=1` ignora el momento.** `getFirstFreeSlot()` devuelve el primer hueco libre sea comida o cena, así que un plato de comida puede caer en hueco de cena. ¿Respetamos el momento del plato clicado (buscar primero hueco libre de su momento)?
4. **Warning en `index.php` lín. 267:** `max(array_keys($diasEfectivos))` lanza advertencia si `$diasEfectivos` está vacío. ¿Lo protegemos con un `empty()`?
5. **CSS de assets parecen inexistentes.** `includes/header.php` enlaza `css/style.css`, `css/responsive.css`, `css/caloric.css`, pero al leer `assets/css/*.css` no se obtuvo contenido (archivos vacíos/ausentes). Sin ellos, el layout responsive y los badges calóricos no se ven. ¿Creamos los CSS mínimos o los quita del header?
6. **Typo coherente `ingredientes_avitar`** (debería ser "evitar") en BD, `perfil.php` y `Ingredient::getAvoidedList()`. Funciona porque es consistente, pero es un error de ortografía en el modelo de datos. ¿Lo mantenemos o lo renombramos a `ingredientes_evitar`?
7. **`Menu::getPlato()` parece código muerto** (no se invoca desde ningún sitio revisado). ¿Descartar?

### Decisión 16 — `favoritos.php` usa PDO preparado con la transacción abierta (resuelve duda §14.1)
- **Contexto:** en la acción "cargar" de `favoritos.php` (líns. 65-66) se usaba `$db->exec("DELETE ... WHERE id_menu = {$menuActual->getId()}")` con interpolación de variable, rompiendo el estándar PDO del resto de la app.
- **Decisión:** sustituir por sentencias **preparadas sobre la misma conexión/transacción abierta** (`$db->prepare()->execute([$menuActual->getId()])`), eliminando la interpolación. Así se mantiene la transacción existente y se unifica el estilo de acceso a datos.
- **Pendiente de implementar:** reescribir esas dos líneas en `favoritos.php` usando `prepare()`/`execute()` con parámetro `?`.

### Decisión 17 — `forcePescado()` debe cumplir la frecuencia (resuelve duda §14.2)
- **Contexto:** `MenuGenerator::forcePescado()` hace `return` tras insertar el primer pescado, incumpliendo "≥1 pescado cada 7 días" en menús largos (p.ej. 14 días → debería haber 2).
- **Decisión:** convertir el `return` en lógica de bucle que siga forzando pescados hasta alcanzar `ceil(numDias / PESCADO_CADA_X_DIAS)` pescados distintos en días distintos. Se respeta la regla de negocio §4.
- **Pendiente de implementar:** reescribir `forcePescado()` para no salir tras el primero y contabilizar cuántos pescados ha forzado.

### Decisión 18 — `add_to_effective` respeta el momento del plato (resuelve duda §14.3)
- **Contexto:** con `auto_find=1`, `getFirstFreeSlot()` devuelve el primer hueco libre sea comida o cena, pudiendo colocar un plato de comida en hueco de cena.
- **Decisión:** pasar el momento deseado a `getFirstFreeSlot($maxDias, $momento)` y buscar primero un hueco libre de **ese** momento; si no hay, entonces cualquier hueco libre (fallback). El JS ya sabe si el clic fue en comida o cena.
- **Pendiente de implementar:** añadir parámetro `$momento` a `getFirstFreeSlot()` y usarlo en `add_to_effective.php` (enviar momento desde `dashboard.js` según la celda clicada).

### Decisión 19 — Proteger `max(array_keys())` vacío en `index.php` (resuelve duda §14.4)
- **Contexto:** lín. 267 `max(array_keys($diasEfectivos))` lanza advertencia PHP si el menú efectivo está vacío.
- **Decisión:** calcular `$maxDiasEfectivo` con `!empty($diasEfectivos) ? max(array_keys($diasEfectivos)) : 0`. Pequeña corrección de robustez.
- **Pendiente de implementar:** ajustar esa línea en `index.php`.

### Decisión 20 — Crear CSS mínimos en `assets/css/` (resuelve duda §14.5)
- **Contexto:** `header.php` enlaza `style.css`, `responsive.css`, `caloric.css` pero los archivos no tenían contenido (vacíos/ausentes); sin ellos el layout responsive y los badges calóricos no se renderizan.
- **Decisión:** **crear los tres CSS mínimos** con los estilos ya inline usados en las vistas (clases `.cal-bajo/.cal-medio/.cal-alto`, `.dashboard-container`, `.header-panel`, `.tentative-item`, `.btn-*`, grid responsive, navbar) para centralizar el diseño y que el responsive funcione de verdad. No se eliminan los enlaces del header.
- **Pendiente de implementar:** crear `assets/css/style.css`, `assets/css/responsive.css`, `assets/css/caloric.css` con los estilos base.

### Decisión 21 — Mantener `ingredientes_avitar` (typo consciente) (resuelve duda §14.6)
- **Contexto:** la clave `ingredientes_avitar` (en BD, `perfil.php`, `Ingredient::getAvoidedList()`) tiene un error de ortografía ("avitar" en vez de "evitar"), pero es consistente en todo el código.
- **Decisión:** **mantenerlo** para no romper el esquema ni el seed; se anota como deuda de nomenclatura. Un renombrado futuro requeriría migración de la tabla `preferencias_usuario`.
- **Pendiente de implementar:** ninguno (solo documentar).

### Decisión 22 — Descartar `Menu::getPlato()` (resuelve duda §14.7)
- **Contexto:** método no invocado desde ninguna vista/API revisada (el dashboard usa `Recipe::getByPlatoId()`).
- **Decisión:** **descartar** el método (código muerto) para simplificar `Menu.php`.
- **Pendiente de implementar:** eliminar `getPlato()` de `classes/Menu.php`.

---

## 15. TERCERA RONDA — NUEVAS DUDA / MEJORAS + REVISIÓN DE SEGURIDAD

Tras leer `auth.php`, `receta.php`, `api/add_to_effective.php`, `api/save_as_favorite.php` y cruzar con `config.php`.

### 15.1 Revisión de seguridad (hallazgos)

| # | Hallazgo | Severidad | Estado |
|---|----------|-----------|--------|
| S1 | Credenciales BD reales y `DEBUG_MODE=true` en repo | Alta | Resuelto en Decisión 10 (pendiente implementar) |
| S2 | `receta.php` imprime `titulo_html`/`texto_html` **sin `sanitize()`** (HTML crudo de admin) | Media | Stored XSS potencial si un rol no confiable edita recetas |
| S3 | Sin **rate-limiting** en `loginUser`/`registerUser` | Media | Brute-force / registro spam |
| S4 | Sin **cabeceras HTTP de seguridad** (CSP, X-Frame-Options, nosniff, HSTS) | Media | Clickjacking / sniffing |
| S5 | `registerUser` revela "usuario o email ya registrados" → **enumeración de cuentas** | Baja | |
| S6 | `add_to_effective.php` usa `== 1` (comparación débil) para `auto_find` | Baja | |
| S7 | `receta.php` sigue enlazando `admin/editar_receta.php` (roto) | Baja | Pendiente Decisión 14 |
| S8 | `loginUser` regenera sesión tras setear vars (correcto); pero no invalida sesiones previas en logout parcial | Info | |

**Puntos fuertes confirmados:** PDO `prepare()` en todo el acceso a datos (salvo §14.1 ya resuelto), `password_hash` BCRYPT + `password_verify`, `session_regenerate_id(true)` anti-fixation, cookies `HttpOnly`/`SameSite=Strict`, validación CSRF en todos los POST, `sanitize()` (htmlspecialchars) en salidas de usuario, whitelist en `updateCurrentUser()`, autorización por ownership en APIs (`getUsuarioCreadorId() != getCurrentUserId()`).

### 15.2 Nuevas dudas

1. **Stored XSS en `receta.php` (S2).** `titulo_html`/`texto_html`/`subtitulo_html` se emiten crudos. ¿Los tratamos como confianza de admin (aceptable) o aplicamos una allowlist de tags (p.ej. `strip_tags` con etiquetas permitidas) antes de guardar/mostrar?
2. **Rate-limiting login/registro (S3).** ¿Añadimos tope de intentos (sesión/IP) o CAPTCHA?
3. **Cabeceras de seguridad (S4).** ¿Añadimos CSP/X-Frame-Options/nosniff/HSTS en `config.php` o `.htaccess`?
4. **Enumeración de cuentas (S5).** ¿Unificamos el mensaje de registro a genérico?
5. **`auto_find == 1` (S6).** ¿Cambiamos a `filter_var($_POST['auto_find'], FILTER_VALIDATE_BOOLEAN)` o `=== '1'`?

### Decisión 23 — Sanitizar HTML de recetas con HTMLPurifier (resuelve S2)
- **Contexto:** `receta.php` imprime `titulo_html`/`subtitulo_html`/`texto_html` crudos → Stored XSS si un editor comprometido inyecta `<script>`.
- **Decisión:** instalar **HTMLPurifier** (librería externa) y sanitizar el HTML al **mostrar** (o al guardar) en `receta.php` y en `admin/recipes.php`. Se define una configuración permisiva pero segura (allowlist de tags: p, br, b, i, ul, li, h3, a con href http(s), img con src http(s)). Así se conserva el formato de recetas sin riesgo XSS.
- **Pendiente de implementar:** añadir HTMLPurifier al proyecto (composer o vendor), crear helper `purifyHtml()` en `functions.php`, usarlo en `receta.php` y en el guardado de `Recipe::save()`.

### Decisión 24 — Rate-limiting + cabeceras de seguridad (resuelve S3 y S4)
- **Contexto:** no hay tope de intentos en login/registro (brute-force) ni cabeceras HTTP de seguridad (clickjacking/sniffing).
- **Decisión:**
  - **Rate-limiting:** en `loginUser`/`registerUser` limitar a N intentos por IP+sesión en ventana de tiempo (p.ej. 5 intentos/15 min) usando `$_SESSION` o tabla `login_attempts`. Sin CAPTCHA para no complicar.
  - **Cabeceras de seguridad:** en `config.php` (o `.htaccess`) enviar: `Content-Security-Policy` (básica, self + data para PDF), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Strict-Transport-Security` (si HTTPS), `Referrer-Policy: no-referrer`.
- **Pendiente de implementar:** helper `applySecurityHeaders()` en `config.php`/functions y lógica de intentos en `auth.php`.

### Decisión 25 — Mensajes genéricos + validación booleana (resuelve S5 y S6)
- **Contexto:** `registerUser` revela "usuario o email ya registrados" (enumeración) y `add_to_effective.php` usa `== 1` (comparación débil).
- **Decisión:**
  - Registro y login devuelven **error genérico** ("Credenciales o datos no válidos / ya en uso") sin distinguir si el usuario/email existe.
  - `auto_find` se valida con `filter_var($_POST['auto_find'] ?? false, FILTER_VALIDATE_BOOLEAN)` en `add_to_effective.php` (evita `== 1` débil).
- **Pendiente de implementar:** ajustar mensajes en `auth.php` y la validación en `api/add_to_effective.php`.

---

## 16. CUARTA RONDA — NUEVAS DUDA / MEJORAS (tras revisar admin/recipes, ingredients, users, api)

### 16.1 Hallazgos

1. **BUG crítico en `admin/recipes.php`: variables no definidas.** El formulario y la tabla usan `$allIngredients`, `$allTools` y `$recipes`, pero el archivo solo define `$platosData` (lín. 23). Esas tres variables **nunca se inicializan** → la página de administración de recetas falla (warning de variable indefinida y `<select>`/lista vacíos). Falta: `$allIngredients = Ingredient::getAllActive();`, `$allTools = Tool::getAllActive();`, `$recipes = Recipe::getAllWithPlato();` (o query equivalente).
2. **`categoria` sigue como texto plano en `admin/recipes.php`** (lín. 188) y no en `admin/ingredients.php` → pendiente aplicar el `<datalist>` acordado en Decisión 8.
3. **`texto` HTML se guarda crudo** en `Recipe::save()` (sin HTMLPurifier) → pendiente Decisión 23.
4. **`getTools()` vs `array_column(..., 'id')`**: en edición (lín. 277) se asume que `getTools()` devuelve array de arrays con clave `id`; si devuelve objetos Tool, `array_column` falla. Revisar el tipo de retorno.
5. **`requireAdmin()` en `admin/recipes.php`** pero la edición de catálogo debería permitir también a `colaborador` (según RBAC §6). ¿Relajar a `requireRole(['admin','colaborador'])`?
6. **`addIngredientRow()` inyecta nombres de ingredientes en JS** sin escapar (lín. 339) → si un ingrediente tiene comilla/apóstrofe rompe el JS o XSS. Usar `json_encode()`/`htmlspecialchars` en el JS.

### Decisión 26 — Definir variables faltantes en `admin/recipes.php` (resuelve 16.1.1)
- **Contexto:** BUG que deja la página de gestión de recetas rota.
- **Decisión:** añadir tras los requires: `$allIngredients = Ingredient::getAllActive();`, `$allTools = Tool::getAllActive();` y `$recipes = ...` (listado de recetas con datos de plato, p.ej. `Recipe::getAllWithPlato()` o un `fetchAll` join). Sin esto la página no funciona.
- **Pendiente de implementar:** agregar las tres asignaciones en `admin/recipes.php`.

### Decisión 27 — Aplicar datalist de `categoria` en admin (resuelve 16.1.2 + Decisión 8)
- **Decisión:** en `admin/recipes.php` y `admin/ingredients.php` cambiar el `<input name="categoria">` por `<input list="cat-list">` + `<datalist id="cat-list">` con `SELECT DISTINCT categoria`. Reutilizable y consistente con Decisión 8.
- **Pendiente de implementar:** añadir el datalist en ambos formularios.

### Decisión 28 — `colaborador` puede gestionar catálogo (resuelve 16.1.5)
- **Contexto:** `admin/recipes.php` usa `requireAdmin()` pero el RBAC (§6) dice que `colaborador` gestiona catálogo.
- **Decisión:** crear helper `requireRole(array $roles)` y usarlo en `admin/recipes.php`, `admin/ingredients.php`, `admin/tools.php` con `['admin','colaborador']`. `admin/users.php` sigue solo `admin`.
- **Pendiente de implementar:** añadir `requireRole()` en `auth.php` y sustituir `requireAdmin()` en las páginas de catálogo.

### Decisión 29 — Escapar ingredientes en JS de `addIngredientRow()` (resuelve 16.1.6)
- **Decisión:** generar las `<option>` del JS usando `htmlspecialchars($ing['nombre'], ENT_QUOTES)` o construir el array en PHP y `json_encode()` para evitar rotura de JS / XSS.
- **Pendiente de implementar:** ajustar el script en `admin/recipes.php`.

### Decisión 30 — Unificar tipo de retorno de `getTools()` (resuelve 16.1.4)
- **Contexto:** en edición (lín. 277) se hace `array_column($recipeData['herramientas'], 'id')`, asumiendo que `getTools()` devuelve array de arrays. Hay que garantizar ese tipo de retorno (o ajustar el uso a objetos) para que no falle.
- **Decisión:** documentar/garantizar que `Recipe::getTools()` devuelve array de arrays (`['id'=>, 'nombre'=>, ...]`) para que `array_column(...,'id')` funcione; o cambiar la edición a iterar objetos. Se unifica a array de arrays en todas las clases Tool/Recipe.
- **Pendiente de implementar:** verificar y ajustar `getTools()` / uso en `admin/recipes.php`.

> **Estado:** las Decisiones 26-30 fueron **confirmadas por el usuario** en la cuarta ronda. Los 6 hallazgos de §16.1 quedan resueltos (26→16.1.1, 27→16.1.2, 28→16.1.5, 29→16.1.6, 30→16.1.4; el 16.1.3 es continuación de la Decisión 23).

---

## 17. QUINTA RONDA — NUEVAS DUDA / MEJORAS (tras revisar admin/users, api/admin_users, login, register)

### 17.1 Hallazgos

1. **`api/admin_users.php` permite que el único admin se degrade a `user`.** El switch `remove_role`/`set_admin`/`toggle_active` no impide que el último admin activo pierda su rol, dejando el sistema sin administradores. Falta validar "no degradar al último admin activo".
2. **`login.php` muestra mensaje de error específico** ("Usuario o contraseña incorrectos") en lugar del genérico acordado en Decisión 25. Pendiente aplicar.
3. **Sin rate-limiting aún** en `login.php`/`register.php` (Decisión 24 pendiente de implementar).
4. **`admin_users.php` usa `location.reload()`** tras acción AJAX → recarga completa; aceptable pero se podría actualizar solo la fila (mejora UX).
5. **`register.php`** debe aplicar también el mensaje genérico (Decisión 25) y rate-limiting.

### Decisión 31 — Proteger al último admin (resuelve 17.1.1)
- **Contexto:** `api/admin_users.php` permite degradar/desactivar al único admin, bloqueando el sistema.
- **Decisión:** antes de `remove_role`/`toggle_active`(a inactivo) sobre un usuario con rol `admin`, contar cuántos admins activos quedan; si es el último, rechazar con "No se puede dejar el sistema sin administradores".
- **Pendiente de implementar:** añadir la comprobación en `api/admin_users.php` (count de admins activos vía `getUserRoles`/query).

### Decisión 32 — Aplicar mensaje genérico en login/register (refuerza Decisión 25)
- **Decisión:** `loginUser`/`registerUser` devuelven mensaje genérico (Decisión 25); `login.php` y `register.php` deben mostrarlo sin añadir distinciones. Texto unificado: "Credenciales o datos no válidos".
- **Pendiente de implementar:** ajustar `login.php` y `register.php` para no sobre-escribir con mensajes específicos.

### Decisión 33 — Rate-limiting en login/register (refuerza Decisión 24)
- **Decisión:** implementar tope de intentos (5/15 min por IP+sesión) en `loginUser`/`registerUser` usando `$_SESSION` o tabla `login_attempts`. Sin CAPTCHA.
- **Pendiente de implementar:** lógica en `auth.php` + tabla `login_attempts` en el script SQL.

### Decisión 34 — Mejorar UX de `admin_users.php` (resuelve 17.1.4)
- **Decisión:** tras `userAction` exitosa, actualizar solo la fila afectada (toggle de badges/botones) en vez de `location.reload()`. Opcional; se mantiene `reload` si es más simple, pero se anota como mejora.
- **Pendiente de implementar:** opcional; manipular DOM en el `.then()` de `userAction`.

---

## 18. SEXTA RONDA — NUEVAS DUDA / MEJORAS ESTRUCTURALES Y SEGURIDAD

### 18.1 Hallazgos

1. **`header.php` hardcodea `/f00dlist/`** en los estilos de "página activa" (líns. 98-100: `$currentPath === '/f00dlist/index.php'`). Contradice la Decisión 10 (BASE_URL derivado de `$_SERVER`). Si se despliega en raíz o subdirectorio distinto, el resaltado de navegación falla.
2. **`admin/ingredients.php` usa `requireAdmin()`** en lugar de `requireRole(['admin','colaborador'])` (Decisión 28 pendiente) y `categoria` sigue texto plano (Decisión 27 pendiente).
3. **`register.php` no limita longitud de `nombre_completo`** → posible abuso de almacenamiento; `registerUser()` tampoco lo valida.
4. **`config/db.php` usa `die()` en fallo de conexión** en producción; mejor lanzar excepción para un manejo centralizado (menor).
5. **`pdf/generate_list.php` no aplica cabeceras de seguridad** (Decisión 24) — aunque es una página autenticada de impresión, conviene aplicarlas globalmente vía `config.php` (una sola vez).

### Decisión 35 — Deshardcodear ruta en `header.php` (resuelve 18.1.1)
- **Contexto:** el resaltado de página activa compara contra `/f00dlist/...` fijo, roto si cambia la base.
- **Decisión:** derivar la ruta base de `BASE_PATH`/`$_SERVER['SCRIPT_NAME']` y comparar el segmento final (`basename($currentPath)`) en vez de la ruta absoluta hardcodeada. Así el navbar resalta correctamente en cualquier despliegue.
- **✅ IMPLEMENTADO:** `header.php` usa `$currentScript = basename($currentPath)` para el resaltado. Funciona en cualquier `BASE_PATH`.

### Decisión 36 — Aplicar `requireRole` y datalist en `admin/ingredients.php` (refuerza 28 y 27)
- **Decisión:** sustituir `requireAdmin()` por `requireRole(['admin','colaborador'])` y cambiar el `<input name="categoria">` a `<input list="cat-list-ing">` + `<datalist>` con `SELECT DISTINCT categoria FROM ingredientes` (igual que en recipes).
- **Pendiente de implementar:** ajustar `admin/ingredients.php`.

### Decisión 37 — Limitar `nombre_completo` en registro (resuelve 18.1.3)
- **Decisión:** añadir validación de longitud máxima (p.ej. 100) en `register.php` y en `registerUser()` (parámetro truncado/validado). Evita abuso de BD.
- **Pendiente de implementar:** validar longitud en `register.php` y `auth.php`.

### Decisión 38 — Cabeceras de seguridad globales vía `config.php` (refuerza 24)
- **Decisión:** en vez de aplicarlas página por página, enviarlas una sola vez en `config.php` (al cargar) con `applySecurityHeaders()`. Así `pdf/`, `admin/`, etc. las heredan sin repetir código.
- **Pendiente de implementar:** llamar a `applySecurityHeaders()` al final de `config.php`.

---

## 19. SÉPTIMA RONDA — NUEVAS DUDA / MEJORAS (seguridad en APIs restantes + tools)

### 19.1 Hallazgos

1. **`api/toggle_ingredient.php` no verifica que `ingrediente_id` pertenezca al `menu_id`.** Un usuario autenticado podría marcar como "comprado" un ingrediente arbitrario (por ID) en su propio menú, aunque ese ingrediente no esté en el menú. Fallo de integridad (no de seguridad crítica, pues está atado a su `id_menu`).
2. **`admin/tools.php` usa `requireAdmin()`** en lugar de `requireRole(['admin','colaborador'])` (Decisión 28 pendiente de aplicar en las 3 páginas de catálogo).
3. **Las APIs AJAX no validan `X-Requested-With`** ni origen adicional; CSRF+sesión bastan para WebView, pero se podría añadir defensa en profundidad.
4. **`remove_from_effective.php`** devuelve éxito aunque el hueco ya estuviera vacío (silencia el caso). Aceptable, pero se podría distinguir.

### Decisión 39 — Validar pertenencia de ingrediente al menú en `toggle_ingredient` (resuelve 19.1.1)
- **Contexto:** `toggle_ingredient.php` marca `ingredientes_comprados` por `id_menu`+`id_ingrediente` sin comprobar que el ingrediente esté realmente en el menú efectivo.
- **Decisión:** antes de insertar/actualizar, verificar con un `JOIN` que el `id_ingrediente` exista en `menu_dias→recetas_ingredientes` para ese `id_menu`; si no, rechazar con "Ingrediente no pertenece a este menú". Refuerza integridad.
- **Pendiente de implementar:** añadir la comprobación en `api/toggle_ingredient.php`.

### Decisión 40 — Aplicar `requireRole` en `admin/tools.php` (refuerza 28)
- **Decisión:** sustituir `requireAdmin()` por `requireRole(['admin','colaborador'])` en `admin/tools.php` (igual que recipes/ingredients, Decisión 28).
- **Pendiente de implementar:** ajustar `admin/tools.php`.

### Decisión 41 — Defensa en profundidad en APIs AJAX (resuelve 19.1.3)
- **Decisión:** añadir validación de cabecera `X-Requested-With: XMLHttpRequest` en los endpoints API (opcional, no bloqueante para WebView) como capa extra sobre CSRF. Se documenta como mejora; no es obligatoria.
- **Pendiente de implementar:** opcional; check en `api/*.php` o helper `requireAjax()`.

### Decisión 42 — Distinguir hueco ya vacío en `remove_from_effective` (resuelve 19.1.4)
- **Decisión:** `removePlato()` puede devolver si no había plato; la API distinguirá "eliminado" vs "ya vacío" para feedback correcto. Menor.
- **Pendiente de implementar:** ajustar respuesta en `api/remove_from_effective.php` según resultado de `removePlato()`.

---

## 20. OCTAVA RONDA — REVISIÓN DE COHESIÓN ESTRUCTURAL Y SEGURIDAD

### 20.1 Hallazgos

1. **🔴 CRÍTICO: `config/config.php` sigue con secretos reales hardcodeados.** Líns. 24-27 tienen `DB_USER='inivi_tXU5o0w'` y `DB_PASS='wbf^7R0q51#8v6FB!xsR4BaE07s*1EQpA'` en claro; lín. 88 `DEBUG_MODE=true`; lín. 47 `$basePath='/f00dlist/'`. **La Decisión 10 (env vars) está documentada como "resuelta" pero NO implementada en el código.** El repo sigue exponiendo credenciales.
2. **`perfil.php` no valida `restriccion_dietetica` contra whitelist.** Acepta cualquier string del POST (p.ej. `admin'--`). Aunque se guarda como texto, el generador luego hace `switch`/comparación; valor arbitrario = dieta no aplicada silenciosamente. Validación de entrada ausente.
3. **`logout.php` es GET sin CSRF.** El header enlaza `<a href="logout.php">`; un atacante puede forzar logout con `<img src="logout.php">` (CSRF de logout). Baja severidad pero real.
4. **`functions.php` tiene código muerto:** `formatMoney()`, `formatDateES()`, `formatDateTimeES()` no se usan en ningún sitio revisado. `arrayGet()` tiene lógica rota (comprueba `$array[$key]` antes de explotar la notación punteada, así que el dot-path nunca funciona). `old()` devuelve `$_POST[$name]` sin `sanitize()` (depende de que el caller lo escape).
5. **Cohesión: `config.php` mezcla configuración + bootstrap (session_start) + helpers (`asset()`/`url()`).** Aceptable para app pequeña, pero rompe la separación; si se carga 2 veces (header + page) usa `defined('APP_NAME')` para evitar redefinir constantes, pero las `ini_set` de sesión y `session_start()` se ejecutan solo una vez por el guard.

### Decisión 43 — Implementar de verdad la Decisión 10 (CRÍTICO) (refuerza 10)
- **Contexto:** el repo aún expone credenciales BD reales, `DEBUG_MODE=true` y base path hardcodeada, contradiciendo la Decisión 10 que dice "migrar a env vars".
- **Decisión:** reescribir `config/config.php` para leer `DB_HOST/DB_NAME/DB_USER/DB_PASS/DEBUG_MODE/BASE_PATH` desde `getenv()` con fallbacks seguros (sin contraseña real en el repo). Rotar la contraseña expuesta en el servidor. Poner `DEBUG_MODE=false` por defecto. Derivar `BASE_URL` de `$_SERVER` + `BASE_PATH` desde env (default `/`). Añadir `.env.example` y `.gitignore` para secretos.
- **✅ IMPLEMENTADO:** `config.php` reescrito con `getenv()`; `DB_PASS` leída de `F00DLIST_DB_PASS` (sin hardcode); `DEBUG_MODE` desde `F00DLIST_DEBUG`; `BASE_URL` derivado de `$_SERVER`+`F00DLIST_BASE_PATH`; `applySecurityHeaders()` añadido (Decisión 38). Creados `.env.example` y `.gitignore`. **Pendiente:** rotar la contraseña real expuesta en el servidor de producción y definir las env vars allí.

### Decisión 44 — Validar `restriccion_dietetica` en `perfil.php` (resuelve 20.1.2)
- **Decisión:** en `perfil.php` validar que `$restriccionInput` esté en `['normal','vegetariano','vegan','celiaco','sin_lactosa']` antes de guardar; si no, usar `'normal'`. Evita entrada arbitraria.
- **✅ IMPLEMENTADO:** `perfil.php` valida contra whitelist con `in_array(..., true)` antes de guardar.

### Decisión 45 — Logout por POST con token (resuelve 20.1.3)
- **Decisión:** cambiar el enlace de logout a un formulario POST con CSRF (o token en GET validado). Así se previene el logout forzado por CSRF. Opcional si se considera riesgo bajo, pero se recomienda.
- **✅ IMPLEMENTADO:** `logout.php` exige POST + `validateCSRFToken`; el enlace de `header.php` es ahora un `<form method="POST">` con `csrfField()` y botón "Salir".

### Decisión 46 — Limpiar `functions.php` (resuelve 20.1.4)
- **Decisión:** eliminar funciones muertas (`formatMoney`, `formatDateES`, `formatDateTimeES`) o usarlas; corregir `arrayGet()` para soportar dot-path realmente; documentar que `old()` requiere `sanitize()` en el caller.
- **Pendiente de implementar:** limpiar `functions.php`.

### Decisión 47 — Separar bootstrap de config (resuelve 20.1.5)
- **Decisión:** mover `session_start()`, `date_default_timezone_set` y los helpers `asset()`/`url()` a un `bootstrap.php` o dejarlos en `config.php` pero documentar que es el punto de arranque único. Se mantiene en `config.php` por simplicidad, pero se anota.
- **Pendiente de implementar:** ninguno obligatorio; documentar en ANALYSIS.
