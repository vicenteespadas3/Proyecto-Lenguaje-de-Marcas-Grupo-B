// ==============================================================================
// ARCHIVO: Js/ejecuciones.js
// PROPÓSITO: Controlador principal para la gestión completa (CRUD) de
// ejecuciones de mantenimiento. Gestiona la carga, visualización, creación,
// edición y eliminación de registros de ejecuciones.
// RELACIONES:
//   - HTML: HTML/mantenimientoMenu/ejecuciones.html
//   - API: api/mantenimiento/ejecuciones.php
//   - Datos: Tabla 'ejecuciones_mantenimiento' en la BD
// FUNCIONALIDADES PRINCIPALES:
//   1. Cargar y mostrar lista de ejecuciones en tabla
//   2. Buscar y filtrar ejecuciones por ID, resultado y fecha
//   3. Crear nuevas ejecuciones
//   4. Modificar ejecuciones existentes
//   5. Eliminar ejecuciones
//   6. Ver detalles de una ejecución
//   7. Ver y actualizar tareas ejecutadas
// ==============================================================================

// URL del API REST para operaciones CRUD de ejecuciones
const API_EJECUCIONES_URL = '../../api/mantenimiento/ejecuciones.php';

// Cache en memoria de ejecuciones cargadas (para no recargar innecesariamente)
let ejecucionesCache = [];

// ID de la ejecución que se está editando (null si es nuevo registro)
let editandoEjecucionId = null;

// EVENT LISTENER PRINCIPAL: Se ejecuta cuando el DOM está listo
document.addEventListener('DOMContentLoaded', () => {
    // Renderiza el menú lateral si existe la función (definida en app.js)
    if (typeof renderMenu === 'function') renderMenu();

    // ========== REFERENCIAS A ELEMENTOS DEL DOM ==========
    // Modales
    const modalEjecucion = document.getElementById('modalEjecucion');
    const modalDetalle = document.getElementById('modalDetalleEjecucion');
    const modalTareas = document.getElementById('modalTareas');

    // Botones de acción principales
    const btnNuevaEjecucion = document.getElementById('btnNuevaEjecucion');
    const btnVerTodasTareas = document.getElementById('btnVerTodasTareas');

    // Botones de cierre de modales
    const btnCerrarModal = document.getElementById('btnCerrarModalEjecucion');
    const btnCancelar = document.getElementById('btnCancelarEjecucion');
    const btnCerrarDetalle = document.getElementById('btnCerrarDetalleEjecucion');
    const btnCerrarDetalleInf = document.getElementById('btnCerrarDetalleEjecucionInferior');
    const btnCerrarTareas = document.getElementById('btnCerrarModalTareas');
    const btnCerrarTareasInf = document.getElementById('btnCerrarModalTareasInferior');

    // Formulario y tabla
    const formEjecucion = document.getElementById('formEjecucion');
    const tbody = document.getElementById('tabla-ejecuciones-body');
    const listaTareas = document.getElementById('listaTareasContainer');

    // Controles de búsqueda y filtrado
    const btnBuscar = document.getElementById('btnBuscarEjecucion');
    const inputBuscar = document.getElementById('inputBuscarEjecucion');
    const filtroResultado = document.getElementById('filtroResultadoEjecucion');
    const filtroOrden = document.getElementById('filtroOrdenEjecucion');

    // ========== EVENT LISTENERS PARA BÚSQUEDA Y FILTROS ==========
    // Permitir solo números en el input de búsqueda
    if (inputBuscar) {
        inputBuscar.addEventListener('input', () => {
            inputBuscar.value = inputBuscar.value.replace(/\D/g, ''); // Remove non-digits
        });
        // Presionar Enter ejecuta la búsqueda
        inputBuscar.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') cargarEjecuciones();
        });
    }

    // Ejecutar búsqueda al hacer clic en el botón Filtrar
    btnBuscar?.addEventListener('click', () => cargarEjecuciones());

    // Recargar tabla cuando cambian los filtros de resultado u orden
    filtroResultado?.addEventListener('change', () => cargarEjecuciones());
    filtroOrden?.addEventListener('change', () => cargarEjecuciones());

    // ========== EVENT LISTENERS PARA MODALES ==========
    // Abrir modal para crear nueva ejecución
    btnNuevaEjecucion?.addEventListener('click', abrirModalNuevaEjecucion);

    // Abrir modal de todas las tareas ejecutadas
    btnVerTodasTareas?.addEventListener('click', () => verTareas(null));

    // CERRAR MODAL DE EDICIÓN/CREACIÓN
    btnCerrarModal?.addEventListener('click', cerrarModalEjecucion);
    btnCancelar?.addEventListener('click', cerrarModalEjecucion);
    // Cerrar al hacer clic fuera del modal
    modalEjecucion?.addEventListener('click', (e) => {
        if (e.target === modalEjecucion) cerrarModalEjecucion();
    });

    // CERRAR MODAL DE DETALLE
    btnCerrarDetalle?.addEventListener('click', cerrarDetalleEjecucion);
    btnCerrarDetalleInf?.addEventListener('click', cerrarDetalleEjecucion);
    modalDetalle?.addEventListener('click', (e) => {
        if (e.target === modalDetalle) cerrarDetalleEjecucion();
    });

    // CERRAR MODAL DE TAREAS
    btnCerrarTareas?.addEventListener('click', cerrarModalTareas);
    btnCerrarTareasInf?.addEventListener('click', cerrarModalTareas);
    modalTareas?.addEventListener('click', (e) => {
        if (e.target === modalTareas) cerrarModalTareas();
    });

    // TECLA ESCAPE cierra cualquier modal abierto
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (modalEjecucion?.classList.contains('show')) cerrarModalEjecucion();
            if (modalDetalle?.classList.contains('show')) cerrarDetalleEjecucion();
            if (modalTareas?.classList.contains('show')) cerrarModalTareas();
        }
    });

    // ========== EVENT LISTENERS PARA ACCIONES EN LA TABLA ==========
    // Usa delegación de eventos: el listener se define en el tbody
    // pero solo responde a clics en botones específicos
    tbody?.addEventListener('click', (event) => {
        // Botón para ver detalle de la ejecución
        const btnVer = event.target.closest('[data-accion="ver-ejecucion"]');
        if (btnVer) { abrirDetalleEjecucion(btnVer.dataset.id); return; }

        // Botón para editar la ejecución
        const btnEditar = event.target.closest('[data-accion="modificar-ejecucion"]');
        if (btnEditar) { abrirModificarEjecucion(btnEditar.dataset.id); return; }

        // Botón para eliminar la ejecución
        const btnBorrar = event.target.closest('[data-accion="borrar-ejecucion"]');
        if (btnBorrar) { borrarEjecucion(btnBorrar.dataset.id); return; }

        // Botón para ver tareas de esta ejecución
        const btnTareas = event.target.closest('[data-accion="ver-tareas"]');
        if (btnTareas) { verTareas(btnTareas.dataset.idEjecucion); return; }
    });

    // EVENT LISTENER para cambios en checkboxes de tareas
    listaTareas?.addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-action="toggle-tarea"]');
        if (checkbox) actualizarEstadoTarea(checkbox);
    });

    // Submit del formulario para guardar ejecución
    formEjecucion?.addEventListener('submit', guardarEjecucion);

    // Carga inicial de ejecuciones al abrir la página
    cargarEjecuciones();
});

// ==============================================================================
// SECCIÓN: CARGA Y MOSTRADO DE DATOS
// ==============================================================================

/**
 * Carga todas las ejecuciones del servidor (con filtros si se especifican)
 * y las muestra en la tabla. Esta función se llama:
 * - Al cargar la página por primera vez
* - Al presionar el botón Filtrar
 * - Al cambiar los filtros de resultado u orden
 * - Después de crear, editar o eliminar una ejecución
 */
async function cargarEjecuciones() {
    const tbody = document.getElementById('tabla-ejecuciones-body');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px;">Cargando historial...</td></tr>';

    try {
        const filtros = obtenerFiltros();
        const params = new URLSearchParams();

        if (filtros.idEjecucion) params.set('id_ejecucion', filtros.idEjecucion);
        if (filtros.resultado) params.set('resultado', filtros.resultado);
        if (filtros.orden) params.set('orden', filtros.orden);

        const url = API_EJECUCIONES_URL + (params.toString() ? `?${params}` : '');
        const res = await api('GET', url);

        if (res && res.ok !== false && res.ejecuciones) {
            ejecucionesCache = res.ejecuciones;

            if (res.ejecuciones.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px;">No hay registros.</td></tr>';
                return;
            }

            tbody.innerHTML = res.ejecuciones.map(renderFilaEjecucion).join('');
        } else {
            ejecucionesCache = [];
            tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: #e74c3c;">Error al cargar los datos.</td></tr>';
        }
    } catch (error) {
        ejecucionesCache = [];
        tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: #e74c3c;">Error de conexión.</td></tr>';
    }
}

/**
 * Obtiene los valores actuales de los filtros del formulario
 * @returns {Object} Objeto con filtros: idEjecucion, resultado, orden
 */
function obtenerFiltros() {
    return {
        idEjecucion: document.getElementById('inputBuscarEjecucion')?.value.trim() || '',
        resultado: document.getElementById('filtroResultadoEjecucion')?.value || '',
        orden: document.getElementById('filtroOrdenEjecucion')?.value || 'recientes'
    };
}

// ==============================================================================
// SECCIÓN: RENDER Y VISUALIZACIÓN DE TABLA
// ==============================================================================

/**
 * Genera el HTML de una fila de la tabla para una ejecución
 * Incluye todos los datos y botones de acción (ver, editar, borrar, ver tareas)
 * @param {Object} e - Objeto ejecución con todos sus datos
 * @returns {String} HTML de la fila para insertar en la tabla
 */
function renderFilaEjecucion(e) {
    return `
        <tr>
            <td class="col-id"><strong>${escaparHtml(e.id_ejecucion)}</strong></td>
            <td>${escaparHtml(e.id_orden ?? '---')}</td>
            <td>${escaparHtml(e.fecha_ejecucion ?? e.fecha ?? '---')}</td>
            <td>${escaparHtml(e.hora_inicio ?? '---')}</td>
            <td>${escaparHtml(e.hora_fin ?? '---')}</td>
            <td>${escaparHtml(e.id_tecnico ?? '---')}</td>
            <td><span class="${claseResultado(e.resultado)}">${escaparHtml(formatearResultado(e.resultado))}</span></td>
            <td style="max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escaparHtml(e.observaciones ?? '')}">${escaparHtml(e.observaciones ?? '---')}</td>
            <td class="action-buttons col-acciones">
                <button class="btn btn-icon" title="Ver detalle" data-accion="ver-ejecucion" data-id="${escaparHtml(e.id_ejecucion)}">👁️</button>
                <button class="btn btn-icon" title="Ver tareas" data-accion="ver-tareas" data-id-ejecucion="${escaparHtml(e.id_ejecucion)}">📋</button>
                <button class="btn btn-icon" title="Modificar" data-accion="modificar-ejecucion" data-id="${escaparHtml(e.id_ejecucion)}">✏️</button>
                <button class="btn btn-icon" style="color: #e74c3c;" title="Borrar" data-accion="borrar-ejecucion" data-id="${escaparHtml(e.id_ejecucion)}">🗑️</button>
            </td>
        </tr>
    `;
}

// ==============================================================================
// SECCIÓN: MODAL NUEVA EJECUCIÓN
// ==============================================================================

/**
 * Abre el modal para crear una nueva ejecución
 * Limpia el formulario, reinicia el estado de edición y muestra el modal
 */
function abrirModalNuevaEjecucion() {
    editandoEjecucionId = null;
    const titulo = document.getElementById('modalEjecucionTitulo');
    if (titulo) titulo.textContent = 'Nueva Ejecución';
    document.getElementById('formEjecucion')?.reset();
    limpiarMensajeEjecucion();
    document.getElementById('modalEjecucion')?.classList.add('show');
    document.getElementById('ejecucionIdOrden')?.focus();
}

/**
 * Cierra el modal de crear/editar ejecución
 * Limpia el formulario y cualquier mensaje de error
 */
function cerrarModalEjecucion() {
    document.getElementById('modalEjecucion')?.classList.remove('show');
    limpiarMensajeEjecucion();
}

// ==============================================================================
// SECCIÓN: MODAL MODIFICAR EJECUCIÓN
// ==============================================================================

/**
 * Abre el modal en modo edición para una ejecución existente
 * Carga los datos de la ejecución en los campos del formulario
 * @param {number|string} id - ID de la ejecución a editar
 */
function abrirModificarEjecucion(id) {
    const e = ejecucionesCache.find(item => String(item.id_ejecucion) === String(id));
    if (!e) return;

    editandoEjecucionId = id;

    const titulo = document.getElementById('modalEjecucionTitulo');
    if (titulo) titulo.textContent = 'Modificar Ejecución';

    const idOrden = document.getElementById('ejecucionIdOrden');
    if (idOrden) idOrden.value = e.id_orden ?? '';

    const fecha = document.getElementById('ejecucionFecha');
    if (fecha) fecha.value = e.fecha_ejecucion ?? e.fecha ?? '';

    const horaInicio = document.getElementById('ejecucionHoraInicio');
    if (horaInicio) horaInicio.value = e.hora_inicio ?? '';

    const horaFin = document.getElementById('ejecucionHoraFin');
    if (horaFin) horaFin.value = e.hora_fin ?? '';

    const obs = document.getElementById('ejecucionObservaciones');
    if (obs) obs.value = e.observaciones ?? '';

    const resultado = document.getElementById('ejecucionResultado');
    if (resultado) resultado.value = e.resultado ?? 'exitoso';

    limpiarMensajeEjecucion();
    document.getElementById('modalEjecucion')?.classList.add('show');
    document.getElementById('ejecucionIdOrden')?.focus();
}

// ==============================================================================
// SECCIÓN: MODAL DETALLE EJECUCIÓN
// ==============================================================================

/**
 * Abre el modal de solo lectura para ver todos los detalles de una ejecución
 * @param {number|string} id - ID de la ejecución a mostrar
 */
function abrirDetalleEjecucion(id) {
    const e = ejecucionesCache.find(item => String(item.id_ejecucion) === String(id));
    const modal = document.getElementById('modalDetalleEjecucion');
    const titulo = document.getElementById('detalleTituloEjecucion');
    const contenido = document.getElementById('detalleContenidoEjecucion');

    if (!e || !modal || !titulo || !contenido) return;

    titulo.textContent = `Detalle Ejecución #${e.id_ejecucion}`;

    contenido.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
            ${campoDet('ID Ejecución', e.id_ejecucion)}
            ${campoDet('ID Orden', e.id_orden)}
            ${campoDet('Fecha', e.fecha_ejecucion ?? e.fecha)}
            ${campoDet('Hora Inicio', e.hora_inicio)}
            ${campoDet('Hora Fin', e.hora_fin)}
            ${campoDet('ID Técnico', e.id_tecnico)}
            ${campoDet('Resultado', formatearResultado(e.resultado))}
        </div>
        <div style="margin-top: 12px;">
            <strong style="font-size: 0.85em; color: #7f8c8d; display: block; margin-bottom: 4px;">Observaciones:</strong>
            <p style="margin: 0; color: #555;">${escaparHtml(e.observaciones || 'Sin observaciones')}</p>
        </div>
    `;

    modal.classList.add('show');
}

/**
 * Cierra el modal de detalle de ejecución
 */
function cerrarDetalleEjecucion() {
    document.getElementById('modalDetalleEjecucion')?.classList.remove('show');
}

/**
 * Genera el HTML para mostrar un campo en el modal de detalle
 * @param {string} etiqueta - Título o nombre del campo
 * @param {*} valor - Valor a mostrar en el campo
 * @returns {string} HTML del campo formateado
 */
function campoDet(etiqueta, valor) {
    return `
        <div>
            <strong style="font-size: 0.85em; color: #7f8c8d; display: block; margin-bottom: 2px;">${escaparHtml(etiqueta)}</strong>
            <span>${escaparHtml(valor || '---')}</span>
        </div>
    `;
}

// ==============================================================================
// SECCIÓN: GUARDAR EJECUCIÓN (POST / PUT)
// ==============================================================================

/**
 * Guarda una nueva ejecución o actualiza una existente
 * Valida que los campos obligatorios estén rellenos antes de enviar
 * Usa POST para crear (editandoEjecucionId = null) o PUT para actualizar
 * @param {Event} event - Evento del submit del formulario
 */
async function guardarEjecucion(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const btnGuardar = document.getElementById('btnGuardarEjecucion');
    const formData = new FormData(form);

    const datos = {
        id_orden: String(formData.get('id_orden') || '').trim(),
        fecha_ejecucion: String(formData.get('fecha_ejecucion') || '').trim(),
        hora_inicio: String(formData.get('hora_inicio') || '').trim(),
        hora_fin: String(formData.get('hora_fin') || '').trim(),
        observaciones: String(formData.get('observaciones') || '').trim(),
        resultado: String(formData.get('resultado') || '').trim()
    };

    if (editandoEjecucionId) datos.id_ejecucion = editandoEjecucionId;

    if (!datos.id_orden || !datos.fecha_ejecucion || !datos.resultado) {
        mostrarMensajeEjecucion('Completa los campos obligatorios (ID Orden, Fecha y Resultado).', 'error');
        return;
    }

    btnGuardar.disabled = true;
    const metodo = editandoEjecucionId ? 'PUT' : 'POST';

    try {
        const respuesta = await fetch(API_EJECUCIONES_URL, {
            method: metodo,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(datos)
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok || resultado.ok === false || resultado.success === false) {
            throw new Error(resultado.error || 'No se pudo guardar la ejecución.');
        }

        cerrarModalEjecucion();
        form.reset();
        await cargarEjecuciones();
    } catch (error) {
        mostrarMensajeEjecucion(error.message, 'error');
    } finally {
        btnGuardar.disabled = false;
    }
}

// ==============================================================================
// SECCIÓN: BORRAR EJECUCIÓN (DELETE)
// ==============================================================================

/**
 * Elimina una ejecución después de confirmar con el usuario
 * @param {number|string} id - ID de la ejecución a eliminar
 */
async function borrarEjecucion(id) {
    if (!confirm(`¿Seguro que quieres borrar la ejecución con ID: ${id}?`)) return;

    try {
        const respuesta = await fetch(API_EJECUCIONES_URL, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id_ejecucion: id })
        });

        const resultado = await respuesta.json();

        if (!respuesta.ok || resultado.ok === false || resultado.success === false) {
            throw new Error(resultado.error || 'No se pudo eliminar la ejecución.');
        }

        alert(resultado.msg || 'Ejecución eliminada correctamente.');
        await cargarEjecuciones();
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// ==============================================================================
// SECCIÓN: MODAL VER TAREAS EJECUTADAS
// ==============================================================================

/**
 * Abre el modal que muestra todas las tareas ejecutadas de una ejecución
 * o de todas las ejecuciones si idEjecucion es null
 * Las tareas pueden ser marcadas como completadas o no (guardan automáticamente)
 * @param {number|string|null} idEjecucion - ID de ejecución o null para todas
 */
async function verTareas(idEjecucion = null) {
    const modal = document.getElementById('modalTareas');
    const container = document.getElementById('listaTareasContainer');
    const titulo = document.getElementById('modalTitulo');
    const subtitulo = document.getElementById('modalSubtitulo');
    const estadoGuard = document.getElementById('estadoGuardadoTareas');

    if (!modal || !container || !titulo || !subtitulo) return;

    modal.classList.add('show');

    if (idEjecucion) {
        titulo.innerText = `Tareas de la Ejecución #${idEjecucion}`;
        subtitulo.innerText = 'Marca o desmarca tareas para guardar su estado al instante.';
    } else {
        titulo.innerText = 'Todas las Tareas Ejecutadas';
        subtitulo.innerText = 'Historial completo de tareas ejecutadas de todas las ejecuciones.';
    }

    container.innerHTML = '<p style="text-align:center; padding: 20px; color:#888;">Cargando tareas...</p>';
    if (estadoGuard) estadoGuard.innerText = '';

    try {
        const url = idEjecucion
            ? `${API_EJECUCIONES_URL}?action=get_tareas&id_ejecucion=${encodeURIComponent(idEjecucion)}`
            : `${API_EJECUCIONES_URL}?action=get_tareas`;

        const respuesta = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'include'
        });
        const res = await respuesta.json();

        if (!respuesta.ok || !res || res.ok === false) {
            throw new Error(res.error || 'Error al cargar el detalle de las tareas.');
        }

        if (!res.tareas || res.tareas.length === 0) {
            container.innerHTML = '<p style="text-align:center; padding: 20px; color:#888;">No hay tareas registradas para esta ejecución.</p>';
            return;
        }

        const columnas = Array.isArray(res.columnas) ? res.columnas.join(', ') : 'id_tarea_ejecutada, id_ejecucion, id_tarea, completada, observaciones, tiempo_empleado';

        container.innerHTML = `
            <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:6px; padding:10px 14px; margin-bottom:12px;">
                <p style="font-size:0.75rem; font-weight:bold; color:#c2410c; margin:0;">Tabla: tareas_ejecutadas</p>
                <p style="font-size:0.75rem; color:#555; margin:4px 0 0;">Columnas: ${escaparHtml(columnas)}</p>
            </div>
            <div style="overflow-x:auto; border:1px solid #e2e8f0; border-radius:6px;">
                <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                    <thead style="background:#f8fafc;">
                        <tr>
                            <th style="padding:10px 12px; text-align:left; font-size:0.75rem; text-transform:uppercase; color:#64748b;">ID Ejec.</th>
                            <th style="padding:10px 12px; text-align:left; font-size:0.75rem; text-transform:uppercase; color:#64748b;">ID Tarea Ejec.</th>
                            <th style="padding:10px 12px; text-align:left; font-size:0.75rem; text-transform:uppercase; color:#64748b;">ID Tarea</th>
                            <th style="padding:10px 12px; text-align:left; font-size:0.75rem; text-transform:uppercase; color:#64748b;">Observaciones</th>
                            <th style="padding:10px 12px; text-align:left; font-size:0.75rem; text-transform:uppercase; color:#64748b;">Tiempo</th>
                            <th style="padding:10px 12px; text-align:left; font-size:0.75rem; text-transform:uppercase; color:#64748b;">Completada</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${res.tareas.map(tarea => renderTarea(tarea, idEjecucion)).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        container.innerHTML = `<p style="text-align:center; color:#e74c3c; padding:20px;">${escaparHtml(error.message)}</p>`;
    }
}

/**
 * Cierra el modal de tareas ejecutadas
 */
function cerrarModalTareas() {
    document.getElementById('modalTareas')?.classList.remove('show');
}

/**
 * Genera el HTML de una fila de tarea para mostrar en la tabla de tareas
 * Incluye checkbox para marcar como completada
 * @param {Object} tarea - Objeto tarea con datos
 * @param {number|string} idEjecucion - ID de la ejecución padre
 * @returns {string} HTML de la fila de tarea
 */
function renderTarea(tarea, idEjecucion) {
    const completada = esTareaCompletada(tarea.estado_tarea);
    return `
        <tr style="border-top:1px solid #f1f5f9;">
            <td style="padding:10px 12px; color:#1e293b; font-weight:600;">${escaparHtml(tarea.id_ejecucion || idEjecucion)}</td>
            <td style="padding:10px 12px; color:#475569;">${escaparHtml(tarea.id_tarea_ejecutada || tarea.id || '---')}</td>
            <td style="padding:10px 12px; color:#475569;">${escaparHtml(tarea.id_tarea || '---')}</td>
            <td style="padding:10px 12px; color:#475569; min-width:200px;">
                <span style="${completada ? 'text-decoration:line-through; opacity:0.6;' : ''}">${escaparHtml(tarea.observaciones || 'Sin observaciones')}</span>
            </td>
            <td style="padding:10px 12px; color:#475569; white-space:nowrap;">${escaparHtml(tarea.tiempo_empleado || 0)} min</td>
            <td style="padding:10px 12px;">
                <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                    <input type="checkbox"
                        data-action="toggle-tarea"
                        data-id="${escaparHtml(tarea.id || tarea.id_tarea_ejecutada || '')}"
                        data-id-ejecucion="${escaparHtml(idEjecucion)}"
                        data-id-tarea="${escaparHtml(tarea.id_tarea || '')}"
                        ${completada ? 'checked' : ''}
                        style="width:16px; height:16px; cursor:pointer;"
                    >
                    <span style="font-size:0.75rem; font-weight:bold; padding:2px 8px; border-radius:999px; ${completada ? 'background:#dcfce7; color:#16a34a;' : 'background:#f1f5f9; color:#64748b;'}">
                        ${completada ? 'Sí' : 'No'}
                    </span>
                </label>
            </td>
        </tr>
    `;
}

/**
 * Actualiza el estado (completada/no completada) de una tarea ejecutada
 * Se ejecuta automáticamente cuando el usuario marca/desmarca un checkbox
 * Envía un POST al API con el nuevo estado
 * @param {HTMLElement} checkbox - Elemento checkbox de la tarea
 */
async function actualizarEstadoTarea(checkbox) {
    const estadoGuard = document.getElementById('estadoGuardadoTareas');
    const fila = checkbox.closest('tr');
    const nuevoEstado = checkbox.checked ? 'Completada' : 'No Completada';

    checkbox.disabled = true;
    if (estadoGuard) estadoGuard.innerText = 'Guardando cambios...';

    try {
        const respuesta = await fetch(`${API_EJECUCIONES_URL}?action=update_tarea`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                id: checkbox.dataset.id,
                id_tarea: checkbox.dataset.idTarea,
                id_ejecucion: checkbox.dataset.idEjecucion,
                estado_tarea: nuevoEstado
            })
        });

        const res = await respuesta.json();
        if (!respuesta.ok || !res || res.ok === false) throw new Error(res.error || 'No se pudo actualizar la tarea.');

        actualizarVistaTarea(fila, checkbox.checked);
        if (estadoGuard) estadoGuard.innerText = 'Cambios guardados.';
    } catch (error) {
        checkbox.checked = !checkbox.checked;
        actualizarVistaTarea(fila, checkbox.checked);
        if (estadoGuard) estadoGuard.innerText = error.message;
    } finally {
        checkbox.disabled = false;
    }
}

/**
 * Actualiza la vista visual de una tarea después de cambiar su estado
 * @param {HTMLElement} fila - Elemento <tr> de la tarea
 * @param {boolean} completada - true si la tarea está completada
 */
function actualizarVistaTarea(fila, completada) {
    if (!fila) return;
    const span = fila.querySelector('span[style*="text-decoration"]') || fila.querySelector('td:nth-child(4) span');
    if (span) {
        span.style.textDecoration = completada ? 'line-through' : '';
        span.style.opacity = completada ? '0.6' : '1';
    }
    const badge = fila.querySelector('label > span');
    if (badge) {
        badge.textContent = completada ? 'Sí' : 'No';
        badge.style.background = completada ? '#dcfce7' : '#f1f5f9';
        badge.style.color = completada ? '#16a34a' : '#64748b';
    }
}

// ─── MENSAJES DEL FORMULARIO ──────────────────────────────────────────────────

function mostrarMensajeEjecucion(texto, tipo = 'info') {
    const el = document.getElementById('mensajeEjecucion');
    if (!el) return;
    el.style.backgroundColor = tipo === 'error' ? '#f8d7da' : '#fff3cd';
    el.style.color = tipo === 'error' ? '#721c24' : '#856404';
    el.style.border = tipo === 'error' ? '1px solid #f5c6cb' : '1px solid #ffeeba';
    el.textContent = texto;
    el.classList.remove('hidden');
}

function limpiarMensajeEjecucion() {
    const el = document.getElementById('mensajeEjecucion');
    if (!el) return;
    el.textContent = '';
    el.className = 'hidden';
}

// ─── UTILIDADES ───────────────────────────────────────────────────────────────

function esTareaCompletada(estado) {
    return estado === true || estado === 1 || estado === '1' || String(estado).toLowerCase() === 'completada';
}

function claseResultado(resultado) {
    const valor = String(resultado || '').toLowerCase();
    const base = 'status-badge inline-flex whitespace-nowrap';
    if (valor.includes('exitoso')) return `${base} success`;
    if (valor.includes('incidencia')) return `${base} bg-orange-100 text-orange-700`;
    return `${base} bg-slate-100 text-slate-700`;
}

function formatearResultado(resultado) {
    const valor = String(resultado || '').trim().toLowerCase();
    if (valor === 'exitoso') return 'Exitoso';
    if (valor === 'con_incidencias') return 'Con incidencias';
    if (!valor) return '---';
    return valor.replaceAll('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function escaparHtml(valor) {
    return String(valor ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
