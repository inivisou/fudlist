<?php
/**
 * CLASE TOOL (HERRAMIENTAS)
 * =========================
 * Gestiona el catálogo de herramientas y sus relaciones con recetas.
 * Permite filtrar recetas que requieren ciertas herramientas.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

class Tool {
    private $id;
    private $nombre;
    private $descripcion;
    private $activo;

    // ========================================================================
    // CONSTRUCTOR Y GETTERS/SETTERS
    // ========================================================================

    public function __construct($id = null, $nombre = null, $descripcion = null, $activo = true) {
        if ($id !== null) {
            $this->loadById($id);
        } else {
            $this->id = null;
            $this->nombre = $nombre;
            $this->descripcion = $descripcion;
            $this->activo = $activo;
        }
    }

    public function getId() { return $this->id; }
    public function getNombre() { return $this->nombre; }
    public function getDescripcion() { return $this->descripcion; }
    public function isActivo() { return $this->activo; }

    public function setNombre($nombre) { $this->nombre = $nombre; }
    public function setDescripcion($descripcion) { $this->descripcion = $descripcion; }
    public function setActivo($activo) { $this->activo = $activo; }

    // ========================================================================
    // MÉTODOS DE CARGA (READ)
    // ========================================================================

    /**
     * Cargar datos de la BD por ID
     */
    private function loadById($id) {
        $sql = "SELECT * FROM herramientas WHERE id = ?";
        $data = fetchOne($sql, [$id]);
        
        if ($data) {
            $this->id = $data['id'];
            $this->nombre = $data['nombre'];
            $this->descripcion = $data['descripcion'];
            $this->activo = (bool)$data['activo'];
            return true;
        }
        return false;
    }

    /**
     * Obtener todas las herramientas activas
     * @return array
     */
    public static function getAllActive() {
        $sql = "SELECT * FROM herramientas WHERE activo = 1 ORDER BY nombre ASC";
        return fetchAll($sql);
    }

    /**
     * Obtener todas las herramientas (activas e inactivas)
     * @return array
     */
    public static function getAll() {
        $sql = "SELECT * FROM herramientas ORDER BY nombre ASC";
        return fetchAll($sql);
    }

    /**
     * Buscar herramientas por nombre (búsqueda parcial)
     * @param string $term
     * @return array
     */
    public static function searchByName($term) {
        $sql = "SELECT * FROM herramientas WHERE nombre LIKE ? AND activo = 1 ORDER BY nombre ASC";
        return fetchAll($sql, ['%' . $term . '%']);
    }

    // ========================================================================
    // MÉTODOS DE RELACIÓN CON RECETAS (CRÍTICO PARA FILTRADO)
    // ========================================================================

    /**
     * Obtener IDs de recetas que utilizan ESTA herramienta
     * @return array
     */
    public function getRecipeIds() {
        if (!$this->id) return [];
        
        $sql = "SELECT id_receta FROM recetas_herramientas WHERE id_herramienta = ?";
        $results = fetchAll($sql, [$this->id]);
        return array_column($results, 'id_receta');
    }

    /**
     * Verificar si una receta específica usa esta herramienta
     * @param int $recipeId
     * @return bool
     */
    public function isUsedByRecipe($recipeId) {
        if (!$this->id) return false;
        
        $sql = "SELECT COUNT(*) as count FROM recetas_herramientas 
                WHERE id_herramienta = ? AND id_receta = ?";
        $result = fetchOne($sql, [$this->id, $recipeId]);
        return $result['count'] > 0;
    }

    // ========================================================================
    // MÉTODOS DE GUARDADO (CREATE/UPDATE)
    // ========================================================================

    /**
     * Guardar la herramienta (Crear o Actualizar)
     * @return bool
     */
    public function save() {
        try {
            if ($this->id) {
                // UPDATE
                $sql = "UPDATE herramientas SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?";
                $success = executeQuery($sql, [$this->nombre, $this->descripcion, $this->activo, $this->id]);
            } else {
                // INSERT
                // Verificar duplicados
                $checkSql = "SELECT id FROM herramientas WHERE nombre = ?";
                if (fetchOne($checkSql, [$this->nombre])) {
                    throw new Exception("Ya existe una herramienta con ese nombre.");
                }

                $sql = "INSERT INTO herramientas (nombre, descripcion, activo) VALUES (?, ?, ?)";
                executeQuery($sql, [$this->nombre, $this->descripcion, $this->activo]);
                $this->id = getLastInsertId();
                $success = true;
            }
            return $success;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error al guardar herramienta: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Eliminar la herramienta (Soft delete: desactivar)
     * @return bool
     */
    public function delete() {
        if (!$this->id) return false;
        
        // En lugar de borrar físicamente (que rompería FK), desactivamos
        $sql = "UPDATE herramientas SET activo = 0 WHERE id = ?";
        return executeQuery($sql, [$this->id]);
    }

    /**
     * Asignar esta herramienta a una receta
     * @param int $recipeId
     * @return bool
     */
    public function assignToRecipe($recipeId) {
        if (!$this->id) return false;
        
        // Verificar si ya existe
        if ($this->isUsedByRecipe($recipeId)) return true;
        
        $sql = "INSERT INTO recetas_herramientas (id_receta, id_herramienta) VALUES (?, ?)";
        return executeQuery($sql, [$recipeId, $this->id]);
    }

    /**
     * Desasignar esta herramienta de una receta
     * @param int $recipeId
     * @return bool
     */
    public function unassignFromRecipe($recipeId) {
        if (!$this->id) return false;
        
        $sql = "DELETE FROM recetas_herramientas WHERE id_receta = ? AND id_herramienta = ?";
        return executeQuery($sql, [$recipeId, $this->id]);
    }

    // ========================================================================
    // MÉTODOS ESTÁTICOS DE UTILIDAD
    // ========================================================================

    /**
     * Obtener lista de herramientas que NO se deben usar (para filtrado en generación)
     * @param array $excludedToolIds Array de IDs de herramientas a excluir
     * @return array IDs de recetas que usan alguna de esas herramientas
     */
    public static function getRecipesUsingTools($excludedToolIds) {
        if (empty($excludedToolIds)) return [];
        
        // Crear placeholders para la consulta IN
        $placeholders = implode(',', array_fill(0, count($excludedToolIds), '?'));
        
        $sql = "SELECT DISTINCT id_receta FROM recetas_herramientas 
                WHERE id_herramienta IN ($placeholders)";
        
        $results = fetchAll($sql, $excludedToolIds);
        return array_column($results, 'id_receta');
    }
}
?>