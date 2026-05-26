// incidencias.js - Gestión de incidencias (Búsqueda, Filtros, Ordenación y CRUD)

let incidenciasData = [];
let ordenAsc = true;
let criterioOrden = 'id'; // 'id' o 'descripcion'

document.addEventListener('DOMContentLoaded', () => {
    if (typeof renderMenu === 'function') renderMenu('incidencias');
    cargarIncidencias();
});

async function cargarIncidencias() {
    const tbody = document.getElementById('tabla-incidencias-body');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px;">Cargando incidencias... 🛠️</td></tr>';

    try {
        const res = await api('GET', '../../api/incidencias/incidencias.php');
        if (res && res.ok) {
            incidenciasData = res.incidencias || [];
            aplicarOrden();
            filtrarIncidencias();
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="error-msg" style="text-align: center; color: #e74c3c; padding: 20px;">Error al cargar datos.</td></tr>';
        }
    } catch (error) {
        console.error("Error:", error);
        tbody.innerHTML = '<tr><td colspan="7" class="error-msg" style="text-align: center; color: #e74c3c; padding: 20px;">Error de conexión.</td></tr>';
    }
}

function togglePanelFiltros() {
    const p = document.getElementById('panelFiltros');
    if (p) {
        p.style.display = p.style.display === 'none' ? 'block' : 'none';
    }
}

function toggleOrden() {
    if (criterioOrden === 'id') {
        if (ordenAsc) {
            ordenAsc = false;
        } else {
            criterioOrden = 'descripcion';
            ordenAsc = true;
        }
    } else {
        if (ordenAsc) {
            ordenAsc = false;
        } else {
            criterioOrden = 'id';
            ordenAsc = true;
        }
    }
    aplicarOrden();
    filtrarIncidencias();
}

function aplicarOrden() {
    incidenciasData.sort((a, b) => {
        let valA, valB;
        if (criterioOrden === 'descripcion') {
            valA = (a.descripcion || '').toLowerCase();
            valB = (b.descripcion || '').toLowerCase();
            return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else {
            valA = parseInt(a.id_incidencia);
            valB = parseInt(b.id_incidencia);
            return ordenAsc ? valA - valB : valB - valA;
        }
    });
}

function limpiarFiltros() {
    const search = document.getElementById('searchIncidencia');
    const prio = document.getElementById('filtroPrioridad');
    const est = document.getElementById('filtroEstado');
    
    if (search) search.value = '';
    if (prio) prio.value = '';
    if (est) est.value = '';
    
    filtrarIncidencias();
}

function filtrarIncidencias() {
    const searchInput = document.getElementById('searchIncidencia');
    const prioSelect = document.getElementById('filtroPrioridad');
    const estSelect = document.getElementById('filtroEstado');
    const tbody = document.getElementById('tabla-incidencias-body');
    const contador = document.getElementById('contadorIncidencias');

    if (!tbody) return;

    const search = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const prioridadF = prioSelect ? prioSelect.value : '';
    const estadoF = estSelect ? estSelect.value : '';

    const filtradas = incidenciasData.filter(i => {
        const matchSearch = !search || 
            (i.descripcion || '').toLowerCase().includes(search) || 
            (i.maquina || '').toLowerCase().includes(search) || 
            (i.tecnico_asignado || '').toLowerCase().includes(search);
        const matchPrio = !prioridadF || i.prioridad === prioridadF;
        const matchEst = !estadoF || i.estado === estadoF;
        return matchSearch && matchPrio && matchEst;
    });

    if (contador) {
        contador.textContent = `${filtradas.length} incidencias encontradas`;
    }

    tbody.innerHTML = '';

    if (filtradas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #7f8c8d;">No hay coincidencias.</td></tr>';
        return;
    }

    filtradas.forEach(i => {
        let pClass = 'success';
        if (i.prioridad === 'Media') pClass = 'warning';
        if (i.prioridad === 'Alta') pClass = 'danger';

        let eClass = 'info';
        if (i.estado === 'abierta') eClass = 'danger';
        if (i.estado === 'en_curso') eClass = 'warning';
        if (i.estado === 'resuelta' || i.estado === 'cerrada') eClass = 'success';

        const estTxt = (i.estado || '').charAt(0).toUpperCase() + (i.estado || '').slice(1).replace('_', ' ');

        tbody.innerHTML += `
            <tr>
                <td><strong>INC-${i.id_incidencia}</strong></td>
                <td>${escapingHtml(i.maquina || 'Máquina ' + i.id_maquina)}</td>
                <td>${escapingHtml(i.descripcion)}</td>
                <td><span class="status-badge ${pClass}">${escapingHtml(i.prioridad)}</span></td>
                <td><span class="status-badge ${eClass}">${escapingHtml(estTxt)}</span></td>
                <td>${escapingHtml(i.tecnico_asignado || 'Sin asignar')}</td>
                <td class="action-buttons">
                    <button class="btn-icon" title="Gestionar" onclick="gestionarInc(${i.id_incidencia})">⚙️</button>
                    <button class="btn-icon" title="Eliminar" style="color:#e74c3c;" onclick="borrarInc(${i.id_incidencia})">🗑️</button>
                </td>
            </tr>
        `;
    });
}

function abrirModalIncidencia() {
    const titulo = document.getElementById('modalTituloInc');
    const form = document.getElementById('formIncidencia');
    const idInput = document.getElementById('inc_id');
    const modal = document.getElementById('modalIncidencia');

    if (titulo) titulo.textContent = 'Reportar Incidencia';
    if (form) form.reset();
    if (idInput) idInput.value = '';
    if (modal) modal.classList.add('show');
}

function cerrarModalInc() {
    const modal = document.getElementById('modalIncidencia');
    if (modal) modal.classList.remove('show');
}

async function guardarIncidencia(e) {
    e.preventDefault();
    
    const idMaquina = document.getElementById('inc_maquina')?.value;
    const prioridad = document.getElementById('inc_prioridad')?.value;
    const descripcion = document.getElementById('inc_desc')?.value;

    const datos = {
        id_maquina: idMaquina,
        prioridad: prioridad,
        descripcion: descripcion
    };

    try {
        const res = await api('POST', '../../api/incidencias/incidencias.php', datos);
        if (res && res.ok) { 
            cerrarModalInc(); 
            cargarIncidencias(); 
        } else {
            alert('Error: ' + (res.error || 'No se pudo guardar la incidencia.'));
        }
    } catch (error) {
        alert('Error de conexión.');
    }
}

async function gestionarInc(id) {
    alert('Lógica de gestión para INC-' + id + ' en desarrollo (abriría modal de resolución)');
}

async function borrarInc(id) {
    if (!confirm('¿Seguro que deseas eliminar la incidencia INC-' + id + '?')) return;
    try {
        const res = await api('DELETE', '../../api/incidencias/incidencias.php', { id_incidencia: id });
        if (res && res.ok) {
            cargarIncidencias();
        } else {
            alert('Error: ' + (res.error || 'No se pudo eliminar la incidencia.'));
        }
    } catch (error) {
        alert('Error de conexión.');
    }
}

function escapingHtml(valor) {
    return String(valor ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

// Asegurar compatibilidad
if (typeof escaparHtml !== 'function') {
    window.escaparHtml = escapingHtml;
}