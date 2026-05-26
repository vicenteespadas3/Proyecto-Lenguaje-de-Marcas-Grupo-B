/**
 * @project ERP Mecatrónica
 * @module  Administración / Autenticación
 * @author  Guadalupe MGM
 * @date    2026
 * @description Lógica para la gestión de la pantalla de Login (HTML/login.html).
 *              Controla tanto la animación interactiva de los "Robots" como el
 *              envío del formulario a la API (api/auth/login.php).
 */

// ----------------------------------------------------------------------------
// REFERENCIAS A ELEMENTOS DEL DOM
// ----------------------------------------------------------------------------
const passwordInput = document.getElementById("password");
const loginForm     = document.getElementById("loginForm");
const machines      = document.querySelectorAll(".machine");      // Todos los robots
const spyBot        = document.querySelector(".machine.spy");     // El robot espía
const errorMsg      = document.getElementById("error-msg");       // Mensaje de error

// ----------------------------------------------------------------------------
// LÓGICA DE ANIMACIÓN DE LOS ROBOTS
// ----------------------------------------------------------------------------

/**
 * Función central para mover las pupilas de los robots hacia un punto (X, Y).
 * 
 * @param {HTMLElement} machine - El contenedor del robot.
 * @param {number} objetivoX - Coordenada X (del ratón o del cursor).
 * @param {number} objetivoY - Coordenada Y (del ratón o del cursor).
 */
function actualizarMirada(machine, objetivoX, objetivoY) {
    const pupilas = machine.querySelectorAll(".eye");
    const esEspiaAsomado = machine.classList.contains("spy") && machine.classList.contains("peek");

    // 1. Si está tapado y NO es el espía asomado, ojos al centro
    if (machine.classList.contains("cover-eyes") && !esEspiaAsomado) {
        pupilas.forEach(p => p.style.transform = `translate(0px, 0px)`);
        return;
    }

    // 2. Si es el espía asomado, el ojo derecho mira al objetivo, el izquierdo se queda quieto
    if (esEspiaAsomado) {
        pupilas.forEach((pupila, index) => {
            if (index === 1) { // Ojo derecho
                moverPupilaHacia(pupila, objetivoX, objetivoY);
            } else {
                pupila.style.transform = `translate(0px, 0px)`;
            }
        });
        return;
    }

    // 3. Comportamiento normal: ambos ojos siguen el objetivo
    pupilas.forEach(pupila => moverPupilaHacia(pupila, objetivoX, objetivoY));
}

function moverPupilaHacia(pupila, x, y) {
    const rect = pupila.getBoundingClientRect();
    const ojoX = rect.left + rect.width / 2;
    const ojoY = rect.top + rect.height / 2;
    const angulo = Math.atan2(y - ojoY, x - ojoX);
    const dist = 5; // Distancia máxima que la pupila puede moverse del centro del ojo
    pupila.style.transform = `translate(${Math.cos(angulo) * dist}px, ${Math.sin(angulo) * dist}px)`;
}

// ----------------------------------------------------------------------------
// EVENTOS DE INTERACCIÓN (Raton y Teclado)
// ----------------------------------------------------------------------------

// 1. Los robots siguen el movimiento del ratón por la pantalla
document.addEventListener("mousemove", (e) => {
    machines.forEach(m => actualizarMirada(m, e.clientX, e.clientY));
});

// Al escribir (cuando la mano se mueve para espiar)
passwordInput.addEventListener("input", (e) => {
    const inputRect = passwordInput.getBoundingClientRect();
    const targetX = inputRect.left + inputRect.width / 2;
    const targetY = inputRect.top + inputRect.height / 2;

    if (e.target.value.length >= 2) {
        spyBot.classList.add("peek");
        // Forzamos al espía a mirar el input inmediatamente sin esperar al mouse
        actualizarMirada(spyBot, targetX, targetY);
    } else {
        spyBot.classList.remove("peek");
        actualizarMirada(spyBot, targetX, targetY); // Volverá al centro por lógica interna
    }
});

passwordInput.addEventListener("focus", () => {
    machines.forEach(m => {
        m.classList.add("cover-eyes");
        // Ojos al centro inmediatamente
        const pupilas = m.querySelectorAll(".eye");
        pupilas.forEach(p => p.style.transform = `translate(0px, 0px)`);
    });
});

passwordInput.addEventListener("blur", () => {
    machines.forEach(m => {
        m.classList.remove("cover-eyes", "peek");
    });
});

// ----------------------------------------------------------------------------
// LÓGICA DE AUTENTICACIÓN Y CONEXIÓN CON LA API
// ----------------------------------------------------------------------------

/**
 * Gestión del envío del formulario (Submit) con conexión a la API.
 */
loginForm.addEventListener("submit", async (e) => {
    e.preventDefault(); // Evita que la página se recargue al enviar el formulario
    
    // Capturamos los valores ingresados por el usuario
    const usuarioIngresado = loginForm.querySelector('input[type="text"]').value;
    const passwordIngresado = passwordInput.value;

    try {
        // Enviar petición POST a la API de login
        const response = await fetch('../api/auth/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include', // IMPORTANTE: permite que la API establezca cookies de sesión (PHPSESSID)
            body: JSON.stringify({
                usuario: usuarioIngresado,
                password: passwordIngresado
            })
        });

        const data = await response.json();

        if (response.ok && data.ok) {
            
            // ÉXITO: Quitamos estados de alerta y redirigimos
            errorMsg.style.display = "none";
            machines.forEach(m => {
                m.classList.remove("cover-eyes", "peek", "cover-mouth", "shake");
            });

            // Pequeño retardo para suavizar la transición al inicio.html
            setTimeout(() => {
                window.location.href = 'inicio.html';
            }, 500);

        } else {
            
            // ERROR: Los robots reaccionan negativamente
            errorMsg.style.display = "block";
            errorMsg.textContent = "❌ Acceso Denegado: " + (data.error || "Credenciales incorrectas");
            
            machines.forEach(m => {
                m.classList.remove("cover-eyes", "peek");
                m.classList.add("cover-mouth", "shake");
            });

            // Limpiamos el efecto de "susto" tras 2 segundos
            setTimeout(() => {
                machines.forEach(m => m.classList.remove("cover-mouth", "shake"));
            }, 2000);
        }
    } catch (error) {
        console.error("Error en el login:", error);
        errorMsg.style.display = "block";
        errorMsg.textContent = "❌ Error de conexión al servidor";
    }
});