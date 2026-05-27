#!/bin/bash
# ==============================================================================
# SCRIPT DE ARRANQUE DEL SERVIDOR DE DESARROLLO (iniciar_servidor.sh)
# ==============================================================================
# ¿Para qué sirve?
# Levanta un servidor web local usando la característica integrada de PHP.
# Esto evita la necesidad de usar Apache o Nginx
# y permite probar el frontend y el backend al instante desde http://localhost:8000
# 
# ¿Cómo funciona?
# 1. Comprueba si PHP está instalado en el sistema.
# 2. Si lo encuentra, levanta el servidor y abre el navegador.
# ==============================================================================

echo "======================================================="
echo "   Iniciando Servidor de Desarrollo del ERP (Linux)"
echo "======================================================="
echo ""
echo "El proyecto estara disponible en: http://localhost:8000"
echo ""
echo "Presiona CTRL+C para detener el servidor."
echo ""

# Cambiar al directorio donde está el script para evitar problemas de rutas
cd "$(dirname "$0")" || exit

# Comprobar si php está instalado
if ! command -v php >/dev/null 2>&1; then
    echo "[ERROR] PHP no está instalado."
    echo "Por favor, instálalo ejecutando: sudo apt update && sudo apt install php-cli -y"
    exit 1
fi

# Intentar abrir el navegador por defecto (xdg-open en Linux)
if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "http://localhost:8000" &
fi

# Iniciar servidor PHP
php -S 0.0.0.0:8000
