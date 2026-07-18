<?php
/**
 * CLASE INGREDIENT (INGREDIENTES)
 * ================================
 * Gestiona el catálogo de ingredientes, cálculos nutricionales y relaciones.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

class Ingredient {
    private $id;
    private $nombre;
    private $caloriasX100g;
    private $supermercado;
    private $categoria;
    private $activo;

    // ========================================================================
    // CONSTRUCTOR Y GETTERS/SETTERS
    // ========================================================================

    public function __construct($id = null, $nombre = null, $calorias = 0.0, $supermercado = 'General', $categoria = 'general', $activo = true) {
        if ($id !== null) {
            $this->loadById($id);
        } else {
            $this->id = null;
            $this->nombre = $nombre;
            $this->caloriasX100g = $calorias;
            $this->supermercado = $supermercado;
            $this->categoria = $categoria;
            $this->activo = $activo;
        }
    }

    public function getId() { return $this->id; }
    public function getNombre() { return $this->nombre; }
    public function getCaloriasX100g() { return $this->caloriasX100g; }
    public function getSupermercado() { return $this->supermercado; }
    public function getCategoria() { return $this->categoria; }
    public function isActivo() { return $this->activo; }

    public function setNombre($nombre) { $this->nombre = $nombre; }
    public function setCaloriasX100g($calorias) { $this->caloriasX100g = $calorias; }
    public function setSupermercado($supermercado) { $this->supermercado = $supermercado; }
    public function setCategoria($categoria) { $this->categoria = $categoria; }
    public function setActivo($activo) { $this->activo = $activo; }

    // ========================================================================
    // MÉTODOS DE CARGA (READ)
    // ========================================================================

    /**
     * Cargar datos de la BD por ID
     */
    private function loadById($id) {
        $sql = "SELECT * FROM ingredientes WHERE id = ?";
        $data = fetchOne($sql, [$id]);
        
        if ($data) {
            $this->id = $data['id'];
            $this->nombre = $data['nombre'];
            $this->caloriasX100g = (float)$data['calorias_x_100g'];
            $this->supermercado = $data['supermercado'];
            $this->categoria = $data['categoria'];
            $this->activo = (bool)$data['activo'];
            return true;
        }
        return false;
    }

    /**
     * Obtener todos los ingredientes activos
     * @return array
     */
    public static function getAllActive() {
        $sql = "SELECT * FROM ingredientes WHERE activo = 1 ORDER BY nombre ASC";
        return fetchAll($sql);
    }

    /**
     * Buscar ingredientes por nombre (búsqueda parcial)
     * @param string $term
     * @return array
     */
    public static function searchByName($term) {
        $sql = "SELECT * FROM ingredientes WHERE nombre LIKE ? AND activo = 1 ORDER BY nombre ASC";
        return fetchAll($sql, ['%' . $term . '%']);
    }

    /**
     * Obtener ingrediente por nombre exacto (útil para autocompletado)
     * @param string $nombre
     * @return array|null
     */
    public static function findByName($nombre) {
        $sql = "SELECT * FROM ingredientes WHERE nombre = ? AND activo = 1";
        return fetchOne($sql, [$nombre]);
    }

    // ========================================================================
    // CÁLCULOS NUTRICIONALES
    // ========================================================================

    /**
     * Calcular calorías totales para una cantidad dada
     * @param float $cantidad
     * @param string $unidad (g, kg, ml, unid, etc.)
     * @return float
     */
    public function calculateCalories($cantidad, $unidad = 'g') {
        if ($this->caloriasX100g <= 0) return 0.0;

        $gramos = 0;
        
        // Convertir a gramos según unidad
        switch (strtolower($unidad)) {
            case 'kg':
                $gramos = $cantidad * 1000;
                break;
            case 'l':
            case 'ml':
                // Asumimos densidad 1g/ml para líquidos (aproximación)
                // Para mayor precisión, se podría tener un campo 'densidad' en la BD
                $gramos = $cantidad; 
                break;
            case 'unid':
            case 'unidad':
                // Si es por unidad, asumimos un peso promedio (ej: 1 huevo = 50g)
                // Esto es una simplificación. Lo ideal sería tener un peso promedio por ingrediente.
                // Por ahora, si es 'unid', usamos la cantidad como gramos (o ajustar según necesidad)
                // Para este ejemplo, asumiremos que 'unid' se trata como gramos si no hay peso definido
                // O mejor: si es 'unid', devolvemos 0 o requerimos un peso promedio.
                // Simplificación: tratamos 'unid' como gramos para el cálculo base
                $gramos = $cantidad * 50; // Promedio arbitrario de 50g por unidad (ej: huevo)
                break;
            default:
                $gramos = $cantidad;
        }

        // Fórmula: (gramos * calorias_x_100g) / 100
        return ($gramos * $this->caloriasX100g) / 100;
    }

    // ========================================================================
    // MÉTODOS DE RELACIÓN CON RECETAS
    // ========================================================================

    /**
     * Obtener IDs de recetas que utilizan ESTE ingrediente
     * @return array
     */
    public function getRecipeIds() {
        if (!$this->id) return [];
        
        $sql = "SELECT id_receta FROM recetas_ingredientes WHERE id_ingrediente = ?";
        $results = fetchAll($sql, [$this->id]);
        return array_column($results, 'id_receta');
    }

    /**
     * Verificar si una receta específica usa este ingrediente
     * @param int $recipeId
     * @return bool
     */
    public function isUsedByRecipe($recipeId) {
        if (!$this->id) return false;
        
        $sql = "SELECT COUNT(*) as count FROM recetas_ingredientes 
                WHERE id_ingrediente = ? AND id_receta = ?";
        $result = fetchOne($sql, [$this->id, $recipeId]);
        return $result['count'] > 0;
    }

    /**
     * Obtener la cantidad y unidad de este ingrediente en una receta específica
     * @param int $recipeId
     * @return array|null ['cantidad' => float, 'unidad' => string]
     */
    public function getQuantityInRecipe($recipeId) {
        if (!$this->id) return null;
        
        $sql = "SELECT cantidad, unidad FROM recetas_ingredientes 
                WHERE id_ingrediente = ? AND id_receta = ?";
        return fetchOne($sql, [$this->id, $recipeId]);
    }

    // ========================================================================
    // MÉTODOS DE GUARDADO (CREATE/UPDATE)
    // ========================================================================

    /**
     * Guardar el ingrediente (Crear o Actualizar)
     * @return bool
     */
    public function save() {
        try {
            if ($this->id) {
                // UPDATE
                $sql = "UPDATE ingredientes SET nombre = ?, calorias_x_100g = ?, supermercado = ?, categoria = ?, activo = ? WHERE id = ?";
                $success = executeQuery($sql, [$this->nombre, $this->caloriasX100g, $this->supermercado, $this->categoria, $this->activo, $this->id]);
            } else {
                // INSERT
                // Verificar duplicados
                $checkSql = "SELECT id FROM ingredientes WHERE nombre = ?";
                if (fetchOne($checkSql, [$this->nombre])) {
                    throw new Exception("Ya existe un ingrediente con ese nombre.");
                }

                $sql = "INSERT INTO ingredientes (nombre, calorias_x_100g, supermercado, categoria, activo) VALUES (?, ?, ?, ?, ?)";
                executeQuery($sql, [$this->nombre, $this->caloriasX100g, $this->supermercado, $this->categoria, $this->activo]);
                $this->id = getLastInsertId();
                $success = true;
            }
            return $success;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error al guardar ingrediente: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Eliminar el ingrediente (Soft delete: desactivar)
     * @return bool
     */
    public function delete() {
        if (!$this->id) return false;
        
        // Verificar si está en uso en recetas activas (opcional, para evitar errores)
        // Si hay recetas que lo usan, no se puede borrar físicamente (RESTRICT en FK)
        // Así que solo desactivamos.
        $sql = "UPDATE ingredientes SET activo = 0 WHERE id = ?";
        return executeQuery($sql, [$this->id]);
    }

    /**
     * Asignar este ingrediente a una receta con cantidad y unidad
     * @param int $recipeId
     * @param float $cantidad
     * @param string $unidad
     * @return bool
     */
    public function assignToRecipe($recipeId, $cantidad, $unidad = 'g') {
        if (!$this->id) return false;
        
        // Verificar si ya existe
        if ($this->isUsedByRecipe($recipeId)) {
            // Actualizar cantidad si ya existe
            $sql = "UPDATE recetas_ingredientes SET cantidad = ?, unidad = ? WHERE id_receta = ? AND id_ingrediente = ?";
            return executeQuery($sql, [$cantidad, $unidad, $recipeId, $this->id]);
        }
        
        // Insertar nueva relación
        $sql = "INSERT INTO recetas_ingredientes (id_receta, id_ingrediente, cantidad, unidad) VALUES (?, ?, ?, ?)";
        return executeQuery($sql, [$recipeId, $this->id, $cantidad, $unidad]);
    }

    /**
     * Desasignar este ingrediente de una receta
     * @param int $recipeId
     * @return bool
     */
    public function unassignFromRecipe($recipeId) {
        if (!$this->id) return false;
        
        $sql = "DELETE FROM recetas_ingredientes WHERE id_receta = ? AND id_ingrediente = ?";
        return executeQuery($sql, [$recipeId, $this->id]);
    }

    // ========================================================================
    // MÉTODOS ESTÁTICOS DE UTILIDAD
    // ========================================================================

    /**
     * Obtener lista de ingredientes que un usuario debe evitar (por preferencias)
     * @param int $userId
     * @return array Array de IDs de ingredientes
     */
    public static function getAvoidedList($userId) {
        $sql = "SELECT valor FROM preferencias_usuario WHERE usuario_id = ? AND clave = 'ingredientes_avitar'";
        $row = fetchOne($sql, [$userId]);
        
        if ($row && !empty($row['valor'])) {
            $ids = json_decode($row['valor'], true);
            return is_array($ids) ? $ids : [];
        }
        return [];
    }

    /**
     * Obtener lista de ingredientes necesarios para un menú específico
     * @param int $menuId
     * @return array
     */
    public static function getShoppingListByMenu($menuId) {
        $sql = "SELECT i.id, i.nombre, i.supermercado, SUM(ri.cantidad) as total_cantidad, ri.unidad
                FROM menu_dias md
                JOIN platos p ON md.id_plato = p.id
                JOIN recetas r ON p.id = r.id_plato
                JOIN recetas_ingredientes ri ON r.id = ri.id_receta
                JOIN ingredientes i ON ri.id_ingrediente = i.id
                WHERE md.id_menu = ? AND i.activo = 1
                GROUP BY i.id, i.nombre, i.supermercado, ri.unidad
                ORDER BY i.supermercado, i.nombre";
        
        $ingredients = fetchAll($sql, [$menuId]);
        
        $compradosSql = "SELECT id_ingrediente FROM ingredientes_comprados WHERE id_menu = ? AND comprado = 1";
        $comprados = fetchAll($compradosSql, [$menuId]);
        $compradosIds = array_column($comprados, 'id_ingrediente');

        foreach ($ingredients as &$ing) {
            $ing['comprado'] = in_array($ing['id'], $compradosIds);
        }
        return $ingredients;
    }
}
?>