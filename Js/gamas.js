// Js/gamas.js

document.addEventListener('DOMContentLoaded', async () => {
    // Dibujamos el menú superior
    renderMenu();

    // Cargamos los datos
    await cargarTabla();

    // Eventos de búsqueda
    const btnBuscarGama = document.getElementById('btnBuscarGama');
    const inputBuscarGama = document.getElementById('inputBuscarGama');
    
    if (btnBuscarGama && inputBuscarGama) {
        btnBuscarGama.addEventListener('click', () => {
            cargarTabla(inputBuscarGama.value);
        });

        inputBuscarGama.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                cargarTabla(inputBuscarGama.value);
            }
        });
    }

    // --- EVENTOS DEL FORMULARIO DE NUEVA GAMA ---
    const btnNuevaGama = document.getElementById('btnNuevaGama');
    const formularioNuevaGama = document.getElementById('formularioNuevaGama');
    const btnCancelarGama = document.getElementById('btnCancelarGama');
    const btnGuardarGama = document.getElementById('btnGuardarGama');

    // 1. Mostrar el formulario al hacer clic en el botón '+'
    if (btnNuevaGama && formularioNuevaGama) {
        btnNuevaGama.addEventListener('click', () => {
            formularioNuevaGama.style.display = 'block'; // Lo hace visible
        });
    }

    // 2. Ocultar el formulario al cancelar
    if (btnCancelarGama && formularioNuevaGama) {
        btnCancelarGama.addEventListener('click', () => {
            formularioNuevaGama.style.display = 'none'; // Lo oculta
            document.getElementById('nombreGama').value = ''; // Limpia el texto introducido
        });
    }

    // 3. Enviar los datos al PHP
    if (btnGuardarGama) {
        btnGuardarGama.addEventListener('click', async () => {
            // Obtenemos los valores de los campos
            const nombre = document.getElementById('nombreGama').value.trim();
            const tipo_mantenimiento = document.getElementById('tipoMantenimiento').value;
            const tipo_gama = document.getElementById('tipoGama').value;

            // Validación sencilla para asegurar que se ha escrito un nombre
            if (nombre === '') {
                alert('Por favor, escribe un nombre para la gama.');
                return;
            }

            // Estructura de datos que enviamos al PHP en formato JSON
            const datos = {
                nombre: nombre,
                tipo_mantenimiento: tipo_mantenimiento,
                tipo_gama: tipo_gama
            };

            // Hacemos la llamada mágica a PHP usando el método POST (alta)
            const respuesta = await api('POST', '../../api/mantenimiento/gamas.php', datos);

            // Verificamos si ha ido bien
            if (respuesta && respuesta.ok !== false) {
                alert(respuesta.msg || 'Gama creada correctamente');
                
                // Ocultamos el formulario y lo limpiamos para la próxima vez
                formularioNuevaGama.style.display = 'none';
                document.getElementById('nombreGama').value = '';
                
                // Recargamos la tabla para ver la nueva gama añadida sin recargar la página entera
                cargarTabla();
            } else {
                // Si la base de datos o el PHP fallan, mostramos el error
                alert('Error: ' + (respuesta.error || 'No se pudo crear la gama'));
            }
        });
    }
});

async function cargarTabla(query = '') {
    const tbody = document.getElementById('cuerpoTablaGamas');
    
    // Petición al PHP 
    let url = '../../api/mantenimiento/gamas.php';
    if (query.trim() !== '') {
        url += `?buscar=${encodeURIComponent(query.trim())}`;
    }
    const respuesta = await api('GET', url);

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

async function borrarGama(id_gama) {
    if(confirm('¿Seguro que quieres borrar la gama ' + id_gama + '?')) {
       // El PHP espera un JSON con la propiedad "id_gama", así que le pasamos un objeto: { id_gama: id_gama }
       const respuesta = await api('DELETE', '../../api/mantenimiento/gamas.php', { id_gama: id_gama });

       if (respuesta && respuesta.ok !== false) {
           alert(respuesta.msg || 'Gama borrada correctamente');
           cargarTabla(); // Recargamos la tabla para reflejar el cambio
       } else {
           alert('Error: ' + (respuesta.error || 'No se pudo borrar la gama'));
       }
    }
}