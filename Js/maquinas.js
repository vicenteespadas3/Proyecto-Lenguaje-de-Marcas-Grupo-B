/* ==========================================================================
  LÓGICA JAVASCRIPT - maquinas.html
  Interactúa con: ../../api/activos/maquinas.php y ../../api/inventario/piezas.php
=========================================================================== */
let maquinasData = [];
let ordenAsc = true;
let criterioOrden = 'id';

async function cargarMaquinas() {
    const tbody = document.getElementById('tabla-maquinas-body');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px;">Cargando inventario... ⚙️</td></tr>';
    const res = await api('GET', '../../api/activos/maquinas.php');
    if (res && res.ok) {
        maquinasData = res.maquinas || [];
        aplicarOrden();
        filtrarMaquinas();
        actualizarUIOrden();
    } else {
        tbody.innerHTML = `<tr><td colspan="7" class="error-msg">Error: ${res.error || 'No se pudo conectar'}</td></tr>`;
    }
}

function togglePanelFiltros() {
    const p = document.getElementById('panelFiltros');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}

function toggleDropdownOrden(event) {
    event.stopPropagation();
    document.getElementById('dropdownOrden').classList.toggle('show');
}

function cambiarCriterioOrden(criterio) {
    if (criterioOrden === criterio) { ordenAsc = !ordenAsc; }
    else { criterioOrden = criterio; ordenAsc = true; }
    aplicarOrden();
    filtrarMaquinas();
    actualizarUIOrden();
    document.getElementById('dropdownOrden').classList.remove('show');
}

function obtenerRangoEstado(estado) {
    const map = { 'disponible': 1, 'en_mantenimiento': 2, 'averia': 3, 'desactivada': 4 };
    return map[estado] || 5;
}

function aplicarOrden() {
    maquinasData.sort((a, b) => {
        let valA, valB;
        if (criterioOrden === 'nombre') {
            valA = (a.nombre || '').toLowerCase(); valB = (b.nombre || '').toLowerCase();
            return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else if (criterioOrden === 'tipo') {
            valA = (a.tipo || '').toLowerCase(); valB = (b.tipo || '').toLowerCase();
            return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else if (criterioOrden === 'estado') {
            valA = obtenerRangoEstado(a.estado); valB = obtenerRangoEstado(b.estado);
            return ordenAsc ? valA - valB : valB - valA;
        } else {
            valA = parseInt(a.id_maquina); valB = parseInt(b.id_maquina);
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

function limpiarFiltros() {
    document.getElementById('searchMaquina').value = '';
    document.getElementById('filtroEstado').value = '';
    document.getElementById('filtroUbicacion').value = '';
    document.getElementById('filtroTipo').value = '';
    filtrarMaquinas();
}

function filtrarMaquinas() {
    const search = document.getElementById('searchMaquina').value.toLowerCase();
    const estadoF = document.getElementById('filtroEstado').value;
    const ubicF = document.getElementById('filtroUbicacion').value.toLowerCase();
    const tipoF = document.getElementById('filtroTipo').value.toLowerCase();
    const tbody = document.getElementById('tabla-maquinas-body');

    const filtradas = maquinasData.filter(m => {
        const matchSearch = !search || m.nombre.toLowerCase().includes(search) ||
            (m.numero_serie && m.numero_serie.toLowerCase().includes(search)) ||
            (m.fabricante && m.fabricante.toLowerCase().includes(search));
        const matchEstado = !estadoF || m.estado === estadoF;
        const matchUbic = !ubicF || (m.ubicacion && m.ubicacion.toLowerCase().includes(ubicF));
        const matchTipo = !tipoF || (m.tipo && m.tipo.toLowerCase().includes(tipoF));
        return matchSearch && matchEstado && matchUbic && matchTipo;
    });

    document.getElementById('contadorMaquinas').textContent = `${filtradas.length} resultados encontrados`;
    tbody.innerHTML = '';
    if (filtradas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No hay resultados</td></tr>';
        return;
    }
    filtradas.forEach(m => {
        let bClass = 'success';
        if (m.estado === 'en_mantenimiento') bClass = 'warning';
        if (m.estado === 'averia' || m.estado === 'desactivada') bClass = 'danger';
        const estadoTxt = m.estado.charAt(0).toUpperCase() + m.estado.slice(1).replace('_', ' ');
        tbody.innerHTML += `
            <tr>
                <td><strong>MAQ-${m.id_maquina}</strong></td>
                <td><div style="font-weight:bold;">${m.nombre}</div><small style="color:#7f8c8d;">${m.fabricante || 'Sin fabricante'}</small></td>
                <td>${m.tipo || '-'}</td>
                <td><code>${m.numero_serie || 'N/A'}</code></td>
                <td><span class="status-badge ${bClass}">${estadoTxt}</span></td>
                <td>${m.ubicacion || '-'}</td>
                <td class="action-buttons">
                    <button class="btn-icon" title="Ver Piezas" onclick="abrirModalPiezas(${m.id_maquina})">📦</button>
                    <button class="btn-icon" title="Editar" onclick="editarMaquina(${m.id_maquina})">✏️</button>
                    <button class="btn-icon" title="Eliminar" style="color:#e74c3c;" onclick="borrarMaquina(${m.id_maquina})">🗑️</button>
                </td>
            </tr>`;
    });
}

function abrirModalAlta() {
    document.getElementById('modalTitulo').textContent = 'Añadir Nueva Máquina';
    document.getElementById('formMaquina').reset();
    document.getElementById('maq_id').value = '';
    document.getElementById('modalMaquina').classList.add('show');
}

function editarMaquina(id) {
    const m = maquinasData.find(x => x.id_maquina == id);
    if (!m) return;
    document.getElementById('modalTitulo').textContent = 'Editar Máquina';
    document.getElementById('maq_id').value = m.id_maquina;
    document.getElementById('maq_nombre').value = m.nombre;
    document.getElementById('maq_tipo').value = m.tipo || '';
    document.getElementById('maq_serie').value = m.numero_serie || '';
    document.getElementById('maq_fabricante').value = m.fabricante || '';
    document.getElementById('maq_estado').value = m.estado;
    document.getElementById('maq_ubicacion').value = m.ubicacion || '';
    document.getElementById('modalMaquina').classList.add('show');
}

function cerrarModal(id = 'modalMaquina') {
    document.getElementById(id).classList.remove('show');
}

async function guardarMaquina(e) {
    e.preventDefault();
    const id = document.getElementById('maq_id').value;
    const payload = {
        nombre: document.getElementById('maq_nombre').value,
        tipo: document.getElementById('maq_tipo').value,
        numero_serie: document.getElementById('maq_serie').value,
        fabricante: document.getElementById('maq_fabricante').value,
        estado: document.getElementById('maq_estado').value,
        ubicacion: document.getElementById('maq_ubicacion').value
    };
    const res = await api(id ? 'PUT' : 'POST', '../../api/activos/maquinas.php', id ? {...payload, id_maquina: id} : payload);
    if (res.ok) { cerrarModal(); cargarMaquinas(); }
    else alert(res.error || 'Error al guardar');
}

async function borrarMaquina(id) {
    if (!confirm('¿Seguro?')) return;
    const res = await api('DELETE', '../../api/activos/maquinas.php', { id_maquina: id });
    if (res.ok) cargarMaquinas();
    else alert(res.error || 'No se puede eliminar');
}

// ========================================================================
// Piezas - Autor: Sara
// ========================================================================
let maquinaActual = null;

async function abrirModalPiezas(id) {
    maquinaActual = id;
    document.getElementById('modalPiezas').classList.add('show');
    const tbody = document.getElementById('tabla-piezas-modal-body');
    tbody.innerHTML = '<tr><td colspan="3">Cargando...</td></tr>';
    const res = await api('GET', `../../api/inventario/piezas.php?id_maquina=${id}`);
    if (res && res.piezas) {
        tbody.innerHTML = res.piezas.map(p => `
            <tr>
                <td>${p.nombre || 'Sin nombre'}</td>
                <td>${p.stock_actual || 0}</td>
                <td><button class="btn-icon" style="color:red;" onclick="quitarAsignacion(${p.id_pieza})">❌</button></td>
            </tr>`).join('') || '<tr><td colspan="3">Sin piezas</td></tr>';
    }
}

async function quitarAsignacion(id) {
    if (await api('DELETE', `../../api/inventario/piezas.php?id_maquina=${maquinaActual}&id_pieza=${id}`)) {
        abrirModalPiezas(maquinaActual);
    }
}

async function mostrarSeccionAnadirPieza() {
    const s = document.getElementById('seccionAnadirPieza');
    s.style.display = s.style.display === 'none' ? 'block' : 'none';
    if (s.style.display === 'block') {
        const res = await api('GET', '../../api/inventario/pedidos_opciones.php');
        const sel = document.getElementById('selectNuevaPieza');
        sel.innerHTML = res.piezas.map(p => `<option value="${p.id_pieza}">${p.nombre}</option>`).join('');
    }
}

async function asignarPiezaAMaquina() {
    const p = document.getElementById('selectNuevaPieza').value;
    if (await api('POST', '../../api/inventario/piezas.php', {id_maquina: maquinaActual, id_pieza: p})) {
        document.getElementById('seccionAnadirPieza').style.display = 'none';
        abrirModalPiezas(maquinaActual);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    renderMenu();
    cargarMaquinas();
});
