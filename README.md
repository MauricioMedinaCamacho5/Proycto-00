# Proycto-00 — Gestión Institucional

Aplicación institucional (proyecto de prueba y práctica) para XAMPP con una API PHP/PDO compartida por el sitio web y
la aplicación Android. Los datos persisten en MySQL/MariaDB; el panel web
actualiza sus indicadores cada 8 segundos.

Para instrucciones de uso paso a paso, consulta el [Manual de uso](MANUAL_DE_USO.md).

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
   Para habilitar el tipo de documento, la aceptación de tratamiento de datos y
   el acceso con PIN del carné en una base existente, ejecuta una sola vez
   `database/migrations/006_registro_pin_carnet.sql`.
   Para agregar la configuración institucional y la bitácora de auditoría,
   ejecuta una sola vez `database/migrations/007_configuracion_administrador.sql`.
   Para agregar programas, competencias, resultados de aprendizaje y calendario,
   respalda primero la base y ejecuta una sola vez
   `database/migrations/008_base_academica_calendario.sql`. La migración crea
   catálogos aditivos, enlaza las fichas con los programas que ya estaban en su
   campo de texto y conserva las columnas y registros anteriores. Los programas
   heredados reciben nivel técnico y duración inicial de seis trimestres, que
   administración puede editar. Los horarios existentes quedan sin competencia
   hasta que se les asigne una desde el módulo **Horarios**.
   Para habilitar las excusas con soporte, las notificaciones tipadas y los
   juicios evaluativos, respalda primero la base y ejecuta una sola vez
   `database/migrations/009_modulo_aprendiz.sql`, después de las migraciones
   anteriores. La migración conserva los registros existentes y crea la tabla
   de historial evaluativo. Los soportes de excusa se guardan en
   `api/uploads/excuses/`; el acceso directo al directorio está bloqueado y los
   archivos se sirven solo mediante rutas autenticadas.
   El módulo instructor usa las migraciones 007, 008 y 009 ya descritas;
   consulta la sección siguiente para la migración de sincronización y alertas.
   Incluye panel de fichas asignadas, control de asistencia por sesión y QR,
   revisión auditada de excusas, juicios, comunicados y reportes de riesgo
   exportables. Los permisos de instructor se comprueban en la API para cada
   ficha asignada.
   Para registrar faltas injustificadas y evitar alertas automáticas duplicadas,
   respalda primero la base y ejecuta una sola vez
   `database/migrations/010_sincronizacion_alertas.sql`, después de las
   migraciones anteriores. La tarea diaria usa los umbrales y días hábiles de
   **Configuración institucional** y el calendario académico activo.
   Puedes probarla sin guardar cambios con
   `scripts\tarea_diaria.bat --dry-run` o ejecutarla manualmente con
   `scripts\tarea_diaria.bat`. En el Programador de tareas de Windows, crea una
   tarea diaria cuyo programa sea la ruta completa a `scripts\tarea_diaria.bat`;
   si PHP no está en PATH ni en XAMPP, define `PHP_CLI` con la ruta a
   `php.exe`. Si la base usa credenciales no predeterminadas, define también
   `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` para la cuenta de Windows
   que ejecuta la tarea. Consulta el resultado en el historial del Programador
   de tareas.
   Para activar la portería conectada, crea un respaldo completo y ejecuta una
   sola vez `database/migrations/011_porteria_conectada.sql`. Agrega el
   registro diario de QR y equipos, propietarios de equipos, llaves de
   ambientes, novedades de turno y objetos perdidos. Configura el tiempo máximo
   de devolución de llaves en **Configuración institucional**; la misma tarea
   diaria marca préstamos vencidos, notifica a portería y administración y
   evita duplicados. El rol de portería usa **Ingreso de equipos**; aprendices
   e instructores registran, editan y desactivan sus equipos desde **Mis equipos**.
   Los equipos admiten portátil, tablet y otros tipos. Administración
   consulta el **Panel de portería** y puede exportar su historial a PDF o Excel.
   El filtro del panel y del reporte incluye el serial. Para permitir equipos
   tipo tablet, crea primero un respaldo y aplica una sola vez
   `database/migrations/014_tablet_equipos.sql`.
   Para completar el cierre administrativo, respalda la base y ejecuta una sola
   vez `database/migrations/012_cierre_productiva_coordinador.sql`. La migración
   agrega el rol coordinador, el estado activo/desactivado del carné, los cierres
   de trimestre, las etapas productivas y sus bitácoras, y las solicitudes de
   corrección que requieren aprobación. Administración puede consultar y
   descargar carnets, regenerar su QR o desactivarlos; el QR desactivado deja de
   validar. El panel incluye indicadores académicos, de asistencia, alertas y
   portería. **Cierre de trimestre** guarda el resumen y avanza la ficha según la
   duración de su programa; **Etapa productiva** registra empresa, alternativa,
   instructor de seguimiento y bitácoras. El coordinador puede consultar
   reportes, cierres y alertas, y aprobar o rechazar correcciones; no accede a
   configuración ni a operaciones de portería.
   Para añadir a las fichas el campo de jornada usado por los datos de prueba,
   respalda la base y aplica una sola vez
   `database/migrations/013_jornada_fichas.sql`.
   Los scripts `scripts\cargar_datos_prueba.php` y
   `scripts\eliminar_datos_prueba.php` cargan y eliminan, respectivamente, los
   registros de prueba solicitados. Antes de aplicar la migración o cargar los
   datos, genera un respaldo completo con `backups\respaldo_diario.bat`.
   Después, ejecuta desde la carpeta del proyecto
   `C:\xampp\php\php.exe scripts\cargar_datos_prueba.php` (ajusta la ruta de PHP
   si es distinta).    el seed crea 15 cuentas de prueba y sus carnés, además de un portátil de
   prueba para Laura Gómez Rojas y otro para Catalina Londoño Ríos; las
   contraseñas y PIN quedan almacenados con `password_hash`, y cada registro
   propio queda identificado para permitir una limpieza acotada. Para retirar
   el seed, ejecuta
   `C:\xampp\php\php.exe scripts\eliminar_datos_prueba.php`. La limpieza aborta
   sin cambios si detecta dependencias que impidan borrar con seguridad los
   registros marcados; nunca elimina cuentas administradoras.
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

El plazo institucional para corregir asistencia se cuenta desde su registro
original. Los instructores pueden editar o eliminar dentro de ese plazo;
administración conserva la facultad de corregir después del vencimiento. Las
excusas se registran como novedades de tipo `inasistencia`, vinculadas a una
asistencia ausente del mismo aprendiz y fecha. Se aceptan dentro de los días
hábiles configurados: se cuentan lunes a viernes y se excluyen las fechas
activas de tipo festivo o vacaciones registradas en **Calendario académico**.

Las personas ingresan tipo y número de documento, teléfono, nombres, apellidos,
correo electrónico, contraseña y un PIN de 4 a 6 números. También aceptan el
tratamiento de datos personales. Pueden agregar una foto propia opcional para
el carné (JPG, PNG o WEBP, máximo 1 MB). Al terminar se genera un carné QR
descargable como PNG. Los estudiantes quedan activos; las solicitudes de instructores y
portería necesitan aprobación en **Solicitudes de registro**. El registro
público no permite crear administradores: los crea un administrador ya
autenticado y no requiere confirmar el correo electrónico.

Estudiantes, instructores, portería y administradores pueden iniciar sesión
con correo o documento y contraseña, o escanear el QR del carné e ingresar el
PIN. El PIN se guarda como hash; tres intentos fallidos bloquean el acceso con
QR durante cinco minutos. La cámara requiere permiso del navegador y que el
sitio se abra desde `localhost` o HTTPS. El QR por sí solo no permite entrar,
pero contiene datos del carné: no lo publiques ni compartas. En el panel, el
botón **Mi carné QR** permite volver a descargarlo. Las cuentas existentes
pueden configurar su PIN desde el ingreso con documento o correo y contraseña.
El listado y el panel de administración se actualizan automáticamente cada 3
segundos desde la misma base de datos. Si la cámara o el lector no están
disponibles, se puede escribir el documento manualmente.

## Fase 2: portal del aprendiz

El rol estudiante inicia en **Inicio**, donde consulta su ficha, programa,
trimestre, porcentaje de asistencia con umbrales de alerta, notificaciones sin
leer y las clases del día. **Horarios** tiene vistas diaria y semanal;
**Asistencia** muestra los detalles de cada sesión y permite presentar una
excusa para una falta dentro del plazo hábil, con soporte PDF o imagen de hasta
5 MB. El estado de la excusa se muestra en **Mis excusas**. Administración y
los instructores asignados revisan las solicitudes desde **Excusas**.

**Notificaciones** muestra tipo, remitente y mensaje y permite marcarlas como
leídas. **Mi formación** presenta competencias, resultados y el último juicio
evaluativo, y permite descargar el historial en PDF. Administración e
instructores registran juicios desde **Juicios evaluativos**; el instructor
solo puede evaluar aprendices y resultados de sus fichas/programas asignados.
El aprendiz también puede consultar **Mi carné** y cambiar solo correo,
celular, contraseña y PIN desde **Mi perfil**. Los cambios de contraseña o PIN
requieren confirmar la contraseña actual.

Las rutas del portal aprendiz derivan el usuario de la sesión y no aceptan un
ID de aprendiz indicado por el navegador como autoridad. Para probar el
aislamiento, inicia sesión con una cuenta estudiante e intenta solicitar el
perfil, asistencia o archivos de otro ID: la API no devuelve datos ajenos.

Prueba de extremo a extremo: crea o selecciona un programa, competencia,
resultado de aprendizaje y ficha con horario; inscribe una cuenta de estudiante
y asigna un instructor a la ficha. Entra como estudiante para comprobar Inicio,
horario, carné y porcentaje; con una cuenta autorizada registra una falta de
prueba y vuelve a entrar como estudiante para enviar la excusa con soporte.
Entra como instructor asignado para revisar la excusa y registrar un juicio.
Verifica que el aprendiz reciba la notificación y pueda descargar el historial
PDF.

Cuenta administradora inicial (la contraseña temporal es `password`):

| Rol | Correo |
|---|---|
| Administrador | `mauriciomedinacamacho5@gmail.com` |

En una instalación nueva o para cuentas existentes, ingresa con documento o
correo y contraseña. Si una cuenta anterior todavía no tiene PIN, puede
configurarlo desde ese formulario para habilitar su acceso QR.

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

### Vaciar datos y recrear la cuenta administradora

El procedimiento destructivo `backups\reiniciar_base_y_crear_administrador.ps1`
comprueba que exista un dump reciente y completo, muestra las tablas, cantidades
de registros, fotos y soportes de excusas que afectará, y exige escribir `BORRAR TODO proyecto01`
antes de continuar. Después solicita en la terminal, sin mostrarlos, la
contraseña y el PIN nuevos. Guarda ambos mediante hashes de verificación
(`password_hash`), no como texto recuperable.

Ejecuta PowerShell desde la carpeta del proyecto:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ".\backups\reiniciar_base_y_crear_administrador.ps1" -BackupPath ".\backups\proyecto01_full_before_reset_FECHA.sql"
```

El script conserva el esquema, los roles y las migraciones; restablece la
configuración institucional a sus valores predeterminados, limpia las tablas
de registros, las fotos de `api\uploads\profiles` y los soportes de
`api\uploads\excuses`, reinicia los contadores y crea una cuenta administradora
con carné QR. No borra imágenes ni recursos propios del portal. Revisa
cuidadosamente la lista que presenta; si cancelas o no escribes la frase exacta,
no modifica la base ni los archivos.

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

La aplicación móvil incluye inicio de sesión con QR y PIN o documento/correo y
contraseña para todos los roles,
consulta de indicadores, horarios/asistencia y carné QR para estudiantes, además
del escaneo de carnets para registrar ingresos o salidas y el escaneo encadenado
de equipo y responsable para validar su salida. El panel móvil también refresca cada 8 s.

## API principal

Todas las rutas están bajo `/gestion/api/` y reciben/devuelven JSON salvo las
descargas de reportes. Las operaciones autenticadas requieren una sesión; la
vista previa del carné está limitada al nombre y la foto para confirmar la
identidad antes de solicitar el PIN. El endpoint `/health` sirve para comprobar
el servicio.

- `POST /auth/qr-preview`, `POST /auth/qr-login` (QR y PIN),
  `GET /auth/card`, `POST /auth/card-issued`,
  `POST /auth/register` (estudiantes y solicitudes de personal),
  `POST /auth/login` (correo/documento y contraseña; permite configurar el PIN),
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
