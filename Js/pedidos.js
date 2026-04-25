// ============================================================
//  Js/pedidos.js - Lógica para el módulo de Pedidos de Compra
// ============================================================

let currentIdPedido = null;
let piezasGlobal = [];
let pedidosGlobal = {}; // Para guardar los pedidos agrupados

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

    if (res.piezas) {
        piezasGlobal = res.piezas;
    }
}

// Actualiza el input de precio unitario basado en la pieza seleccionada en esa fila
function actualizarPrecioUnitario(selectElement) {
    const fila = selectElement.closest('.detalle-fila');
    const inputPrecio = fila.querySelector('.precio-input');
    const id_pieza = selectElement.value;
    
    if (id_pieza && piezasGlobal) {
        const pieza = piezasGlobal.find(p => p.id_pieza == id_pieza);
        if (pieza) {
            inputPrecio.value = pieza.precio_unitario || 0;
        }
    } else {
        inputPrecio.value = '';
    }
}

// Añade una fila de detalle al contenedor
function agregarFilaDetalle(idPieza = '', cantSol = 1, cantRec = 0, precioUnit = '') {
    const contenedor = document.getElementById('contenedor-detalles');
    
    let opcionesPiezas = '<option value="">Seleccione una pieza...</option>';
    piezasGlobal.forEach(p => {
        const sel = p.id_pieza == idPieza ? 'selected' : '';
        opcionesPiezas += `<option value="${p.id_pieza}" ${sel}>${p.nombre} (${p.precio_unitario || 0}€)</option>`;
    });

    const divFila = document.createElement('div');
    divFila.className = 'detalle-fila';
    divFila.style = 'display: flex; gap: 10px; margin-bottom: 15px; align-items: flex-end;';
    
    divFila.innerHTML = `
        <div class="form-group" style="flex: 2;">
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:0.9em;">Pieza:</label>
            <select class="pieza-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required onchange="actualizarPrecioUnitario(this)">
                ${opcionesPiezas}
            </select>
        </div>
        <div class="form-group" style="flex: 1;">
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:0.9em;">Cant. Sol:</label>
            <input type="number" class="cantsol-input" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" min="1" value="${cantSol}" required>
        </div>
        <div class="form-group" style="flex: 1;">
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:0.9em;">Cant. Rec:</label>
            <input type="number" class="cantrec-input" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" min="0" value="${cantRec}">
        </div>
        <div class="form-group" style="flex: 1;">
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:0.9em;">Precio (€):</label>
            <input type="number" class="precio-input" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" step="0.01" min="0" value="${precioUnit}">
        </div>
        <div>
            <button type="button" onclick="this.closest('.detalle-fila').remove()" style="background: #e74c3c; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;" title="Eliminar fila">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    contenedor.appendChild(divFila);
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

    const pedidosCrudos = res.pedidos;
    tbody.innerHTML = '';

    if (pedidosCrudos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No hay pedidos registrados.</td></tr>';
        return;
    }

    // Agrupar pedidos
    pedidosGlobal = {};
    pedidosCrudos.forEach(p => {
        if (!pedidosGlobal[p.id_pedido]) {
            pedidosGlobal[p.id_pedido] = { ...p, detalles: [] };
        }
        if (p.id_pieza) {
            pedidosGlobal[p.id_pedido].detalles.push({
                id_pieza: p.id_pieza,
                nombre_pieza: p.nombre_pieza,
                cantidad_solicitada: p.cantidad_solicitada,
                cantidad_recibida: p.cantidad_recibida,
                precio_unitario: p.precio_unitario
            });
        }
    });

    const pedidosAgrupados = Object.values(pedidosGlobal);

    pedidosAgrupados.forEach(p => {
        const fecha = new Date(p.fecha_pedido).toLocaleDateString();
        const fechaReal = p.fecha_entrega_real ? new Date(p.fecha_entrega_real).toLocaleDateString() : '-';
        const importeStr = parseFloat(p.importe_total).toFixed(2) + '€';
        
        let colorEstado = '#95a5a6';
        const nomEstado = (p.nombre_estado || '').toLowerCase();
        if (nomEstado.includes('pendiente')) colorEstado = '#f1c40f';
        else if (nomEstado.includes('recibido') || nomEstado.includes('completado')) colorEstado = '#2ecc71';
        else if (nomEstado.includes('cancelado')) colorEstado = '#e74c3c';
        
        let textoPiezas = '-';
        if (p.detalles.length === 1) {
            textoPiezas = p.detalles[0].nombre_pieza;
        } else if (p.detalles.length > 1) {
            textoPiezas = 'Varias piezas (' + p.detalles.length + ')';
        }
        
        const filaHTML = `
            <tr>
                <td>#PED-${p.id_pedido.toString().padStart(3, '0')}</td>
                <td>${fecha}</td>
                <td>${fechaReal}</td>
                <td>${p.nombre_proveedor || 'Sin proveedor'}</td>
                <td>${textoPiezas}</td>
                <td>${importeStr}</td>
                <td><span style="background: ${colorEstado}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8em;">${p.nombre_estado || 'N/A'}</span></td>
                <td>
                    <button title="Ver Desglose" style="background:none; border:none; color:#2ecc71; cursor:pointer; margin-right: 5px;" onclick="abrirModalDesglose(${p.id_pedido})"><i class="fas fa-list"></i></button>
                    <button title="Editar Pedido" style="background:none; border:none; color:#3498db; cursor:pointer; margin-right: 5px;" onclick="abrirModalEdicion(${p.id_pedido})"><i class="fas fa-edit"></i></button>
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
    
    // Recopilar detalles
    const detalles = [];
    const filasDetalle = document.querySelectorAll('.detalle-fila');
    let errorDetalle = false;
    
    filasDetalle.forEach(fila => {
        const id_pieza = fila.querySelector('.pieza-select').value;
        const cantidad_solicitada = fila.querySelector('.cantsol-input').value;
        const cantidad_recibida = fila.querySelector('.cantrec-input').value;
        const precio_unitario = fila.querySelector('.precio-input').value;
        
        if (!id_pieza) {
            errorDetalle = true;
        } else {
            detalles.push({
                id_pieza,
                cantidad_solicitada,
                cantidad_recibida,
                precio_unitario
            });
        }
    });

    if (!id_proveedor) {
        alert("El proveedor es obligatorio.");
        return;
    }
    
    if (errorDetalle) {
        alert("Asegúrate de haber seleccionado una pieza en cada fila de detalle.");
        return;
    }

    const cuerpo = {
        id_proveedor,
        fecha_pedido,
        fecha_entrega_esperada,
        id_estado,
        observaciones,
        detalles
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
    
    document.getElementById('contenedor-detalles').innerHTML = '';
    agregarFilaDetalle(); // Al menos 1 vacía
    
    document.getElementById('modalNuevoPedido').style.display = 'block';
}

// Abre el modal para Editar (carga los datos existentes)
function abrirModalEdicion(id) {
    const pedido = pedidosGlobal[id];
    if (!pedido) return;
    
    currentIdPedido = id;
    document.getElementById('modalTitulo').textContent = 'Editar Pedido #PED-' + id.toString().padStart(3, '0');
    
    document.getElementById('id_proveedor').value = pedido.id_proveedor || '';
    document.getElementById('fecha_pedido').value = pedido.fecha_pedido || '';
    document.getElementById('fecha_entrega_esperada').value = pedido.fecha_entrega_esperada || '';
    
    const inputEst = document.getElementById('id_estado');
    if (inputEst) inputEst.value = pedido.id_estado || '';
    
    document.getElementById('observaciones').value = pedido.observaciones || '';
    
    const contenedor = document.getElementById('contenedor-detalles');
    contenedor.innerHTML = '';
    
    if (pedido.detalles && pedido.detalles.length > 0) {
        pedido.detalles.forEach(d => {
            agregarFilaDetalle(d.id_pieza, d.cantidad_solicitada, d.cantidad_recibida, d.precio_unitario);
        });
    } else {
        agregarFilaDetalle(); // 1 vacía
    }
    
    document.getElementById('modalNuevoPedido').style.display = 'block';
}

// Abrir modal de desglose
function abrirModalDesglose(id) {
    const pedido = pedidosGlobal[id];
    if (!pedido) return;
    
    document.getElementById('modalDesgloseTitulo').textContent = 'Desglose del Pedido #PED-' + id.toString().padStart(3, '0');
    const tbody = document.getElementById('tablaDesgloseBody');
    tbody.innerHTML = '';
    
    if (pedido.detalles && pedido.detalles.length > 0) {
        let totalPedido = 0;
        pedido.detalles.forEach(d => {
            const subtotal = parseFloat(d.cantidad_solicitada * (d.precio_unitario || 0));
            totalPedido += subtotal;
            tbody.innerHTML += `
                <tr>
                    <td>${d.nombre_pieza}</td>
                    <td style="text-align: center;">${d.cantidad_solicitada}</td>
                    <td style="text-align: center;">${d.cantidad_recibida}</td>
                    <td style="text-align: right;">${parseFloat(d.precio_unitario || 0).toFixed(2)}€</td>
                    <td style="text-align: right;">${subtotal.toFixed(2)}€</td>
                </tr>
            `;
        });
        
        tbody.innerHTML += `
            <tr style="font-weight: bold;">
                <td colspan="4" style="text-align: right;">TOTAL ESTIMADO:</td>
                <td style="text-align: right; color: var(--primary);">${totalPedido.toFixed(2)}€</td>
            </tr>
        `;
    } else {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Este pedido no tiene piezas asociadas.</td></tr>';
    }
    
    document.getElementById('modalDesglose').style.display = 'block';
}

function cerrarModalDesglose() {
    document.getElementById('modalDesglose').style.display = 'none';
}

// Cierra el modal
function cerrarModal() {
    document.getElementById('modalNuevoPedido').style.display = 'none';
}
