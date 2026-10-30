# ANALYSIS_Base.md - Plantilla para Bitácora de Decisiones

## Propósito del Fichero

Este fichero sirve como plantilla genérica para documentar decisiones de diseño, arquitectura y desarrollo en cualquier proyecto de software. Su objetivo es:

1. **Rastrear la evolución** del proyecto a través de decisiones tomadas.
2. **Justificar** cada decisión con su contexto y consecuencias.
3. **Facilitar la comunicación** entre miembros del equipo sobre cambios y motivos.
4. **Garantizar la consistencia** en la documentación de decisiones técnicas y de negocio.

## Estructura Recomendada

### 1. Introducción al Proyecto
- Descripción general del proyecto y su propósito.
- Tecnologías y stack utilizado.
- Alcance y limitaciones iniciales.

### 2. Bitácora de Decisiones
Cada decisión debe seguir este formato:

#### Decisión [NÚMERO] — [Título Breve]
- **Contexto:** ¿Cuál era el problema o la situación anterior?
- **Decisión:** Qué se decidió hacer (o no hacer).
- **Razonamiento:** Explicación detallada de por qué se tomó esta decisión.
- **Consecuencias:** Efectos positivos y negativos en el proyecto.
- **Pendientes:** Acciones futuras relacionadas con esta decisión (si aplica).

### 3. Decisiones Clave (Ejemplos)
- Decisión 1 — Enfoque de generación: ya NO se usa YAML ni un LLM con prompt
- Decisión 2 — Backend en PHP (POO, PDO)
- Decisión 3 — Base de datos MariaDB/MySQL
- etc.

### 4. Estado Actual
- Resumen del estado actual del proyecto.
- Decisiones críticas completadas.
- Decisiones pendientes o en progreso.

### 15. Checklist de Estado
- [ ] Decisiones implementadas
- [ ] Decisiones pendientes
- [ ] Tareas críticas completadas
- [ ] Tareas críticas pendientes

## Formato de Cada Decisión

### Decisión [NÚMERO] — [Título]
- **Contexto:** Descripción breve del problema o necesidad que motivó la decisión.
- **Decisión:** Enunciado claro de la decisión tomada.
- **Razonamiento:** 
  - Factores técnicos
  - Factores de negocio
  - Limitaciones de tiempo o recursos
  - Recomendaciones de buenas prácticas
- **Consecuencias:** 
  - Beneficios obtenidos
  - Posibles riesgos o desventajas
  - Impacto en otras áreas del proyecto
- **Pendientes:** Tareas futuras derivadas de esta decisión.

## Buenas Prácticas
- Mantener las decisiones concisas pero completas.
- Usar un formato consistente para todas las decisiones.
- Incluir referencias a archivos, líneas de código o tickets de proyecto cuando sea relevante.
- Actualizar la bitácora cada vez que se tome una nueva decisión o se modifique una existente.
- Usar un formato de lista de verificación (- [ ] para pendientes, - [x] para completadas) para seguir el progreso.

## Ejemplo de Decisión Completa

### Decisión 1 — Enfoque de generación: ya NO se usa YAML ni un LLM con prompt
- **Contexto:** Los primeros prompts (`Prompt.txt`, `Prompt_platos_e_ingredientes.yaml.txt`) definían un flujo donde un LLM leía un catálogo en YAML y generaba un CSV al escribir exactamente `Genera`.
- **Decisión:** Se descarta el fichero YAML y el enfoque de prompt/LLM. La aplicación será una página web normal, con la lógica de generación de menús implementada en código (PHP) y los datos persistidos en base de datos.
- **Razonamiento:** 
  - Evita dependencia de herramientas externas que pueden cambiar sin aviso.
  - Simplifica la arquitectura y reduce la complejidad del sistema.
  - Permite un control total sobre la generación de menús mediante algoritmos definidos en código.
  - Alinea el proyecto con las mejores prácticas de desarrollo web tradicional.
- **Consecuencias:** 
  - `Prompt.txt` y `Prompt_platos_e_ingredientes.yaml.txt` quedan como documentación histórica.
  - El catálogo de platos/ingredientes ahora vive en la base de datos, no en archivos de texto.
  - Se gana mayor control y seguridad sobre los datos.
- **Pendientes:** 
  - Añadir los `<datalist>` + `list=` en ambos formularios de admin y poblar `categoria` en el seed/platos existentes.

## Checklist de Uso de la Bitácora

- [ ] Documentar cada decisión relevante en el formato especificado.
- [ ] Mantener la bitácora actualizada con cada cambio significativo.
- [ ] Revisar periódicamente la bitácora para asegurar que todas las decisiones estén documentadas.
- [ ] Usar la bitácora como referencia durante revisiones de código y planificación.