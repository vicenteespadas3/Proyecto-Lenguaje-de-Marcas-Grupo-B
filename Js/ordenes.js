// ==============================================================================
// ARCHIVO: Js/ordenes.js
// PROPÓSITO: Controlador principal para la gestión de Órdenes de Trabajo.
// Gestiona la carga, visualización, creación, edición y eliminación de
// órdenes de mantenimiento (correctivas, preventivas o predictivas).
// RELACIONES:
//   - HTML: HTML/mantenimientoMenu/ordenes.html
//   - API: api/mantenimiento/ordenes.php
//   - Datos: Tabla 'ordenes_trabajo' en la BD
// FUNCIONALIDADES PRINCIPALES:
//   1. Cargar y mostrar lista de órdenes en tabla
//   2. Buscar y filtrar órdenes por texto, estado y prioridad
//   3. Crear nuevas órdenes de trabajo
//   4. Modificar órdenes existentes
//   5. Eliminar órdenes
//   6. Ver detalles de una orden
// ==============================================================================

// URL del API REST para operaciones CRUD de órdenes
const API_ORDENES_URL = '../../api/mantenimiento/ordenes.php';

// Cache en memoria de órdenes cargadas (para no recargar innecesariamente)
let ordenesCache = [];

// ID de la orden que se está editando (null si es nuevo registro)
let editandoId = null;

// EVENT LISTENER PRINCIPAL: Se ejecuta cuando el DOM está listo
document.addEventListener('DOMContentLoaded', () => {
    // Renderiza el menú lateral si existe la función (definida en app.js)
    if (typeof renderMenu === 'function') {
        renderMenu();
    }

    // ========== REFERENCIAS A ELEMENTOS DEL DOM ==========
    const modal = document.getElementById('modalOrden');
    const btnNuevaOrden = document.getElementById('btnNuevaOrden');
    const btnCerrarModal = document.getElementById('btnCerrarModal');
    const btnCancelarOrden = document.getElementById('btnCancelarOrden');
    const formOrden = document.getElementById('formOrden');
    const tablaOrdenesBody = document.getElementById('tablaOrdenesBody');
    const modalDetalle = document.getElementById('modalDetalleOrden');
    const btnCerrarDetalle = document.getElementById('btnCerrarDetalle');
    const btnCerrarDetalleInferior = document.getElementById('btnCerrarDetalleInferior');

    // ========== EVENT LISTENERS PARA MODAL ==========
    // Abrir modal para crear nueva orden
    btnNuevaOrden?.addEventListener('click', abrirModalOrden);

    // Cerrar modal al hacer clic en botones o fuera
    btnCerrarModal?.addEventListener('click', cerrarModalOrden);
    btnCancelarOrden?.addEventListener('click', cerrarModalOrden);
    btnCerrarDetalle?.addEventListener('click', cerrarDetalleOrden);
    btnCerrarDetalleInferior?.addEventListener('click', cerrarDetalleOrden);

    // ========== EVENT LISTENERS PARA ACCIONES EN LA TABLA ==========
    // Usa delegación de eventos en el tbody de la tabla
    tablaOrdenesBody?.addEventListener('click', (event) => {
        // Botón para ver detalle de la orden
        const btnVer = event.target.closest('[data-accion="ver-orden"]');
        if (btnVer) {
            abrirDetalleOrden(btnVer.dataset.id);
            return;
        }

        // Botón para editar la orden
        const btnModificar = event.target.closest('[data-accion="modificar-orden"]');
        if (btnModificar) {
            abrirModificarOrden(btnModificar.dataset.id);
            return;
        }

        // Botón para eliminar la orden
        const btnBorrar = event.target.closest('[data-accion="borrar-orden"]');
        if (btnBorrar) {
            borrarOrden(btnBorrar.dataset.id);
            return;
        }
    });

    // Cerrar modal al hacer clic fuera de su contenido
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) cerrarModalOrden();
    });

    modalDetalle?.addEventListener('click', (event) => {
        if (event.target === modalDetalle) cerrarDetalleOrden();
    });

    // ========== TECLA ESCAPE CIERRA MODALES ==========
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal?.classList.contains('show')) {
            cerrarModalOrden();
        }

        if (event.key === 'Escape' && modalDetalle?.classList.contains('show')) {
            cerrarDetalleOrden();
        }
    });

    // ========== SUBMIT DEL FORMULARIO ==========
    formOrden?.addEventListener('submit', guardarOrden);

    // ========== EVENT LISTENERS PARA BÚSQUEDA Y FILTROS ==========
    const btnBuscarOrden = document.getElementById('btnBuscarOrden');
    const inputBuscarOrden = document.getElementById('inputBuscarOrden');
    const selectFiltroEstado = document.getElementById('selectFiltroEstado');
    const selectFiltroPrioridad = document.getElementById('selectFiltroPrioridad');

    // Ejecutar búsqueda al hacer clic en el botón Filtrar
    btnBuscarOrden?.addEventListener('click', cargarOrdenes);

    // Permitir buscar presionando Enter en el campo de texto
    inputBuscarOrden?.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') cargarOrdenes();
    });

    // Recargar tabla al cambiar los filtros de estado o prioridad
    selectFiltroEstado?.addEventListener('change', cargarOrdenes);
    selectFiltroPrioridad?.addEventListener('change', cargarOrdenes);

    // Carga inicial de órdenes al abrir la página
    cargarOrdenes();
});

// ==============================================================================
// SECCIÓN: MODALES
// ==============================================================================

/**
 * Abre el modal para crear una nueva orden de trabajo
 * Limpia el formulario y reinicia el estado de edición
 */
function abrirModalOrden() {
    const modal = document.getElementById('modalOrden');
    const titulo = document.getElementById('modalOrdenTitulo');
    const form = document.getElementById('formOrden');

    editandoId = null;
    if (titulo) titulo.textContent = 'Nueva Orden de Trabajo';
    form?.reset();

    limpiarMensaje();
    modal?.classList.add('show');
    document.getElementById('descripcion')?.focus();
}

/**
 * Cierra el modal de crear/editar orden
 * Limpia el formulario y cualquier mensaje de error
 */
function cerrarModalOrden() {
    const modal = document.getElementById('modalOrden');
    modal?.classList.remove('show');
    limpiarMensaje();
}

// ==============================================================================
// SECCIÓN: CARGA Y MOSTRADO DE DATOS
// ==============================================================================

/**
 * Carga todas las órdenes del servidor (con filtros si se especifican)
 * y las muestra en la tabla. Se llama:
 * - Al cargar la página por primera vez
 * - Al presionar el botón Filtrar
 * - Al cambiar los filtros de estado o prioridad
 * - Después de crear, editar o eliminar una orden
 */
async function cargarOrdenes() {
    const tbody = document.getElementById('tablaOrdenesBody');
    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="14" style="text-align: center; padding: 20px;">Cargando órdenes...</td>
        </tr>
    `;

    const txtBuscar = document.getElementById('inputBuscarOrden')?.value.trim() || '';
    const valEstado = document.getElementById('selectFiltroEstado')?.value || '';
    const valPrioridad = document.getElementById('selectFiltroPrioridad')?.value || '';

    const params = new URLSearchParams();
    if (txtBuscar !== '') params.append('buscar', txtBuscar);
    if (valEstado !== '') params.append('estado', valEstado);
    if (valPrioridad !== '') params.append('prioridad', valPrioridad);

    const queryStr = params.toString();
    const url = queryStr ? `${API_ORDENES_URL}?${queryStr}` : API_ORDENES_URL;

    try {
        const respuesta = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });

        const datos = await respuesta.json();
        const ordenes = Array.isArray(datos) ? datos : (datos.ordenes || []);

        if (!respuesta.ok || datos.success === false) {
            throw new Error(datos.error || 'No se pudieron cargar las ordenes.');
        }

        if (ordenes.length === 0) {
            ordenesCache = [];
            tbody.innerHTML = `
                <tr>
                    <td colspan="14" style="text-align: center; padding: 20px;">No hay órdenes registradas.</td>
                </tr>
            `;
            return;
        }

        ordenesCache = ordenes;
        tbody.innerHTML = ordenes.map(renderFilaOrden).join('');
    } catch (error) {
        ordenesCache = [];
        tbody.innerHTML = `
            <tr>
                <td colspan="14" style="text-align: center; padding: 20px; color: #e74c3c; font-weight: bold;">${escaparHtml(error.message)}</td>
            </tr>
        `;
    }
}

/**
 * Genera el HTML de una fila de la tabla para una orden
 * Incluye todos los datos y botones de acción (ver, editar, borrar)
 * @param {Object} orden - Objeto orden con todos sus datos
 * @returns {String} HTML de la fila para insertar en la tabla
 */
function renderFilaOrden(orden) {
    return `
        <tr>
            <td class="col-id">${escaparHtml(orden.id_orden)}</td>
            <td>${escaparHtml(orden.id_maquina)}</td>
            <td>${escaparHtml(orden.id_plan || '---')}</td>
            <td>${escaparHtml(orden.id_tipo)}</td>
            <td>${escaparHtml(orden.id_estado)}</td>
            <td>${escaparHtml(orden.id_prioridad)}</td>
            <td>${escaparHtml(orden.id_rol_asignado || '---')}</td>
            <td>${escaparHtml(orden.fecha_programada || '---')}</td>
            <td>${escaparHtml(orden.fecha_inicio || '---')}</td>
            <td>${escaparHtml(orden.fecha_finalizacion || '---')}</td>
            <td>${formatoMinutos(orden.tiempo_estimado)}</td>
            <td>${formatoMinutos(orden.tiempo_real)}</td>
            <td>${escaparHtml(orden.observaciones || 'Sin observaciones')}</td>
            <td class="action-buttons col-acciones">
                <button class="btn btn-icon" title="Ver" data-accion="ver-orden" data-id="${escaparHtml(orden.id_orden)}">👁️</button>
                <button class="btn btn-icon" title="Modificar" data-accion="modificar-orden" data-id="${escaparHtml(orden.id_orden)}">✏️</button>
                <button class="btn btn-icon" style="color: #e74c3c;" title="Borrar" data-accion="borrar-orden" data-id="${escaparHtml(orden.id_orden)}">🗑️</button>
            </td>
        </tr>
    `;
}

/**
 * Convierte el tipo de mantenimiento numérico a texto
 * @param {number|string} id_tipo - ID del tipo (1=Preventivo, 2=Correctivo, etc)
 * @returns {string} Nombre del tipo de mantenimiento
 */
function getTipoText(id_tipo) {
    id_tipo = String(id_tipo);
    if (id_tipo === '1') return 'Preventivo';
    if (id_tipo === '2') return 'Correctivo';
    return 'Predictivo';
}

/**
 * Convierte el ID de prioridad numérico a texto
 * @param {number|string} id_prioridad - ID de prioridad (8=Baja, 9=Media, 10=Alta, etc)
 * @returns {string} Nombre de la prioridad
 */
function getPrioridadText(id_prioridad) {
    id_prioridad = String(id_prioridad);
    if (id_prioridad === '8') return 'Baja';
    if (id_prioridad === '9') return 'Media';
    if (id_prioridad === '10') return 'Alta';
    return 'Media';
}

// ==============================================================================
// SECCIÓN: MODAL MODIFICAR ORDEN
// ==============================================================================

/**
 * Abre el modal en modo edición para una orden existente
 * Carga los datos de la orden en los campos del formulario
 * @param {number|string} id - ID de la orden a editar
 */
function abrirModificarOrden(id) {
    const orden = ordenesCache.find((item) => String(item.id_orden) === String(id));
    if (!orden) return;

    editandoId = id;

    const modal = document.getElementById('modalOrden');
    const titulo = document.getElementById('modalOrdenTitulo');
    if (titulo) titulo.textContent = 'Modificar Orden de Trabajo';

    const inputDesc = document.getElementById('descripcion');
    if (inputDesc) inputDesc.value = orden.observaciones || '';

    const inputMaquina = document.getElementById('maquina');
    if (inputMaquina) inputMaquina.value = orden.id_maquina || '';

    const inputTipo = document.getElementById('tipo');
    if (inputTipo) {
        inputTipo.value = getTipoText(orden.id_tipo);
    }

    const prioridadVal = getPrioridadText(orden.id_prioridad);
    const radioPrioridad = document.querySelector(`input[name="prioridad"][value="${prioridadVal}"]`);
    if (radioPrioridad) {
        radioPrioridad.checked = true;
    }

    limpiarMensaje();
    modal?.classList.add('show');
    document.getElementById('descripcion')?.focus();
}

// ==============================================================================
// SECCIÓN: MODAL DETALLE ORDEN
// ==============================================================================

/**
 * Abre el modal de solo lectura para ver todos los detalles de una orden
 * @param {number|string} id - ID de la orden a mostrar
 */
function abrirDetalleOrden(id) {
    const orden = ordenesCache.find((item) => String(item.id_orden) === String(id));
    const modal = document.getElementById('modalDetalleOrden');
    const titulo = document.getElementById('detalleTitulo');
    const contenido = document.getElementById('detalleContenido');

    if (!orden || !modal || !titulo || !contenido) return;

    titulo.textContent = `Detalle OT-${String(orden.id_orden || '').padStart(3, '0')}`;

    contenido.innerHTML = `
        <div class="detalle-fila" style="margin-bottom: 10px;">
            <strong style="font-size: 0.85em; color: #7f8c8d; display: block; margin-bottom: 2px;">Observaciones:</strong>
            <p style="margin: 5px 0 0 0; color: #555;">${escaparHtml(orden.observaciones || 'Sin observaciones')}</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
            ${detalleCampo('ID Orden', orden.id_orden)}
            ${detalleCampo('ID Máquina', orden.id_maquina)}
            ${detalleCampo('ID Plan', orden.id_plan)}
            ${detalleCampo('ID Tipo', orden.id_tipo)}
            ${detalleCampo('ID Estado', orden.id_estado)}
            ${detalleCampo('ID Prioridad', orden.id_prioridad)}
            ${detalleCampo('ID Rol Asignado', orden.id_rol_asignado)}
            ${detalleCampo('Fecha Programada', formatearFecha(orden.fecha_programada))}
            ${detalleCampo('Fecha Inicio', orden.fecha_inicio)}
            ${detalleCampo('Fecha Finalización', orden.fecha_finalizacion)}
            ${detalleCampo('Tiempo Estimado', formatoMinutos(orden.tiempo_estimado))}
            ${detalleCampo('Tiempo Real', formatoMinutos(orden.tiempo_real))}
        </div>
    `;

    modal.classList.add('show');
}

/**
 * Cierra el modal de detalle de orden
 */
function cerrarDetalleOrden() {
    const modal = document.getElementById('modalDetalleOrden');
    modal?.classList.remove('show');
}

/**
 * Genera el HTML para mostrar un campo en el modal de detalle
 * @param {string} etiqueta - Título o nombre del campo
 * @param {*} valor - Valor a mostrar
 * @returns {string} HTML del campo formateado
 */
function detalleCampo(etiqueta, valor) {
    return `
        <div class="detalle-fila">
            <strong style="font-size: 0.85em; color: #7f8c8d; display: block; margin-bottom: 2px;">${escaparHtml(etiqueta)}</strong>
            <span>${escaparHtml(valor || '---')}</span>
        </div>
    `;
}

/**
 * Formatea una fecha para mostrarla en formato local (DD/MM/YYYY)
 * @param {string|Date} fecha - Fecha a formatear
 * @returns {string} Fecha formateada o fecha original si no es válida
 */
function formatearFecha(fecha) {
    if (!fecha) return '';

    const fechaObjeto = new Date(fecha);
    if (Number.isNaN(fechaObjeto.getTime())) return fecha;

    return fechaObjeto.toLocaleDateString('es-ES');
}

/**
 * Formatea un valor de minutos para mostrar con etiqueta 'min'
 * @param {number|string|null} valor - Número de minutos
 * @returns {string} Valor formateado o '---' si es vacío
 */
function formatoMinutos(valor) {
    if (valor === null || valor === undefined || valor === '') return '---';
    return `${escaparHtml(valor)} min`;
}

// ==============================================================================
// SECCIÓN: GUARDAR ORDEN (POST / PUT)
// ==============================================================================

/**
 * Guarda una nueva orden o actualiza una existente
 * Valida que los campos obligatorios estén rellenos antes de enviar
 * Usa POST para crear (editandoId = null) o PUT para actualizar
 * @param {Event} event - Evento del submit del formulario
 */
async function guardarOrden(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const btnGuardar = document.getElementById('btnGuardarOrden');
    const formData = new FormData(form);

    const datos = {
        descripcion: String(formData.get('descripcion') || '').trim(),
        maquina: String(formData.get('maquina') || '').trim(),
        tipo: String(formData.get('tipo') || '').trim(),
        prioridad: String(formData.get('prioridad') || '').trim()
    };

    if (editandoId) {
        datos.id_orden = editandoId;
    }

    if (!datos.descripcion || !datos.maquina || !datos.tipo || !datos.prioridad) {
        mostrarMensaje('Completa todos los campos antes de guardar.', 'error');
        return;
    }

    btnGuardar.disabled = true;
    btnGuardar.classList.add('opacity-70', 'cursor-not-allowed');

    const metodo = editandoId ? 'PUT' : 'POST';

    try {
        const respuesta = await fetch(API_ORDENES_URL, {
            method: metodo,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(datos)
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok || resultado.success !== true) {
            throw new Error(resultado.error || 'No se pudo procesar la orden.');
        }

        cerrarModalOrden();
        form.reset();
        await cargarOrdenes();
    } catch (error) {
        mostrarMensaje(error.message, 'error');
    } finally {
        btnGuardar.disabled = false;
        btnGuardar.classList.remove('opacity-70', 'cursor-not-allowed');
    }
}

// ==============================================================================
// SECCIÓN: BORRAR ORDEN (DELETE)
// ==============================================================================

/**
 * Elimina una orden después de confirmar con el usuario
 * @param {number|string} id - ID de la orden a eliminar
 */
async function borrarOrden(id) {
    if (confirm(`¿Seguro que quieres borrar la orden con ID: ${id}?`)) {
        try {
            const respuesta = await fetch(API_ORDENES_URL, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ id_orden: id })
            });

            const resultado = await respuesta.json();

            if (!respuesta.ok || resultado.success !== true) {
                throw new Error(resultado.error || 'No se pudo eliminar la orden.');
            }

            alert(resultado.msg || 'Orden eliminada correctamente.');
            await cargarOrdenes();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
}

// ==============================================================================
// SECCIÓN: FUNCIONES AUXILIARES
// ==============================================================================

/**
 * Muestra un mensaje de error o info en el modal
 * @param {string} texto - Texto del mensaje a mostrar
 * @param {string} tipo - Tipo de mensaje: 'error' o 'info' (default: 'info')
 */
function mostrarMensaje(texto, tipo = 'info') {
    const mensaje = document.getElementById('mensajeOrden');
    if (!mensaje) return;

    const colorFondo = tipo === 'error' ? '#f8d7da' : '#fff3cd';
    const colorTexto = tipo === 'error' ? '#721c24' : '#856404';
    const colorBorde = tipo === 'error' ? '#f5c6cb' : '#ffeeba';

    mensaje.style.backgroundColor = colorFondo;
    mensaje.style.color = colorTexto;
    mensaje.style.border = `1px solid ${colorBorde}`;
    mensaje.textContent = texto;
    mensaje.classList.remove('hidden');
}

/**
 * Limpia cualquier mensaje mostrado en el modal
 */
function limpiarMensaje() {
    const mensaje = document.getElementById('mensajeOrden');
    if (!mensaje) return;

    mensaje.textContent = '';
    mensaje.className = 'hidden';
}

/**
 * Escapa caracteres especiales de HTML para evitar XSS
 * Convierte: & < > " '
 * @param {*} valor - Valor a escapar
 * @returns {string} Valor escapado seguro para HTML
 */
function escaparHtml(valor) {
    return String(valor ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
/* Swipe detection to open sidebar on right swipe */
// ==============================================================================
// SECCIÓN: DETECCIÓN DE GESTOS (SWIPE) PARA MÓVIL
// ==============================================================================
// Permitir abrir la barra lateral con un gesto de deslizamiento hacia la derecha

// Variable para guardar la posición X inicial del toque
let touchStartX = 0;
// Variable para guardar la posición X final del toque
let touchEndX = 0;
// Distancia mínima en píxeles para considerar un gesto de deslizamiento
const minSwipeDist = 50;

// Event listener para detectar el inicio del toque en pantalla táctil
document.addEventListener('touchstart', e => {
    touchStartX = e.changedTouches[0].screenX;
});

// Event listener para detectar el final del toque
document.addEventListener('touchend', e => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe(); // Procesar el gesto detectado
});

/**
 * Procesa el gesto de deslizamiento detectado
 * Si es un deslizamiento hacia la derecha, expande la barra lateral
 */
function handleSwipe() {
    const diffX = touchEndX - touchStartX;
    if (diffX > minSwipeDist) {
        // Right swipe detected
        const sidebar = document.querySelector('.sidebar');
        if (sidebar && sidebar.classList.contains('collapsed')) {
            toggleSidebar(); // expand sidebar
        }
    }
}


