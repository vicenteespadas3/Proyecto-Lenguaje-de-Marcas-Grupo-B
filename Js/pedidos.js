// ============================================================
//  Js/pedidos.js - Lógica para el módulo de Pedidos de Compra
// ============================================================

let currentIdPedido = null;

// Cuando cargue la página, cargar opciones y pedidos
document.addEventListener('DOMContentLoaded', async () => {
    await cargarOpciones();
    await cargarPedidos();
});

// Función para obtener las opciones para los <select> (Proveedores y Estados)
async function cargarOpciones() {
    const res = await api('GET', '../../api/inventario/pedidos_opciones.php');
    if (!res || !res.ok) {
        console.error("Error al cargar opciones");
        return;
    }

    const selectProv = document.getElementById('id_proveedor');
    if (selectProv && res.proveedores) {
        selectProv.innerHTML = '<option value="">Seleccione un proveedor...</option>';
        res.proveedores.forEach(p => {
            selectProv.innerHTML += `<option value="${p.id_proveedor}">${p.nombre}</option>`;
        });
    }

    const selectEst = document.getElementById('id_estado');
    if (selectEst && res.estados) {
        selectEst.innerHTML = '<option value="">Estado por defecto</option>';
        res.estados.forEach(e => {
            selectEst.innerHTML += `<option value="${e.id_definicion}">${e.valor}</option>`;
        });
    }
}

// Función para obtener todos los pedidos y pintarlos en la tabla
async function cargarPedidos() {
    const tbody = document.getElementById('tablaPedidosBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Cargando pedidos...</td></tr>';

    const res = await api('GET', '../../api/inventario/pedidos.php');
    
    if (!res || !res.ok) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:red;">Error: ${res?.error || 'No se pudo conectar'}</td></tr>`;
        return;
    }

    const pedidos = res.pedidos;
    tbody.innerHTML = '';

    if (pedidos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay pedidos registrados.</td></tr>';
        return;
    }

    pedidos.forEach(p => {
        // Formatear fechas
        const fecha = new Date(p.fecha_pedido).toLocaleDateString();
        
        // Formatear importe
        const importeStr = parseFloat(p.importe_total).toFixed(2) + '€';
        
        // Color del estado (Lógica básica)
        let colorEstado = '#95a5a6'; // Gris por defecto
        const nomEstado = (p.nombre_estado || '').toLowerCase();
        if (nomEstado.includes('pendiente')) colorEstado = '#f1c40f'; // Amarillo
        else if (nomEstado.includes('recibido') || nomEstado.includes('completado')) colorEstado = '#2ecc71'; // Verde
        else if (nomEstado.includes('cancelado')) colorEstado = '#e74c3c'; // Rojo
        
        const filaHTML = `
            <tr>
                <td>#PED-${p.id_pedido.toString().padStart(3, '0')}</td>
                <td>${fecha}</td>
                <td>${p.nombre_proveedor || 'Sin proveedor'}</td>
                <td>${importeStr}</td>
                <td><span style="background: ${colorEstado}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8em;">${p.nombre_estado || 'N/A'}</span></td>
                <td>
                    <button title="Editar Pedido" style="background:none; border:none; color:#3498db; cursor:pointer;" onclick="abrirModalEdicion(${p.id_pedido}, '${p.id_proveedor}', '${p.fecha_pedido}', '${p.fecha_entrega_esperada || ''}', '${p.id_estado}', '${p.observaciones || ''}')"><i class="fas fa-edit"></i></button>
                    <button title="Eliminar Pedido" style="background:none; border:none; color:#e74c3c; cursor:pointer;" onclick="eliminarPedido(${p.id_pedido})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', filaHTML);
    });
}

// Función para guardar el pedido (Crear o Actualizar)
async function guardarPedido(event) {
    event.preventDefault(); // Evitar que el formulario recargue la página

    const id_proveedor = document.getElementById('id_proveedor').value;
    const fecha_pedido = document.getElementById('fecha_pedido').value;
    const fecha_entrega_esperada = document.getElementById('fecha_entrega_esperada').value;
    const id_estado = document.getElementById('id_estado')?.value || '';
    const observaciones = document.getElementById('observaciones').value;

    if (!id_proveedor) {
        alert("El proveedor es obligatorio.");
        return;
    }

    const cuerpo = {
        id_proveedor,
        fecha_pedido,
        fecha_entrega_esperada,
        id_estado,
        observaciones
    };

    let metodo = 'POST'; // Por defecto crear
    if (currentIdPedido !== null) {
        metodo = 'PUT'; // Actualizar
        cuerpo.id_pedido = currentIdPedido;
    }

    const res = await api(metodo, '../../api/inventario/pedidos.php', cuerpo);

    if (res && res.ok) {
        cerrarModal();
        cargarPedidos();
    } else {
        alert("Error al guardar: " + (res?.error || 'Desconocido'));
    }
}

// Función para eliminar un pedido
async function eliminarPedido(id_pedido) {
    if (confirm('¿Estás seguro de que deseas eliminar este pedido? Se borrarán sus detalles asociados.')) {
        const res = await api('DELETE', '../../api/inventario/pedidos.php', { id_pedido });
        if (res && res.ok) {
            cargarPedidos();
        } else {
            alert("Error al eliminar: " + (res?.error || 'Desconocido'));
        }
    }
}

// Abre el modal para Crear Nuevo
function abrirModalCrear() {
    currentIdPedido = null;
    document.getElementById('modalTitulo').textContent = 'Generar Pedido de Compra';
    document.getElementById('formPedido').reset();
    
    // Poner fecha de hoy por defecto
    document.getElementById('fecha_pedido').value = new Date().toISOString().split('T')[0];
    
    document.getElementById('modalNuevoPedido').style.display = 'block';
}

// Abre el modal para Editar (carga los datos existentes)
function abrirModalEdicion(id, idProv, fPed, fEnt, idEst, obs) {
    currentIdPedido = id;
    document.getElementById('modalTitulo').textContent = 'Editar Pedido #PED-' + id.toString().padStart(3, '0');
    
    document.getElementById('id_proveedor').value = idProv || '';
    document.getElementById('fecha_pedido').value = fPed || '';
    document.getElementById('fecha_entrega_esperada').value = fEnt || '';
    
    const inputEst = document.getElementById('id_estado');
    if (inputEst) inputEst.value = idEst || '';
    
    document.getElementById('observaciones').value = obs || '';
    
    document.getElementById('modalNuevoPedido').style.display = 'block';
}

// Cierra el modal
function cerrarModal() {
    document.getElementById('modalNuevoPedido').style.display = 'none';
}
