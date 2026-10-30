# CONTEXT: f00dlist
## Tech Stack
- Frontend: Bootstrap 5.3 + CSS propio (responsive, mobile-first)
- Backend: PHP 7+ (POO, PDO, sin frameworks MVC)
- DB: MariaDB/MySQL (inivi_f00dlist, utf8mb4)
- Mobile: Cordova (rutas relativas, no /css)

## Project Status
- Done: Auth+RBAC, generador menús, favoritos, lista compra, PDF, APIs AJAX, admin CRUD, migración Bootstrap 5.3 CDN (header/footer/login/register OK), D50 (favoritos/perfil/receta/admin a Bootstrap), responsive.css + caloric.css creados
- Next: D8-D9 (datalists, etiquetas), D16-D47 (bugs, seguridad), eliminar CSS específicos obsoletos

## Decision Log
### D43-D45 - Seguridad (env vars, dieta whitelist, logout POST)
- ✅ Implementado

### D49 - Bootstrap 5.3 CDN
- Context: Estilos propios no cargaban correctamente
- Action: Añadir Bootstrap CSS en head + JS antes /body. Reescribir nav (navbar-dark), footer, login, register con clases Bootstrap. header.php y footer.php como layout compartido (require_once)
- Why: Recuperar estilos rápidamente, responsive gratuito, navbar funcional
- Impact: 4 PHP modificados (header, footer, login, register). Pendiente admin/*.php
- ✅ Completado

### D50 - Migración CSS a Bootstrap (favoritos, perfil, receta, admin)
- Context: favoritos.php, perfil.php, receta.php sin header.php (HTML manual). admin/*.php con estilos inline masivos. responsive.css y caloric.css NO EXISTÍAN (referencias rotas en header.php)
- Action: 
  1. Crear responsive.css (tablas responsive, grids, flex, containers, botones, navbar, cards, scroll-container)
  2. Crear caloric.css (badges, indicadores, leyendas de calorías)
  3. Migrar favoritos.php → header.php + Bootstrap (cards grid, modal Bootstrap)
  4. Migrar perfil.php → header.php + Bootstrap (form-check, form-select, cards)
  5. Migrar receta.php → header.php + Bootstrap (cards, list-unstyled)
  6. Migrar admin/index.php → Bootstrap (cards grid, estadísticas)
- Why: Eliminar estilos inline, CSS duplicados, inconsistencias. Bootstrap proporciona responsive nativo + componentes reutilizables. Cordova compatible (rutas relativas)
- Impact: 6 archivos reescritos. CSS específicos (login.css, register.css, favoritos.css, perfil.css, receta.css) ahora obsoletos pero aún referenciados
- ✅ Completado (favoritos, perfil, receta, admin/index)
- ⏳ Pendiente: admin/recipes.php, admin/ingredients.php, admin/tools.php, admin/users.php

## Checklist
- [x] D43-D45: Seguridad (env vars, dieta whitelist, logout POST)
- [x] D49: Bootstrap 5.3 CDN + header/footer compartido + login/register Bootstrap
- [x] D50: Crear responsive.css + caloric.css
- [x] D50: Migrar favoritos.php, perfil.php, receta.php, admin/index.php a Bootstrap
- [ ] D50: Migrar admin/recipes.php, admin/ingredients.php, admin/tools.php, admin/users.php a Bootstrap
- [ ] D50: Eliminar CSS específicos obsoletos (login.css, register.css, favoritos.css, perfil.css, receta.css)
- [ ] D8-D9: Datalists categoría/etiquetas
- [ ] D16-D47: Bugs + seguridad restantes
