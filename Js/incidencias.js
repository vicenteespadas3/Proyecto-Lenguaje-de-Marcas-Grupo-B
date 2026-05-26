// incidencias.js - Carga dinámica de la tabla de incidencias

document.addEventListener('DOMContentLoaded', () => {
    if (typeof renderMenu === 'function') renderMenu();
    cargarIncidencias();

    // Eventos de búsqueda
    const btnBuscar = document.getElementById('btnBuscarIncidencia');
    const inputBuscar = document.getElementById('inputBuscarIncidencia');

    if (btnBuscar && inputBuscar) {
        btnBuscar.addEventListener('click', () => cargarIncidencias(inputBuscar.value));
        inputBuscar.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') cargarIncidencias(inputBuscar.value);
        });
    }
});

async function cargarIncidencias(query = '') {
    const tbody = document.getElementById('tabla-incidencias-body');
    if (!tbody) return;

    // Estado visual de carga
    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">Cargando incidencias... ⚙️</td></tr>';

    try {
        let url = '../../api/incidencias/incidencias.php';
        if (query.trim() !== '') {
            url += `?buscar=${encodeURIComponent(query.trim())}`;
        }
        
        const res = await api('GET', url);

        if (res && res.ok !== false && res.incidencias) {
            tbody.innerHTML = '';

            if (res.incidencias.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No hay incidencias registradas.</td></tr>';
                return;
            }

            res.incidencias.forEach(i => {
                // Colores de prioridad según SQL
                const p = (i.prioridad || '').toLowerCase();
                let badgeClass = 'success';
                if (p === 'critica' || p === 'alta') badgeClass = 'danger';
                else if (p === 'media') badgeClass = 'warning';

                tbody.innerHTML += `
                    <tr>
                        <td><strong>${i.id_incidencia}</strong></td>
                        <td>${i.id_maquina || 'N/A'}</td>
                        <td>${i.fecha_reporte || '---'}</td>
                        <td><span class="status-badge ${badgeClass}">${(i.prioridad || 'BAJA').toUpperCase()}</span></td>
                        <td>${i.descripcion_problema || 'Sin descripción'}</td>
                        <td>Técnico</td> 
                        <td><span class="status-badge warning">${i.estado || 'Abierta'}</span></td>
                        <td class="action-buttons">
                            <button class="btn-icon" title="Ver" onclick="alert('Incidencia ID: ' + ${i.id_incidencia} + '\\nDescripción: ' + '${i.descripcion_problema}')">👁️</button>
                        </td>
                    </tr>`;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: red;">⚠️ Error al cargar las incidencias.</td></tr>';
        }
    } catch (error) {
        console.error("Error:", error);
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: red;">⚠️ Error al conectar con la base de datos.</td></tr>';
    }
}

// Función global para el botón de reportar (si se usa)
function nuevaIncidencia() {
    alert("Función para reportar nueva incidencia en desarrollo.");
}