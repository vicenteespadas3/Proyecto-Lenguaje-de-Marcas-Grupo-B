/* ==========================================================================
  LÓGICA JAVASCRIPT - incidencias.html
  Interactúa con: ../../api/incidencias/incidencias.php y ../../api/activos/maquinas.php
=========================================================================== */

/** Array con todos los registros de incidencias cargados desde la API */
let incidenciasData = [];

/** Array con todas las máquinas disponibles (para el select del modal) */
let maquinasDisponibles = [];

/** Dirección del orden actual: true = ASC, false = DESC */
let ordenAsc = false;

/** Campo por el que se ordena actualmente */
let criterioOrden = 'id';

async function cargarIncidencias() {
    const tbody = document.getElementById('tabla-incidencias-body');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px;">Cargando incidencias... 🛠️</td></tr>';
    const res = await api('GET', '../../api/incidencias/incidencias.php');
    if (res && res.ok) {
        incidenciasData = res.incidencias || [];
        aplicarOrden();
        filtrarIncidencias();
        actualizarUIOrden();
    } else {
        tbody.innerHTML = `<tr><td colspan="8" class="error-msg">Error: ${res.error || 'No se pudo conectar'}</td></tr>`;
    }
}

async function cargarMaquinas() {
    const res = await api('GET', '../../api/activos/maquinas.php');
    if (res && res.ok) maquinasDisponibles = res.maquinas || [];
}

function togglePanelFiltros() {
    const panel = document.getElementById('panelFiltros');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function limpiarFiltros() {
    document.getElementById('searchIncidencia').value = '';
    document.getElementById('filtroPrioridad').value  = '';
    document.getElementById('filtroEstado').value     = '';
    document.getElementById('filtroTipo').value       = '';
    filtrarIncidencias();
}

function toggleDropdownOrden(event) {
    event.stopPropagation();
    document.getElementById('dropdownOrden').classList.toggle('show');
}

function cambiarCriterioOrden(criterio) {
    if (criterioOrden === criterio) { ordenAsc = !ordenAsc; }
    else { criterioOrden = criterio; ordenAsc = criterio !== 'id'; }
    aplicarOrden();
    filtrarIncidencias();
    actualizarUIOrden();
    document.getElementById('dropdownOrden').classList.remove('show');
}

const PRIO_RANK = { 'Critica': 4, 'Alta': 3, 'Media': 2, 'Baja': 1 };
const ESTADO_RANK = { 'abierta': 1, 'en_curso': 2, 'resuelta': 3, 'cerrada': 4 };

function aplicarOrden() {
    incidenciasData.sort((a, b) => {
        let valA, valB;
        switch (criterioOrden) {
            case 'maquina':
                valA = (a.maquina || '').toLowerCase(); valB = (b.maquina || '').toLowerCase();
                return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
            case 'prioridad':
                valA = PRIO_RANK[a.prioridad] || 0; valB = PRIO_RANK[b.prioridad] || 0;
                return ordenAsc ? valA - valB : valB - valA;
            case 'estado':
                valA = ESTADO_RANK[a.estado] || 0; valB = ESTADO_RANK[b.estado] || 0;
                return ordenAsc ? valA - valB : valB - valA;
            case 'fecha':
                valA = a.fecha_reporte || ''; valB = b.fecha_reporte || '';
                return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
            default:
                valA = parseInt(a.id_incidencia) || 0; valB = parseInt(b.id_incidencia) || 0;
                return ordenAsc ? valA - valB : valB - valA;
        }
    });
}

function actualizarUIOrden() {
    document.querySelectorAll('.dropdown-orden-item').forEach(i => i.classList.remove('active'));
    document.querySelectorAll('.check-icon').forEach(i => i.textContent = '');
    const activeItem = document.querySelector(`.dropdown-orden-item[onclick*="'${criterioOrden}'"]`);
    if (activeItem) {
        activeItem.classList.add('active');
        const icon = activeItem.querySelector('.check-icon');
        if (icon) icon.textContent = ordenAsc ? '▲' : '▼';
    }
}

window.addEventListener('click', (e) => {
    const dropdown = document.getElementById('dropdownOrden');
    if (dropdown && !e.target.closest('.dropdown-ordenar-container')) dropdown.classList.remove('show');
});

function filtrarIncidencias() {
    const search     = document.getElementById('searchIncidencia').value.toLowerCase().trim();
    const prioridadF = document.getElementById('filtroPrioridad').value;
    const estadoF    = document.getElementById('filtroEstado').value;
    const tipoF      = document.getElementById('filtroTipo').value;
    const tbody      = document.getElementById('tabla-incidencias-body');

    const filtradas = incidenciasData.filter(i => {
        const matchSearch = !search ||
            (i.descripcion || '').toLowerCase().includes(search) ||
            (i.maquina     || '').toLowerCase().includes(search) ||
            (i.tipo        || '').toLowerCase().includes(search);
        const matchPrio = !prioridadF || i.prioridad === prioridadF;
        const matchEst  = !estadoF    || i.estado    === estadoF;
        const matchTipo = !tipoF      || (i.tipo || '').toLowerCase().includes(tipoF.toLowerCase());
        return matchSearch && matchPrio && matchEst && matchTipo;
    });

    document.getElementById('contadorIncidencias').textContent =
        `${filtradas.length} incidencia${filtradas.length !== 1 ? 's' : ''} encontrada${filtradas.length !== 1 ? 's' : ''}`;

    tbody.innerHTML = '';
    if (filtradas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px;">No se encontraron resultados.</td></tr>';
        return;
    }

    filtradas.forEach(i => {
        const pClases = { 'Baja': 'success', 'Media': 'warning', 'Alta': 'danger', 'Critica': 'danger' };
        const pClass  = pClases[i.prioridad] || 'success';
        const eClases = { 'abierta': 'danger', 'en_curso': 'warning', 'resuelta': 'success', 'cerrada': 'success' };
        const eClass  = eClases[i.estado] || 'info';
        const estadoTxt = (i.estado || '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        let fechaFmt = '-';
        if (i.fecha_reporte) {
            const dt = new Date(i.fecha_reporte.replace(' ', 'T'));
            fechaFmt = dt.toLocaleDateString('es-ES') + ' ' + dt.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        }
        const tipoIconos = { 'Averia': '⚠️', 'Mantenimiento': '🔧', 'Otro': '📋' };
        const tipoIcono  = tipoIconos[i.tipo] || '📋';
        tbody.innerHTML += `
            <tr>
                <td><strong>INC-${i.id_incidencia}</strong></td>
                <td>
                    <div style="font-weight:bold;">${i.maquina || 'Máquina ' + i.id_maquina}</div>
                    <small style="color:#7f8c8d;">ID: ${i.id_maquina}</small>
                </td>
                <td>${tipoIcono} ${i.tipo || '-'}</td>
                <td style="max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                    title="${(i.descripcion || '').replace(/"/g, '&quot;')}">
                    ${i.descripcion || '-'}
                </td>
                <td><span class="status-badge ${pClass}">${i.prioridad || '-'}</span></td>
                <td><span class="status-badge ${eClass}">${estadoTxt}</span></td>
                <td style="white-space:nowrap;">${fechaFmt}</td>
                <td class="action-buttons">
                    <button class="btn-icon" title="Gestionar / Resolver incidencia" onclick="abrirModalGestionar(${i.id_incidencia})">⚙️</button>
                    <button class="btn-icon" title="Eliminar incidencia" style="color:#e74c3c;" onclick="borrarIncidencia(${i.id_incidencia})">🗑️</button>
                </td>
            </tr>`;
    });
}

function abrirModalNueva() {
    document.getElementById('formNueva').reset();
    const sel = document.getElementById('nueva_maquina');
    if (maquinasDisponibles.length > 0) {
        sel.innerHTML = '<option value="">-- Selecciona una máquina --</option>' +
            maquinasDisponibles.map(m =>
                `<option value="${m.id_maquina}">${m.nombre} (ID: ${m.id_maquina} — ${m.estado})</option>`
            ).join('');
    } else {
        sel.innerHTML = '<option value="">No hay máquinas disponibles</option>';
    }
    document.getElementById('modalNueva').classList.add('show');
}

function cerrarModalNueva() {
    document.getElementById('modalNueva').classList.remove('show');
}

async function guardarNuevaIncidencia(e) {
    e.preventDefault();
    const payload = {
        id_maquina:  parseInt(document.getElementById('nueva_maquina').value),
        tipo:        document.getElementById('nueva_tipo').value,
        prioridad:   document.getElementById('nueva_prioridad').value,
        descripcion: document.getElementById('nueva_desc').value
    };
    if (!payload.id_maquina) { alert('Por favor selecciona una máquina.'); return; }
    const res = await api('POST', '../../api/incidencias/incidencias.php', payload);
    if (res && res.ok) {
        cerrarModalNueva();
        await cargarMaquinas();
        await cargarIncidencias();
    } else {
        alert('Error al registrar la incidencia: ' + (res.error || 'Error desconocido'));
    }
}

function abrirModalGestionar(id) {
    const inc = incidenciasData.find(x => x.id_incidencia == id);
    if (!inc) return;
    document.getElementById('tituloGestionar').textContent = `Gestionar INC-${inc.id_incidencia}`;
    const pClases = { 'Baja': '#27ae60', 'Media': '#e67e22', 'Alta': '#e74c3c', 'Critica': '#c0392b' };
    document.getElementById('infoIncidencia').innerHTML = `
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
            <div><strong>Máquina:</strong> ${inc.maquina || 'ID ' + inc.id_maquina}</div>
            <div><strong>Tipo:</strong> ${inc.tipo || '-'}</div>
            <div><strong>Prioridad:</strong> <span style="color:${pClases[inc.prioridad] || '#333'}; font-weight:bold;">${inc.prioridad}</span></div>
            <div><strong>Estado actual:</strong> ${(inc.estado || '').replace('_', ' ')}</div>
            <div style="grid-column:span 2;"><strong>Descripción:</strong> ${inc.descripcion}</div>
            ${inc.solucion_aplicada ? `<div style="grid-column:span 2;"><strong>Solución previa:</strong> ${inc.solucion_aplicada}</div>` : ''}
        </div>`;
    document.getElementById('gest_id').value       = inc.id_incidencia;
    document.getElementById('gest_estado').value   = inc.estado;
    document.getElementById('gest_solucion').value = inc.solucion_aplicada || '';
    document.getElementById('modalGestionar').classList.add('show');
}

function cerrarModalGestionar() {
    document.getElementById('modalGestionar').classList.remove('show');
}

async function guardarGestion(e) {
    e.preventDefault();
    const payload = {
        id_incidencia:     parseInt(document.getElementById('gest_id').value),
        estado:            document.getElementById('gest_estado').value,
        solucion_aplicada: document.getElementById('gest_solucion').value
    };
    const res = await api('PUT', '../../api/incidencias/incidencias.php', payload);
    if (res && res.ok) {
        cerrarModalGestionar();
        await cargarMaquinas();
        await cargarIncidencias();
    } else {
        alert('Error al actualizar: ' + (res.error || 'Error desconocido'));
    }
}

async function borrarIncidencia(id) {
    if (!confirm(`¿Eliminar la incidencia INC-${id}? Esta acción no se puede deshacer.`)) return;
    const res = await api('DELETE', '../../api/incidencias/incidencias.php', { id_incidencia: id });
    if (res && res.ok) { cargarIncidencias(); }
    else alert('Error al eliminar: ' + (res.error || 'Error desconocido'));
}

document.addEventListener('DOMContentLoaded', async () => {
    renderMenu('incidencias');
    await Promise.all([cargarMaquinas(), cargarIncidencias()]);
});