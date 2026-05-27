// =========================================================================
// ARCHIVO: Js/piezas.js
// PROPÓSITO: Gestión del inventario de repuestos (CRUD, búsqueda y control de stock).
// REFERENCIA WEB: HTML/inventarioMenu/piezas.html
// =========================================================================

let piezasData = []; // Array global para almacenar los datos cargados y poder buscar/editar en memoria

document.addEventListener('DOMContentLoaded', () => {
    // Dibujamos el menú lateral/superior común de la aplicación
    renderMenu();
    
    // Cargamos los datos iniciales de las piezas desde la BD
    cargarPiezas();
});

// --- FUNCIONES DE CARGA Y RENDERIZADO ---

/**
 * Obtiene la lista de piezas desde la API y las dibuja en la tabla.
 */
async function cargarPiezas() {
    const tbody = document.getElementById('tabla-piezas-body');

    // Mensaje temporal de carga
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px;">Cargando piezas... ⚙️</td></tr>';

    // Llamada a la API (Método GET)
    const res = await api('GET', '../../api/inventario/piezas.php');

    // Verificamos que la respuesta sea válida
    if (res && res.piezas) {
        tbody.innerHTML = ''; // Limpiamos el mensaje de carga
        piezasData = res.piezas; // Guardamos en la variable global

        if (piezasData.length > 0) {
            // Recorremos los datos y creamos las filas del HTML una a una
            piezasData.forEach(p => {
                const id = p.id_pieza || 'N/A';
                const nombre = p.nombre || 'Sin nombre';
                const cod_interno = p.codigo_interno || '-';
                const cod_proveedor = p.codigo_proveedor || '-';
                const stock = parseInt(p.stock_actual) || 0;
                const min = parseInt(p.stock_min) || 5;

                // Lógica para el estado del stock (colores)
                let claseEtiqueta = 'exito'; // Verde
                let estadoTexto = 'En Stock';
                
                if (stock <= 0) {
                    claseEtiqueta = 'peligro'; // Rojo
                    estadoTexto = 'Agotado';
                } else if (stock <= min) {
                    claseEtiqueta = 'advertencia'; // Amarillo
                    estadoTexto = 'Stock Bajo';
                }

                // Creamos la fila (<tr>) y le inyectamos las celdas (<td>)
                const fila = document.createElement('tr');
                fila.innerHTML = `
                    <td class="col-id">${id}</td>
                    <td>${cod_interno}</td>
                    <td><strong>${nombre}</strong></td>
                    <td>${cod_proveedor}</td>
                    <td>${stock} uds.</td>
                    <td><span class="etiqueta-estado ${claseEtiqueta}">${estadoTexto}</span></td>
                    <td class="action-buttons justify-center">
                        <button class="btn-icon" title="Editar" onclick="editarPieza(${id})">✏️</button>
                        <button class="btn-icon" title="Eliminar" style="color: #e74c3c;" onclick="borrarPieza(${id})">🗑️</button>
                    </td>
                `;
                
                // Añadimos la fila al cuerpo de la tabla
                tbody.appendChild(fila);
            });
            // Una vez cargadas las piezas, inicializamos las opciones del desplegable de filtrado
            cargarFiltrosPiezas();
        } else {
            // Si la base de datos está vacía
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px;">No hay piezas registradas.</td></tr>';
        }
    } else {
        // Si hay un error en el servidor o BBDD caída
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px; color: #e74c3c;">
            <strong>⚠️ Error al conectar con la base de datos.</strong>
        </td></tr>`;
    }
}

// --- FUNCIONES DEL BUSCADOR ---

/**
 * Rellena el desplegable de filtros con opciones basadas en todos los campos disponibles.
 */
function cargarFiltrosPiezas() {
    const select = document.getElementById("filtroPiezas");
    if (!select) return;

    select.innerHTML = `
        <option value="todos">Mostrar Todo (Sin filtros)</option>
        <optgroup label="Filtrar por Estado">
            <option value="estado:exito">En Stock</option>
            <option value="estado:advertencia">Stock Bajo</option>
            <option value="estado:peligro">Agotado</option>
        </optgroup>
        <optgroup label="Filtrar por Existencias">
            <option value="stock:con">Con existencias (>0)</option>
            <option value="stock:sin">Sin existencias (=0)</option>
        </optgroup>
        <optgroup label="Filtrar por Códigos">
            <option value="codigo:si">Con Código Interno</option>
            <option value="codigo:no">Sin Código Interno</option>
        </optgroup>
    `;
}

/**
 * Filtra las piezas mostradas en la tabla según la opción del desplegable único.
 */
function filtrarPiezas() {
    const input = document.getElementById("busquedaPieza");
    const textoBuscado = input.value.toUpperCase(); // Pasamos a mayúsculas para comparar sin importar minúsculas
    const filtro = document.getElementById("filtroPiezas").value; // Obtenemos el filtro seleccionado
    const filas = document.getElementById("tabla-piezas-body").getElementsByTagName("tr");

    // Recorremos todas las filas de la tabla de piezas
    for (let i = 0; i < filas.length; i++) {
        const fila = filas[i];
        
        // Omitimos filas especiales que no contienen datos (ej. "Cargando..." o "No hay piezas...")
        if (fila.cells.length === 1) continue;

        // 1. Comprobamos la coincidencia del texto en cualquier campo de la fila
        const textoFila = fila.textContent || fila.innerText;
        const coincideTexto = textoFila.toUpperCase().includes(textoBuscado);

        // 2. Comprobamos si coincide con el filtro desplegable seleccionado
        let coincideFiltro = true;
        if (filtro !== "todos") {
            const [criterio, valor] = filtro.split(":");
            if (criterio === "estado") {
                const etiqueta = fila.querySelector(".etiqueta-estado");
                coincideFiltro = etiqueta && etiqueta.classList.contains(valor);
            } else if (criterio === "stock") {
                const stockTexto = fila.cells[4].textContent.trim(); // Quinta celda: "X uds."
                const stockVal = parseInt(stockTexto) || 0;
                coincideFiltro = (valor === "con" && stockVal > 0) || (valor === "sin" && stockVal === 0);
            } else if (criterio === "codigo") {
                const codigoVal = fila.cells[1].textContent.trim(); // Segunda celda: Código Interno
                const tieneCodigo = codigoVal !== "" && codigoVal !== "-";
                coincideFiltro = (valor === "si" && tieneCodigo) || (valor === "no" && !tieneCodigo);
            }
        }

        // Mostramos la fila solo si cumple con ambos criterios (texto y filtro desplegable)
        if (coincideTexto && coincideFiltro) {
            fila.style.display = "";
        } else {
            fila.style.display = "none";
        }
    }
}

// --- FUNCIONES DEL MODAL (AÑADIR / EDITAR) ---

/**
 * Abre el formulario (modal) totalmente limpio para crear una NUEVA pieza.
 */
function abrirModalPieza() {
    document.getElementById('modalTitulo').textContent = 'Añadir Nueva Pieza';
    document.getElementById('formPieza').reset();
    document.getElementById('piez_id').value = ''; // ID vacío significa "Crear"
    document.getElementById('modalPieza').classList.add('show');
}

/**
 * Abre el formulario (modal) con los datos cargados para EDITAR una pieza existente.
 */
function editarPieza(id) {
    // Buscamos la pieza en nuestro array cargado previamente
    const piez = piezasData.find(p => p.id_pieza == id);
    if (!piez) return;

    // Rellenamos el formulario con los datos encontrados
    document.getElementById('modalTitulo').textContent = 'Editar Pieza';
    document.getElementById('piez_id').value = piez.id_pieza;
    document.getElementById('piez_nombre').value = piez.nombre || '';
    document.getElementById('piez_cod_int').value = piez.codigo_interno || '';
    document.getElementById('piez_cod_prov').value = piez.codigo_proveedor || '';
    document.getElementById('piez_stock').value = piez.stock_actual || 0;
    document.getElementById('piez_stock_min').value = piez.stock_min || 5;
    document.getElementById('piez_precio').value = piez.precio_unitario || 0.00;

    // Mostramos el modal
    document.getElementById('modalPieza').classList.add('show');
}

/**
 * Oculta y cierra el formulario (modal).
 */
function cerrarModal() {
    document.getElementById('modalPieza').classList.remove('show');
}

// --- FUNCIONES CRUD A LA API ---

/**
 * Envía los datos del formulario a la API para crear o actualizar la pieza.
 */
async function guardarPieza(evento) {
    evento.preventDefault(); // Evita que la página se recargue sola al darle a "Guardar"

    // Recogemos lo que el usuario ha escrito en el formulario
    const id = document.getElementById('piez_id').value;
    const datos = {
        nombre: document.getElementById('piez_nombre').value.trim(),
        codigo_interno: document.getElementById('piez_cod_int').value.trim(),
        codigo_proveedor: document.getElementById('piez_cod_prov').value.trim(),
        stock_actual: parseInt(document.getElementById('piez_stock').value) || 0,
        stock_min: parseInt(document.getElementById('piez_stock_min').value) || 5,
        precio_unitario: parseFloat(document.getElementById('piez_precio').value) || 0.00
    };

    // Si hay un ID en el formulario oculto, significa que estamos editando (PUT)
    // Si está vacío, estamos creando una nueva (POST)
    let metodo = 'POST';
    if (id !== '') {
        metodo = 'PUT';
        datos.id_pieza = id;
    }

    // Enviamos la petición a la API
    const res = await api(metodo, '../../api/inventario/piezas.php', datos);

    // Verificamos si todo ha ido bien
    if (res && res.ok !== false) {
        cerrarModal();          // 1. Ocultamos el modal
        cargarPiezas();         // 2. Recargamos la tabla para ver los cambios
        document.getElementById('busquedaPieza').value = '';     // 3. Limpiamos la búsqueda por texto
        document.getElementById('filtroPiezas').value = 'todos'; // 4. Reiniciamos el filtro
    } else {
        alert('Error: ' + (res.error || 'No se pudo guardar la pieza'));
    }
}

/**
 * Elimina de manera permanente una pieza.
 */
async function borrarPieza(id) {
    // Pedimos confirmación para evitar borrados accidentales
    if (confirm('¿Estás seguro de que deseas eliminar esta pieza? Se borrará de todas las máquinas a las que esté asignada.')) {
        
        // Llamada a la API con método DELETE
        const res = await api('DELETE', '../../api/inventario/piezas.php', { id_pieza: id });
        
        if (res && res.ok !== false) {
            cargarPiezas(); // Recargamos para que desaparezca visualmente
        } else {
            alert('Error al eliminar: ' + (res.error || 'No se pudo borrar la pieza'));
        }
    }
}
