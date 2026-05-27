// Js/tareas_mant.js
// CRUD completo para Tareas de Mantenimiento con vista agrupada por plan
// Estructura BBDD: id_tarea, id_plan (FK), descripcion, orden,
//                  instrucciones_detalladas, requiere_epi,
//                  epis_necesarios, reglas_seguridad, reglas_medio_ambiente

let tareasData  = []; // Todos los datos cargados
let gruposVisibles = {}; // { id_plan: true/false } para colapsar/expandir

document.addEventListener('DOMContentLoaded', async () => {
    renderMenu();

    await cargarSelectorPlanes();
    await cargarTabla();

    // --- BÚSQUEDA ---
    const btnBuscar   = document.getElementById('btnBuscarTarea');
    const inputBuscar = document.getElementById('inputBuscarTarea');
    if (btnBuscar && inputBuscar) {
        btnBuscar.addEventListener('click', () => cargarTabla(inputBuscar.value));
        inputBuscar.addEventListener('keyup', (e) => { if (e.key === 'Enter') cargarTabla(inputBuscar.value); });
    }

    // --- FILTRO POR PLAN ---
    document.getElementById('filtroPlan').addEventListener('change', () => pintarTabla());

    // --- NUEVA TAREA ---
    document.getElementById('btnNuevaTarea').addEventListener('click', () => abrirModalTarea());

    // --- EXPANDIR / COLAPSAR TODO ---
    document.getElementById('btnExpandirTodo').addEventListener('click', toggleTodo);

    // --- EPI CHECKBOX ---
    document.getElementById('tarea_requiere_epi').addEventListener('change', toggleBloqueEpi);

    // --- CERRAR MODAL AL CLIC FUERA ---
    document.getElementById('modalTarea').addEventListener('click', function(e) {
        if (e.target === this) cerrarModalTarea();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
//  SELECTOR DE PLANES (modal + filtro)
// ─────────────────────────────────────────────────────────────────────────────

async function cargarSelectorPlanes() {
    const res = await api('GET', '../../api/mantenimiento/tareas_mant.php?planes=1');

    const selectModal  = document.getElementById('tarea_id_plan');
    const selectFiltro = document.getElementById('filtroPlan');

    if (res && res.planes) {
        selectModal.innerHTML  = '<option value="">-- Selecciona un plan --</option>';
        selectFiltro.innerHTML = '<option value="">— Todos los planes —</option>';

        res.planes.forEach(p => {
            const etiqueta = `[Plan ${p.id_plan}] ${p.nombre_maquina} → ${p.nombre_gama}`;

            const optModal = document.createElement('option');
            optModal.value       = p.id_plan;
            optModal.textContent = etiqueta;
            selectModal.appendChild(optModal);

            const optFiltro = document.createElement('option');
            optFiltro.value       = p.id_plan;
            optFiltro.textContent = etiqueta;
            selectFiltro.appendChild(optFiltro);
        });
    } else {
        selectModal.innerHTML  = '<option value="">⚠️ Error al cargar planes</option>';
        selectFiltro.innerHTML = '<option value="">⚠️ Error al cargar planes</option>';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  CARGA DE DATOS DESDE LA API
// ─────────────────────────────────────────────────────────────────────────────

async function cargarTabla(query = '') {
    const tbody = document.getElementById('cuerpoTablaTareas');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">Cargando... ⚙️</td></tr>';

    let url = '../../api/mantenimiento/tareas_mant.php';
    if (query.trim() !== '') url += `?buscar=${encodeURIComponent(query.trim())}`;

    const respuesta = await api('GET', url);

    if (!respuesta || respuesta.ok === false) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:red; text-align:center; padding:20px;">⚠️ Error al cargar las tareas. Comprueba la conexión con la base de datos.</td></tr>`;
        return;
    }

    tareasData = respuesta.tareas || [];

    // Inicializar todos los grupos como visibles
    const idPlanes = [...new Set(tareasData.map(t => t.id_plan))];
    idPlanes.forEach(id => {
        if (gruposVisibles[id] === undefined) gruposVisibles[id] = true;
    });

    pintarTabla();
}

// ─────────────────────────────────────────────────────────────────────────────
//  RENDERIZADO AGRUPADO POR PLAN
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Pinta la tabla agrupando las tareas por id_plan.
 * Respeta el filtro del <select> y el estado colapsar/expandir de cada grupo.
 */
function pintarTabla() {
    const tbody        = document.getElementById('cuerpoTablaTareas');
    const filtroIdPlan = document.getElementById('filtroPlan').value;

    tbody.innerHTML = '';

    // Filtrar por plan si está seleccionado
    const datosFiltrados = filtroIdPlan
        ? tareasData.filter(t => t.id_plan == filtroIdPlan)
        : tareasData;

    if (datosFiltrados.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:20px; color:#7f8c8d;">No hay tareas que coincidan con los criterios de búsqueda.</td></tr>`;
        return;
    }

    // Agrupar por id_plan manteniendo el orden de aparición
    const grupos = {};
    const ordenGrupos = [];
    datosFiltrados.forEach(t => {
        if (!grupos[t.id_plan]) {
            grupos[t.id_plan] = [];
            ordenGrupos.push(t.id_plan);
        }
        grupos[t.id_plan].push(t);
    });

    ordenGrupos.forEach(idPlan => {
        const tareas   = grupos[idPlan];
        const primera  = tareas[0];
        const visible  = gruposVisibles[idPlan] !== false;
        const nTareas  = tareas.length;

        // ── FILA CABECERA DE PLAN ──────────────────────────────
        const maquina = primera.nombre_maquina || `Máquina ${primera.id_maquina || '?'}`;
        const gama    = primera.nombre_gama    || '';

        const trHeader = document.createElement('tr');
        trHeader.className = 'plan-group-header';
        trHeader.innerHTML = `
            <td colspan="5">
                📋 <strong>Plan ${idPlan}</strong>
                &nbsp;·&nbsp; 🏭 ${maquina}
                ${gama ? `&nbsp;·&nbsp; <span style="opacity:0.85">${gama}</span>` : ''}
                &nbsp;
                <span style="opacity:0.7; font-size:0.85em;">(${nTareas} tarea${nTareas !== 1 ? 's' : ''})</span>
                <button class="btn-toggle-grupo" onclick="toggleGrupo(${idPlan})" id="btnGrupo_${idPlan}">
                    ${visible ? '▲ Colapsar' : '▼ Expandir'}
                </button>
            </td>
        `;
        tbody.appendChild(trHeader);

        // ── FILAS DE TAREAS DEL GRUPO ──────────────────────────
        tareas.forEach(tarea => {
            const trTarea = document.createElement('tr');
            trTarea.className     = 'fila-tarea';
            trTarea.dataset.plan  = idPlan;
            trTarea.dataset.tarea = tarea.id_tarea;
            trTarea.style.display = visible ? '' : 'none';
            trTarea.style.cursor  = 'pointer';
            trTarea.title         = 'Clic para ver detalles';

            // Chips de seguridad
            let chips = '';
            if (parseInt(tarea.requiere_epi) === 1) chips += `<span class="chip chip-epi">🦺 EPI</span>`;
            if (tarea.reglas_seguridad)              chips += `<span class="chip chip-seguridad">⚠️ Seg.</span>`;
            if (tarea.reglas_medio_ambiente)         chips += `<span class="chip chip-medamb">🌿 M.A.</span>`;

            trTarea.innerHTML = `
                <td class="col-id">${tarea.id_tarea}</td>
                <td style="text-align:center;">
                    <span class="paso-badge">${tarea.orden}</span>
                </td>
                <td>
                    <strong>${tarea.descripcion}</strong>
                    ${tarea.instrucciones_detalladas
                        ? `<br><small style="color:#95a5a6; line-height:1.4">${tarea.instrucciones_detalladas.substring(0,90)}${tarea.instrucciones_detalladas.length > 90 ? '…' : ''}</small>`
                        : ''}
                </td>
                <td style="text-align:center;">${chips || '<span style="color:#bdc3c7; font-size:0.85em">—</span>'}</td>
                <td class="action-buttons justify-center">
                    <span class="icono-expand" id="expand_${tarea.id_tarea}" style="font-size:1em; color:#95a5a6; margin-right:6px;" title="Expandir">▶</span>
                    <button class="btn btn-icon" title="Editar tarea" onclick="event.stopPropagation(); editarTarea(${tarea.id_tarea})">✏️</button>
                    <button class="btn btn-icon" style="color:#e74c3c;" title="Eliminar tarea" onclick="event.stopPropagation(); borrarTarea(${tarea.id_tarea})">🗑️</button>
                </td>
            `;

            // ── FILA DE DETALLES (oculta por defecto) ────────────
            const trDetalle = document.createElement('tr');
            trDetalle.className     = 'fila-detalle';
            trDetalle.dataset.plan  = idPlan;
            trDetalle.dataset.tarea = tarea.id_tarea;
            trDetalle.style.display = 'none';

            // Construir secciones del detalle
            const secInstr = tarea.instrucciones_detalladas
                ? `<div class="detalle-seccion">
                       <div class="detalle-seccion-titulo">📋 Instrucciones detalladas</div>
                       <div class="detalle-seccion-cuerpo">${tarea.instrucciones_detalladas}</div>
                   </div>`
                : '';

            const secEpi = parseInt(tarea.requiere_epi) === 1
                ? `<div class="detalle-seccion detalle-epi">
                       <div class="detalle-seccion-titulo">🦺 Equipos de Protección Individual (EPI)</div>
                       <div class="detalle-seccion-cuerpo">${tarea.epis_necesarios || '<em style="color:#95a5a6">No especificados</em>'}</div>
                   </div>`
                : '';

            const secSeg = tarea.reglas_seguridad
                ? `<div class="detalle-seccion detalle-seguridad">
                       <div class="detalle-seccion-titulo">⚠️ Reglas de seguridad</div>
                       <div class="detalle-seccion-cuerpo">${tarea.reglas_seguridad}</div>
                   </div>`
                : '';

            const secMedAmb = tarea.reglas_medio_ambiente
                ? `<div class="detalle-seccion detalle-medamb">
                       <div class="detalle-seccion-titulo">🌿 Medio ambiente</div>
                       <div class="detalle-seccion-cuerpo">${tarea.reglas_medio_ambiente}</div>
                   </div>`
                : '';

            const sinExtra = !secInstr && !secEpi && !secSeg && !secMedAmb;

            trDetalle.innerHTML = `
                <td colspan="5" style="padding:0; border-bottom: 2px solid var(--primary);">
                    <div class="detalle-panel">
                        ${ sinExtra
                            ? '<p style="color:#95a5a6; margin:0; font-style:italic;">Esta tarea no tiene información adicional registrada.</p>'
                            : secInstr + secEpi + secSeg + secMedAmb
                        }
                    </div>
                </td>
            `;

            // Evento clic en la fila principal → toggle del detalle
            trTarea.addEventListener('click', () => toggleDetalle(tarea.id_tarea, trDetalle));

            tbody.appendChild(trTarea);
            tbody.appendChild(trDetalle);
        });
    });
}

// ─────────────────────────────────────────────────────────────────────────────
//  COLAPSAR / EXPANDIR GRUPOS
// ─────────────────────────────────────────────────────────────────────────────

function toggleGrupo(idPlan) {
    gruposVisibles[idPlan] = !gruposVisibles[idPlan];
    const visible = gruposVisibles[idPlan];

    // Mostrar/ocultar filas del grupo (tareas Y detalles)
    document.querySelectorAll(`.fila-tarea[data-plan="${idPlan}"], .fila-detalle[data-plan="${idPlan}"]`).forEach(tr => {
        tr.style.display = visible ? '' : 'none';
    });

    // Actualizar texto del botón
    const btn = document.getElementById(`btnGrupo_${idPlan}`);
    if (btn) btn.textContent = visible ? '▲ Colapsar' : '▼ Expandir';
}

/**
 * Abre o cierra el panel de detalles de una tarea al hacer clic en su fila.
 * @param {number} idTarea - ID de la tarea
 * @param {HTMLElement} trDetalle - La fila <tr> de detalles asociada
 */
function toggleDetalle(idTarea, trDetalle) {
    const estaVisible = trDetalle.style.display !== 'none';
    const icono       = document.getElementById(`expand_${idTarea}`);

    if (estaVisible) {
        trDetalle.style.display = 'none';
        if (icono) { icono.textContent = '▶'; icono.style.color = '#95a5a6'; }
    } else {
        trDetalle.style.display = '';
        if (icono) { icono.textContent = '▼'; icono.style.color = 'var(--primary)'; }
    }
}

let todoExpandido = true;
function toggleTodo() {
    todoExpandido = !todoExpandido;
    const idPlanes = [...new Set(tareasData.map(t => t.id_plan))];
    idPlanes.forEach(id => {
        gruposVisibles[id] = todoExpandido;
    });
    pintarTabla();
}

// ─────────────────────────────────────────────────────────────────────────────
//  MODAL: ABRIR / CERRAR / EPI
// ─────────────────────────────────────────────────────────────────────────────

function toggleBloqueEpi() {
    const checked = document.getElementById('tarea_requiere_epi').checked;
    document.getElementById('bloqueEpi').style.display = checked ? 'block' : 'none';
    if (!checked) document.getElementById('tarea_epis_necesarios').value = '';
}

function abrirModalTarea() {
    document.getElementById('modalTareaTitulo').textContent = '➕ Nueva Tarea de Mantenimiento';
    document.getElementById('formTarea').reset();
    document.getElementById('tarea_id').value    = '';
    document.getElementById('bloqueEpi').style.display = 'none';
    document.getElementById('modalTarea').classList.add('show');
}

function editarTarea(id) {
    const tarea = tareasData.find(t => t.id_tarea == id);
    if (!tarea) { alert('No se encontraron los datos de la tarea. Recarga la página.'); return; }

    document.getElementById('modalTareaTitulo').textContent             = '✏️ Editar Tarea de Mantenimiento';
    document.getElementById('tarea_id').value                           = tarea.id_tarea;
    document.getElementById('tarea_id_plan').value                      = tarea.id_plan           || '';
    document.getElementById('tarea_orden').value                        = tarea.orden             || '';
    document.getElementById('tarea_descripcion').value                  = tarea.descripcion       || '';
    document.getElementById('tarea_instrucciones').value                = tarea.instrucciones_detalladas || '';
    document.getElementById('tarea_requiere_epi').checked               = parseInt(tarea.requiere_epi) === 1;
    document.getElementById('tarea_epis_necesarios').value              = tarea.epis_necesarios   || '';
    document.getElementById('tarea_reglas_seguridad').value             = tarea.reglas_seguridad  || '';
    document.getElementById('tarea_reglas_medio_ambiente').value        = tarea.reglas_medio_ambiente || '';
    document.getElementById('bloqueEpi').style.display = parseInt(tarea.requiere_epi) === 1 ? 'block' : 'none';

    document.getElementById('modalTarea').classList.add('show');
}

function cerrarModalTarea() {
    document.getElementById('modalTarea').classList.remove('show');
}

// ─────────────────────────────────────────────────────────────────────────────
//  CRUD A LA API
// ─────────────────────────────────────────────────────────────────────────────

async function guardarTarea(evento) {
    evento.preventDefault();

    const id           = document.getElementById('tarea_id').value;
    const id_plan      = document.getElementById('tarea_id_plan').value;
    const orden        = document.getElementById('tarea_orden').value;
    const descripcion  = document.getElementById('tarea_descripcion').value.trim();
    const instrucciones= document.getElementById('tarea_instrucciones').value.trim();
    const requiere_epi = document.getElementById('tarea_requiere_epi').checked ? 1 : 0;
    const epis         = document.getElementById('tarea_epis_necesarios').value.trim();
    const seg          = document.getElementById('tarea_reglas_seguridad').value.trim();
    const medAmb       = document.getElementById('tarea_reglas_medio_ambiente').value.trim();

    if (!id_plan)     { alert('Debes seleccionar un plan de mantenimiento.'); return; }
    if (!descripcion) { alert('La descripción de la tarea es obligatoria.'); return; }
    if (!orden || parseInt(orden) < 1) { alert('El orden de ejecución debe ser al menos 1.'); return; }

    const datos = {
        id_plan:                  parseInt(id_plan),
        descripcion,
        orden:                    parseInt(orden),
        instrucciones_detalladas: instrucciones || null,
        requiere_epi,
        epis_necesarios:          requiere_epi ? (epis || null) : null,
        reglas_seguridad:         seg     || null,
        reglas_medio_ambiente:    medAmb  || null
    };

    let metodo = 'POST';
    if (id !== '') { metodo = 'PUT'; datos.id_tarea = parseInt(id); }

    const btnGuardar = document.getElementById('btnGuardarTarea');
    btnGuardar.disabled = true; btnGuardar.textContent = 'Guardando...';

    const respuesta = await api(metodo, '../../api/mantenimiento/tareas_mant.php', datos);

    btnGuardar.disabled = false; btnGuardar.textContent = '💾 Guardar Tarea';

    if (respuesta && respuesta.ok !== false) {
        cerrarModalTarea();
        document.getElementById('inputBuscarTarea').value = '';
        await cargarTabla();
    } else {
        alert('Error: ' + (respuesta ? respuesta.error : 'No se pudo guardar la tarea'));
    }
}

async function borrarTarea(id_tarea) {
    const tarea = tareasData.find(t => t.id_tarea == id_tarea);
    const desc  = tarea ? `"${tarea.descripcion.substring(0, 50)}"` : `ID ${id_tarea}`;

    if (!confirm(`¿Seguro que quieres eliminar la tarea ${desc}?\nEsta acción no se puede deshacer.`)) return;

    const respuesta = await api('DELETE', '../../api/mantenimiento/tareas_mant.php', { id_tarea });

    if (respuesta && respuesta.ok !== false) {
        await cargarTabla(document.getElementById('inputBuscarTarea').value);
    } else {
        alert('Error al eliminar: ' + (respuesta ? respuesta.error : 'No se pudo borrar la tarea'));
    }
}
