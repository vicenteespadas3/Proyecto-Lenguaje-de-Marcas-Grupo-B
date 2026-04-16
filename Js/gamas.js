// Js/gamas.js

document.addEventListener('DOMContentLoaded', async () => {
    // Dibujamos el menú superior
    renderMenu();

    // Cargamos los datos
    await cargarTabla();
});

async function cargarTabla() {
    const tbody = document.getElementById('cuerpoTablaGamas');
    
    // Petición al PHP (¡Asegúrate de tener el gamas.php creado en tu carpeta api!)
    const respuesta = await api('GET', '../api/gamas.php'); 

    if (!respuesta || respuesta.ok === false) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:red; text-align:center;">Error al cargar las gamas</td></tr>`;
        return;
    }

    tbody.innerHTML = '';

    if (respuesta.gamas.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No hay gamas registradas aún.</td></tr>`;
        return;
    }

    // Dibujamos cada fila inyectando las clases CSS
    respuesta.gamas.forEach(gama => {
        const fila = document.createElement('tr');
        
        fila.innerHTML = `
            <td class="col-id">${gama.id_gama}</td>
            <td><strong>${gama.nombre}</strong></td>
            <td>
                <span class="status-badge badge-${gama.tipo_mantenimiento}">
                    ${gama.tipo_mantenimiento.toUpperCase()}
                </span>
            </td>
            <td>
                <span class="tipo-gama gama-${gama.tipo_gama}">
                    ${gama.tipo_gama.toUpperCase()}
                </span>
            </td>
            <td class="action-buttons justify-center">
                <button class="btn btn-icon" title="Editar" onclick="editarGama(${gama.id_gama})">✏️</button>
                <button class="btn btn-icon" style="color: #e74c3c;" title="Borrar" onclick="borrarGama(${gama.id_gama})">🗑️</button>
            </td>
        `;
        tbody.appendChild(fila);
    });
}

// Funciones temporales para los botones
function editarGama(id) {
    alert('Has hecho clic en editar la gama con ID: ' + id);
}

function borrarGama(id) {
    if(confirm('¿Seguro que quieres borrar la gama ' + id + '?')) {
        alert('Pronto conectaremos esto con la base de datos.');
    }
}