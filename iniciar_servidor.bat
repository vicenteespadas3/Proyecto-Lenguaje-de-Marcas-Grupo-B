@echo off
:: ==============================================================================
:: SCRIPT DE ARRANQUE DEL SERVIDOR DE DESARROLLO (iniciar_servidor.bat)
:: ==============================================================================
:: ¿Para qué sirve?
:: Levanta un servidor web local usando la característica integrada de PHP.
:: Esto evita la necesidad de copiar la carpeta del proyecto dentro de C:\xampp\htdocs\
:: y permite probar el frontend y el backend al instante desde http://localhost:8000
:: 
:: ¿Cómo funciona?
:: 1. Busca el ejecutable de PHP directamente en la ruta habitual de XAMPP.
:: 2. Si lo encuentra, lo usa (esto previene errores si PHP no está en el PATH de Windows).
:: 3. Si no existe XAMPP, hace un fallback e intenta usar el motor PHP global del sistema.
:: ==============================================================================

echo =======================================================
echo    Iniciando Servidor de Desarrollo del ERP
echo =======================================================
echo.
echo No necesitas mover el proyecto a htdocs.
echo El proyecto estara disponible en: http://localhost:8000
echo.
echo Presiona CTRL+C para detener el servidor.
echo.

:: Intentamos usar directamente el ejecutable PHP de XAMPP por defecto
:: Esto evita problemas de rutas si XAMPP esta instalado pero PHP no esta en las variables de entorno
set PHP_PATH=C:\xampp\php\php.exe

if exist "%PHP_PATH%" (
    :: Si encuentra PHP en XAMPP, levanta el servidor usando ese ejecutable
    start http://localhost:8000
    "%PHP_PATH%" -S localhost:8000
) else (
    :: Si no existe la carpeta XAMPP, intenta usar el PHP configurado en las variables de entorno del sistema (PATH)
    echo [ADVERTENCIA] No se encontro PHP en C:\xampp\php\php.exe
    echo Intentando usar PHP desde las variables de entorno...
    start http://localhost:8000
    php -S localhost:8000
)
pause
