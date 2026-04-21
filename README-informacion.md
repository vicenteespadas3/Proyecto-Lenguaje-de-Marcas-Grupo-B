# 🛠️ ERP Mecatrónica - Guía del Proyecto

Bienvenido al proyecto ERP de Mecatrónica. Esta aplicación está diseñada para gestionar el mantenimiento, activos, inventario e incidencias de un taller.

## 🚀 Funcionamiento General de la App
El proyecto sigue una arquitectura **Frontend / Backend** moderna:
- **Frontend (HTML, CSS, JS):** Todo el diseño y la lógica visual (menús, tablas, animaciones) se gestiona en las carpetas `HTML`, `CSS` y `Js`. El archivo `Js/app.js` es el corazón del frontend, ya que dibuja el menú lateral (Sidebar) de forma automática en todas las páginas y contiene la función central `api()` para comunicarse con la base de datos sin recargar la pantalla.
- **Backend (PHP):** La carpeta `api/` contiene pequeños scripts ordenados por módulos (`activos/`, `mantenimiento/`, `auth/`, etc.) que se encargan exclusivamente de conectarse a la Base de Datos MySQL, ejecutar las consultas y devolver la información en formato JSON.

---

## 💻 Servidor de Desarrollo Rápido (.bat)
Para facilitar el trabajo en equipo y evitar problemas de configuración, hemos creado el script **`iniciar_servidor.bat`**.

**¿Cómo funciona?**
1. Simplemente haz **doble clic en `iniciar_servidor.bat`**.
2. El script buscará automáticamente PHP en tu equipo (suele estar en `C:\xampp\php\php.exe` o en las variables de entorno de Windows).
3. Levantará un servidor web de pruebas interno de PHP en el puerto 8000.
4. **Abrirá automáticamente tu navegador web** apuntando a `http://localhost:8000`.

**¡Importante!** Gracias a este archivo, ya no necesitas copiar la carpeta del proyecto dentro de `C:\xampp\htdocs\`. Puedes trabajar cómodamente desde el Escritorio, Documentos o un USB.

---

## 🗄️ Conexión a la Base de Datos (Cómo cambiar la IP)
El sistema incluye un método "inteligente" que intenta conectarse a dos IPs diferentes automáticamente (pensado para funcionar en clase con el móvil o en casa con el cable Ethernet). 

Si necesitas **hacer pruebas con tu propia base de datos local (tu propio XAMPP)** en lugar de la del servidor de Ubuntu, debes hacer lo siguiente:

1. Abre el archivo de configuración: `api/config/db_config.php`
2. En las primeras líneas encontrarás esta variable:
   ```php
   define('DB_HOST_CLASE', '172.20.10.2');
   ```
3. Sustituye cualquiera de ellas (o ambas) por `127.0.0.1` o `localhost`.
4. Revisa también que el usuario (`DB_USER`), la contraseña (`DB_PASS`) y el nombre de la BD (`DB_NAME`) coinciden con los que tienes creados en tu phpMyAdmin local.

---

## 📝 Notas y Reglas para el Equipo (Original)
* Para empezar a crear nuevas pantallas debéis usar el archivo llamado **plantilla_base**. En él encontraréis una plantilla donde deberéis pegar vuestro HTML y crear vuestro propio script `.js` para cargar los datos usando la API.
* Tenéis que conectar vuestro Visual Studio con GitHub (preguntadle a Gemini cómo se hace <3). **Como siempre, haced un `git pull` antes de empezar a trabajar** para tener la última versión.
* Si se va a hacer algún commit o modificación que afecte al resto, debéis avisarlo.
* **Fijaros bien en cómo está escrito el enlace en el menú lateral (`Js/app.js`) para poder enlazarlo con vuestro archivo HTML correctamente.**
