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
    const menuHTML = `
        <nav class="menu-principal">
            <div class="logo">⚙️ ERP Mecatrónica</div>
            <ul class="nav-links">
                <li><a href="inicio.html">🏠 Inicio</a></li>
                
                <li class="dropdown">
                    <a href="#">👥 Administración ▼</a>
                    <ul class="submenu">
                        <li><a href="usuarios.html">Usuarios</a></li>
                        <li><a href="horarios.html">Horarios de Trabajo</a></li>
                        <li><a href="definiciones.html">Definiciones (Ajustes)</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#">🏭 Activos ▼</a>
                    <ul class="submenu">
                        <li><a href="maquinas.html">Máquinas</a></li>
                        <li><a href="proveedores.html">Proveedores</a></li>
                        <li><a href="archivos.html">Archivos Adjuntos</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#">🛠️ Mantenimiento ▼</a>
                    <ul class="submenu">
                        <li class="seccion-menu">--- Preventivo---</li>
                        <li><a href="gamas.html">Gamas de Mantenimiento</a></li>
                        <li><a href="planes.html">Planes de Mantenimiento</a></li>
                        <li><a href="tareas_mant.html">Tareas de Mantenimiento</a></li>
                        <li class="seccion-menu">--- Día a Día---</li>
                        <li><a href="ordenes.html">Órdenes de Trabajo</a></li>
                        <li><a href="ejecuciones.html">Ejecuciones</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#">📦 Inventario ▼</a>
                    <ul class="submenu">
                        <li><a href="piezas.html">Piezas de Repuesto</a></li>
                        <li><a href="herramientas.html">Herramientas</a></li>
                        <li><a href="pedidos.html">Pedidos de Compra</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#">🚨 Taller ▼</a>
                    <ul class="submenu">
                        <li><a href="incidencias.html">Incidencias</a></li>
                        <li><a href="proyectos.html">Proyectos Gantt</a></li>
                        <li><a href="analisis.html">Análisis de Máquinas</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
    `;
    
    document.body.insertAdjacentHTML('afterbegin', menuHTML);
}