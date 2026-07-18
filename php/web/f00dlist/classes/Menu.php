<?php
/**
 * CLASE MENU (MENÚS)
 * ===================
 * Gestiona la creación, edición, guardado de favoritos y carga de menús.
 * Maneja la relación con comensales y platos diarios.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Recipe.php';

class Menu {
    private $id;
    private $usuarioCreadorId;
    private $nombre;
    private $tipo; // 'actual' o 'favorito'
    private $fechaGeneracion;
    private $fechaInicio;
    private $fechaFin;
    
    // Relaciones cargadas lazy-load
    private $diasData = null;
    private $comensalesData = null;

    // ========================================================================
    // CONSTRUCTOR Y GETTERS/SETTERS
    // ========================================================================

    public function __construct($id = null, $usuarioCreadorId = null, $nombre = 'Menú', $tipo = 'actual') {
        if ($id !== null) {
            $this->loadById($id);
        } else {
            $this->id = null;
            $this->usuarioCreadorId = $usuarioCreadorId;
            $this->nombre = $nombre;
            $this->tipo = $tipo;
            $this->fechaGeneracion = date('Y-m-d H:i:s');
            $this->fechaInicio = null;
            $this->fechaFin = null;
        }
    }

    public function getId() { return $this->id; }
    public function getUsuarioCreadorId() { return $this->usuarioCreadorId; }
    public function getNombre() { return $this->nombre; }
    public function getTipo() { return $this->tipo; }
    public function getFechaGeneracion() { return $this->fechaGeneracion; }
    public function getFechaInicio() { return $this->fechaInicio; }
    public function getFechaFin() { return $this->fechaFin; }

    public function setNombre($nombre) { $this->nombre = $nombre; }
    public function setTipo($tipo) { $this->tipo = $tipo; }
    public function setFechaInicio($fecha) { $this->fechaInicio = $fecha; }
    public function setFechaFin($fecha) { $this->fechaFin = $fecha; }

    // ========================================================================
    // MÉTODOS DE CARGA (READ)
    // ========================================================================

    /**
     * Cargar datos de la BD por ID
     */
    private function loadById($id) {
        $sql = "SELECT * FROM menus WHERE id = ?";
        $data = fetchOne($sql, [$id]);
        
        if ($data) {
            $this->id = $data['id'];
            $this->usuarioCreadorId = $data['usuario_creador_id'];
            $this->nombre = $data['nombre'];
            $this->tipo = $data['tipo'];
            $this->fechaGeneracion = $data['fecha_generacion'];
            $this->fechaInicio = $data['fecha_inicio'];
            $this->fechaFin = $data['fecha_fin'];
            return true;
        }
        return false;
    }

    /**
     * Obtener el menú "Actual" de un usuario
     * @param int $userId
     * @return Menu|null
     */
    public static function getActualForUser($userId) {
        $sql = "SELECT * FROM menus WHERE usuario_creador_id = ? AND tipo = 'actual' LIMIT 1";
        $data = fetchOne($sql, [$userId]);
        
        if ($data) {
            return new Menu($data['id']);
        }
        return null;
    }

    /**
     * Obtener todos los menús favoritos de un usuario
     * @param int $userId
     * @return array
     */
    public static function getFavoritesForUser($userId) {
        $sql = "SELECT * FROM menus WHERE usuario_creador_id = ? AND tipo = 'favorito' ORDER BY fecha_generacion DESC";
        $rows = fetchAll($sql, [$userId]);
        
        $menus = [];
        foreach ($rows as $row) {
            $menus[] = new Menu($row['id']);
        }
        return $menus;
    }

    /**
     * Obtener datos de los días (Comida y Cena) para este menú (Lazy Load)
     * @return array Array de ['dia_numero' => int, 'tipo_momento' => string, 'id_plato' => int, 'plato_nombre' => string, 'calorias' => float]
     */
    public function getDiasData() {
        if ($this->diasData === null) {
            $this->diasData = [];
            if (!$this->id) return [];

            $sql = "SELECT md.dia_numero, md.tipo_momento, md.id_plato, p.nombre as plato_nombre, p.nivel_calorico
                    FROM menu_dias md
                    LEFT JOIN platos p ON md.id_plato = p.id
                    WHERE md.id_menu = ?
                    ORDER BY md.dia_numero ASC, FIELD(md.tipo_momento, 'comida', 'cena')";
            
            $rows = fetchAll($sql, [$this->id]);
            
            // Estructurar por día y momento para fácil acceso
            $structured = [];
            foreach ($rows as $row) {
                $dia = $row['dia_numero'];
                $momento = $row['tipo_momento'];
                
                $structured[$dia][$momento] = [
                    'id_plato' => $row['id_plato'],
                    'nombre' => $row['plato_nombre'],
                    'nivel_calorico' => $row['nivel_calorico']
                ];
            }
            
            $this->diasData = $structured;
        }
        return $this->diasData;
    }

    /**
     * Obtener datos de los comensales para este menú (Lazy Load)
     * @return array Array de ARRAYS con datos del usuario (NO objetos User)
     */
    public function getComensalesData() {
        if ($this->comensalesData === null) {
            $this->comensalesData = [];
            if (!$this->id) return [];

            $sql = "SELECT u.id, u.username, u.email, u.nombre_completo, u.avatar_url
                    FROM users u
                    JOIN menu_comensales mc ON u.id = mc.id_usuario
                    WHERE mc.id_menu = ? AND u.activo = 1";
            
            $rows = fetchAll($sql, [$this->id]);
            
            // Retornar arrays en lugar de objetos User
            $this->comensalesData = $rows;
        }
        return $this->comensalesData;
    }

    /**
     * Obtener un plato específico para un día y momento
     * @param int $diaNumero
     * @param string $tipoMomento ('comida' o 'cena')
     * @return Recipe|null
     */
    public function getPlato($diaNumero, $tipoMomento) {
        $dias = $this->getDiasData();
        if (isset($dias[$diaNumero][$tipoMomento]['id_plato']) && $dias[$diaNumero][$tipoMomento]['id_plato']) {
            $recipeId = $dias[$diaNumero][$tipoMomento]['id_plato'];
            // Necesitamos el ID de la receta, no del plato. 
            // La tabla menu_dias guarda id_plato. Necesitamos buscar la receta asociada a ese plato.
            // Asumimos 1 receta por plato para simplificar, o tomamos la primera.
            $sql = "SELECT id FROM recetas WHERE id_plato = ? LIMIT 1";
            $recipeData = fetchOne($sql, [$dias[$diaNumero][$tipoMomento]['id_plato']]);
            
            if ($recipeData) {
                return new Recipe($recipeData['id']);
            }
        }
        return null;
    }

    // ========================================================================
    // MÉTODOS DE GUARDADO (CREATE/UPDATE)
    // ========================================================================

    /**
     * Guardar el menú (Crear o Actualizar)
     * @return bool
     */
    public function save() {
        try {
            if ($this->id) {
                // UPDATE
                $sql = "UPDATE menus SET nombre = ?, tipo = ?, fecha_inicio = ?, fecha_fin = ? WHERE id = ?";
                $success = executeQuery($sql, [$this->nombre, $this->tipo, $this->fechaInicio, $this->fechaFin, $this->id]);
            } else {
                // INSERT
                if (!$this->usuarioCreadorId) {
                    throw new Exception("Debe especificar un usuario creador.");
                }
                
                $sql = "INSERT INTO menus (usuario_creador_id, nombre, tipo, fecha_generacion, fecha_inicio, fecha_fin) 
                        VALUES (?, ?, ?, NOW(), ?, ?)";
                executeQuery($sql, [$this->usuarioCreadorId, $this->nombre, $this->tipo, $this->fechaInicio, $this->fechaFin]);
                $this->id = getLastInsertId();
                $success = true;
            }
            return $success;
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Error al guardar menú: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Eliminar el menú (y sus relaciones)
     * @return bool
     */
    public function delete() {
        if (!$this->id) return false;
        
        // Las claves foráneas con ON DELETE CASCADE borrarán:
        // - menu_dias
        // - menu_comensales
        // - ingredientes_comprados (si existe relación directa, si no, se limpia por menú)
        $sql = "DELETE FROM menus WHERE id = ?";
        return executeQuery($sql, [$this->id]);
    }

    // ========================================================================
    // GESTIÓN DE DÍAS Y PLATOS
    // ========================================================================

    /**
     * Asignar un plato a un día y momento
     * @param int $diaNumero
     * @param string $tipoMomento
     * @param int $idPlato
     * @return bool
     */
    public function setPlato($diaNumero, $tipoMomento, $idPlato) {
        if (!$this->id) return false;
        
        // Verificar si ya existe
        $checkSql = "SELECT id FROM menu_dias WHERE id_menu = ? AND dia_numero = ? AND tipo_momento = ?";
        $existing = fetchOne($checkSql, [$this->id, $diaNumero, $tipoMomento]);
        
        if ($existing) {
            // UPDATE
            $sql = "UPDATE menu_dias SET id_plato = ? WHERE id_menu = ? AND dia_numero = ? AND tipo_momento = ?";
            return executeQuery($sql, [$idPlato, $this->id, $diaNumero, $tipoMomento]);
        } else {
            // INSERT
            $sql = "INSERT INTO menu_dias (id_menu, dia_numero, tipo_momento, id_plato) VALUES (?, ?, ?, ?)";
            return executeQuery($sql, [$this->id, $diaNumero, $tipoMomento, $idPlato]);
        }
    }

    /**
     * Quitar un plato de un día y momento (dejar hueco libre)
     * @param int $diaNumero
     * @param string $tipoMomento
     * @return bool
     */
    public function removePlato($diaNumero, $tipoMomento) {
        if (!$this->id) return false;
        
        $sql = "DELETE FROM menu_dias WHERE id_menu = ? AND dia_numero = ? AND tipo_momento = ?";
        return executeQuery($sql, [$this->id, $diaNumero, $tipoMomento]);
    }

    /**
     * Obtener el primer hueco libre (dia, momento) donde no hay plato
     * @param int $maxDias
     * @return array|null ['dia' => int, 'momento' => string]
     */
    public function getFirstFreeSlot($maxDias = 14) {
        if (!$this->id) return null;
        
        for ($d = 1; $d <= $maxDias; $d++) {
            foreach (['comida', 'cena'] as $m) {
                $checkSql = "SELECT id FROM menu_dias WHERE id_menu = ? AND dia_numero = ? AND tipo_momento = ? AND id_plato IS NOT NULL";
                $exists = fetchOne($checkSql, [$this->id, $d, $m]);
                if (!$exists) {
                    return ['dia' => $d, 'momento' => $m];
                }
            }
        }
        return null;
    }

    // ========================================================================
    // GESTIÓN DE COMENSALES
    // ========================================================================

    /**
     * Añadir un comensal al menú
     * @param int $userId
     * @return bool
     */
    public function addComensal($userId) {
        if (!$this->id) return false;
        
        // Verificar si ya existe
        $checkSql = "SELECT id FROM menu_comensales WHERE id_menu = ? AND id_usuario = ?";
        if (fetchOne($checkSql, [$this->id, $userId])) {
            return true; // Ya existe
        }
        
        $sql = "INSERT INTO menu_comensales (id_menu, id_usuario) VALUES (?, ?)";
        return executeQuery($sql, [$this->id, $userId]);
    }

    /**
     * Remover un comensal del menú
     * @param int $userId
     * @return bool
     */
    public function removeComensal($userId) {
        if (!$this->id) return false;
        
        $sql = "DELETE FROM menu_comensales WHERE id_menu = ? AND id_usuario = ?";
        return executeQuery($sql, [$this->id, $userId]);
    }

    /**
     * Reemplazar todos los comensales del menú por una nueva lista
     * @param array $userIds Array de IDs de usuarios
     * @return bool
     */
    public function setComensales($userIds) {
        if (!$this->id) return false;
        
        // Borrar todos los actuales
        $deleteSql = "DELETE FROM menu_comensales WHERE id_menu = ?";
        executeQuery($deleteSql, [$this->id]);
        
        // Insertar los nuevos
        foreach ($userIds as $userId) {
            $this->addComensal($userId);
        }
        
        // Resetear cache
        $this->comensalesData = null;
        
        return true;
    }

    // ========================================================================
    // GESTIÓN DE FAVORITOS
    // ========================================================================

    /**
     * Convertir este menú en un favorito (Clonación profunda)
     * @param string $nombreNuevo Nombre para el favorito
     * @return Menu|null El nuevo menú favorito creado
     */
    public function saveAsFavorite($nombreNuevo) {
        if (!$this->id) return null;
        
        try {
            beginTransaction();
            
            // 1. Crear nuevo registro de menú
            $newMenu = new Menu(null, $this->usuarioCreadorId, $nombreNuevo, 'favorito');
            $newMenu->setFechaInicio($this->fechaInicio);
            $newMenu->setFechaFin($this->fechaFin);
            $newMenu->save();
            
            // 2. Clonar menu_dias
            $dias = $this->getDiasData();
            foreach ($dias as $dia => $momentos) {
                foreach ($momentos as $momento => $datos) {
                    if ($datos['id_plato']) {
                        $newMenu->setPlato($dia, $momento, $datos['id_plato']);
                    }
                }
            }
            
            // 3. Clonar comensales
            $comensales = $this->getComensalesData();
            foreach ($comensales as $comensal) {
                $newMenu->addComensal($comensal['id']);
            }
            
            commit();
            return $newMenu;
            
        } catch (Exception $e) {
            rollback();
            if (DEBUG_MODE) {
                error_log("Error al guardar como favorito: " . $e->getMessage());
            }
            return null;
        }
    }
}
?>