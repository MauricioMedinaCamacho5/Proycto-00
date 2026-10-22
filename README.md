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
   `database/actualizar_verificacion_correo.sql`, además de las migraciones
   pendientes en `database/migrations/`; estas actualizaciones conservan las
   tablas y los demás datos existentes. Antes de importar una migración,
   genera un respaldo completo de la base.
   Para aplicar la formación y asistencia por clase, respalda primero la base,
   selecciona `proyecto01` en phpMyAdmin y ejecuta una sola vez
   `database/migrations/002_formacion_asistencia_por_clase.sql`.
   Para asignar fichas a instructores y permitir que los estudiantes elijan
   su ficha al registrarse, respalda primero la base y ejecuta una sola vez
   `database/migrations/003_asignaciones_instructores_fichas.sql`. Esta
   migración conserva los datos e incorpora las asignaciones que ya aparecen
   en los horarios.
   Para cambiar el módulo al número de ficha de 10 dígitos y limpiar las
   fichas existentes, respalda la base y ejecuta una sola vez
   `database/migrations/004_fichas_numero_10_digitos.sql`. Se borran las fichas,
   sus inscripciones, horarios, asistencias, asignaciones y solicitudes
   relacionadas, pero se conservan las cuentas de usuario. Para pasar después
   a números de 7 dígitos y calcular automáticamente el estado según las fechas,
   respalda la base y ejecuta una sola vez
   `database/migrations/005_fichas_numero_7_digitos.sql`; esta migración no
   elimina registros y se detiene sin cambiar los números si encuentra alguno
   que no tenga exactamente siete dígitos.
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

En **Registrar como**, los estudiantes eligen una ficha vigente creada por
administración. Cada ficha usa un número de exactamente siete dígitos numéricos.
Su estado se calcula por las fechas: **Programada** antes del inicio, **Activa**
durante el rango y **Terminada** después de la fecha fin.
Administración puede
asignar fichas a instructores en **Instructores por ficha**, y cada instructor
puede consultar los aprendices inscritos en sus fichas asignadas. La gestión de
usuarios también permite separar las cuentas por rol.

Solo administración puede abrir **Fichas y programas**, **Instructores por
ficha** y **Asignación de aprendices**. El instructor consulta y registra
asistencia desde **Progreso de estudiantes**: busca una ficha asignada por
programa o número y ve sus aprendices, el porcentaje de asistencia acumulado y
el registro diario. La ficha se elige de un desplegable visual con sus fichas
activas asignadas; los accesos separados a **Asistencia** y **Novedades** no se
muestran al instructor. El progreso incluye un gráfico circular, una barra y
el resumen de presentes, faltas, retrasos y permisos por aprendiz. La fecha de
asistencia se toma automáticamente del día actual; el porcentaje no incluye
tareas porque el sistema no registra entregas.
Allí también puede revisar la ocupación semanal de los ambientes. La disponibilidad se calcula con todos los horarios
vigentes; los detalles de asignatura, ficha e instructor solo se muestran para
las fichas asignadas al instructor.

Las personas ingresan documento, teléfono, nombres, apellidos, correo
electrónico y contraseña. Pueden agregar una foto propia opcional para el carné
(JPG, PNG o WEBP, máximo 1 MB). Al terminar se genera un carné QR descargable
como PNG. Los estudiantes quedan activos; las solicitudes de instructores y
portería necesitan aprobación en **Solicitudes de registro**. El registro
público no permite crear administradores: los crea un administrador ya
autenticado y no requiere confirmar el correo electrónico.

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

### Recuperación por correo

El registro de estudiantes ya no requiere confirmar el correo ni depende de
SMTP. Las solicitudes de instructores y portería siguen pendientes de
aprobación administrativa. Para recuperar contraseñas, configura SMTP en Apache
para la cuenta Gmail o Hotmail/Outlook que enviará los mensajes. El enlace se
envía a la dirección registrada en cada cuenta, sea administrador, estudiante,
instructor o portería.

Para Gmail, activa la verificación en dos pasos y genera una contraseña de
aplicación desde la configuración de seguridad de Google. Para Hotmail/Outlook,
usa `smtp-mail.outlook.com`, el puerto `587` con STARTTLS y una contraseña de
aplicación si Microsoft la solicita; el acceso SMTP debe estar permitido en la
cuenta. No uses la contraseña habitual si el proveedor requiere una contraseña
de aplicación.

En `C:\xampp\apache\conf\extra\httpd-xampp.conf`, agrega **solo uno** de estos
bloques con los datos de la cuenta remitente y reinicia Apache. El archivo es
local: no lo subas a Git ni compartas la contraseña.

Gmail:

```apache
SetEnv SMTP_HOST smtp.gmail.com
SetEnv SMTP_PORT 587
SetEnv SMTP_SECURE tls
SetEnv SMTP_USER tu-cuenta@gmail.com
SetEnv SMTP_PASSWORD CONTRASENA_DE_APLICACION
SetEnv SMTP_FROM tu-cuenta@gmail.com
SetEnv APP_URL http://localhost/gestion
```

Hotmail/Outlook:

```apache
SetEnv SMTP_HOST smtp-mail.outlook.com
SetEnv SMTP_PORT 587
SetEnv SMTP_SECURE tls
SetEnv SMTP_USER tu-cuenta@hotmail.com
SetEnv SMTP_PASSWORD CONTRASENA_DE_APLICACION
SetEnv SMTP_FROM tu-cuenta@hotmail.com
SetEnv APP_URL http://localhost/gestion
```

La pantalla de inicio permite solicitar la recuperación para cualquiera de los
roles. El correo identifica el tipo de cuenta y contiene el botón **Actualizar
contraseña**. El enlace es de un solo uso y vence en 30 minutos. Si la aplicación
se usa fuera de este equipo, cambia `APP_URL` por su dirección pública para que
el enlace enviado pueda abrirse. La respuesta de la pantalla no revela si una
dirección está asociada a una cuenta; si SMTP no acepta el envío, se informa que
hay que revisar la configuración y el motivo técnico queda en el registro de
errores de PHP.

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
- `GET /attendance/roster?ambiente_id=ID&ficha_id=ID&horario_id=ID&fecha=YYYY-MM-DD`
  para que administración e instructores registren asistencias por clase y en
  cualquier fecha. Las fichas, programas y trimestres se administran desde
  **Fichas y programas**; luego se asignan aprendices desde **Asignación de
  aprendices** y se asocian ambientes, instructores y asignaturas en
  **Horarios**. El aprendiz consulta su propio horario, historial y
  notificaciones; no selecciona ambiente ni ficha al registrarse.
- `GET /attendance/progress` para el resumen global de aprendices y
  `GET /attendance/progress?usuario_id=ID` para su historial detallado.
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
en `api/index.php`. Las asistencias y horarios del aprendiz se limitan a sus
fichas; administración e instructores pueden gestionar aprendices del centro.
Los avisos personales se guardan en la aplicación para el estudiante y el
personal correspondiente cuando cambia una asistencia, una ficha, una
asignación o un horario. La API rechaza cruces de reservas y horarios, registra
auditoría y aplica claves foráneas/índices únicos del esquema.

## Alcance de esta primera versión

Incluye autenticación por sesión con expiración por inactividad, permisos por
rol, CRUD web genérico, validaciones principales, notificaciones almacenadas,
bloqueo/desbloqueo, recuperación de contraseña con SMTP, reportes CSV/XLSX/PDF,
auditoría, respaldos programables y el cliente Android compartiendo la API.
Antes de producción todavía se deben configurar HTTPS, credenciales y
restricciones de red apropiadas; esta entrega está diseñada para una instalación
local de desarrollo en XAMPP.
