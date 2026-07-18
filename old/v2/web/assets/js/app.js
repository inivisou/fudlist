// ======================================================
// ESTADO GLOBAL
// ======================================================
let menuEfectivo = [];

let recetasData = null;
let ingredientesData = null;
let platosData = null;

let datosCargados = false; // <-- IMPORTANTE


// ======================================================
// CARGA DE JSON (ASÍNCRONA)
// ======================================================
async function cargarDatos() {
    try {
        const recetasResp = await fetch('data/recetas.json');
        recetasData = await recetasResp.json();

        const ingredientesResp = await fetch('data/ingredientes.json');
        ingredientesData = await ingredientesResp.json();

        const platosResp = await fetch('data/platos.json');
        platosData = await platosResp.json();

        datosCargados = true;
        console.log("Datos cargados correctamente");

        // OCULTAR SPINNER CUANDO JS ESTÁ LISTO
        const sp = document.getElementById('spinner');
        if (sp) sp.style.display = 'none';

    } catch (e) {
        console.error("Error cargando datos JSON:", e);
    }
}


// ======================================================
// TOASTS
// ======================================================
function mostrarToast(mensaje) {
    const cont = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = mensaje;

    cont.appendChild(t);

    setTimeout(() => t.classList.add('visible'), 10);

    setTimeout(() => {
        t.classList.remove('visible');
        setTimeout(() => cont.removeChild(t), 300);
    }, 2000);
}


// ======================================================
// BÚSQUEDA DE PLATOS Y RECETAS
// ======================================================
function buscarPlatoPorId(id) {
    if (!platosData) return null;
    return platosData.platos.find(p => p.id === id) || null;
}

function obtenerPlatoDesdePHP(id) {
    const plato = buscarPlatoPorId(id);
    if (!plato) return null;

    const receta = recetasData.recetas.find(r => r.id === plato.id_receta);

    return {
        id: plato.id,
        id_receta: plato.id_receta,
        nombre: plato.nombre,
        ingredientes: receta ? receta.ingredientes : []
    };
}


// ======================================================
// AÑADIR PLATO AL MENÚ EFECTIVO
// ======================================================
function añadirPlato(idPlato, tipo) {
    const plato = obtenerPlatoDesdePHP(idPlato);
    if (!plato) return;

    let diaLibre = null;

    for (let i = 0; i < menuEfectivo.length; i++) {
        if (tipo === 'comida' && !menuEfectivo[i].comida) {
            diaLibre = i;
            break;
        }
        if (tipo === 'cena' && !menuEfectivo[i].cena) {
            diaLibre = i;
            break;
        }
    }

    if (diaLibre === null) {
        menuEfectivo.push({ comida: null, cena: null });
        diaLibre = menuEfectivo.length - 1;
    }

    menuEfectivo[diaLibre][tipo] = plato;

    mostrarToast(tipo === 'comida' ? 'Comida añadida' : 'Cena añadida');

    pintarMenuEfectivo();
    actualizarIngredientes();
}


// ======================================================
// PINTAR MENÚ EFECTIVO
// ======================================================
function pintarMenuEfectivo() {
    const tbody = document.querySelector('#menu-efectivo tbody');
    tbody.innerHTML = '';

    menuEfectivo.forEach((dia, index) => {
        const n = index + 1;

        const tr = document.createElement('tr');

        const tdDia = document.createElement('td');
        tdDia.textContent = n;
        tr.appendChild(tdDia);

        const tdComida = document.createElement('td');
        if (dia.comida) {
            tdComida.innerHTML = `
                ${dia.comida.nombre}
                <a href="receta.php?id=${dia.comida.id_receta}" target="_blank">Ver receta</a>
            `;
        }
        tr.appendChild(tdComida);

        const tdQ1 = document.createElement('td');
        if (dia.comida) {
            const chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.checked = true;
            chk.addEventListener('change', () => quitarPlato(index, 'comida'));
            tdQ1.appendChild(chk);
        }
        tr.appendChild(tdQ1);

        const tdCena = document.createElement('td');
        if (dia.cena) {
            tdCena.innerHTML = `
                ${dia.cena.nombre}
                <a href="receta.php?id=${dia.cena.id_receta}" target="_blank">Ver receta</a>
            `;
        }
        tr.appendChild(tdCena);

        const tdQ2 = document.createElement('td');
        if (dia.cena) {
            const chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.checked = true;
            chk.addEventListener('change', () => quitarPlato(index, 'cena'));
            tdQ2.appendChild(chk);
        }
        tr.appendChild(tdQ2);

        tbody.appendChild(tr);
    });
}


// ======================================================
// QUITAR PLATO
// ======================================================
function quitarPlato(diaIndex, tipo) {
    menuEfectivo[diaIndex][tipo] = null;

    if (!menuEfectivo[diaIndex].comida && !menuEfectivo[diaIndex].cena) {
        menuEfectivo.splice(diaIndex, 1);
    }

    pintarMenuEfectivo();
    actualizarIngredientes();
}


// ======================================================
// ACTUALIZAR LISTA DE INGREDIENTES
// ======================================================
function actualizarIngredientes() {
    const cont = document.getElementById('lista-ingredientes');
    cont.innerHTML = '';

    let lista = [];

    menuEfectivo.forEach(dia => {
        ['comida', 'cena'].forEach(tipo => {
            if (dia[tipo]) {
                const receta = recetasData.recetas.find(r => r.id === dia[tipo].id);
                receta.ingredientes.forEach(ing => {
                    if (!lista.includes(ing)) lista.push(ing);
                });
            }
        });
    });

    const listaFinal = lista.map(nombre => {
        const ing = ingredientesData.ingredientes.find(i => i.nombre === nombre);
        return {
            nombre,
            supermercado: ing ? ing.supermercado : 'Desconocido'
        };
    });

    listaFinal.sort((a, b) => {
        if (a.supermercado === b.supermercado) {
            return a.nombre.localeCompare(b.nombre);
        }
        return a.supermercado.localeCompare(b.supermercado);
    });

    let html = '<table><thead><tr><th>Nº</th><th>Nombre</th><th>Supermercado</th></tr></thead><tbody>';
    listaFinal.forEach((ing, i) => {
        html += `<tr><td>${i + 1}</td><td>${ing.nombre}</td><td>${ing.supermercado}</td></tr>`;
    });
    html += '</tbody></table>';

    cont.innerHTML = html;
}


// ======================================================
// GENERAR PDF
// ======================================================
function generarPDF(preview = false) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFontSize(18);
    doc.text('Menú efectivo', 14, 20);

    const hoy = new Date();
    const fecha = hoy.toLocaleDateString('es-ES');
    doc.setFontSize(12);
    doc.text(`Generado el: ${fecha}`, 14, 30);

    let filasMenu = [];
    menuEfectivo.forEach((dia, index) => {
        const n = index + 1;

        if (dia.comida) {
            const receta = recetasData.recetas.find(r => r.id === dia.comida.id);
            filasMenu.push([
                n,
                'Comida',
                dia.comida.nombre,
                receta.ingredientes.join(' - ')
            ]);
        }

        if (dia.cena) {
            const receta = recetasData.recetas.find(r => r.id === dia.cena.id);
            filasMenu.push([
                n,
                'Cena',
                dia.cena.nombre,
                receta.ingredientes.join(' - ')
            ]);
        }
    });

    doc.autoTable({
        startY: 40,
        head: [['Día', 'Periodo', 'Nombre', 'Ingredientes']],
        body: filasMenu
    });

    let lista = [];
    menuEfectivo.forEach(dia => {
        ['comida', 'cena'].forEach(tipo => {
            if (dia[tipo]) {
                const receta = recetasData.recetas.find(r => r.id === dia[tipo].id);
                receta.ingredientes.forEach(ing => {
                    if (!lista.includes(ing)) lista.push(ing);
                });
            }
        });
    });

    const listaFinal = lista.map(nombre => {
        const ing = ingredientesData.ingredientes.find(i => i.nombre === nombre);
        return {
            nombre,
            supermercado: ing ? ing.supermercado : 'Desconocido'
        };
    });

    listaFinal.sort((a, b) => {
        if (a.supermercado === b.supermercado) {
            return a.nombre.localeCompare(b.nombre);
        }
        return a.supermercado.localeCompare(b.supermercado);
    });

    const filasIng = listaFinal.map((ing, i) => [
        i + 1,
        ing.nombre,
        ing.supermercado
    ]);

    doc.autoTable({
        startY: doc.lastAutoTable.finalY + 10,
        head: [['Nº', 'Nombre', 'Supermercado']],
        body: filasIng
    });

    if (preview) {
        window.open(doc.output('bloburl'));
    } else {
        doc.save('menu.pdf');
    }
}


// ======================================================
// EVENTOS
// ======================================================

// MOSTRAR SPINNER AL PULSAR "GENERAR"
document.addEventListener('submit', () => {
    const sp = document.getElementById('spinner');
    if (sp) sp.style.display = 'flex';
});

document.getElementById('pdf-preview').addEventListener('click', () => generarPDF(true));
document.getElementById('pdf-download').addEventListener('click', () => generarPDF(false));

document.addEventListener('click', e => {

    if (!datosCargados) {
        console.warn("Datos aún no cargados, ignorando click");
        return;
    }

    if (e.target.classList.contains('clickable')) {
        const id = parseInt(e.target.dataset.id);
        const tipo = e.target.dataset.tipo;
        añadirPlato(id, tipo);
    }
});


// ======================================================
// INICIALIZACIÓN
// ======================================================
cargarDatos();
