/* ==========================================================================
  LÓGICA JAVASCRIPT - analisis.html
  Interactúa con: ../../api/incidencias/analisis_maquinas.php
=========================================================================== */

/** Array con todos los registros cargados desde la API */
let analisisData = [];

/** Dirección del orden actual: true = ASC, false = DESC */
let ordenAsc = true;

/** Campo por el que se ordena actualmente (por defecto: id) */
let criterioOrden = 'id';

async function cargarAnalisis() {
    const tbody = document.getElementById('tabla-analisis-body');
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:20px;">Cargando análisis... 📊</td></tr>';
    const res = await api('GET', '../../api/incidencias/analisis_maquinas.php');
    if (res && res.ok) {
        analisisData = res.analisis || [];
        aplicarOrden();
        filtrarAnalisis();
        actualizarUIOrden();
    } else {
        tbody.innerHTML = `<tr><td colspan="9" class="error-msg">Error: ${res.error || 'No se pudo conectar con el servidor'}</td></tr>`;
    }
}

function togglePanelFiltros() {
    const panel = document.getElementById('panelFiltros');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function limpiarFiltros() {
    document.getElementById('searchAnalisis').value    = '';
    document.getElementById('filtroRango').value       = '';
    document.getElementById('filtroUnidad').value      = '';
    document.getElementById('filtroFechaDesde').value  = '';
    document.getElementById('filtroFechaHasta').value  = '';
    filtrarAnalisis();
}

function toggleDropdownOrden(event) {
    event.stopPropagation();
    document.getElementById('dropdownOrden').classList.toggle('show');
}

function cambiarCriterioOrden(criterio) {
    if (criterioOrden === criterio) { ordenAsc = !ordenAsc; }
    else { criterioOrden = criterio; ordenAsc = true; }
    aplicarOrden();
    filtrarAnalisis();
    actualizarUIOrden();
    document.getElementById('dropdownOrden').classList.remove('show');
}

function aplicarOrden() {
    analisisData.sort((a, b) => {
        let valA, valB;
        switch (criterioOrden) {
            case 'maquina':
                valA = (a.nombre_maquina || '').toLowerCase(); valB = (b.nombre_maquina || '').toLowerCase();
                return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
            case 'variable':
                valA = (a.nombre_variable || '').toLowerCase(); valB = (b.nombre_variable || '').toLowerCase();
                return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
            case 'fecha':
                valA = a.fecha_registro || ''; valB = b.fecha_registro || '';
                return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
            case 'valor':
                valA = parseFloat(a.valor) || 0; valB = parseFloat(b.valor) || 0;
                return ordenAsc ? valA - valB : valB - valA;
            default: // 'id'
                valA = parseInt(a.id_analisis) || 0; valB = parseInt(b.id_analisis) || 0;
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

function filtrarAnalisis() {
    const search     = document.getElementById('searchAnalisis').value.toLowerCase().trim();
    const rangoF     = document.getElementById('filtroRango').value;
    const unidadF    = document.getElementById('filtroUnidad').value.toLowerCase().trim();
    const fechaDesde = document.getElementById('filtroFechaDesde').value;
    const fechaHasta = document.getElementById('filtroFechaHasta').value;
    const tbody      = document.getElementById('tabla-analisis-body');

    const filtrados = analisisData.filter(a => {
        const matchSearch = !search ||
            (a.nombre_variable || '').toLowerCase().includes(search) ||
            (a.nombre_maquina  || '').toLowerCase().includes(search) ||
            (a.unidad_medida   || '').toLowerCase().includes(search);
        const matchRango  = rangoF === '' || String(a.dentro_rango) === rangoF;
        const matchUnidad = !unidadF || (a.unidad_medida || '').toLowerCase().includes(unidadF);
        const fechaReg    = (a.fecha_registro || '').substring(0, 10);
        const matchDesde  = !fechaDesde || fechaReg >= fechaDesde;
        const matchHasta  = !fechaHasta || fechaReg <= fechaHasta;
        return matchSearch && matchRango && matchUnidad && matchDesde && matchHasta;
    });

    document.getElementById('contadorAnalisis').textContent = `${filtrados.length} registro${filtrados.length !== 1 ? 's' : ''} encontrado${filtrados.length !== 1 ? 's' : ''}`;
    tbody.innerHTML = '';
    if (filtrados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:20px;">No se encontraron resultados con los filtros aplicados.</td></tr>';
        return;
    }

    filtrados.forEach(a => {
        const esDentro   = parseInt(a.dentro_rango) === 1;
        const badgeClass = esDentro ? 'success' : 'danger';
        const badgeTxt   = esDentro ? '✅ OK'    : '⚠️ Fuera';
        const valorFmt   = parseFloat(a.valor).toFixed(4);
        let fechaFmt = a.fecha_registro || '-';
        if (fechaFmt !== '-') {
            const dt = new Date(fechaFmt.replace(' ', 'T'));
            fechaFmt = dt.toLocaleDateString('es-ES') + ' ' + dt.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        }
        const nombreMaquina = a.nombre_maquina || `Máquina ${a.id_maquina}`;
        const ejecucionTxt  = a.id_ejecucion ? `EJC-${a.id_ejecucion}` : '—';

        tbody.innerHTML += `
            <tr>
                <td><strong>ANL-${a.id_analisis}</strong></td>
                <td>
                    <div style="font-weight:bold;">${nombreMaquina}</div>
                    <small style="color:#7f8c8d;">ID: ${a.id_maquina}</small>
                </td>
                <td>${ejecucionTxt}</td>
                <td><code style="font-size:0.85em;">${a.nombre_variable}</code></td>
                <td style="font-weight:bold; text-align:right;">${valorFmt}</td>
                <td>${a.unidad_medida || '—'}</td>
                <td style="white-space:nowrap;">${fechaFmt}</td>
                <td><span class="status-badge ${badgeClass}">${badgeTxt}</span></td>
                <td class="action-buttons">
                    <button class="btn-icon" title="Editar análisis" onclick="editarAnalisis(${a.id_analisis})">✏️</button>
                    <button class="btn-icon" title="Eliminar análisis" style="color:#e74c3c;" onclick="borrarAnalisis(${a.id_analisis})">🗑️</button>
                </td>
            </tr>`;
    });
}

function abrirModalNuevo() {
    document.getElementById('modalTituloAnal').textContent = 'Nuevo Análisis';
    document.getElementById('formAnalisis').reset();
    document.getElementById('anal_id').value = '';
    document.getElementById('modalAnalisis').classList.add('show');
}

function editarAnalisis(id) {
    const a = analisisData.find(x => x.id_analisis == id);
    if (!a) return;
    document.getElementById('modalTituloAnal').textContent = 'Editar Análisis';
    document.getElementById('anal_id').value        = a.id_analisis;
    document.getElementById('anal_maquina').value   = a.id_maquina;
    document.getElementById('anal_ejecucion').value = a.id_ejecucion || '';
    document.getElementById('anal_variable').value  = a.nombre_variable;
    document.getElementById('anal_valor').value     = a.valor;
    document.getElementById('anal_unidad').value    = a.unidad_medida || '';
    document.getElementById('anal_rango').value     = a.dentro_rango;
    if (a.fecha_registro) {
        document.getElementById('anal_fecha').value = a.fecha_registro.replace(' ', 'T').substring(0, 16);
    }
    document.getElementById('modalAnalisis').classList.add('show');
}

function cerrarModal() {
    document.getElementById('modalAnalisis').classList.remove('show');
}

async function guardarAnalisis(e) {
    e.preventDefault();
    const id       = document.getElementById('anal_id').value;
    const fechaVal = document.getElementById('anal_fecha').value;
    const payload  = {
        id_maquina:      parseInt(document.getElementById('anal_maquina').value),
        id_ejecucion:    document.getElementById('anal_ejecucion').value ? parseInt(document.getElementById('anal_ejecucion').value) : null,
        nombre_variable: document.getElementById('anal_variable').value,
        valor:           parseFloat(document.getElementById('anal_valor').value),
        unidad_medida:   document.getElementById('anal_unidad').value,
        fecha_registro:  fechaVal ? fechaVal.replace('T', ' ') + ':00' : '',
        dentro_rango:    parseInt(document.getElementById('anal_rango').value)
    };
    let res;
    if (id) {
        payload.id_analisis = parseInt(id);
        res = await api('PUT', '../../api/incidencias/analisis_maquinas.php', payload);
    } else {
        res = await api('POST', '../../api/incidencias/analisis_maquinas.php', payload);
    }
    if (res && res.ok) { cerrarModal(); cargarAnalisis(); }
    else alert('Error: ' + (res.error || 'No se pudo guardar el análisis'));
}

async function borrarAnalisis(id) {
    if (!confirm(`¿Eliminar el análisis ANL-${id}? Esta acción no se puede deshacer.`)) return;
    const res = await api('DELETE', '../../api/incidencias/analisis_maquinas.php', { id_analisis: id });
    if (res && res.ok) { cargarAnalisis(); }
    else alert('Error al eliminar: ' + (res.error || 'Error desconocido'));
}

document.addEventListener('DOMContentLoaded', () => {
    renderMenu('incidencias');
    cargarAnalisis();
});
