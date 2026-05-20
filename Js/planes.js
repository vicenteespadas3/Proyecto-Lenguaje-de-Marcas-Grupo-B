// Js/planes.js
// CRUD completo para Planes de Mantenimiento
// Estructura BBDD: id_plan, id_maquina, id_gama, id_frecuencia, horas_intervalo,
//                  id_dificultad, duracion_estimada, activo, fecha_creacion

let planesData = [];
let planesExpandidos = {}; // { id_plan: true/false }

document.addEventListener('DOMContentLoaded', async () => {
    // Dibujamos el menú superior y lateral
    renderMenu();

    // Cargamos los selectores del modal (máquinas, gamas, frecuencias, dificultades)
    await cargarSelectores();

    // Cargamos la tabla principal
    await cargarTabla();

    // --- EVENTOS DE BÚSQUEDA ---
    const btnBuscar   = document.getElementById('btnBuscarPlan');
    const inputBuscar = document.getElementById('inputBuscarPlan');

    if (btnBuscar && inputBuscar) {
        btnBuscar.addEventListener('click', () => cargarTabla(inputBuscar.value));
        inputBuscar.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') cargarTabla(inputBuscar.value);
        });
    }

    // --- BOTÓN NUEVO PLAN ---
    const btnNuevo = document.getElementById('btnNuevoPlan');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', () => abrirModalPlan());
    }

    // Cerrar modal al hacer clic fuera de él
    document.getElementById('modalPlan').addEventListener('click', function(e) {
        if (e.target === this) cerrarModalPlan();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
//  CARGAR SELECTORES: Máquinas, Gamas, Frecuencias y Dificultades
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Rellena los <select> del modal con datos desde la API.
 * - Máquinas: desde api/activos/maquinas.php
 * - Gamas: desde api/mantenimiento/gamas.php
 * - Frecuencias y Dificultades: desde la tabla 'definiciones' (api/mantenimiento/planes.php?definiciones=1)
 */
async function cargarSelectores() {
    // 1. Máquinas
    const resMaquinas = await api('GET', '../../api/activos/maquinas.php');
    const selectMaquina = document.getElementById('plan_id_maquina');
    if (resMaquinas && resMaquinas.maquinas) {
        selectMaquina.innerHTML = '<option value="">-- Selecciona una máquina --</option>';
        resMaquinas.maquinas.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id_maquina;
            opt.textContent = `[${m.id_maquina}] ${m.nombre}`;
            selectMaquina.appendChild(opt);
        });
    } else {
        selectMaquina.innerHTML = '<option value="">⚠️ Error al cargar máquinas</option>';
    }

    // 2. Gamas
    const resGamas = await api('GET', '../../api/mantenimiento/gamas.php');
    const selectGama = document.getElementById('plan_id_gama');
    if (resGamas && resGamas.gamas) {
        selectGama.innerHTML = '<option value="">-- Selecciona una gama --</option>';
        resGamas.gamas.forEach(g => {
            const opt = document.createElement('option');
            opt.value = g.id_gama;
            opt.textContent = `[${g.id_gama}] ${g.nombre}`;
            selectGama.appendChild(opt);
        });
    } else {
        selectGama.innerHTML = '<option value="">⚠️ Error al cargar gamas</option>';
    }

    // 3. Frecuencias y Dificultades (desde la tabla definiciones)
    const resDef = await api('GET', '../../api/mantenimiento/planes.php?definiciones=1');
    
    const selectFrecuencia = document.getElementById('plan_id_frecuencia');
    const selectDificultad = document.getElementById('plan_id_dificultad');

    if (resDef && resDef.frecuencias) {
        selectFrecuencia.innerHTML = '<option value="">-- Selecciona frecuencia --</option>';
        resDef.frecuencias.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.id_definicion;
            opt.textContent = f.descripcion;
            selectFrecuencia.appendChild(opt);
        });
    } else {
        selectFrecuencia.innerHTML = '<option value="">⚠️ Error al cargar frecuencias</option>';
    }

    if (resDef && resDef.dificultades) {
        selectDificultad.innerHTML = '<option value="">-- Selecciona dificultad --</option>';
        resDef.dificultades.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id_definicion;
            opt.textContent = d.descripcion;
            selectDificultad.appendChild(opt);
        });
    } else {
        selectDificultad.innerHTML = '<option value="">⚠️ Error al cargar dificultades</option>';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  TABLA PRINCIPAL
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Carga los planes desde la API y los pinta en la tabla con sus JOINs.
 * @param {string} query - Texto de búsqueda opcional.
 */
async function cargarTabla(query = '') {
    const tbody = document.getElementById('cuerpoTablaPlanes');
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:20px;">Cargando... ⚙️</td></tr>';

    let url = '../../api/mantenimiento/planes.php';
    if (query.trim() !== '') {
        url += `?buscar=${encodeURIComponent(query.trim())}`;
    }

    const respuesta = await api('GET', url);

    if (!respuesta || respuesta.ok === false) {
        tbody.innerHTML = `<tr><td colspan="9" style="color:red; text-align:center; padding:20px;">⚠️ Error al cargar los planes. Comprueba la conexión con la base de datos.</td></tr>`;
        return;
    }

    planesData = respuesta.planes || [];
    tbody.innerHTML = '';

    if (planesData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:20px; color:#7f8c8d;">No hay planes de mantenimiento registrados aún.</td></tr>`;
        return;
    }

    planesData.forEach(plan => {
        // Badge de estado activo/inactivo
        const esActivo    = parseInt(plan.activo) === 1;
        const badgeActivo = esActivo
            ? `<span class="etiqueta-estado exito">Activo</span>`
            : `<span class="etiqueta-estado peligro">Inactivo</span>`;

        // Badge de dificultad
        const difDesc  = plan.desc_dificultad || '—';
        let difClase   = 'advertencia';
        if (difDesc.toLowerCase() === 'baja') difClase = 'exito';
        if (difDesc.toLowerCase() === 'alta') difClase = 'peligro';
        const badgeDif = `<span class="etiqueta-estado ${difClase}">${difDesc}</span>`;

        const horasHTML = plan.horas_intervalo
            ? `<strong>${plan.horas_intervalo}</strong> h`
            : `<span style="color:#bdc3c7">—</span>`;

        const durHTML = plan.duracion_estimada
            ? `${plan.duracion_estimada} min`
            : `<span style="color:#bdc3c7">—</span>`;

        // ── Fila principal del plan ──────────────────────────────
        const fila = document.createElement('tr');
        fila.className    = 'fila-plan';
        fila.style.cursor = 'pointer';
        fila.title        = 'Clic para ver tareas del plan';
        fila.dataset.plan = plan.id_plan;
        fila.innerHTML = `
            <td class="col-id">${plan.id_plan}</td>
            <td>🏭 <strong>${plan.nombre_maquina || `ID ${plan.id_maquina}`}</strong></td>
            <td><span class="tipo-gama">${plan.nombre_gama || `ID ${plan.id_gama}`}</span></td>
            <td>🔁 ${plan.desc_frecuencia || `ID ${plan.id_frecuencia}`}</td>
            <td>${horasHTML}</td>
            <td>${badgeDif}</td>
            <td>${durHTML}</td>
            <td>${badgeActivo}</td>
            <td class="action-buttons justify-center">
                <span class="plan-expand-icon" id="planExpand_${plan.id_plan}" style="color:#bdc3c7; margin-right:6px; font-size:0.9em;">▶ Tareas</span>
                <button class="btn btn-icon" title="Editar plan" onclick="event.stopPropagation(); editarPlan(${plan.id_plan})">✏️</button>
                <button class="btn btn-icon" style="color:#e74c3c;" title="Eliminar plan" onclick="event.stopPropagation(); borrarPlan(${plan.id_plan})">🗑️</button>
            </td>
        `;

        // ── Fila de tareas (expandible, oculta por defecto) ──────
        const filaTareas = document.createElement('tr');
        filaTareas.className    = 'fila-tareas-plan';
        filaTareas.dataset.plan = plan.id_plan;
        filaTareas.style.display = 'none';
        filaTareas.innerHTML = `
            <td colspan="9" style="padding:0; border-bottom: 2px solid var(--primary);">
                <div class="tareas-plan-panel" id="panelTareas_${plan.id_plan}">
                    <p style="color:#95a5a6; margin:0; font-style:italic;">Cargando tareas...</p>
                </div>
            </td>
        `;

        fila.addEventListener('click', () => toggleTareasPlan(plan.id_plan, filaTareas));

        tbody.appendChild(fila);
        tbody.appendChild(filaTareas);
    });
}

// ─────────────────────────────────────────────────────────────────────────────
//  MODAL: ABRIR / CERRAR
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Abre el modal en blanco para CREAR un nuevo plan.
 */
function abrirModalPlan() {
    document.getElementById('modalPlanTitulo').textContent = '➕ Nuevo Plan de Mantenimiento';
    document.getElementById('formPlan').reset();
    document.getElementById('plan_id').value = '';
    document.getElementById('modalPlan').classList.add('show');
}

/**
 * Abre el modal con los datos rellenos para EDITAR un plan existente.
 * @param {number} id - ID del plan a editar.
 */
function editarPlan(id) {
    const plan = planesData.find(p => p.id_plan == id);
    if (!plan) {
        alert('No se encontraron los datos del plan. Recarga la página.');
        return;
    }

    document.getElementById('modalPlanTitulo').textContent = '✏️ Editar Plan de Mantenimiento';
    document.getElementById('plan_id').value              = plan.id_plan;
    document.getElementById('plan_id_maquina').value      = plan.id_maquina   || '';
    document.getElementById('plan_id_gama').value         = plan.id_gama      || '';
    document.getElementById('plan_id_frecuencia').value   = plan.id_frecuencia|| '';
    document.getElementById('plan_horas_intervalo').value = plan.horas_intervalo || '';
    document.getElementById('plan_id_dificultad').value   = plan.id_dificultad|| '';
    document.getElementById('plan_duracion_estimada').value = plan.duracion_estimada || '';
    document.getElementById('plan_activo').value          = plan.activo !== undefined ? plan.activo : '1';

    document.getElementById('modalPlan').classList.add('show');
}

/**
 * Cierra el modal de planes.
 */
function cerrarModalPlan() {
    document.getElementById('modalPlan').classList.remove('show');
}

// ─────────────────────────────────────────────────────────────────────────────
//  TAREAS EXPANDIBLES POR PLAN
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Muestra u oculta el panel de tareas de un plan y lo carga si es la primera vez.
 */
async function toggleTareasPlan(idPlan, filaTareas) {
    const icono  = document.getElementById(`planExpand_${idPlan}`);
    const panel  = document.getElementById(`panelTareas_${idPlan}`);
    const abierto = filaTareas.style.display !== 'none';

    if (abierto) {
        filaTareas.style.display = 'none';
        planesExpandidos[idPlan] = false;
        if (icono) { icono.textContent = '▶ Tareas'; icono.style.color = '#bdc3c7'; }
        return;
    }

    // Abrir
    filaTareas.style.display = '';
    planesExpandidos[idPlan] = true;
    if (icono) { icono.textContent = '▼ Tareas'; icono.style.color = 'var(--primary)'; }

    // Solo cargamos si aún no lo hemos hecho (el panel tiene el texto de carga)
    if (panel.dataset.cargado === '1') return;
    panel.dataset.cargado = '1';

    const res = await api('GET', `../../api/mantenimiento/tareas_mant.php?id_plan=${idPlan}`);
    const tareas = res && res.tareas ? res.tareas : [];

    if (tareas.length === 0) {
        panel.innerHTML = `<p style="color:#95a5a6; margin:0; font-style:italic; padding:4px 0;">Este plan no tiene tareas asignadas todavía.</p>`;
        return;
    }

    panel.innerHTML = tareas.map(t => {
        let chips = '';
        if (parseInt(t.requiere_epi) === 1)  chips += `<span class="chip chip-epi">🦺 EPI</span>`;
        if (t.reglas_seguridad)              chips += `<span class="chip chip-seguridad">⚠️ Seg.</span>`;
        if (t.reglas_medio_ambiente)         chips += `<span class="chip chip-medamb">🌿 M.A.</span>`;

        return `
            <div class="tarea-mini-card">
                <div class="tarea-mini-paso">${t.orden}</div>
                <div class="tarea-mini-body">
                    <strong>${t.descripcion}</strong>
                    ${chips ? `<div style="margin-top:4px">${chips}</div>` : ''}
                    ${t.instrucciones_detalladas
                        ? `<small style="color:#95a5a6; display:block; margin-top:2px">${t.instrucciones_detalladas.substring(0,80)}${t.instrucciones_detalladas.length > 80 ? '…' : ''}</small>`
                        : ''}
                </div>
            </div>
        `;
    }).join('');
}

// ─────────────────────────────────────────────────────────────────────────────
//  CRUD A LA API
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Envía el formulario del modal a la API (POST para crear, PUT para editar).
 */
async function guardarPlan(evento) {
    evento.preventDefault();

    const id              = document.getElementById('plan_id').value;
    const id_maquina      = document.getElementById('plan_id_maquina').value;
    const id_gama         = document.getElementById('plan_id_gama').value;
    const id_frecuencia   = document.getElementById('plan_id_frecuencia').value;
    const horas_intervalo = document.getElementById('plan_horas_intervalo').value;
    const id_dificultad   = document.getElementById('plan_id_dificultad').value;
    const duracion        = document.getElementById('plan_duracion_estimada').value;
    const activo          = document.getElementById('plan_activo').value;

    // Validaciones básicas
    if (!id_maquina)    { alert('Debes seleccionar una máquina.'); return; }
    if (!id_gama)       { alert('Debes seleccionar una gama de mantenimiento.'); return; }
    if (!id_frecuencia) { alert('Debes seleccionar una frecuencia.'); return; }
    if (!id_dificultad) { alert('Debes seleccionar un nivel de dificultad.'); return; }

    const datos = {
        id_maquina:        parseInt(id_maquina),
        id_gama:           parseInt(id_gama),
        id_frecuencia:     parseInt(id_frecuencia),
        horas_intervalo:   horas_intervalo !== '' ? parseInt(horas_intervalo) : null,
        id_dificultad:     parseInt(id_dificultad),
        duracion_estimada: duracion !== '' ? parseInt(duracion) : null,
        activo:            parseInt(activo)
    };

    // POST (crear) o PUT (editar)
    let metodo = 'POST';
    if (id !== '') {
        metodo = 'PUT';
        datos.id_plan = parseInt(id);
    }

    const btnGuardar = document.getElementById('btnGuardarPlan');
    btnGuardar.disabled     = true;
    btnGuardar.textContent  = 'Guardando...';

    const respuesta = await api(metodo, '../../api/mantenimiento/planes.php', datos);

    btnGuardar.disabled    = false;
    btnGuardar.textContent = '💾 Guardar Plan';

    if (respuesta && respuesta.ok !== false) {
        cerrarModalPlan();
        document.getElementById('inputBuscarPlan').value = '';
        await cargarTabla();
    } else {
        alert('Error: ' + (respuesta ? respuesta.error : 'No se pudo guardar el plan'));
    }
}

/**
 * Elimina permanentemente un plan de mantenimiento.
 * @param {number} id_plan - ID del plan a eliminar.
 */
async function borrarPlan(id_plan) {
    if (!confirm(`¿Seguro que quieres eliminar el plan ID ${id_plan}?\nEsta acción no se puede deshacer.`)) return;

    const respuesta = await api('DELETE', '../../api/mantenimiento/planes.php', { id_plan: id_plan });

    if (respuesta && respuesta.ok !== false) {
        await cargarTabla(document.getElementById('inputBuscarPlan').value);
    } else {
        alert('Error al eliminar: ' + (respuesta ? respuesta.error : 'No se pudo borrar el plan'));
    }
}
