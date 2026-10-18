# Gestión Institucional

Aplicación inicial para XAMPP con una API PHP/PDO compartida por el sitio web y
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
   `proyecto01`, sus tablas y datos de demostración. Si ya habías importado la
   base, importa en su lugar `database/actualizar_administrador.sql`; este
   cambio conserva las tablas y los demás datos existentes.
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

- `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`
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
