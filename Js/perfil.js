let userData = null;

/**
 * Carga los datos del perfil de usuario autenticado desde la API.
 */
async function cargarPerfil() {
    const res = await api('GET', '../api/auth/perfil.php');
    if (res && res.usuario) {
        userData = res.usuario;
        renderPerfil();
    } else {
        console.error('Error al cargar perfil:', res);
    }
}

/**
 * Renderiza los datos del usuario en la interfaz del perfil.
 */
function renderPerfil() {
    const u = userData;
    const letra = u.nombre ? u.nombre[0].toUpperCase() : u.email[0].toUpperCase();
    document.getElementById('perf-avatar').textContent = letra;
    document.getElementById('perf-nombre-completo').textContent = `${u.nombre} ${u.apellidos}`.trim();
    document.getElementById('perf-rol').textContent = u.rol.charAt(0).toUpperCase() + u.rol.slice(1);
    
    document.getElementById('perf-nombre').textContent = u.nombre || '-';
    document.getElementById('perf-apellidos').textContent = u.apellidos || '-';
    document.getElementById('perf-email').textContent = u.email;
    document.getElementById('perf-rol-detalle').textContent = u.rol.toUpperCase();
    document.getElementById('perf-registro').textContent = u.fecha_creacion || 'N/A';
    document.getElementById('perf-acceso').textContent = u.ultimo_acceso || 'Ahora mismo';
}

/**
 * Abre el modal para editar los datos del perfil del usuario.
 */
function abrirModalPerfil() {
    if (!userData) return;
    document.getElementById('editNombre').value = userData.nombre || '';
    document.getElementById('editApellidos').value = userData.apellidos || '';
    document.getElementById('editEmail').value = userData.email || '';
    document.getElementById('editPassword').value = '';
    document.getElementById('modalPerfil').classList.add('show');
}

/**
 * Cierra el modal de edición del perfil.
 */
function cerrarModalPerfil() {
    document.getElementById('modalPerfil').classList.remove('show');
}

/**
 * Envía los datos actualizados del perfil a la API.
 * @param {Event} e - Evento de envío del formulario.
 */
async function guardarPerfil(e) {
    e.preventDefault();
    const payload = {
        nombre: document.getElementById('editNombre').value,
        apellidos: document.getElementById('editApellidos').value,
        email: document.getElementById('editEmail').value,
        password: document.getElementById('editPassword').value
    };

    const res = await api('PUT', '../api/auth/perfil.php', payload);
    if (res && res.ok) {
        alert(res.msg || 'Perfil actualizado correctamente.');
        cerrarModalPerfil();
        await cargarPerfil(); 
        // Recargamos para que el topbar se actualice
        location.reload();
    } else {
        alert('Error: ' + (res.error || 'No se pudo actualizar el perfil.'));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    renderMenu('perfil'); 
    cargarPerfil();
});
