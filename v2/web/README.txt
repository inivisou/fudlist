===========================================================
GENERADOR DE MENÚS SEMANALES – DOCUMENTACIÓN DEL PROYECTO
===========================================================

1. DESCRIPCIÓN GENERAL
----------------------
Este proyecto es una aplicación web en PHP que genera menús semanales
(comida y cena) basados exclusivamente en ficheros JSON. No utiliza
base de datos ni frameworks. Toda la lógica de negocio está definida
en PHP y JavaScript moderno (Fetch API), y el menú efectivo vive
únicamente en cliente.

El usuario puede:
- Generar un menú tentativo cumpliendo restricciones.
- Seleccionar platos para construir su menú efectivo.
- Ver una lista dinámica de ingredientes.
- Generar un PDF con el menú efectivo y los ingredientes únicos.


2. TECNOLOGÍAS UTILIZADAS
-------------------------
- PHP 7+ sin frameworks.
- JSON como única fuente de datos.
- JavaScript moderno (Fetch API).
- jsPDF + autotable para generación de PDF en cliente.
- HTML5 + CSS3.
- Accesibilidad WCAG 2.2.
- Diseño responsive.


3. ESTRUCTURA DE FICHEROS
-------------------------
/
|-- index.php                      # Página principal
|-- receta.php                     # Vista de receta individual
|
|-- data/
|   |-- platos.json                # Platos disponibles
|   |-- recetas.json               # Recetas completas
|   |-- ingredientes.json          # Ingredientes y supermercados
|
|-- assets/
|   |-- css/
|   |   |-- styles.css             # Estilos generales
|   |
|   |-- js/
|       |-- app.js                 # Lógica cliente
|
|-- lib/
|   |-- pdf/
|       |-- jspdf.umd.min.js       # Librería PDF
|       |-- jspdf.plugin.autotable.js
|
|-- partials/
|   |-- header.php                 # Cabecera HTML
|   |-- footer.php                 # Pie HTML
|
|-- api/
|   |-- ingredientes.php           # Resolución de supermercados (opcional)
|
|-- README.txt                     # Este documento


4. FLUJO FUNCIONAL
------------------

4.1 Menú Tentativo (PHP)
- Se genera al pulsar "Generar".
- Aplica filtros de herramientas.
- Aplica restricciones de tipo (pasta, fajitas, tortillas, cremas, pescado).
- Aplica lógica de personas (Eme/Cris).
- Evita repeticiones salvo necesidad.
- No es editable.
- Se resetea al generar de nuevo.

4.2 Menú Efectivo (JS)
- Vive solo en cliente.
- Comienza vacío.
- Se rellena haciendo click en el menú tentativo.
- Crece dinámicamente.
- Compacta días automáticamente.
- Es editable mediante checkboxes.
- Es la base para ingredientes y PDF.

4.3 Lista Dinámica de Ingredientes
- Se actualiza en tiempo real.
- Agrupa ingredientes por nombre.
- Resuelve supermercados.
- Ordena por supermercado y nombre.

4.4 PDF
- Generado en cliente.
- Contiene:
  1. Título + fecha.
  2. Tabla del menú efectivo.
  3. Tabla de ingredientes únicos.
- No incluye cantidades.

5. ACCESIBILIDAD
----------------
- Navegación por teclado.
- Foco visible.
- Tablas accesibles.
- Colores con contraste suficiente.
- HTML semántico.

6. RESPONSIVE
-------------
- Tablas con scroll horizontal en móvil.
- Formularios adaptados.
- Botones accesibles en pantallas pequeñas.

7. DESPLIEGUE
-------------
Requisitos:
- Servidor con PHP 7+.
- Permisos de lectura para la carpeta /data.

Modo de uso:
- Colocar el proyecto en un servidor PHP.
- Acceder a /index.php desde el navegador.

8. NOTAS IMPORTANTES
--------------------
- No usa base de datos.
- No usa frameworks.
- JSON es la única fuente de verdad.
- El menú efectivo no se guarda en servidor.
- La única persistencia real es el PDF generado por el usuario.
