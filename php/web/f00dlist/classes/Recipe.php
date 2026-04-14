<?php
/**
 * CLASE RECIPE (RECETAS)
 * ======================
 * Gestiona las recetas, sus ingredientes, herramientas y cálculos nutricionales.
 * Es el núcleo para la generación de menús y la visualización de detalles.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Ingredient.php';
require_once __DIR__ . '/Tool.php';

class Recipe {
    private $id;
    private $idPlato;
    private $tituloHtml;
    private $subtituloHtml;
    private $textoHtml;
    private $enlace;
    
    // Relaciones cargadas lazy-load
    private $platoData = null;
    private $ingredients = null;
    private $tools = null;

    // ========================================================================
    // CONSTRUCTOR Y GETTERS/SETTERS
    // ========================================================================

    public function __construct($id = null, $idPlato = null, $titulo = '', $subtitulo = '', $texto = '', $enlace = '') {
        if ($id !== null) {
            $this->loadById($id);
        } else {
            $this->id = null;
            $this->idPlato = $idPlato;
            $this->tituloHtml = $titulo;
            $this->subtituloHtml = $subtitulo;
            $this->textoHtml = $texto;
            $this->enlace = $enlace;
        }
    }

    public function getId() { return $this->id; }
    public function getIdPlato() { return $this->idPlato; }
    public function getTituloHtml() { return $this->tituloHtml; }
    public function getSubtituloHtml() { return $this->subtituloHtml; }
    public function getTextoHtml() { return $this->textoHtml; }
    public function getEnlace() { return $this->enlace; }

    public function setTituloHtml($titulo) { $this->tituloHtml = $titulo; }
    public function setSubtituloHtml($subtitulo) { $this->subtituloHtml = $subtitulo; }
    public function setTextoHtml($texto) { $this->textoHtml = $texto; }
    public function setEnlace($enlace) { $this->enlace = $enlace; }

    // ========================================================================
    // MÉTODOS DE CARGA (READ)
    // ========================================================================

    /**
     * Cargar datos de la BD por ID
     */
    private function loadById($id) {
        $sql = "SELECT * FROM recetas WHERE id = ?";
        $data = fetchOne($sql, [$id]);
        
        if ($data) {
            $this->id = $data['id'];
            $this->idPlato = $data['id_plato'];
            $this->tituloHtml = $data['titulo_html'];
            $this->subtituloHtml = $data['subtitulo_html'];
            $this->textoHtml = $data['texto_html'];
            $this->enlace = $data['enlace'];
            return true;
        }
        return false;
    }

    /**
     * Obtener datos del plato asociado (Lazy Load)
     * @return array|null
     */
    public function getPlatoData() {
        if ($this->platoData === null && $this->idPlato) {
            $sql = "SELECT p.*, r.herramientas_json FROM platos p 
                    LEFT JOIN (
                        SELECT id_plato, GROUP_CONCAT(t.nombre SEPARATOR ', ') as herramientas_json
                        FROM platos p2
                        JOIN recetas r2 ON p2.id = r2.id_plato
                        JOIN recetas_herramientas rh ON r2.id = rh.id_receta
                        JOIN herramientas t ON rh.id_herramienta = t.id
                        GROUP BY id_plato
                    ) r ON p.id = r.id_plato
                    WHERE p.id = ?";
            $this->platoData = fetchOne($sql, [$this->idPlato]);
        }
        return $this->platoData;
    }

    /**
     * Obtener todos los ingredientes de esta receta (Lazy Load)
     * @return array Array de objetos Ingredient
     */
    public function getIngredients() {
        if ($this->ingredients === null) {
            $this->ingredients = [];
            if (!$this->id) return [];

            $sql = "SELECT i.*, ri.cantidad, ri.unidad 
                    FROM ingredientes i
                    JOIN recetas_ingredientes ri ON i.id = ri.id_ingrediente
                    WHERE ri.id_receta = ? AND i.activo = 1
                    ORDER BY i.supermercado, i.nombre";
            
            $rows = fetchAll($sql, [$this->id]);
            
            foreach ($rows as $row) {
                $ing = new Ingredient();
                $ing->setNombre($row['nombre']);
                $ing->setCaloriasX100g($row['calorias_x_100g']);
                $ing->setSupermercado($row['supermercado']);
                $ing->setCategoria($row['categoria']);
                
                // Guardamos cantidad y unidad en propiedades dinámicas para acceso fácil
                $ing->cantidad = $row['cantidad'];
                $ing->unidad = $row['unidad'];
                
                $this->ingredients[] = $ing;
            }
        }
        return $this->ingredients;
    }

    /**
     * Obtener todas las herramientas de esta receta (Lazy Load)
     * @return array Array de objetos Tool
     */
    public function getTools() {
        if ($this->tools === null) {
            $this->tools = [];
            if (!$this->id) return [];

            $sql = "SELECT t.* FROM herramientas t
                    JOIN recetas_herramientas rh ON t.id = rh.id_herramienta
                    WHERE rh.id_receta = ? AND t.activo = 1
                    ORDER BY t.nombre";
            
            $rows = fetchAll($sql, [$this->id]);
            
            foreach ($rows as $row) {
                $tool = new Tool($row['id'], $row['nombre'], $row['descripcion'], $row['activo']);
                $this->tools[] = $tool;
            }
        }
        return $this->tools;
    }

    /**
     * Obtener todas las recetas activas con datos del plato (para listas)
     * @return array
     */
    public static function getAllWithPlato() {
        $sql = "SELECT r.*, p.nombre as plato_nombre, p.nivel_calorico, p.es_comida, p.es_cena
                FROM recetas r
                JOIN platos p ON r.id_plato = p.id
                WHERE p.activo = 1
                ORDER BY p.nombre ASC";
        return fetchAll($sql);
    }

    // ========================================================================
    // CÁLCULOS NUTRICIONALES
    // ========================================================================

    /**
     * Calcular calorías totales de la receta sumando sus ingredientes
     * @return float
     */
    public function calculateTotalCalories() {
        $total = 0.0;
        $ingredients = $this->getIngredients();
        
        foreach ($ingredients as $ing) {
            $cantidad = $ing->cantidad ?? 0;
            $unidad = $ing->unidad ?? 'g';
            $total += $ing->calculateCalories($cantidad, $unidad);
        }
        
        return $total;
    }

    /**
     * Obtener resumen de calorías formateado
     * @return string
     */
    public function getCaloriesFormatted() {
        return formatCalories($this->calculateTotalCalories());
    }

    // ========================================================================
    // MÉTODOS DE RELACIÓN Y FILTRADO
    // ========================================================================

    /**
     * Verificar si esta receta usa alguna de las herramientas excluidas
     * @param array $excludedToolIds Array de IDs de herramientas
     * @return bool
     */
    public function usesExcludedTools($excludedToolIds) {
        if (empty($excludedToolIds)) return false;
        
        $tools = $this->getTools();
        foreach ($tools as $tool) {
            if (in_array($tool->getId(), $excludedToolIds)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar si esta receta contiene algún ingrediente prohibido
     * @param array $avoidedIngredientIds Array de IDs de ingredientes
     * @return bool
     */
    public function containsAvoidedIngredients($avoidedIngredientIds) {
        if (empty($avoidedIngredientIds)) return false;
        
        $ingredients = $this->getIngredients();
        foreach ($ingredients as $ing) {
            if (in_array($ing->getId(), $avoidedIngredientIds)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar si la receta es compatible con una restricción dietética
     * @param string $restriccion (ej: 'vegetariano', 'vegan', 'sin_gluten')
     * @return bool
     * Nota: Esta es una implementación básica. En un sistema real, 
     * los ingredientes tendrían etiquetas dietéticas.
     */
    public function isCompatibleWithDiet($restriccion) {
        if (empty($restriccion) || $restriccion === 'normal') return true;
        
        // Lógica simplificada: si es vegetariano, verificamos si hay carne/pescado
        // En una versión completa, cada ingrediente tendría un flag 'es_carne', 'es_pescado', etc.
        if ($restriccion === 'vegetariano') {
            $ingredientes = $this->getIngredients();
            // IDs aproximados de carnes/pescados (deberían venir de una tabla de categorías)
            $carnesIds = [1, 5, 9]; // Lomo, Pollo, Pescado (ejemplos de datos seed)
            
            foreach ($ingredientes as $ing) {
                if (in_array($ing->getId(), $carnesIds)) {
                    return false;
                }
            }
        }
        
        // Aquí se añadirían más lógicas según la restricción
        return true;
    }

    // ========================================================================
    // MÉTODOS DE GUARDADO (CREATE/UPDATE)
    // ========================================================================

    /**
     * Guardar la receta (Crear o Actualizar)
     * @return bool
     */
    public function save() {
        try {
            if ($this->id) {
                // UPDATE
                $sql = "UPDATE recetas SET id_plato = ?, titulo_html = ?, subtitulo_html = ?, texto_html = ?, enlace = ? WHERE id = ?";
                $success = executeQuery($sql, [$this->idPlato, $this->tituloHtml, $this->subtituloHtml, $this->textoHtml, $this->enlace, $this->id]);
            } else {
                // INSERT
                if (!$this->idPlato) {
                    throw new Exception("Debe seleccionar un plato.");
                }
                
                $sql = "INSERT INTO recetas (id_plato, titulo_html, subtitulo_html, texto_html, enlace) VALUES (?, ?, ?, ?, ?)";
                executeQuery($sql, [$this->idPlato, $this->tituloHtml, $this->subtituloHtml, $this->textoHtml, $this->enlace]);
                $this->id = getLastInsertId();
                $success = true;
            }
            return $success;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error al guardar receta: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Eliminar la receta (Hard delete: borra también relaciones)
     * @return bool
     */
    public function delete() {
        if (!$this->id) return false;
        
        // Las claves foráneas con ON DELETE CASCADE borrarán automáticamente:
        // - recetas_ingredientes
        // - recetas_herramientas
        $sql = "DELETE FROM recetas WHERE id = ?";
        return executeQuery($sql, [$this->id]);
    }

    /**
     * Asignar un ingrediente a esta receta
     * @param int $ingredientId
     * @param float $cantidad
     * @param string $unidad
     * @return bool
     */
    public function addIngredient($ingredientId, $cantidad, $unidad = 'g') {
        $ing = new Ingredient($ingredientId);
        if (!$ing->getId()) return false;
        
        return $ing->assignToRecipe($this->id, $cantidad, $unidad);
    }

    /**
     * Asignar una herramienta a esta receta
     * @param int $toolId
     * @return bool
     */
    public function addTool($toolId) {
        $tool = new Tool($toolId);
        if (!$tool->getId()) return false;
        
        return $tool->assignToRecipe($this->id);
    }

    /**
     * Limpiar todas las relaciones (ingredientes y herramientas) de esta receta
     * @return bool
     */
    public function clearRelations() {
        if (!$this->id) return false;
        
        // Borrar ingredientes
        $sqlIng = "DELETE FROM recetas_ingredientes WHERE id_receta = ?";
        executeQuery($sqlIng, [$this->id]);
        
        // Borrar herramientas
        $sqlTool = "DELETE FROM recetas_herramientas WHERE id_receta = ?";
        executeQuery($sqlTool, [$this->id]);
        
        // Resetear cache
        $this->ingredients = null;
        $this->tools = null;
        
        return true;
    }
}
?>