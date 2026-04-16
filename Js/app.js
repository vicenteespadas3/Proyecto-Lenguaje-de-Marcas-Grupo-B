// ==========================================
// js/app.js - ARCHIVO COMÚN PARA TODO EL EQUIPO
// ==========================================

// 1. Función mágica para hablar con PHP sin recargar la página
async function api(metodo, url, cuerpo = null) {
    const opciones = { method: metodo, headers: {} };
    
    if (cuerpo !== null) {
        opciones.headers['Content-Type'] = 'application/json';
        opciones.body = JSON.stringify(cuerpo);
    }
    
    try {
        const respuesta = await fetch(url, opciones);
        return await respuesta.json(); // Convierte el texto de PHP a un objeto JS
    } catch (error) {
        console.error("Error de conexión:", error);
        return { ok: false, error: 'Error de red' };
    }
}

//Función temporal del Menú (Sin comprobación de sesión de momento)
// js/app.js

function renderMenu() {
    // Topbar simplificado y embellecido
    const topbarHTML = `
        <nav class="menu-principal" style="justify-content: space-between;">
            <div class="logo">
                <span style="font-size: 1.2em;">⚙️</span> 
                <span>ERP Mecatrónica</span>
            </div>
            <div class="user-profile">
                <span class="user-name">Administrador</span>
                <div class="avatar">A</div>
            </div>
        </nav>
    `;

    // Determinar rutas relativas
    const inSubFolder = window.location.pathname.includes('Menu/');
    const pRoot = inSubFolder ? '../' : '';

    // Sidebar dinámico
    const sidebarHTML = `
        <aside class="sidebar">
            <div class="sidebar-header">
                GMAO System
            </div>
            <nav class="sidebar-nav">
                <a href="${pRoot}inicio.html" class="nav-link">🏠 Inicio</a>
                
                <div class="sidebar-category">
                    <a href="${pRoot}administracionMenu/administracion.html" class="category-link">👥 Administración</a>
                    <button class="category-toggle" onclick="toggleSubmenu('sub-admin', this)" title="Desplegar Opciones">▼</button>
                </div>
                <div class="sidebar-submenu" id="sub-admin">
                    <a href="${pRoot}administracionMenu/usuarios.html" class="nav-link">Usuarios</a>
                    <a href="${pRoot}administracionMenu/horarios.html" class="nav-link">Horarios de Trabajo</a>
                    <a href="${pRoot}administracionMenu/definiciones.html" class="nav-link">Definiciones (Ajustes)</a>
                </div>

                <div class="sidebar-category">
                    <a href="${pRoot}activosMenu/activos.html" class="category-link">🏭 Activos</a>
                    <button class="category-toggle" onclick="toggleSubmenu('sub-activos', this)" title="Desplegar Opciones">▼</button>
                </div>
                <div class="sidebar-submenu" id="sub-activos">
                    <a href="${pRoot}activosMenu/maquinas.html" class="nav-link">Máquinas</a>
                    <a href="${pRoot}activosMenu/proveedores.html" class="nav-link">Proveedores</a>
                    <a href="${pRoot}activosMenu/archivos.html" class="nav-link">Archivos Adjuntos</a>
                </div>

                <div class="sidebar-category">
                    <a href="${pRoot}mantenimientoMenu/mantenimiento.html" class="category-link">🛠️ Mantenimiento</a>
                    <button class="category-toggle" onclick="toggleSubmenu('sub-mant', this)" title="Desplegar Opciones">▼</button>
                </div>
                <div class="sidebar-submenu" id="sub-mant">
                    <div class="seccion-menu" style="background:transparent; color:#7f8c8d;">--- Preventivo ---</div>
                    <a href="${pRoot}mantenimientoMenu/gamas.html" class="nav-link">Gamas de Mantenimiento</a>
                    <a href="${pRoot}mantenimientoMenu/planes.html" class="nav-link">Planes de Mantenimiento</a>
                    <a href="${pRoot}mantenimientoMenu/tareas_mant.html" class="nav-link">Tareas de Mantenimiento</a>
                    <div class="seccion-menu" style="background:transparent; color:#7f8c8d;">--- Día a Día ---</div>
                    <a href="${pRoot}mantenimientoMenu/ordenes.html" class="nav-link">Órdenes de Trabajo</a>
                    <a href="${pRoot}mantenimientoMenu/ejecuciones.html" class="nav-link">Ejecuciones</a>
                </div>

                <div class="sidebar-category">
                    <a href="${pRoot}inventarioMenu/inventario.html" class="category-link">📦 Inventario</a>
                    <button class="category-toggle" onclick="toggleSubmenu('sub-inv', this)" title="Desplegar Opciones">▼</button>
                </div>
                <div class="sidebar-submenu" id="sub-inv">
                    <a href="${pRoot}inventarioMenu/piezas.html" class="nav-link">Piezas de Repuesto</a>
                    <a href="${pRoot}inventarioMenu/herramientas.html" class="nav-link">Herramientas</a>
                    <a href="${pRoot}inventarioMenu/pedidos.html" class="nav-link">Pedidos de Compra</a>
                </div>

                <div class="sidebar-category">
                    <a href="${pRoot}incidenciasMenu/incidencias_taller.html" class="category-link">🚨 Taller</a>
                    <button class="category-toggle" onclick="toggleSubmenu('sub-taller', this)" title="Desplegar Opciones">▼</button>
                </div>
                <div class="sidebar-submenu" id="sub-taller">
                    <a href="${pRoot}incidenciasMenu/incidencias.html" class="nav-link">Incidencias</a>
                    <a href="${pRoot}incidenciasMenu/proyectos.html" class="nav-link">Proyectos Gantt</a>
                    <a href="${pRoot}incidenciasMenu/analisis.html" class="nav-link">Análisis de Máquinas</a>
                </div>
            </nav>
        </aside>
    `;
    
    // Inyectar el topbar al principio del body
    document.body.insertAdjacentHTML('afterbegin', topbarHTML);
    
    // Inyectar el sidebar al principio del contenedor app-body (si existe)
    const appBody = document.querySelector('.app-body');
    if (appBody) {
        appBody.insertAdjacentHTML('afterbegin', sidebarHTML);
    }
}

// Función para abrir/cerrar submenús (acordeón)
function toggleSubmenu(id, btnElement) {
    const submenu = document.getElementById(id);
    if (submenu.style.display === 'flex') {
        submenu.style.display = 'none';
        btnElement.textContent = '▼';
    } else {
        submenu.style.display = 'flex';
        btnElement.textContent = '▲';
    }
}