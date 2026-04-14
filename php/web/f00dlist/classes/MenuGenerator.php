<?php
/**
 * CLASE MENU GENERATOR (GENERADOR DE MENÚS)
 * ==========================================
 * Motor de generación de menús con restricciones complejas.
 * Aplica reglas de negocio: distancias mínimas, exclusión de herramientas/ingredientes,
 * lógica de comensales (exclusivo vs compartido), y cálculo de huecos libres.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Recipe.php';
require_once __DIR__ . '/Menu.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Ingredient.php';

class MenuGenerator {
    private $userId;
    private $numDias;
    private $excludedToolIds = [];
    private $comensalIds = [];
    private $availableRecipes = [];
    private $usedPlatoIds = []; // Para evitar repetidos en el mismo menú
    private $lastUsageDates = []; // Para controlar distancias mínimas (plato_id => ultimo_dia_usado)
    
    // Cache de restricciones por tipo de plato
    private $minDistance = [
        'pasta' => DISTANCIA_PASTA,
        'fajitas' => DISTANCIA_FAJITAS,
        'tortilla' => DISTANCIA_TORTILLAS,
        'crema' => DISTANCIA_CREMAS
    ];

    public function __construct($userId, $numDias, $excludedToolIds = [], $comensalIds = []) {
        $this->userId = $userId;
        $this->numDias = max(MIN_DIAS_GENERACION, min($numDias, MAX_DIAS_GENERACION));
        $this->excludedToolIds = $excludedToolIds;
        $this->comensalIds = $comensalIds;
    }

    /**
     * Generar el menú tentativo
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function generate() {
        try {
            // 1. Preparar datos
            $this->prepareData();
            
            // 2. Filtrar recetas disponibles según restricciones
            $candidates = $this->filterCandidates();
            
            if (empty($candidates)) {
                return ['success' => false, 'message' => 'No hay suficientes recetas disponibles con las restricciones seleccionadas.'];
            }

            // 3. Generar el menú día a día
            $menuData = [];
            $pescadoCount = 0;
            
            for ($dia = 1; $dia <= $this->numDias; $dia++) {
                $menuData[$dia] = ['comida' => null, 'cena' => null];
                
                // Asignar Comida
                $platoComida = $this->selectBestRecipe($dia, MOMENTO_COMIDA, $candidates, $pescadoCount);
                if ($platoComida) {
                    $menuData[$dia][MOMENTO_COMIDA] = $platoComida;
                    $this->markAsUsed($platoComida['id_plato'], $dia);
                    if ($this->isPescado($platoComida['id_plato'])) {
                        $pescadoCount++;
                    }
                }

                // Asignar Cena
                $platoCena = $this->selectBestRecipe($dia, MOMENTO_CENA, $candidates, $pescadoCount);
                if ($platoCena) {
                    $menuData[$dia][MOMENTO_CENA] = $platoCena;
                    $this->markAsUsed($platoCena['id_plato'], $dia);
                    if ($this->isPescado($platoCena['id_plato'])) {
                        $pescadoCount++;
                    }
                }
            }

            // 4. Verificar regla de pescado (al menos 1 cada 7 días)
            // Si no se cumplió, intentar forzar un pescado en los días faltantes
            if ($pescadoCount < ceil($this->numDias / PESCADO_CADA_X_DIAS)) {
                $this->forcePescado($menuData, $candidates);
            }

            return ['success' => true, 'message' => 'Menú generado correctamente.', 'data' => $menuData];

        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error en generación: " . $e->getMessage());
            }
            return ['success' => false, 'message' => 'Error al generar el menú: ' . $e->getMessage()];
        }
    }

    // ========================================================================
    // PREPARACIÓN DE DATOS
    // ========================================================================

    private function prepareData() {
        // Obtener preferencias de todos los comensales
        $avoidedIngredients = [];
        $dietRestrictions = [];
        $exclusivePlatos = []; // plato_id => [user_id]
        $preferredPlatos = [];

        foreach ($this->comensalIds as $userId) {
            // Ingredientes a evitar
            $avoided = Ingredient::getAvoidedList($userId);
            $avoidedIngredients = array_merge($avoidedIngredients, $avoided);
            
            // Restricciones dietéticas
            $sql = "SELECT valor FROM preferencias_usuario WHERE usuario_id = ? AND clave = 'restriccion_dietetica'";
            $row = fetchOne($sql, [$userId]);
            if ($row && !empty($row['valor'])) {
                $dietRestrictions[] = $row['valor'];
            }

            // Platos exclusivos
            $sql = "SELECT valor FROM preferencias_usuario WHERE usuario_id = ? AND clave = 'platos_exclusivos'";
            $row = fetchOne($sql, [$userId]);
            if ($row && !empty($row['valor'])) {
                $exclusivos = json_decode($row['valor'], true);
                if (is_array($exclusivos)) {
                    foreach ($exclusivos as $platoId) {
                        if (!isset($exclusivePlatos[$platoId])) {
                            $exclusivePlatos[$platoId] = [];
                        }
                        $exclusivePlatos[$platoId][] = $userId;
                    }
                }
            }

            // Platos preferidos
            $sql = "SELECT valor FROM preferencias_usuario WHERE usuario_id = ? AND clave = 'platos_preferidos'";
            $row = fetchOne($sql, [$userId]);
            if ($row && !empty($row['valor'])) {
                $preferidos = json_decode($row['valor'], true);
                if (is_array($preferidos)) {
                    $preferredPlatos = array_merge($preferredPlatos, $preferidos);
                }
            }
        }

        // Unificar y limpiar arrays
        $this->avoidedIngredients = array_unique($avoidedIngredients);
        $this->dietRestrictions = array_unique($dietRestrictions);
        $this->exclusivePlatos = $exclusivePlatos;
        $this->preferredPlatos = array_unique($preferredPlatos);
    }

    // ========================================================================
    // FILTRADO DE CANDIDATOS
    // ========================================================================

    private function filterCandidates() {
        // Obtener todas las recetas activas con datos del plato
        $sql = "SELECT r.*, p.nombre as plato_nombre, p.nivel_calorico, p.es_comida, p.es_cena, p.categoria
                FROM recetas r
                JOIN platos p ON r.id_plato = p.id
                WHERE p.activo = 1";
        
        $allRecipes = fetchAll($sql);
        $candidates = [];

        foreach ($allRecipes as $recipe) {
            $recipeObj = new Recipe($recipe['id']);
            
            // 1. Filtrar por herramientas excluidas
            if ($recipeObj->usesExcludedTools($this->excludedToolIds)) {
                continue;
            }

            // 2. Filtrar por ingredientes prohibidos
            if ($recipeObj->containsAvoidedIngredients($this->avoidedIngredients)) {
                continue;
            }

            // 3. Filtrar por restricciones dietéticas (si aplica)
            if (!empty($this->dietRestrictions)) {
                $compatible = true;
                foreach ($this->dietRestrictions as $restriccion) {
                    if (!$recipeObj->isCompatibleWithDiet($restriccion)) {
                        $compatible = false;
                        break;
                    }
                }
                if (!$compatible) continue;
            }

            // 4. Filtrar por momento (comida/cena)
            // Si el plato no es para comida y estamos buscando comida, saltar
            // (Nota: un plato puede ser para ambos, o solo uno)
            // En la BD: es_comida y es_cena son booleanos.
            // Si estamos generando comida, el plato debe tener es_comida=1.
            // Si estamos generando cena, el plato debe tener es_cena=1.
            // Esto se maneja en selectBestRecipe, no aquí.

            $candidates[] = $recipe;
        }

        return $candidates;
    }

    // ========================================================================
    // SELECCIÓN DEL MEJOR PLATO
    // ========================================================================

    private function selectBestRecipe($dia, $momento, $candidates, &$pescadoCount) {
        $filtered = [];
        
        foreach ($candidates as $recipe) {
            // Verificar si el plato sirve para este momento
            if ($momento === MOMENTO_COMIDA && !$recipe['es_comida']) continue;
            if ($momento === MOMENTO_CENA && !$recipe['es_cena']) continue;

            $platoId = $recipe['id_plato'];

            // 1. Verificar si ya se usó este plato en el menú actual
            if (in_array($platoId, $this->usedPlatoIds)) {
                // Excepción: Algunos platos pueden repetirse si es necesario, pero la regla dice "No repetir"
                // Por ahora, saltamos si ya se usó.
                continue;
            }

            // 2. Verificar distancias mínimas
            if (!$this->checkDistance($platoId, $dia)) {
                continue;
            }

            // 3. Verificar lógica de comensales (exclusivo vs compartido)
            // Si el plato es exclusivo de alguien que NO está en los comensales seleccionados, saltar.
            if (isset($this->exclusivePlatos[$platoId])) {
                $allowedUsers = $this->exclusivePlatos[$platoId];
                $valid = false;
                foreach ($allowedUsers as $uid) {
                    if (in_array($uid, $this->comensalIds)) {
                        $valid = true;
                        break;
                    }
                }
                if (!$valid) continue;
            }

            $filtered[] = $recipe;
        }

        if (empty($filtered)) {
            return null;
        }

        // Priorizar platos favoritos
        $prioritized = [];
        $nonPrioritized = [];
        
        foreach ($filtered as $recipe) {
            if (in_array($recipe['id_plato'], $this->preferredPlatos)) {
                $prioritized[] = $recipe;
            } else {
                $nonPrioritized[] = $recipe;
            }
        }

        $pool = !empty($prioritized) ? $prioritized : $nonPrioritized;
        
        // Selección aleatoria del pool
        $randomIndex = array_rand($pool);
        return $pool[$randomIndex];
    }

    // ========================================================================
    // REGLAS DE DISTANCIA
    // ========================================================================

    private function checkDistance($platoId, $currentDia) {
        if (!isset($this->lastUsageDates[$platoId])) {
            return true; // No se ha usado antes
        }

        $lastDay = $this->lastUsageDates[$platoId];
        $diff = $currentDia - $lastDay;

        // Determinar tipo de plato para aplicar la regla correcta
        $sql = "SELECT categoria FROM platos WHERE id = ?";
        $row = fetchOne($sql, [$platoId]);
        $categoria = strtolower($row['categoria'] ?? '');

        $minDays = 0;
        
        if (strpos($categoria, 'pasta') !== false) {
            $minDays = $this->minDistance['pasta'];
        } elseif (strpos($categoria, 'fajita') !== false) {
            $minDays = $this->minDistance['fajitas'];
        } elseif (strpos($categoria, 'tortilla') !== false) {
            $minDays = $this->minDistance['tortilla'];
        } elseif (strpos($categoria, 'crema') !== false || strpos($categoria, 'sopa') !== false) {
            $minDays = $this->minDistance['crema'];
        }

        if ($minDays > 0 && $diff < $minDays) {
            return false;
        }

        return true;
    }

    private function markAsUsed($platoId, $dia) {
        $this->usedPlatoIds[] = $platoId;
        $this->lastUsageDates[$platoId] = $dia;
    }

    // ========================================================================
    // FORZAR PESCADOS
    // ========================================================================

    private function forcePescado(&$menuData, $candidates) {
        // Buscar días sin pescado y asignar uno
        for ($dia = 1; $dia <= $this->numDias; $dia++) {
            // Verificar si ya hay pescado en este día
            $hasPescado = false;
            if ($menuData[$dia][MOMENTO_COMIDA] && $this->isPescado($menuData[$dia][MOMENTO_COMIDA]['id_plato'])) {
                $hasPescado = true;
            }
            if ($menuData[$dia][MOMENTO_CENA] && $this->isPescado($menuData[$dia][MOMENTO_CENA]['id_plato'])) {
                $hasPescado = true;
            }

            if (!$hasPescado) {
                // Buscar un pescado disponible
                $pescado = $this->findPescado($candidates, $dia);
                if ($pescado) {
                    // Asignar a comida si está libre, si no a cena
                    if (!$menuData[$dia][MOMENTO_COMIDA]) {
                        $menuData[$dia][MOMENTO_COMIDA] = $pescado;
                    } elseif (!$menuData[$dia][MOMENTO_CENA]) {
                        $menuData[$dia][MOMENTO_CENA] = $pescado;
                    }
                    $this->markAsUsed($pescado['id_plato'], $dia);
                    return; // Ya forzamos uno, salir
                }
            }
        }
    }

    private function findPescado($candidates, $dia) {
        foreach ($candidates as $recipe) {
            if ($this->isPescado($recipe['id_plato'])) {
                if (!in_array($recipe['id_plato'], $this->usedPlatoIds) && $this->checkDistance($recipe['id_plato'], $dia)) {
                    return $recipe;
                }
            }
        }
        return null;
    }

    private function isPescado($platoId) {
        $sql = "SELECT categoria FROM platos WHERE id = ?";
        $row = fetchOne($sql, [$platoId]);
        $categoria = strtolower($row['categoria'] ?? '');
        return strpos($categoria, 'pescado') !== false || strpos($categoria, 'pesca') !== false;
    }
}
?>