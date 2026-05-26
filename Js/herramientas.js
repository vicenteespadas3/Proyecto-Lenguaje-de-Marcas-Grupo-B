// =========================================================================
// ARCHIVO: Js/herramientas.js
// PROPÓSITO: Gestión del catálogo de herramientas (CRUD y visualización).
// REFERENCIA WEB: HTML/inventarioMenu/herramientas.html
// =========================================================================

let herramientasData = []; // Array global para almacenar los datos cargados y poder buscar/editar en memoria

document.addEventListener('DOMContentLoaded', () => {
    // 1. Dibujamos el menú lateral/superior común de la aplicación
    renderMenu();

    // 2. Cargamos los datos iniciales de las herramientas desde la BD
    cargarHerramientas();
});

/**
 * Obtiene la lista de herramientas desde la API y las dibuja en la tabla.
 */
async function cargarHerramientas() {
    const tbody = document.getElementById('tabla-herramientas-body');

    // Mensaje temporal de carga
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">Cargando herramientas... ⚙️</td></tr>';

    // Llamada a la API (Método GET)
    const res = await api('GET', '../../api/inventario/herramientas.php');

    // Verificamos que la respuesta sea válida
    if (res && res.herramientas) {
        tbody.innerHTML = ''; // Limpiamos el mensaje de carga
        herramientasData = res.herramientas; // Guardamos en la variable global

        if (herramientasData.length > 0) {
            // Recorremos los datos y creamos las filas del HTML una a una
            herramientasData.forEach(h => {
                const id = h.id_herramienta || 'N/A';
                const nombre = h.nombre || 'Sin nombre';
                const tipo = h.tipo || 'General';
                const cod_int = h.codigo_interno || '-';
                const estado = h.estado || 'Disponible';
                const ubicacion = h.ubicacion || 'Taller';

                // Formateamos el texto del estado para que quede bonito (ej: en_uso -> En Uso)
                let textoEstado = estado;
                if (estado === 'disponible') textoEstado = 'Disponible';
                else if (estado === 'en_uso') textoEstado = 'En Uso';
                else if (estado === 'en_reparacion') textoEstado = 'En Reparación';
                else if (estado === 'no_disponible') textoEstado = 'No Disponible';

                // Determinamos el color de la etiqueta ("badge") según el estado exacto de la base de datos
                let claseEtiqueta = 'exito'; // Verde (disponible por defecto)
                if (estado === 'en_uso') claseEtiqueta = 'advertencia'; // Amarillo
                if (estado === 'en_reparacion' || estado === 'no_disponible') claseEtiqueta = 'peligro'; // Rojo

                // Creamos la fila (<tr>) y le inyectamos las celdas (<td>)
                const fila = document.createElement('tr');
                fila.innerHTML = `
                    <td class="col-id">${id}</td>
                    <td>${cod_int}</td>
                    <td><strong>${nombre}</strong></td>
                    <td><span class="tipo-gama">${tipo}</span></td>
                    <td><span class="etiqueta-estado ${claseEtiqueta}">${textoEstado}</span></td>
                    <td>${ubicacion}</td>
                    <td class="action-buttons justify-center">
                        <button class="btn-icon" title="Editar" onclick="editarHerramienta(${id})">✏️</button>
                        <button class="btn-icon" title="Eliminar" style="color: #e74c3c;" onclick="borrarHerramienta(${id})">🗑️</button>
                    </td>
                `;

                // Añadimos la fila al cuerpo de la tabla de forma limpia y óptima
                tbody.appendChild(fila);
            });
            // Una vez cargadas las herramientas, inicializamos las opciones del desplegable de filtrado
            cargarFiltrosHerramientas();
        } else {
            // Si la base de datos está vacía
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No hay herramientas registradas.</td></tr>';
        }
    } else {
        // Si hay un error en el servidor o BBDD caída
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 20px; color: #e74c3c;">
            <strong>⚠️ Error al conectar con la base de datos.</strong>
        </td></tr>`;
    }
}

// --- FUNCIONES DEL BUSCADOR ---

/**
 * Rellena el desplegable de filtros con opciones dinámicas basadas en todos los campos (Estado, Tipo, Ubicación).
 */
function cargarFiltrosHerramientas() {
    const select = document.getElementById("filtroHerramientas");
    if (!select) return;

    // Limpiamos y añadimos la opción por defecto
    select.innerHTML = '<option value="todos">Mostrar Todo (Sin filtros)</option>';

    // 1. Grupo de Estados (estático)
    const optgroupEstado = document.createElement("optgroup");
    optgroupEstado.label = "Filtrar por Estado";
    optgroupEstado.innerHTML = `
        <option value="estado:exito">Disponible</option>
        <option value="estado:advertencia">En Uso</option>
        <option value="estado:peligro">No Disponible / En Rep.</option>
    `;
    select.appendChild(optgroupEstado);

    // 2. Grupo de Tipos (dinámico a partir del array global herramientasData)
    const tipos = [...new Set(herramientasData.map(h => h.tipo).filter(Boolean))];
    if (tipos.length > 0) {
        const optgroupTipo = document.createElement("optgroup");
        optgroupTipo.label = "Filtrar por Tipo";
        tipos.forEach(tipo => {
            const option = document.createElement("option");
            option.value = `tipo:${tipo}`;
            option.textContent = tipo;
            optgroupTipo.appendChild(option);
        });
        select.appendChild(optgroupTipo);
    }

    // 3. Grupo de Ubicaciones (dinámico a partir del array global herramientasData)
    const ubicaciones = [...new Set(herramientasData.map(h => h.ubicacion).filter(Boolean))];
    if (ubicaciones.length > 0) {
        const optgroupUbi = document.createElement("optgroup");
        optgroupUbi.label = "Filtrar por Ubicación";
        ubicaciones.forEach(ubi => {
            const option = document.createElement("option");
            option.value = `ubicacion:${ubi}`;
            option.textContent = ubi;
            optgroupUbi.appendChild(option);
        });
        select.appendChild(optgroupUbi);
    }
}

/**
 * Filtra las herramientas mostradas en la tabla según la opción del desplegable único.
 */
function filtrarHerramientas() {
    // 1. LEER EL INPUT DE TEXTO (Esto es lo que faltaba)
    const input = document.getElementById("busquedaHerramienta");
    const textoBuscado = input.value.toUpperCase();

    const filtro = document.getElementById("filtroHerramientas").value;
    const filas = document.getElementById("tabla-herramientas-body").getElementsByTagName("tr");

    // Recorremos todas las filas de la tabla de herramientas
    for (let i = 0; i < filas.length; i++) {
        const fila = filas[i];

        // Omitimos filas especiales que no contienen datos
        if (fila.cells.length === 1) continue;

        // 2. COMPROBAR SI EL TEXTO COINCIDE (Igual que en piezas)
        const textoFila = fila.textContent || fila.innerText;
        const coincideTexto = textoFila.toUpperCase().includes(textoBuscado);

        // 3. COMPROBAR EL FILTRO DESPLEGABLE
        let coincideFiltro = true;

        if (filtro !== "todos") {
            const [criterio, valor] = filtro.split(":");

            // Evaluamos según el criterio seleccionado en el desplegable
            if (criterio === "estado") {
                const etiqueta = fila.querySelector(".etiqueta-estado");
                coincideFiltro = etiqueta && etiqueta.classList.contains(valor);
            } else if (criterio === "tipo") {
                const tipoCelda = fila.cells[3].textContent.trim(); // Cuarta celda: Tipo
                coincideFiltro = tipoCelda.toUpperCase() === valor.toUpperCase();
            } else if (criterio === "ubicacion") {
                const ubiCelda = fila.cells[5].textContent.trim(); // Sexta celda: Ubicación
                coincideFiltro = ubiCelda.toUpperCase() === valor.toUpperCase();
            }
        }

        // 4. MOSTRAR U OCULTAR LA FILA (Solo si cumple ambos requisitos)
        if (coincideTexto && coincideFiltro) {
            fila.style.display = "";
        } else {
            fila.style.display = "none";
        }
    }
}

// --- FUNCIONES DEL MODAL (AÑADIR / EDITAR) ---

/**
 * Abre el formulario (modal) totalmente limpio para crear una NUEVA herramienta.
 */
function abrirModalHerramienta() {
    document.getElementById('modalTitulo').textContent = 'Añadir Nueva Herramienta';
    document.getElementById('formHerramienta').reset();
    document.getElementById('herr_id').value = ''; // ID vacío significa "Crear"
    document.getElementById('modalHerramienta').classList.add('show');
}

/**
 * Abre el formulario (modal) con los datos cargados para EDITAR una herramienta existente.
 */
function editarHerramienta(id) {
    // Buscamos la herramienta en nuestro array cargado previamente
    const herr = herramientasData.find(h => h.id_herramienta == id);
    if (!herr) return;

    // Rellenamos el formulario con los datos encontrados
    document.getElementById('modalTitulo').textContent = 'Editar Herramienta';
    document.getElementById('herr_id').value = herr.id_herramienta;
    document.getElementById('herr_nombre').value = herr.nombre || '';
    document.getElementById('herr_cod_int').value = herr.codigo_interno || '';
    document.getElementById('herr_tipo').value = herr.tipo || 'General';
    document.getElementById('herr_estado').value = herr.estado || 'disponible';
    document.getElementById('herr_ubicacion').value = herr.ubicacion || 'Taller';

    // Mostramos el modal
    document.getElementById('modalHerramienta').classList.add('show');
}

/**
 * Oculta y cierra el formulario (modal).
 */
function cerrarModal() {
    document.getElementById('modalHerramienta').classList.remove('show');
}

// --- FUNCIONES CRUD A LA API ---

/**
 * Envía los datos del formulario a la API para crear o actualizar la herramienta.
 */
async function guardarHerramienta(evento) {
    evento.preventDefault(); // Evita que la página se recargue sola al darle a "Guardar"

    // Recogemos lo que el usuario ha escrito en el formulario
    const id = document.getElementById('herr_id').value;
    const datos = {
        nombre: document.getElementById('herr_nombre').value.trim(),
        codigo_interno: document.getElementById('herr_cod_int').value.trim(),
        tipo: document.getElementById('herr_tipo').value.trim(),
        estado: document.getElementById('herr_estado').value,
        ubicacion: document.getElementById('herr_ubicacion').value.trim()
    };

    // Si hay un ID en el formulario oculto, significa que estamos editando (PUT)
    // Si está vacío, estamos creando una nueva (POST)
    let metodo = 'POST';
    if (id !== '') {
        metodo = 'PUT';
        datos.id_herramienta = id;
    }

    // Enviamos la petición a la API
    const res = await api(metodo, '../../api/inventario/herramientas.php', datos);

    // Verificamos si todo ha ido bien
    if (res && res.ok !== false) {
        cerrarModal();          // 1. Ocultamos el modal
        cargarHerramientas();   // 2. Recargamos la tabla para ver los cambios
        document.getElementById('filtroHerramientas').value = 'todos'; // 3. Reiniciamos el filtro
    } else {
        alert('Error: ' + (res.error || 'No se pudo guardar la herramienta'));
    }
}

/**
 * Elimina de manera permanente una herramienta.
 */
async function borrarHerramienta(id) {
    // Pedimos confirmación para evitar borrados accidentales
    if (confirm('¿Estás seguro de que deseas eliminar esta herramienta? Esta acción no se puede deshacer.')) {

        // Llamada a la API con método DELETE
        const res = await api('DELETE', '../../api/inventario/herramientas.php', { id_herramienta: id });

        if (res && res.ok !== false) {
            cargarHerramientas(); // Recargamos para que desaparezca visualmente
        } else {
            alert('Error al eliminar: ' + (res.error || 'No se pudo borrar la herramienta'));
        }
    }
}