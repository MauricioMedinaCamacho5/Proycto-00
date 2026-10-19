# Proycto-00 — Gestión Institucional

Aplicación institucional (proyecto de prueba y práctica) para XAMPP con una API PHP/PDO compartida por el sitio web y
la aplicación Android. Los datos persisten en MySQL/MariaDB; el panel web
actualiza sus indicadores cada 8 segundos.

## Requisitos

- XAMPP con Apache, MySQL/MariaDB y PHP 8 (extensiones `pdo_mysql`, `json`,
  `mbstring`, `gd`, `zip`, `dom` y `xml`; habilítalas en `php.ini` si Composer
  indica que falta alguna).
- Composer 2 para correo y exportación PDF/Excel.
- Flutter 3.19 o posterior, Android SDK y un dispositivo/emulador para Android.
- `mod_rewrite` y `AllowOverride All` habilitados para el directorio del proyecto.

## Instalación en XAMPP

1. Copia el proyecto a `C:\xampp\htdocs\gestion`.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. En phpMyAdmin, importa `database/proyecto01.sql`. El script crea la base
   `proyecto01`, sus tablas y datos de demostración. **Esta importación elimina
   y vuelve a crear las tablas**: úsala solo para una instalación nueva, nunca
   para trasladar una base que ya contiene tus datos. Para actualizar una
   instalación existente, importa únicamente
   `database/actualizar_administrador.sql` y
   `database/actualizar_solicitudes_registro.sql`; estas actualizaciones
   conservan las tablas y los demás datos existentes.
4. En una terminal, ejecuta `composer install` desde
   `C:\xampp\htdocs\gestion` para habilitar PHPMailer, Dompdf y PhpSpreadsheet.
5. Si MySQL usa credenciales distintas de las predeterminadas de XAMPP, configura
   las variables `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` en el entorno de
   Apache y reinícialo. Los valores predeterminados son `127.0.0.1`,
   `proyecto01`, `root` y contraseña vacía.
6. Abre `http://localhost/gestion/`.

**No abras `index.html` directamente** (la dirección empieza por `file:///`):
en ese modo el navegador no ejecuta PHP y no puede iniciar sesión. Usa la
dirección local de Apache indicada arriba.

### Trasladar todos los datos a otro computador

El código del proyecto y la base MySQL son cosas separadas: copiar el proyecto
o descargarlo desde Git no copia los registros de MySQL. Para conservar usuarios,
asistencias y los demás datos, traslada un respaldo `.sql`:

1. En el computador anterior, inicia MySQL en XAMPP y ejecuta
   `backups\respaldo_diario.bat`. El archivo completo se crea en la carpeta
   `backups` del proyecto. También puedes programar ese mismo archivo para
   generar copias diarias. Mantén una copia del `.sql` fuera del computador.
2. Copia el proyecto al nuevo computador (por ejemplo, a
   `C:\xampp\htdocs\gestion`) y copia allí el archivo `.sql` de respaldo. No
   subas respaldos con datos personales al repositorio Git; están excluidos por
   `.gitignore`.
3. Instala XAMPP y Composer en el nuevo computador, inicia Apache y MySQL, y
   ejecuta `composer install` dentro de la carpeta del proyecto.
4. Desde una terminal abierta en la carpeta del proyecto, ejecuta
   `backups\restaurar_respaldo.bat "backups\NOMBRE_DEL_RESPALDO.sql"` y confirma
   con `S`. La restauración reemplaza la base `proyecto01` del computador nuevo
   por los datos del respaldo. No la ejecutes contra la base que quieras
   conservar.
5. Abre `http://localhost/gestion/`. Comprueba también
   `http://localhost/gestion/api/health`; debe responder JSON con
   `"status":"ok"`.

Si prefieres hacerlo desde phpMyAdmin: en el computador anterior abre
`http://localhost/phpmyadmin`, selecciona `proyecto01` en el menú izquierdo,
pulsa **Exportar**, deja el método **Rápido** y el formato **SQL**, y pulsa
**Exportar** para guardar el archivo. En el computador nuevo, abre phpMyAdmin,
crea una base llamada `proyecto01` con cotejamiento `utf8mb4_unicode_ci`,
selecciónala, entra en **Importar**, elige el archivo guardado y pulsa
**Importar** o **Continuar**. No importes allí `database/proyecto01.sql`: ese
archivo es para iniciar una base nueva con datos de demostración y borra las
tablas existentes.

Los scripts buscan MySQL dentro de la instalación XAMPP que contiene `htdocs`
o en `C:\xampp`. Si tu instalación está en otra ruta, define la variable de
entorno `XAMPP_DIR` con la carpeta de XAMPP. Por defecto usan `127.0.0.1`, el
usuario `root` y contraseña vacía. Si tu MySQL tiene contraseña, no la escribas
en estos scripts: configura un archivo de opciones de cliente protegido y
define la variable de entorno `MYSQL_OPTION_FILE` con su ruta. Puedes definir
también `MYSQL_HOST`, `MYSQL_USER` y `DB_NAME` si usas otros valores.

Si aparece **HTTP 405**, no significa que falte importar MySQL. Inicia Apache y
MySQL en XAMPP, habilita `mod_rewrite` y `AllowOverride All`, reinicia Apache y
abre `/gestion/api/health`. Si esa dirección devuelve `"status":"ok"`, API y
base de datos están conectadas; si devuelve un error JSON, revisa las
credenciales `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` y que la base haya
sido restaurada.

La página principal presenta el Servicio Nacional de Aprendizaje SENA y su
Centro de la Industria, la Empresa y los Servicios. El panel principal y la
administración de datos requieren una sesión de administrador; instructores,
estudiantes y personal de portería solo ven los módulos permitidos por su rol.
La portada, los formularios de acceso, registro y recuperación, el panel web y
la app Android comparten la identidad visual verde, el emblema «S» y el lema
«Formación · Servicio · Oportunidades».
El logotipo institucional se encuentra en `web/assets/images/sena-logo.png`
y `app_movil/assets/images/sena-logo.png` para el sitio web y la app Android.

Desde **Registrar como**, un estudiante puede llenar el documento usando la
cámara o una foto del código lineal de su carné. La foto se procesa en el
navegador y no se envía al servidor. El sistema genera un QR digital único para
la cuenta. Instructores y personal de portería pueden enviar una solicitud; su
cuenta permanece inactiva hasta que un administrador la apruebe en **Solicitudes
de registro**. La solicitud lleva el documento leído del código lineal de la
cédula o de una foto. El administrador inicia sesión, abre esa pestaña, revisa
la solicitud y pulsa **Aprobar**; el sistema muestra un contador junto a la
pestaña cuando hay solicitudes pendientes. El registro público no permite crear
administradores.
Un estudiante registrado también puede ingresar con **Escanear carné** o
**Elegir foto**. El acceso por código lineal usa el documento como única
credencial; cualquier persona con una copia legible puede entrar a esa cuenta.
En **Ingresar como equipo institucional**, instructores, portería y
administradores escanean el código de barras de la cédula con la cámara o una
foto; luego verifican su correo y contraseña para abrir la cuenta. La cédula
por sí sola no da acceso al panel administrativo, porque el código lineal es
visible y se puede copiar.
Para escanear con cámara, permite el acceso cuando el navegador lo solicite y
abre el sitio desde `localhost` o HTTPS; los navegadores bloquean la cámara en
direcciones HTTP por IP. El lector reconoce códigos de barras lineales (Code 39,
Code 93, Code 128, EAN, ITF, UPC y Codabar). Centra el código completo, usa buena
luz y evita reflejos; puedes escribir el documento manualmente si hace falta.
Para leer una foto, elige una imagen enfocada y con el código completo visible;
el lector reconoce el mismo formato de códigos lineales que la cámara.
El listado y el panel de administración se actualizan automáticamente cada 3
segundos desde la misma base de datos. Si la cámara o el lector no están
disponibles, se puede escribir el documento manualmente.

Cuenta administradora inicial (la contraseña temporal es `password`):

| Rol | Correo |
|---|---|
| Administrador | `mauriciomedinacamacho5@gmail.com` |

Después de iniciar sesión, ve a **Usuarios → Editar** junto al administrador,
escribe la contraseña personal que quieras usar en el campo **Contraseña** y
guarda. La aplicación almacena únicamente su hash. No guardes esa contraseña
en los archivos del proyecto.

Otras cuentas de demostración (la contraseña de ambas es `password`):

| Rol | Correo |
|---|---|
| Instructor | `instructor@institucion.local` |
| Estudiante | `estudiante@institucion.local` |
| Portero | `portero@institucion.local` |

Las credenciales son exclusivamente para desarrollo local: cámbialas antes de
usar información real.

### Correo de recuperación

Después de `composer install`, configura en Apache las variables
`SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD`, `SMTP_FROM` y
`APP_URL` (por ejemplo `http://localhost/gestion`). El enlace vence en 30
minutos. Sin PHPMailer o sin configuración SMTP, el endpoint muestra un error
explícito y no reporta un correo enviado. En XAMPP local, si SMTP no está
configurado, el endpoint ofrece un enlace de recuperación de un solo uso,
limitado a solicitudes desde `localhost`; vence en 30 minutos y no se entrega a
equipos conectados por la red. La pantalla ofrece **Ir a corregir la
contraseña** y, después de guardarla, **Volver a la página de inicio de
sesión**.

### Copias de seguridad

El script `backups/respaldo_diario.bat` genera un dump consistente en
`C:\xampp\backups\gestion` y elimina los respaldos con más de 30 días. En el
Programador de tareas de Windows, crea una tarea diaria que ejecute ese archivo
con permisos suficientes para acceder a MySQL. Si la instalación protege la
cuenta de MySQL con contraseña, configura un archivo de opciones de cliente
protegido y adapta el comando `mysqldump`; no guardes la contraseña en el script.

## Aplicación Android

El API acepta por defecto `http://192.168.1.10/gestion/api`. En la red local,
averigua la IPv4 del PC con `ipconfig`, permite Apache en el firewall y genera
el APK desde `app_movil`:

```powershell
flutter pub get
flutter build apk --dart-define=API_BASE_URL=http://192.168.1.25/gestion/api
```

Reemplaza `192.168.1.25` por la IP real del PC. Para abrir en Android Studio,
usa la carpeta `app_movil`. Android permite HTTP sin cifrar para desarrollo en
LAN; no expongas esta configuración a Internet.

La aplicación móvil incluye inicio de sesión, consulta de indicadores,
horarios/asistencia y carnet QR para estudiantes, además del escaneo de
carnets para registrar ingresos o salidas y el escaneo encadenado de equipo y
responsable para validar su salida. El panel móvil también refresca cada 8 s.

## API principal

Todas las rutas están bajo `/gestion/api/`, reciben/devuelven JSON salvo las
descargas de reportes y requieren una sesión para operaciones autenticadas.
El endpoint `/health` sirve para comprobar el servicio.

- `POST /auth/login`, `POST /auth/register` (solo estudiantes), `POST /auth/logout`, `GET /auth/me`
- `POST /auth/recovery`, `POST /auth/reset-password`
- `GET /dashboard`, `GET /attendance/alerts`, `POST /attendance/mark`
- `GET /attendance?desde=YYYY-MM-DD&hasta=YYYY-MM-DD` para consultar por rango
- CRUD por colección: `users`, `environments`, `fichas`, `enrollments`,
  `cards`, `maintenance`, `schedules`, `reservations`, `attendance`,
  `novelties`, `accesses`, `visitors`, `equipment`, `equipment-movements`,
  `loans`, `temporary-permits`, `notifications` y `blocks`
- `GET /reports/{coleccion}?format=csv|xlsx|pdf` (máximo 1.000 filas)
- `POST /accesses` admite `tipo` (`ingreso` o `salida`), `usuario_id` o
  `visitante_id`; para carnets también admite `qr`. Para equipos admite el
  `serial` autorizado y valida la secuencia de ingreso/salida y responsable.
- `POST /equipment-movements/scan` registra un equipo autorizado usando su QR,
  el QR del carnet del responsable y el tipo de movimiento.

Los nombres exactos de campos, roles por endpoint y validaciones se encuentran
en `api/index.php`; los usuarios se filtran por ficha y las asistencias por
identidad. La API rechaza cruces de reservas y horarios, registra auditoría y
aplica claves foráneas/índices únicos del esquema.

## Alcance de esta primera versión

Incluye autenticación por sesión con expiración por inactividad, permisos por
rol, CRUD web genérico, validaciones principales, notificaciones almacenadas,
bloqueo/desbloqueo, recuperación de contraseña con SMTP, reportes CSV/XLSX/PDF,
auditoría, respaldos programables y el cliente Android compartiendo la API.
Antes de producción todavía se deben configurar HTTPS, credenciales y
restricciones de red apropiadas; esta entrega está diseñada para una instalación
local de desarrollo en XAMPP.
