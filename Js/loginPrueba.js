/**
 * @project ERP Industrial - CMMS
 * @module  Administración
 * @author  Guadalupe MGM
 * @date    2026
 * @description Lógica para la gestión de Login en el módulo de Administración del ERP.
 */

const passwordInput = document.getElementById("password");
const loginForm = document.getElementById("loginForm");
const machines = document.querySelectorAll(".machine");
const spyBot = document.querySelector(".machine.spy");
const errorMsg = document.getElementById("error-msg");

/**
 * Función central para mover las pupilas
 * @param {HTMLElement} machine - El contenedor del robot
 * @param {number} objetivoX - Coordenada X a mirar
 * @param {number} objetivoY - Coordenada Y a mirar
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
    const dist = 5;
    pupila.style.transform = `translate(${Math.cos(angulo) * dist}px, ${Math.sin(angulo) * dist}px)`;
}

/* --- EVENTOS --- */

// Seguir el cursor normalmente
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

loginForm.addEventListener("submit", (e) => {
    e.preventDefault(); 
    machines.forEach(m => {
        m.classList.remove("cover-eyes", "peek");
        m.classList.add("cover-mouth", "shake");
    });
    errorMsg.style.display = "block";
    setTimeout(() => {
        machines.forEach(m => m.classList.remove("cover-mouth", "shake"));
    }, 2000);
});