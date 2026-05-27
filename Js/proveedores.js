/* ==========================================================================
  LÓGICA JAVASCRIPT (Controlador del Frontend)
  Interactúa con: ../../api/activos/proveedores.php
=========================================================================== */
let proveedoresData = []; // Caché de datos traídos de la API
let ordenAsc = true;      // Estado de orden (ascendente/descendente)
let criterioOrden = 'id'; // Columna seleccionada para ordenar

/**
 * Llama a la API (GET) para obtener todos los proveedores
 * y repinta la tabla al finalizar.
 */
async function cargarProveedores() {
    const tbody = document.getElementById('tabla-proveedores-body');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">Cargando directorio... 🚚</td></tr>';
    const res = await api('GET', '../../api/activos/proveedores.php');
    if (res && res.ok) {
        proveedoresData = res.proveedores || [];
        aplicarOrden();
        filtrarProveedores();
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
    const dropdown = document.getElementById('dropdownOrden');
    dropdown.classList.toggle('show');
}

function cambiarCriterioOrden(criterio) {
    if (criterioOrden === criterio) {
        ordenAsc = !ordenAsc; // Invierte el orden si es el mismo criterio
    } else {
        criterioOrden = criterio;
        ordenAsc = true; // Por defecto ascendente al cambiar de criterio
    }
    aplicarOrden();
    filtrarProveedores();
    actualizarUIOrden();
    
    // Cerrar el dropdown después de elegir
    document.getElementById('dropdownOrden').classList.remove('show');
}

function aplicarOrden() {
    proveedoresData.sort((a, b) => {
        let valA, valB;
        if (criterioOrden === 'empresa') {
            valA = (a.empresa || '').toLowerCase();
            valB = (b.empresa || '').toLowerCase();
            return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else if (criterioOrden === 'contacto') {
            valA = (a.contacto || '').toLowerCase();
            valB = (b.contacto || '').toLowerCase();
            return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else if (criterioOrden === 'equipos') {
            valA = (a.equipos || '').toLowerCase();
            valB = (b.equipos || '').toLowerCase();
            return ordenAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
        } else { // id
            valA = parseInt(a.id_proveedor);
            valB = parseInt(b.id_proveedor);
            return ordenAsc ? valA - valB : valB - valA;
        }
    });
}

function actualizarUIOrden() {
    // Limpiar clases active y checkmarks
    document.querySelectorAll('.dropdown-orden-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelectorAll('.check-icon').forEach(icon => {
        icon.textContent = '';
    });

    // Establecer el activo
    const activeItem = document.querySelector(`.dropdown-orden-item[onclick*="'${criterioOrden}'"]`);
    if (activeItem) {
        activeItem.classList.add('active');
        const icon = activeItem.querySelector('.check-icon');
        if (icon) {
            icon.textContent = ordenAsc ? '▲' : '▼';
        }
    }
}

// Cerrar dropdown al hacer click fuera
window.addEventListener('click', (e) => {
    const dropdown = document.getElementById('dropdownOrden');
    if (dropdown && !e.target.closest('.dropdown-ordenar-container')) {
        dropdown.classList.remove('show');
    }
});

function limpiarFiltros() {
    document.getElementById('searchProveedor').value = '';
    document.getElementById('filtroEquipos').value = '';
    filtrarProveedores();
}

/**
 * Filtra la variable global `proveedoresData` usando los inputs de búsqueda
 * y dibuja los resultados dinámicamente en el tbody de la tabla.
 */
function filtrarProveedores() {
    const search = document.getElementById('searchProveedor').value.toLowerCase().trim();
    const equiposF = document.getElementById('filtroEquipos').value.toLowerCase().trim();
    const tbody = document.getElementById('tabla-proveedores-body');

    const filtrados = proveedoresData.filter(p => {
        const matchSearch = !search || 
            (p.empresa || '').toLowerCase().includes(search) || 
            (p.contacto || '').toLowerCase().includes(search) || 
            (p.email || '').toLowerCase().includes(search);
        const matchEquipos = !equiposF || (p.equipos || '').toLowerCase().includes(equiposF);
        return matchSearch && matchEquipos;
    });

    document.getElementById('contadorProveedores').textContent = `${filtrados.length} proveedores encontrados`;

    tbody.innerHTML = '';
    if (filtrados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No se encontraron resultados.</td></tr>';
        return;
    }

    filtrados.forEach(p => {
        const telEmail = (p.telefono && p.email) ? `${p.telefono} <br> <small>${p.email}</small>` : (p.telefono || p.email || '-');
        tbody.innerHTML += `
            <tr>
                <td><strong>PRV-${p.id_proveedor}</strong></td>
                <td><div style="font-weight:bold;">${p.empresa}</div></td>
                <td>${p.contacto || '-'}</td>
                <td>${telEmail}</td>
                <td>${p.equipos || 'Variados'}</td>
                <td class="action-buttons">
                    <button class="btn-icon" title="Editar" onclick="editarProveedor(${p.id_proveedor})">✏️</button>
                    <button class="btn-icon" title="Eliminar" style="color: #e74c3c;" onclick="borrarProveedor(${p.id_proveedor})">🗑️</button>
                </td>
            </tr>
        `;
    });
}

/**
 * Abre el modal preparado para registrar un NUEVO proveedor.
 */
function nuevoProveedor() {
    document.getElementById('modalTituloProv').textContent = 'Nuevo Proveedor';
    document.getElementById('formProveedor').reset();
    document.getElementById('prov_id').value = '';
    document.getElementById('modalProveedor').classList.add('show');
}

/**
 * Abre el modal y rellena los inputs con los datos de un proveedor EXISTENTE (edición).
 * @param {number} id - Identificador del proveedor a editar.
 */
function editarProveedor(id) {
    const p = proveedoresData.find(x => x.id_proveedor == id);
    if (!p) return;
    document.getElementById('modalTituloProv').textContent = 'Editar Proveedor';
    document.getElementById('prov_id').value = p.id_proveedor;
    document.getElementById('prov_empresa').value = p.empresa || '';
    document.getElementById('prov_contacto').value = p.contacto || '';
    document.getElementById('prov_telefono').value = p.telefono || '';
    document.getElementById('prov_email').value = p.email || '';
    document.getElementById('prov_equipos').value = p.equipos || '';
    document.getElementById('modalProveedor').classList.add('show');
}

function cerrarModalProv() {
    document.getElementById('modalProveedor').classList.remove('show');
}

/**
 * Evento de guardado del modal de proveedor (nuevo o editado).
 * Llama a: api/activos/proveedores.php por POST (nuevo) o PUT (editar).
 */
async function guardarProveedor(e) {
    e.preventDefault();
    const id = document.getElementById('prov_id').value;
    const datos = {
        empresa: document.getElementById('prov_empresa').value,
        contacto: document.getElementById('prov_contacto').value,
        telefono: document.getElementById('prov_telefono').value,
        email: document.getElementById('prov_email').value,
        equipos: document.getElementById('prov_equipos').value
    };
    const res = await api(id ? 'PUT' : 'POST', '../../api/activos/proveedores.php', id ? {...datos, id_proveedor: id} : datos);
    if (res.ok) { cerrarModalProv(); cargarProveedores(); }
    else alert('Error: ' + res.error);
}

/**
 * Elimina un proveedor.
 * Llama a: api/activos/proveedores.php usando DELETE.
 */
async function borrarProveedor(id) {
    if (!confirm('¿Estás seguro?')) return;
    const res = await api('DELETE', '../../api/activos/proveedores.php', { id_proveedor: id });
    if (res.ok) cargarProveedores();
    else alert('Error: ' + res.error);
}

document.addEventListener('DOMContentLoaded', () => {
    renderMenu('activos'); 
    cargarProveedores();
});
