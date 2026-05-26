/**
 * =========================================================================
 * ARCHIVO: Js/horarioTrabajo.js
 * PROPÓSITO: Gestión de Horarios con persistencia en Base de Datos usando FullCalendar.
 * REFERENCIA WEB: HTML/administracionMenu/horarioTrabajo.html
 * =========================================================================
 */

let calendar;

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendario');
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        firstDay: 1, 
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana' },
        events: [] 
    });
    
    calendar.render();

    // NUEVO: Cargar los turnos que ya existen en la base de datos
    cargarHorarios();
});

// 1. FUNCIÓN PARA LEER DE LA BBDD
async function cargarHorarios() {
    try {
        // Usamos la ruta absoluta que ya sabemos que funciona en tu servidor
        const res = await api('GET', '/api/administracion/horarios.php');
        
        if (res && res.horarios) {
            // Limpiar eventos actuales y añadir los de la BBDD
            calendar.removeAllEvents();
            const tabla = document.getElementById("tablaTurnos");
            tabla.innerHTML = ""; // Limpiar tabla visual

            res.horarios.forEach(h => {
                // Añadir al calendario
                calendar.addEvent({
                    id: h.id,
                    title: `${h.nombre} (${h.turno})`,
                    start: h.fecha,
                    className: `turno-${h.turno.toLowerCase()}`
                });

                // Añadir a la tabla
                const fila = `
                    <tr id="turno-${h.id}">
                        <td>${h.nombre}</td>
                        <td><span class="status-badge">${h.turno}</span></td>
                        <td>${h.fecha}</td>
                        <td><button class="btn-icon" onclick="eliminarTurno(${h.id})">🗑️</button></td>
                    </tr>`;
                tabla.insertAdjacentHTML('beforeend', fila);
            });
        }
    } catch (error) {
        console.error("Error cargando horarios:", error);
    }
}

// 2. FUNCIÓN PARA AGREGAR (Y GUARDAR EN BBDD)
async function agregarTurno() {
    const nombre = document.getElementById("nombreEmpleado").value;
    const turno = document.getElementById("turno").value;
    const fecha = document.getElementById("fechaTurno").value;

    if (!nombre || !fecha) {
        alert("Por favor, rellena todos los campos.");
        return;
    }

    // Enviamos los datos al PHP
    const datos = { nombre, turno, fecha };
    const res = await api('POST', '/api/administracion/horarios.php', datos);

    if (res && res.ok) {
        // Si el PHP dice que OK, recargamos la vista para ver los cambios
        cargarHorarios();
        document.getElementById("nombreEmpleado").value = "";
    } else {
        alert("Error al guardar en la base de datos: " + (res.error || "Desconocido"));
    }
}

// 3. FUNCIÓN PARA ELIMINAR (Opcional pero recomendada)
async function eliminarTurno(id) {
    if (!confirm("¿Seguro que quieres borrar este turno?")) return;

    const res = await api('DELETE', '/api/administracion/horarios.php', { id });
    if (res && res.ok) {
        cargarHorarios(); // Refrescar
    }
}