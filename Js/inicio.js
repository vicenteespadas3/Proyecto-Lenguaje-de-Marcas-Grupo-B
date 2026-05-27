document.addEventListener('DOMContentLoaded', () => {
    // Le decimos al menú que marque "inicio" como activo
    renderMenu('inicio'); 
    cargarResumenDashboard();
});

async function cargarResumenDashboard() {
    const res = await api('GET', '../api/dashboard/resumen.php');
    const contIncidencias = document.getElementById('dashboard-incidencias');
    const contTareas = document.getElementById('dashboard-tareas');

    if (!res || !res.ok) {
        contIncidencias.innerHTML = '<div class="inicio-error">Error al cargar</div>';
        contTareas.innerHTML = '<div class="inicio-error">Error al cargar</div>';
        return;
    }

    // Renderizar Incidencias
    if (res.incidencias && res.incidencias.length > 0) {
        contIncidencias.innerHTML = '';
        res.incidencias.forEach(inc => {
            let prioridadColor = inc.prioridad === 'Crítica' ? '#e74c3c' : (inc.prioridad === 'Alta' ? '#e67e22' : '#f1c40f');
            contIncidencias.innerHTML += `
                <div class="inicio-item" style="border-left-color: ${prioridadColor};">
                    <div class="inicio-item-titulo">${inc.titulo || inc.descripcion.substring(0, 40) + '...'}</div>
                    <div class="inicio-item-detalles">
                        <span>Reporte: ${new Date(inc.fecha_reporte).toLocaleDateString()}</span>
                        <span class="inicio-item-estado">${inc.estado.replace('_', ' ')}</span>
                    </div>
                </div>
            `;
        });
    } else {
        contIncidencias.innerHTML = `
            <div class="inicio-empty-success">
                <div class="inicio-empty-icon">✨</div>
                No hay incidencias abiertas.
            </div>`;
    }

    // Renderizar Tareas Planeadas
    if (res.tareas && res.tareas.length > 0) {
        contTareas.innerHTML = '';
        
        // Agrupar por hoy vs próximas
        const hoyStr = new Date().toISOString().split('T')[0];
        let tareasHoy = res.tareas.filter(t => t.fecha_programada.startsWith(hoyStr));
        let tareasProximas = res.tareas.filter(t => !t.fecha_programada.startsWith(hoyStr));

        // Mostrar etiqueta si hay de hoy, o si solo mostramos próximas
        let mostrar = tareasHoy.length > 0 ? tareasHoy : tareasProximas;
        let tituloExtra = tareasHoy.length > 0 ? 'Para Hoy' : 'Próximas Planeadas';

        contTareas.innerHTML += `<div class="inicio-tareas-titulo">${tituloExtra}</div>`;

        mostrar.slice(0, 4).forEach(t => {
            contTareas.innerHTML += `
                <div class="inicio-item" style="border-left-color: #3498db;">
                    <div class="inicio-item-titulo">OT-${t.id_orden}: ${t.tipo || 'Mantenimiento'}</div>
                    <div class="inicio-item-detalles">
                        <span>Para: ${new Date(t.fecha_programada).toLocaleDateString()}</span>
                        <span>Prioridad: ${t.prioridad}</span>
                    </div>
                </div>
            `;
        });
    } else {
        contTareas.innerHTML = `
            <div class="inicio-empty-muted">
                <div class="inicio-empty-icon">📅</div>
                No hay tareas planeadas próximamente.
            </div>`;
    }
}
