// Js/gamas.js
// CRUD completo para Gamas de Mantenimiento

let gamasData = []; // Array global para poder editar sin volver a llamar a la API

document.addEventListener('DOMContentLoaded', async () => {
    renderMenu();
    await cargarTabla();

    // --- BÚSQUEDA ---
    const btnBuscarGama   = document.getElementById('btnBuscarGama');
    const inputBuscarGama = document.getElementById('inputBuscarGama');

    if (btnBuscarGama && inputBuscarGama) {
        btnBuscarGama.addEventListener('click', () => cargarTabla(inputBuscarGama.value));
        inputBuscarGama.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') cargarTabla(inputBuscarGama.value);
        });
    }

    // --- BOTÓN NUEVA GAMA → abre el modal en modo Crear ---
    const btnNuevaGama = document.getElementById('btnNuevaGama');
    if (btnNuevaGama) {
        btnNuevaGama.addEventListener('click', () => abrirModalGama());
    }

    // --- CERRAR MODAL AL CLIC FUERA ---
    document.getElementById('modalGama').addEventListener('click', function(e) {
        if (e.target === this) cerrarModalGama();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
//  TABLA PRINCIPAL
// ─────────────────────────────────────────────────────────────────────────────

async function cargarTabla(query = '') {
    const tbody = document.getElementById('cuerpoTablaGamas');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">Cargando... ⚙️</td></tr>';

    let url = '../../api/mantenimiento/gamas.php';
    if (query.trim() !== '') url += `?buscar=${encodeURIComponent(query.trim())}`;

    const respuesta = await api('GET', url);

    if (!respuesta || respuesta.ok === false) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:red; text-align:center;">Error al cargar las gamas</td></tr>`;
        return;
    }

    gamasData = respuesta.gamas || [];
    tbody.innerHTML = '';

    if (gamasData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:#7f8c8d; padding:20px;">No hay gamas registradas aún.</td></tr>`;
        return;
    }

    gamasData.forEach(gama => {
        const fila = document.createElement('tr');
        fila.innerHTML = `
            <td class="col-id">${gama.id_gama}</td>
            <td><strong>${gama.nombre}</strong>${gama.descripcion ? `<br><small style="color:#95a5a6">${gama.descripcion.substring(0,60)}${gama.descripcion.length > 60 ? '…' : ''}</small>` : ''}</td>
            <td>
                <span class="status-badge badge-${gama.tipo_mantenimiento}">
                    ${gama.tipo_mantenimiento.toUpperCase()}
                </span>
            </td>
            <td>
                <span class="tipo-gama gama-${gama.tipo_gama}">
                    ${gama.tipo_gama.toUpperCase()}
                </span>
            </td>
            <td class="action-buttons justify-center">
                <button class="btn btn-icon" title="Editar gama" onclick="editarGama(${gama.id_gama})">✏️</button>
                <button class="btn btn-icon" style="color:#e74c3c;" title="Borrar gama" onclick="borrarGama(${gama.id_gama})">🗑️</button>
            </td>
        `;
        tbody.appendChild(fila);
    });
}

// ─────────────────────────────────────────────────────────────────────────────
//  MODAL: ABRIR / CERRAR
// ─────────────────────────────────────────────────────────────────────────────

function abrirModalGama() {
    document.getElementById('modalGamaTitulo').textContent = '➕ Nueva Gama de Mantenimiento';
    document.getElementById('formGama').reset();
    document.getElementById('gama_id').value = '';
    document.getElementById('modalGama').classList.add('show');
}

function editarGama(id) {
    const gama = gamasData.find(g => g.id_gama == id);
    if (!gama) { alert('No se encontraron los datos de la gama. Recarga la página.'); return; }

    document.getElementById('modalGamaTitulo').textContent    = '✏️ Editar Gama de Mantenimiento';
    document.getElementById('gama_id').value                  = gama.id_gama;
    document.getElementById('gama_nombre').value              = gama.nombre           || '';
    document.getElementById('gama_tipo_mantenimiento').value  = gama.tipo_mantenimiento || 'preventivo';
    document.getElementById('gama_tipo_gama').value           = gama.tipo_gama        || 'mecanico';
    document.getElementById('gama_descripcion').value         = gama.descripcion      || '';

    document.getElementById('modalGama').classList.add('show');
}

function cerrarModalGama() {
    document.getElementById('modalGama').classList.remove('show');
}

// ─────────────────────────────────────────────────────────────────────────────
//  CRUD A LA API
// ─────────────────────────────────────────────────────────────────────────────

async function guardarGama(evento) {
    evento.preventDefault();

    const id                 = document.getElementById('gama_id').value;
    const nombre             = document.getElementById('gama_nombre').value.trim();
    const tipo_mantenimiento = document.getElementById('gama_tipo_mantenimiento').value;
    const tipo_gama          = document.getElementById('gama_tipo_gama').value;
    const descripcion        = document.getElementById('gama_descripcion').value.trim();

    if (!nombre) { alert('El nombre de la gama es obligatorio.'); return; }

    const datos = { nombre, tipo_mantenimiento, tipo_gama, descripcion: descripcion || null };

    let metodo = 'POST';
    if (id !== '') { metodo = 'PUT'; datos.id_gama = parseInt(id); }

    const btnGuardar = document.getElementById('btnGuardarGamaModal');
    btnGuardar.disabled    = true;
    btnGuardar.textContent = 'Guardando...';

    const respuesta = await api(metodo, '../../api/mantenimiento/gamas.php', datos);

    btnGuardar.disabled    = false;
    btnGuardar.textContent = '💾 Guardar Gama';

    if (respuesta && respuesta.ok !== false) {
        cerrarModalGama();
        document.getElementById('inputBuscarGama').value = '';
        await cargarTabla();
    } else {
        alert('Error: ' + (respuesta ? respuesta.error : 'No se pudo guardar la gama'));
    }
}

async function borrarGama(id_gama) {
    const gama = gamasData.find(g => g.id_gama == id_gama);
    const nombre = gama ? `"${gama.nombre}"` : `ID ${id_gama}`;

    if (!confirm(`¿Seguro que quieres borrar la gama ${nombre}?\nEsta acción no se puede deshacer.`)) return;

    const respuesta = await api('DELETE', '../../api/mantenimiento/gamas.php', { id_gama });

    if (respuesta && respuesta.ok !== false) {
        await cargarTabla(document.getElementById('inputBuscarGama').value);
    } else {
        alert('Error: ' + (respuesta ? respuesta.error : 'No se pudo borrar la gama'));
    }
}