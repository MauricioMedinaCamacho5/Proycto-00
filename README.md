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
   `database/actualizar_administrador.sql`,
   `database/actualizar_solicitudes_registro.sql` y
   `database/actualizar_carnets_qr.sql` y
   `database/actualizar_verificacion_correo.sql`; estas actualizaciones
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
la app Android usan el emblema original del portal: un libro abierto con un
brote, junto con la identidad visual verde y el lema
«Formación · Servicio · Oportunidades». El emblema web está en
`web/assets/images/portal-mark.svg`; Android lo dibuja con Flutter.

En **Registrar como**, las personas ingresan documento, teléfono, nombres,
apellidos, correo electrónico y contraseña. Pueden agregar una foto propia
opcional para el carné (JPG, PNG o WEBP, máximo 1 MB). Al terminar se genera un
carné QR descargable como PNG. Los estudiantes quedan activos; las solicitudes
de instructores y portería necesitan aprobación en **Solicitudes de registro**.
El registro público no permite crear administradores: los crea un administrador
ya autenticado. Antes de crear la cuenta, se envía un código de seis dígitos al
correo indicado; solo se completa el registro después de introducir el código.
El código vence en 15 minutos y permite hasta cinco intentos. La persona debe
poder recibir mensajes en esa dirección; escribir una dirección válida no
demuestra por sí solo que exista o pertenezca al usuario.

Estudiantes, instructores, portería y administradores inician sesión escaneando
el QR de su carné o eligiendo una imagen que lo contenga. La cámara requiere
permiso del navegador y que el sitio se abra desde `localhost` o HTTPS. El QR
es una credencial de acceso: no lo publiques ni compartas; cualquier persona
con una copia puede entrar a la cuenta. En el panel, el botón **Mi carné QR**
permite volver a descargar el carné. Las cuentas existentes pueden usar el
acceso temporal con correo y contraseña para obtener su primer carné; después
de descargarlo, el acceso con contraseña queda deshabilitado para esa cuenta.
El listado y el panel de administración se actualizan automáticamente cada 3
segundos desde la misma base de datos. Si la cámara o el lector no están
disponibles, se puede escribir el documento manualmente.

Cuenta administradora inicial (la contraseña temporal es `password`):

| Rol | Correo |
|---|---|
| Administrador | `mauriciomedinacamacho5@gmail.com` |

En una instalación nueva o para cuentas existentes sin carné, usa el acceso
temporal con correo y contraseña una vez para abrir el panel y descargar el
carné QR. Después inicia sesión escaneando el carné.

Otras cuentas de demostración (la contraseña de ambas es `password`):

| Rol | Correo |
|---|---|
| Instructor | `instructor@institucion.local` |
| Estudiante | `estudiante@institucion.local` |
| Portero | `portero@institucion.local` |

Las credenciales son exclusivamente para desarrollo local: cámbialas antes de
usar información real.

### Verificación y recuperación por correo

Después de `composer install`, configura SMTP para Apache. Para Gmail, activa la
verificación en dos pasos en la cuenta remitente y crea una contraseña de
aplicación desde la configuración de seguridad de Google. Guarda esa contraseña
solo en la configuración local protegida de Apache; no la pongas en Git, en el
código ni la compartas en el chat. Usa el mismo Gmail como remitente.

En `C:\xampp\apache\conf\extra\httpd-xampp.conf`, agrega estas líneas con tus
datos (reemplaza los valores de ejemplo) y reinicia Apache. Ese archivo es local
y no debe subirse al repositorio:

```apache
SetEnv SMTP_HOST smtp.gmail.com
SetEnv SMTP_PORT 587
SetEnv SMTP_SECURE tls
SetEnv SMTP_USER tu-cuenta@gmail.com
SetEnv SMTP_PASSWORD TU_CONTRASENA_DE_APLICACION
SetEnv SMTP_FROM tu-cuenta@gmail.com
SetEnv APP_URL http://localhost/gestion
```

También se admite SSL con puerto `465` y `SMTP_SECURE=ssl`. `APP_URL` debe ser
la dirección base pública de la aplicación cuando se use fuera de este equipo.
El formulario de recuperación siempre envía un enlace de un solo uso al correo
registrado; vence en 30 minutos. La respuesta de la pantalla no revela si una
dirección está asociada a una cuenta. Si PHPMailer o SMTP no están configurados
o el servidor de correo no acepta el mensaje, la API muestra un error y no
ofrece enlaces locales.

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

La aplicación móvil incluye inicio de sesión con QR para todos los roles,
consulta de indicadores, horarios/asistencia y carné QR para estudiantes, además
del escaneo de carnets para registrar ingresos o salidas y el escaneo encadenado
de equipo y responsable para validar su salida. El panel móvil también refresca cada 8 s.

## API principal

Todas las rutas están bajo `/gestion/api/`, reciben/devuelven JSON salvo las
descargas de reportes y requieren una sesión para operaciones autenticadas.
El endpoint `/health` sirve para comprobar el servicio.

- `POST /auth/qr-login`, `GET /auth/card`, `POST /auth/card-issued`,
  `POST /auth/register` (estudiantes y solicitudes de personal),
  `POST /auth/login` (acceso temporal para obtener el carné de cuentas existentes),
  `POST /auth/logout`, `GET /auth/me`
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
