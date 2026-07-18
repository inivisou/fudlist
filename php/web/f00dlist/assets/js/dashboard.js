const MENU_ID = window.MENU_ID; // Acceder a la variable global definida en PHP
const CSRF_TOKEN = window.CSRF_TOKEN; // Acceder a la variable global definida en PHP

function showToast(message) {
    const t = document.getElementById('toast');
    t.innerText = message;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

// Función para refrescar secciones sin recargar página
async function silentRefresh() {
    const response = await fetch(window.location.href);
    const html = await response.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    
    document.getElementById('menuEfectivoTable').innerHTML = doc.getElementById('menuEfectivoTable').innerHTML;
    // Refrescar también la lista de compra
    const shoppingList = document.querySelector('.dashboard-container .header-panel:last-child');
    shoppingList.innerHTML = doc.querySelector('.dashboard-container .header-panel:last-child').innerHTML;
}

// Añadir plato al menú efectivo (desde el tentativo)
function addToEffective(element) {
    const platoId = element.dataset.plato;
    const nombre = element.querySelector('strong').innerText;

    fetch('api/add_to_effective.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        // El backend ahora debe buscar el primer hueco libre si no se pasan dia/momento
        body: `csrf_token=${CSRF_TOKEN}&menu_id=${MENU_ID}&plato_id=${platoId}&auto_find=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Añadido: ' + nombre);
            silentRefresh(); 
        } else {
            showToast('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error de conexión.');
    });
}

// Quitar plato del menú efectivo
function removeFromEffective(dia, momento) {
    // Acciones críticas mantienen confirm nativo
    if (!confirm('¿Quitar este plato?')) return;

    fetch('api/remove_from_effective.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `csrf_token=${CSRF_TOKEN}&menu_id=${MENU_ID}&dia=${dia}&momento=${momento}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            silentRefresh();
        } else {
            showToast('Error: ' + data.message);
        }
    });
}

// Marcar ingrediente como comprado
function toggleIngredient(ingredienteId, comprado, checkboxElement) {
    fetch('api/toggle_ingredient.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `csrf_token=${CSRF_TOKEN}&menu_id=${MENU_ID}&ingrediente_id=${ingredienteId}&comprado=${comprado ? 1 : 0}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = checkboxElement.closest('.ingredient-row');
            if (row) { comprado ? row.classList.add('comprado') : row.classList.remove('comprado'); }
            showToast('Estado actualizado');
        } else {
            showToast('Error al actualizar');
            silentRefresh();
        }
    });
}

// Guardar como favorito
function saveAsFavorite() {
    const nombre = prompt('Nombre para el menú favorito:');
    if (!nombre) return;

    fetch('api/save_as_favorite.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `csrf_token=${CSRF_TOKEN}&menu_id=${MENU_ID}&nombre=${encodeURIComponent(nombre)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Menú guardado como favorito: ' + nombre);
            location.href = 'favoritos.php';
        } else {
            alert('Error: ' + data.message);
        }
    });
}