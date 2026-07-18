/* ============================================================
   dynamic.js — Inputs dinámicos + Autocompletado moderno
   ============================================================ */

/* ------------------------------------------------------------
   1. INPUTS DINÁMICOS (tipos, ingredientes, herramientas)
   ------------------------------------------------------------ */

function addDynamicItem(containerId, value = "") {
    const container = document.getElementById(containerId);

    const div = document.createElement("div");
    div.className = "dynamic-item";

    const input = document.createElement("input");
    input.type = "text";
    input.value = value;

    // Asignar name dinámico según el contenedor
    const baseName = containerId.replace("-container", "");
    input.name = baseName + "[]";

    // Autocompletado si aplica
    if (["ingredientes", "herramientas", "tipos"].includes(baseName)) {
        const wrapper = document.createElement("div");
        wrapper.className = "autocomplete-box";
        wrapper.style.flex = "1";

        const results = document.createElement("div");
        results.className = "autocomplete-results";
        results.style.display = "none";

        input.oninput = () => autocomplete(input, results, baseName);

        // CORRECCIÓN: evitar que el blur cierre antes de seleccionar
        input.onblur = () => {
            setTimeout(() => {
                results.style.display = "none";
            }, 150);
        };

        wrapper.appendChild(input);
        wrapper.appendChild(results);

        div.appendChild(wrapper);
    } else {
        div.appendChild(input);
    }

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn-danger";
    btn.textContent = "X";
    btn.onclick = () => div.remove();

    div.appendChild(btn);

    container.appendChild(div);
}

/* ------------------------------------------------------------
   2. CARGA DE DATOS PARA AUTOCOMPLETADO
   ------------------------------------------------------------ */

let cache = {
    ingredientes: null,
    herramientas: null,
    tipos: null
};

async function loadData(type) {
    if (cache[type]) return cache[type];

    if (type === "ingredientes") {
        const res = await fetch("../data/ingredientes.json");
        const json = await res.json();
        cache.ingredientes = json.ingredientes.map(i => i.nombre);
        return cache.ingredientes;
    }

    if (type === "herramientas") {
        cache.herramientas = [
            "sartén", "olla", "horno", "microondas", "batidora",
            "cazo", "plancha", "cuchillo", "tabla", "colador"
        ];
        return cache.herramientas;
    }

    if (type === "tipos") {
        cache.tipos = [
            "carne", "pescado", "sopa", "ensalada", "rápido",
            "pasta", "arroz", "verdura", "horno", "frito"
        ];
        return cache.tipos;
    }
}

/* ------------------------------------------------------------
   3. AUTOCOMPLETADO GENERAL
   ------------------------------------------------------------ */

async function autocomplete(input, resultsBox, type) {
    const query = input.value.trim().toLowerCase();
    if (!query) {
        resultsBox.style.display = "none";
        return;
    }

    const list = await loadData(type);

    const matches = list.filter(item =>
        item.toLowerCase().includes(query)
    );

    resultsBox.innerHTML = "";
    resultsBox.style.display = "block";

    // Mostrar coincidencias
    matches.forEach(item => {
        const div = document.createElement("div");
        div.className = "autocomplete-item";

        // CORRECCIÓN: usar mousedown para evitar conflicto con blur
        div.onmousedown = () => {
            input.value = item;
            resultsBox.style.display = "none";
        };

        div.textContent = item;
        resultsBox.appendChild(div);
    });

    // Si no hay coincidencias → opción de crear ingrediente
    if (matches.length === 0 && type === "ingredientes") {
        const div = document.createElement("div");
        div.className = "autocomplete-item autocomplete-new";
        div.textContent = `➕ Añadir "${input.value}"`;

        div.onmousedown = async () => {
            await addNewIngredient(input.value);
            cache.ingredientes = null; // refrescar cache
            input.value = input.value;
            resultsBox.style.display = "none";
        };

        resultsBox.appendChild(div);
    }
}

/* ------------------------------------------------------------
   4. CREAR INGREDIENTE NUEVO (AJAX)
   ------------------------------------------------------------ */

async function addNewIngredient(nombre) {
    await fetch("guardar.php?action=add_ingredient", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ nombre })
    });
}
