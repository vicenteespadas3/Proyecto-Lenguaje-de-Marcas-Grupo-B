/* ==========================================================================
  LÓGICA JAVASCRIPT - archivos.html
  Interactúa con: ../../api/activos/archivos.php
=========================================================================== */
let archivosData = [];
let ordenAsc = true;
let criterioOrden = 'id';

async function cargarArchivos() {
    const tbody = document.getElementById('tabla-archivos-body');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">Cargando archivos... 📁</td></tr>';
    const res = await api('GET', '../../api/activos/archivos.php');
    if (res && res.ok) {
        archivosData = res.archivos || [];
        aplicarOrden();
        filtrarArchivos();
        actualizarUIOrden();
    } else {
        tbody.innerHTML = '<tr><td colspan="6" class="error-msg">Error al conectar con la base de datos.</td></tr>';
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
    filtrarArchivos();
    actualizarUIOrden();
    document.getElementById('dropdownOrden').classList.remove('show');
}

function aplicarOrden() {
    archivosData.sort((a, b) => {
        let valA, valB;
        if (criterioOrden === 'nombre') {
            valA = (a.nombre_archivo || '').toLowerCase(); valB = (b.nombre_archivo || '').toLowerCase();
            return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else if (criterioOrden === 'tipo') {
            valA = (a.tipo || '').toLowerCase(); valB = (b.tipo || '').toLowerCase();
            return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else if (criterioOrden === 'fecha') {
            valA = a.fecha_subida || ''; valB = b.fecha_subida || '';
            return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else {
            valA = parseInt(a.id_archivo); valB = parseInt(b.id_archivo);
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
    document.getElementById('searchArchivo').value = '';
    document.getElementById('filtroTipo').value = '';
    filtrarArchivos();
}

function filtrarArchivos() {
    const search = document.getElementById('searchArchivo').value.toLowerCase().trim();
    const tipoF = document.getElementById('filtroTipo').value;
    const tbody = document.getElementById('tabla-archivos-body');

    const filtrados = archivosData.filter(doc => {
        const matchSearch = !search ||
            (doc.nombre_archivo || '').toLowerCase().includes(search) ||
            (doc.maquina || '').toLowerCase().includes(search);
        const matchTipo = !tipoF || doc.tipo === tipoF;
        return matchSearch && matchTipo;
    });

    document.getElementById('contadorArchivos').textContent = `${filtrados.length} archivos encontrados`;
    tbody.innerHTML = '';
    if (filtrados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No se encontraron resultados.</td></tr>';
        return;
    }
    filtrados.forEach(doc => {
        const vinculo = doc.maquina || (doc.id_maquina ? `Máquina ${doc.id_maquina}` : 'General');
        let fecha = doc.fecha_subida || '';
        if (fecha.includes(' ')) fecha = fecha.split(' ')[0];
        tbody.innerHTML += `
            <tr>
                <td><strong>DOC-${doc.id_archivo}</strong></td>
                <td>${doc.nombre_archivo}</td>
                <td><span class="status-badge" style="background:#34495e; color:white;">${doc.tipo || 'PDF'}</span></td>
                <td>${vinculo}</td>
                <td>${fecha}</td>
                <td class="action-buttons">
                    <button class="btn-icon" title="Editar" onclick="editarArchivo(${doc.id_archivo})">✏️</button>
                    <button class="btn-icon" title="Eliminar" style="color: #e74c3c;" onclick="eliminarArchivo(${doc.id_archivo})">🗑️</button>
                </td>
            </tr>`;
    });
}

function subirArchivo() {
    document.getElementById('modalTituloArch').textContent = 'Subir Archivo';
    document.getElementById('formArchivo').reset();
    document.getElementById('arch_id').value = '';
    document.getElementById('modalArchivo').classList.add('show');
}

function editarArchivo(id) {
    const a = archivosData.find(x => x.id_archivo == id);
    if (!a) return;
    document.getElementById('modalTituloArch').textContent = 'Editar Archivo';
    document.getElementById('arch_id').value = a.id_archivo;
    document.getElementById('arch_nombre').value = a.nombre_archivo || '';
    document.getElementById('arch_tipo').value = a.tipo || 'PDF';
    document.getElementById('arch_maquina').value = a.id_maquina || '';
    document.getElementById('modalArchivo').classList.add('show');
}

function cerrarModalArch() {
    document.getElementById('modalArchivo').classList.remove('show');
}

async function guardarArchivo(e) {
    e.preventDefault();
    const id = document.getElementById('arch_id').value;
    const datos = {
        nombre_archivo: document.getElementById('arch_nombre').value,
        tipo: document.getElementById('arch_tipo').value,
        id_maquina: document.getElementById('arch_maquina').value || null
    };
    const res = await api(id ? 'PUT' : 'POST', '../../api/activos/archivos.php', id ? {...datos, id_archivo: id} : datos);
    if (res.ok) { cerrarModalArch(); cargarArchivos(); }
    else alert('Error: ' + res.error);
}

async function eliminarArchivo(id) {
    if (!confirm('¿Seguro?')) return;
    const res = await api('DELETE', '../../api/activos/archivos.php', { id_archivo: id });
    if (res.ok) cargarArchivos();
    else alert('Error: ' + res.error);
}

document.addEventListener('DOMContentLoaded', () => {
    renderMenu('activos');
    cargarArchivos();
});
