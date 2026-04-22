/**
 * @project ERP Industrial - CMMS
 * @module  Administración
 * @author  Guadalupe MGM
 * @date    2026
 * @description Lógica para la gestión de Usuarios en el módulo de Administración del ERP.
 */


document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios();
});

// 1. CARGAR USUARIOS DESDE LA BBDD
async function cargarUsuarios() {
    const tbody = document.getElementById('tablaUsuarios');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">Cargando...</td></tr>';

    // Usamos la función api() que ya tiene tu proyecto en app.js
    const res = await api('GET', '../../api/usuarios.php');

    if (res && res.usuarios) {
        tbody.innerHTML = '';
        res.usuarios.forEach(u => {
            const rol = u.es_admin ? 'Administrador' : 'Operario';
            tbody.innerHTML += `
                <tr>
                    <td>${u.nombre} ${u.apellidos}</td>
                    <td><strong>${u.usuario}</strong></td>
                    <td><span class="status-badge" style="background:#eee">${rol}</span></td>
                    <td><span class="status-badge success">Activo</span></td>
                    <td class="action-buttons">
                        <button class="btn-icon" title="Eliminar" onclick="eliminarUsuario(${u.id})">🗑️</button>
                    </td>
                </tr>`;
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center">No se pudieron cargar los datos.</td></tr>';
    }
}

// 2. GUARDAR NUEVO USUARIO
async function guardarUsuario() {
    const nombre = document.getElementById("nombre").value;
    const user = document.getElementById("user").value;
    const rol = document.getElementById("rol").value; // "Administrador" o "Operario"

    const datos = {
        usuario: user,
        password: "1234", // Password por defecto
        nombre: nombre,
        apellidos: "",     // Puedes añadir un input para esto si quieres
        es_admin: rol === "Administrador" ? 1 : 0
    };

    const res = await api('POST', '../../api/usuarios.php', datos);

    if (res && res.id) {
        cerrarModal();
        cargarUsuarios(); // Recargar la tabla
    } else {
        alert("Error al crear: " + (res.error || "Desconocido"));
    }
}

// 3. ELIMINAR USUARIO
async function eliminarUsuario(id) {
    if (!confirm('¿Seguro que quieres eliminar este usuario?')) return;

    const res = await api('DELETE', '../../api/usuarios.php', { id: id });

    if (res && res.msg) {
        cargarUsuarios();
    } else {
        alert("Error: " + (res.error || "No puedes eliminarte a ti mismo"));
    }
}

// Funciones de UI
function abrirModal() { document.getElementById("modalUsuario").style.display = "block"; }
function cerrarModal() { document.getElementById("modalUsuario").style.display = "none"; }


async function cargarUsuarios() {
    const tbody = document.getElementById('tablaUsuarios');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">⏳ Forzando conexión...</td></tr>';

    try {
        // Llamada directa sin filtros del app.js para ver qué pasa
        const respuesta = await fetch('../../api/administracion/usuarios.php');
        const textoRaw = await respuesta.text(); // Leemos la respuesta como texto puro
        
        console.log("LO QUE DICE EL PHP REALMENTE:", textoRaw);

        const res = JSON.parse(textoRaw); // Intentamos convertirlo a objeto

        if (res.usuarios) {
            tbody.innerHTML = '';
            res.usuarios.forEach(u => {
                const rol = u.es_admin == 1 ? 'Admin' : 'Operario';
                tbody.innerHTML += `
                    <tr>
                        <td>${u.nombre} ${u.apellidos}</td>
                        <td><strong>${u.usuario}</strong></td>
                        <td>${rol}</td>
                        <td>Activo</td>
                        <td><button onclick="eliminarUsuario(${u.id})">🗑️</button></td>
                    </tr>`;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="color:orange">PHP respondió pero sin usuarios. Revisa la consola.</td></tr>`;
        }
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:red">Error crítico: ${error.message}</td></tr>`;
        console.error("Error detallado:", error);
    }
}
