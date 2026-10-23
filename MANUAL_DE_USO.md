# Manual de uso de Gestión Institucional

Esta guía explica cómo abrir la página y cómo realizar las tareas habituales
según el tipo de cuenta: administración, coordinación, instructor, estudiante o
portería.

## 1. Abrir la página

1. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
2. Abre Chrome y visita `http://localhost/gestion/`.
3. No abras `index.html` desde el explorador de archivos: con una dirección
   `file:///` no funcionan el inicio de sesión ni la conexión con la base de
   datos.
4. Si la página muestra un error del servidor, visita
   `http://localhost/gestion/api/health`. La respuesta
   `{"status":"ok","service":"gestion-institucional"}` indica que la API y la
   conexión con la base de datos están disponibles.

## 2. Crear una cuenta

1. En la portada, pulsa **Registrarme como estudiante o personal**.
2. Escoge el tipo de cuenta:
   - **Estudiante**: selecciona una ficha activa.
   - **Instructor** o **Portería**: la cuenta queda pendiente hasta que
     administración la apruebe.
3. Completa tipo y número de documento, teléfono, nombres, apellidos, correo,
   contraseña y PIN del carné (de 4 a 6 números). Confirma la contraseña y el
   PIN, y acepta el tratamiento de datos personales.
4. Si quieres, añade una foto JPG, PNG o WEBP de máximo 1 MB para el carné.
5. Pulsa **Crear cuenta** y sigue el aviso que aparece. Las solicitudes de
   instructor y portería no tendrán acceso institucional hasta ser aprobadas.

El registro público no permite crear cuentas de administrador ni coordinador.
Administración puede crear ambos tipos de cuenta desde el módulo **Usuarios**.

## 3. Iniciar sesión y usar el carné QR

1. En la portada, selecciona **Ingresar como estudiante**, **Ingresar como
   equipo institucional** o **Ingresar como administrador**.
2. Inicia sesión con documento o correo y contraseña, o escanea el QR del carné
   con la cámara / elige una imagen que contenga el QR y luego ingresa el PIN.
3. Si el navegador lo solicita, permite el uso de la cámara. La cámara requiere
   `localhost` o HTTPS. También puedes elegir una imagen del QR en vez de usar
   la cámara.
4. En el acceso con QR, confirma que la foto y el nombre correspondan a tu
   cuenta antes de ingresar el PIN. Tres PIN incorrectos bloquean temporalmente
   el ingreso con carné durante cinco minutos.
5. Las cuentas existentes pueden establecer o cambiar su PIN al iniciar sesión
   con documento o correo y contraseña, escribiéndolo en el campo opcional.
6. Guarda el carné en un lugar seguro. El QR por sí solo no permite iniciar
   sesión, pero contiene datos del carné: no lo compartas ni publiques. En la
   sesión, **Mi carné QR** permite volver a abrirlo.
7. Para salir, pulsa **Cerrar sesión**.

## 4. Flujo de administración

El administrador ve el resumen general y los módulos autorizados para gestionar
la institución. Los formularios se abren con **＋ Nuevo registro**; cuando el
módulo lo permite, también aparecen acciones para editar, eliminar, aprobar o
rechazar registros.

En **Configuración institucional** puede ajustar los umbrales de alerta de
asistencia, los plazos para excusas y correcciones, y los intentos y la duración
del bloqueo del PIN. Los umbrales se reflejan en las alertas del panel; los
intentos y minutos se aplican al ingreso con carné. El plazo de corrección se
cuenta desde el registro original: el instructor solicita la corrección dentro
de ese límite y debe indicar un motivo; coordinación o administración la
aprueba o rechaza. Administración puede corregirlas directamente incluso
después del vencimiento. Las asistencias no se eliminan; cada corrección queda
auditada.
Para presentar una excusa, registra una **Novedad** de tipo `inasistencia` y
selecciona la asistencia marcada como ausente del aprendiz. El plazo se cuenta
de lunes a viernes y excluye las fechas activas de tipo festivo o vacaciones
registradas en **Calendario académico**. No se aceptan excusas para asistencias
futuras o que ya excedieron el límite. **Auditoría** permite
consultar las 500 acciones más recientes y conserva el historial sin acciones
para editarlo o eliminarlo.

### 4.1. Aprobar solicitudes de personal

1. Abre **Solicitudes de registro**.
2. Revisa los datos y el rol solicitado.
3. Pulsa **Aprobar** para activar la solicitud o **Rechazar** si no corresponde.
4. Las solicitudes pendientes también se cuentan en el indicador del menú.

### 4.2. Configurar la base académica

1. En **Programas académicos**, crea cada programa con código, nombre, nivel,
   duración en trimestres y estado activo.
2. En **Competencias**, crea las competencias y selecciona el programa al que
   pertenecen.
3. En **Resultados de aprendizaje**, registra el código y la descripción y
   vincúlalos a una competencia.
4. En **Calendario académico**, registra una fila por fecha y selecciona
   **Festivo** o **Vacaciones**. Para excluir varios días de vacaciones,
   registra cada fecha por separado. Solo se excluyen del cálculo las fechas
   cuyo estado esté **Activo**.
5. Para retirar un elemento sin perder su historial o sus relaciones, edítalo y
   cambia su estado a **Inactivo**. Los catálogos académicos no se eliminan.

**Prueba rápida de esta fase:**

1. Crea un programa, su competencia y un resultado de aprendizaje.
2. Crea una ficha y selecciona ese programa. En **Horarios**, verifica que la
   competencia disponible sea la del programa asociado a la ficha.
3. Configura temporalmente el plazo de excusa en un día hábil. Registra una
   ausencia de un viernes, marca el lunes siguiente como festivo y presenta la
   novedad de inasistencia el martes enlazándola a esa ausencia. Debe aceptarse
   porque el lunes no cuenta. Para crear una asistencia de prueba con fecha
   pasada, administración puede usar `POST /api/attendance/mark` con una sesión
   autenticada y los campos `usuario_id`, `ficha_id`, `fecha` (viernes) y
   `estado: "ausente"`. Compara con otra ausencia sin festivo entre las fechas:
   al superar un día hábil, la excusa debe rechazarse.

### 4.3. Crear fichas y asignarlas

1. En **Fichas y programas**, crea la ficha con su número de siete dígitos,
   un programa académico activo, fecha de inicio y fecha de fin.
2. El estado se calcula con esas fechas: programada antes de empezar, activa
   durante el rango y terminada después de la fecha final.
3. En **Instructores por ficha**, asigna cada instructor a las fichas que debe
   atender.
4. En **Asignación de aprendices**, vincula las cuentas de estudiante con su
   ficha.
5. Los aprendices podrán escoger una ficha activa al registrarse. Las fichas
   asignadas son la base para limitar la información que ve cada instructor.

### 4.4. Configurar horarios y ambientes

1. Abre **Horarios** y registra la ficha, una competencia del mismo programa,
   el ambiente, el instructor, el día, la hora de inicio y fin, y la asignatura.
2. En **Ambientes**, administra el código, nombre, ubicación, capacidad y
   estado del espacio.
3. Un instructor puede consultar ambientes, pero su gestión corresponde a
   administración.
4. La vista semanal de disponibilidad usa los horarios registrados para
   mostrar los ambientes libres, ocupados o en mantenimiento.

### 4.5. Consultar progreso y asistencia

1. Abre **Progreso de estudiantes** y selecciona una ficha.
2. Consulta el resumen visual de asistencia, con porcentaje general, gráfico
   circular, barra y conteos de presentes, faltas, retrasos y permisos.
3. Revisa el porcentaje individual de cada aprendiz o pulsa **Ver historial**
   para consultar sus registros.
4. En **Asistencia**, selecciona la ficha y marca el estado de cada aprendiz
   para el día actual. Puedes agregar o editar el motivo/observación; los
   registros no se eliminan.

El porcentaje usa esta fórmula:
**(presentes + retrasos) / (presentes + retrasos + faltas)**. Los permisos no
entran en el cálculo. La plataforma registra asistencia, pero no tiene un
módulo de entregas de tareas; por eso el porcentaje no representa tareas ni
notas académicas.

### 4.6. Otros módulos de administración

- **Usuarios**: consulta y administra las cuentas y sus roles.
- **Notificaciones**: gestiona avisos institucionales.
- **Excusas**: revisa el motivo y el soporte enviado por los aprendices. Solo
  los instructores asignados pueden revisar las de sus fichas; al aprobar una
  excusa, la asistencia se convierte en permiso y el aprendiz recibe un aviso.
- **Juicios evaluativos**: registra aprobado, no aprobado o por evaluar para
  los resultados de aprendizaje de los aprendices autorizados.
- **Novedades**: registra novedades relacionadas con aprendices.
- **Carnets digitales**: consulta o administra los carnés.
- **Reservas**: administra reservas de ambientes.
- **Bloqueos de usuarios**: consulta bloqueos y utiliza la acción de desbloqueo
  cuando corresponda.
- **Resumen**: presenta indicadores generales y alertas de asistencia.

## 5. Flujo del instructor

1. **Inicio** muestra las fichas activas asignadas, las clases del día y las
   excusas pendientes. Usa los accesos del panel para abrir **Mis fichas** o
   **Excusas**.
2. En **Mis fichas**, selecciona una ficha asignada para consultar sus
   aprendices, el porcentaje individual de asistencia y el resumen de presentes,
   faltas, retrasos y permisos. El porcentaje excluye los permisos.
3. Para registrar asistencia, selecciona la sesión cuando la ficha tenga más
   de una clase ese día. Usa **Asistió**, **No asistió**, **Tarde** o
   **Permiso**; cada registro requiere una observación y notifica al aprendiz.
   También puedes pulsar **Escanear carné QR**, aceptar el permiso de cámara y
   confirmar el estado y la observación del aprendiz identificado.
4. Para corregir una asistencia existente, elige un estado, escribe la
   observación y explica el motivo de la corrección. El plazo se cuenta desde el
   registro original y se configura en **Configuración institucional**.
   Las asistencias no se eliminan; cada corrección queda auditada y notifica al
   aprendiz.
5. **Excusas** muestra las solicitudes de aprendices de tus fichas. Para aprobar
   o rechazar una pendiente, escribe una observación para el aprendiz. Aprobarla
   cambia la falta a permiso y recalcula el porcentaje; el aprendiz recibe la
   notificación de la decisión.
6. **Juicios evaluativos** permite registrar aprobado, no aprobado o por evaluar
   para los resultados de aprendizaje correspondientes a las fichas asignadas.
   El aprendiz recibe una notificación.
7. **Horarios** permite consultar y administrar los horarios de tus fichas.
   Desde **Mis fichas**, pulsa **Disponibilidad semanal de ambientes** para ver
   la ocupación semanal y usa **Volver a aprendices** para regresar.
8. **Comunicados** permite seleccionar una ficha asignada y enviar un título y
   mensaje a toda la ficha o a un aprendiz específico; cada destinatario recibe
   una notificación.
9. En **Mis fichas**, elige CSV, Excel o PDF y pulsa **Exportar aprendices en
   riesgo** para descargar el informe de asistencia.

El servidor verifica las fichas asignadas para las consultas y acciones del
instructor. Para probar el acceso, intenta consultar una ficha no asignada:
la API debe rechazarla. Las excusas se presentan por el aprendiz dentro de los
días hábiles configurados; se excluyen sábados, domingos y las fechas activas
de tipo festivo o vacaciones registradas en **Calendario académico**.

## 6. Sincronización y alertas automáticas

- Cada registro o corrección de asistencia notifica al aprendiz, a los
  instructores asignados y a administración con el estado, quién lo registró,
  fecha y sesión.
- El sistema genera alertas amarillas y rojas con los porcentajes configurados.
  También genera una alerta roja al completar tres días consecutivos con falta
  y sin excusa. Una alerta activa no se vuelve a enviar cada día; puede
  notificarse de nuevo si el estado se recupera y luego vuelve a incumplir.
- La tarea diaria envía un recordatorio un día hábil antes del vencimiento de la
  excusa y, al vencerse el plazo configurado, marca la falta como injustificada
  y notifica al aprendiz. Los días hábiles excluyen fines de semana y las fechas
  activas del calendario académico.
- Para probar la tarea sin escribir en la base:
  `scripts\tarea_diaria.bat --dry-run`. Para ejecutarla manualmente:
  `scripts\tarea_diaria.bat`. Puede programarse diariamente en el Programador
  de tareas de Windows usando ese mismo archivo; configura `PHP_CLI` si `php.exe`
  no está en PATH ni en la instalación de XAMPP. Si MySQL usa credenciales
  distintas a las predeterminadas, define las variables `DB_HOST`, `DB_NAME`,
  `DB_USER` y `DB_PASSWORD` para la cuenta de Windows de la tarea. La ejecución
  usa un bloqueo para impedir dos tareas simultáneas.
- Las notificaciones de juicios, excusas, cambios de horario y comunicados
  describen quién realizó la acción, qué ocurrió, la fecha y la sesión
  correspondiente (o indican cuando no aplica).

## 7. Flujo del estudiante

1. **Inicio** muestra el nombre, ficha, programa, trimestre, porcentaje de
   asistencia, alertas institucionales, notificaciones sin leer y clases del
   día.
2. **Horarios** permite alternar entre día y semana y consultar hora,
   competencia, ambiente, instructor, ficha, programa y trimestre.
3. **Asistencia** muestra el estado, competencia, horario, ambiente, instructor
   y observación de cada sesión. El porcentaje es
   `(asistió + llegó tarde) / (total de sesiones - permisos) × 100`.
4. Para justificar una falta, pulsa **Presentar excusa**, escribe el motivo y
   adjunta PDF o imagen JPG, PNG o WEBP de hasta 5 MB. El sistema verifica el
   plazo hábil configurado, excluyendo sábados, domingos, festivos y vacaciones
   activas. Consulta el resultado en **Mis excusas**.
5. **Notificaciones** muestra fecha, tipo, remitente y mensaje; puedes marcar
   cada aviso o todos como leídos.
6. **Mi formación** muestra competencias, resultados de aprendizaje y el último
   juicio evaluativo. Usa **Descargar historial PDF** para guardar el
   historial.
7. **Mi carné QR** abre el carné digital. **Mi perfil** permite editar
   únicamente correo, celular, contraseña y PIN; los dos últimos requieren la
   contraseña actual.

El servidor determina la identidad del aprendiz desde su sesión y filtra todos
los datos personales por esa identidad. No se aceptan IDs enviados por la
pantalla para consultar información de otro aprendiz ni para usar funciones de
instructor.

## 8. Flujo de portería

En una base existente, crea primero un respaldo y aplica una sola vez
`database/migrations/014_tablet_equipos.sql` para habilitar la opción **Tablet**.

1. Como aprendiz o instructor, abre **Mis equipos** y registra una vez cada
   portátil, tablet, teclado, mouse, maleta u otro equipo con tipo, nombre, serial,
   marca, color y foto opcional. Puedes editarlo o desactivarlo; el serial debe
   ser único. Marca los equipos que llevas y pulsa **Generar QR de ingreso
   de hoy**. El código es diario; genera uno nuevo si cambias la lista.
2. En **Ingreso de equipos**, escanea el QR diario o el QR del carné con el
   lector conectado (también puedes escribir el código en el campo). Revisa
   foto, nombre, documento, ficha o rol, estado del carné y la foto/serial de
   cada equipo. Si el
   carné está bloqueado o vencido, la confirmación queda deshabilitada.
3. Confirma el ingreso. Para salir, vuelve a escanear y compara la lista de
   equipos con lo que lleva la persona; desmarca cualquier equipo que no salga.
   El servidor registra entrada/salida, conserva la auditoría y genera una
   alerta a administración si las listas no coinciden.
4. Para una visita, registra documento, nombre, entidad y motivo; entrega el
   QR temporal generado. Registra sus equipos con el ID de visitante. Escanea
   ese QR para dejar constancia de entrada y salida.
5. Para prestar una llave, indica el ID del ambiente y escanea el carné del
   instructor. El servidor solo autoriza si tiene clase o reserva activa para
   ese ambiente. Registra la devolución con el ID del préstamo y el QR del
   instructor. El plazo está en **Configuración institucional**; la tarea diaria
   avisa de llaves vencidas a portería y administración.
6. Usa **Novedad de turno** para registrar hechos y a quién se entrega el turno.
   Usa **Objeto perdido** para registrar el objeto y su ubicación.
7. Administración abre **Panel de portería** para consultar accesos, equipos,
   llaves, visitantes, novedades y objetos; filtra por fecha, persona, ficha,
   ambiente o serial y exporta el reporte en PDF o Excel.

El servidor permite al rol de portería consultar únicamente las superficies de
operación: identidad mínima, ficha/rol, estado del carné, equipos, ambientes,
llaves, visitantes, novedades y objetos. Las rutas genéricas de asistencia,
excusas, juicios, notificaciones y datos personales no están habilitadas para
ese rol.

**Prueba rápida:** usa el portátil de prueba de Laura o Catalina, o registra un
portátil y un mouse en **Mis equipos**; genera
el QR diario y confirma la entrada en portería. Al salir, desmarca un equipo
para comprobar la alerta; presta una llave a un instructor con clase y prueba
con otro que no tenga clase ni reserva; finalmente revisa el historial y la
exportación en la cuenta administradora.

## 9. Recuperar contraseña

1. En la pantalla de acceso, abre **Olvidé mi contraseña**.
2. Escribe el correo asociado a la cuenta y envía la solicitud.
3. Si el correo está registrado y el envío SMTP está configurado, recibirás un
   enlace para actualizar la contraseña. El enlace es de un solo uso y vence en
   30 minutos.

El envío requiere una cuenta remitente de Gmail o Outlook/Hotmail con SMTP
configurado en Apache. Si no llega el mensaje, pide al administrador que revise
la configuración SMTP y que el servidor de correo permita el envío.

## 10. Actualización de la información

La página consulta la API y la base de datos del servidor. El panel y los
listados se actualizan automáticamente; también puedes pulsar **Actualizar
lista** cuando ese botón aparezca. Los cambios que guardes se reflejan en la
base de datos conectada a esa instalación.

## 11. Si algo no funciona

- **No abre o aparece error del servidor**: comprueba que Apache y MySQL estén
  iniciados y visita `http://localhost/gestion/api/health`.
- **No puedes ingresar con QR**: confirma que el PIN tenga entre 4 y 6 números.
  Tras tres intentos fallidos, espera cinco minutos. Si tu cuenta es anterior a
  la activación del PIN, entra con contraseña y configúralo.
- **El QR no se lee**: verifica que la imagen esté nítida y muestre el código
  completo; también puedes probar la cámara o una imagen distinta.
- **La cámara no aparece**: concede permiso al navegador y usa `localhost` o
  HTTPS.
- **No aparece una ficha para el estudiante**: administración debe crearla y
  mantenerla dentro de sus fechas vigentes.
- **El instructor no ve una ficha o aprendices**: administración debe asignarle
  la ficha y vincular los aprendices a ella.
- **El instructor no puede modificar ambientes o fichas**: estas tareas están
  reservadas a administración.
- **No llega el correo de recuperación**: solicita al administrador que revise
  el remitente, la contraseña de aplicación y SMTP.
- **El menú no muestra una opción**: las opciones disponibles dependen del rol
  de la cuenta; pide a administración que verifique que el rol sea correcto.

## 12. Privacidad y cuidado de los datos

- No compartas el carné QR: aunque requiere el PIN, muestra datos personales.
- Cierra sesión si utilizas un computador compartido.
- No uses credenciales de demostración ni datos ficticios al trabajar con
  información real.
- Antes de cambiar una base de datos existente, genera un respaldo. No importes
  el archivo de creación completa sobre una base que quieras conservar.

## 13. Cierre administrativo y etapa productiva

La migración `database/migrations/012_cierre_productiva_coordinador.sql` debe
aplicarse una sola vez y después de crear un respaldo completo. Administración
crea cuentas coordinador desde **Usuarios**. El coordinador ve **Reportes
generales**, el historial de cierres y las solicitudes de corrección; no puede
abrir **Configuración institucional** ni las operaciones de portería.

### Cierre de trimestre

1. En **Cierre de trimestre**, administración elige una ficha activa y pulsa
   **Cerrar trimestre**.
2. El sistema guarda el resumen de asistencia y juicios hasta la fecha, registra
   quién hizo el cierre y aumenta el trimestre de la ficha. Al alcanzar la
   duración definida en el programa, finaliza la ficha.
3. El coordinador puede consultar el historial, pero no ejecutar cierres. Los
   cierres quedan registrados en **Auditoría** y no se pueden repetir para la
   misma ficha y trimestre.

### Etapa productiva

1. Administración crea la etapa para un aprendiz inscrito en una ficha, elige
   una alternativa, registra la empresa y selecciona un instructor asignado a
   esa ficha.
2. El aprendiz y el instructor de seguimiento consultan la etapa. Pueden abrir
   su bitácora y agregar entradas; el aprendiz recibe una notificación cuando
   se registra información nueva. Administración puede finalizar la etapa.

### Coordinación y carnets

1. El instructor solicita una corrección desde la lista de asistencia dentro
   del plazo institucional y explica el motivo. La asistencia no cambia hasta
   que el coordinador o administración la aprueba.
2. En **Correcciones de asistencia**, el revisor aprueba o rechaza la solicitud
   con una respuesta. La decisión se notifica al aprendiz y queda en auditoría.
3. En **Carnets digitales**, administración puede abrir y descargar un carné,
   regenerar su QR (invalida el anterior y restablece el PIN) o desactivarlo.
   Un carné desactivado no autentica ni se acepta en portería.

**Prueba rápida:** crea un usuario coordinador desde **Usuarios** y comprueba
que puede abrir **Reportes generales** y **Correcciones de asistencia**, pero
recibe acceso denegado al intentar abrir `api/settings`. Crea una ficha activa
con programa y duración, ciérrala y verifica el aumento de trimestre y el
resumen. Registra una etapa productiva y una bitácora; verifica la notificación
del aprendiz y la auditoría. Genera otro carné de prueba, desactívalo y prueba
que su QR ya no valida; luego regénéralo y comprueba que solo el QR nuevo es
válido.

## 14. Datos de prueba

Los datos ficticios de programas, fichas, ambientes, competencias, usuarios,
horarios y calendario se cargan desde scripts separados; las cuentas de prueba
usan correos `@prueba.com`. No ejecutes el seed en una base con esos códigos,
documentos o correos ya registrados.

1. Inicia MySQL y crea un respaldo completo ejecutando
   `backups\respaldo_diario.bat`. Conserva el archivo generado antes de continuar.
2. En phpMyAdmin, selecciona `proyecto01` y ejecuta una sola vez
   `database/migrations/013_jornada_fichas.sql`.
3. Desde la carpeta del proyecto, ejecuta
   `C:\xampp\php\php.exe scripts\cargar_datos_prueba.php` (usa la ruta de PHP
   de tu instalación). El script muestra el total de registros en cada tabla
   incluida. Las contraseñas y PIN se almacenan mediante `password_hash`; cada
   cuenta de prueba tiene un carné QR.
4. Para eliminar los datos creados por el seed, ejecuta
   `C:\xampp\php\php.exe scripts\eliminar_datos_prueba.php`. La limpieza se
   limita a los IDs marcados por el script y protege las cuentas administrativas.
   Si otros registros ya dependen de los datos de prueba, la operación falla y
   revierte la transacción; revisa esas dependencias antes de volver a intentar.
