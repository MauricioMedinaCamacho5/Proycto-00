# Manual de uso de Gestión Institucional

Esta guía explica cómo abrir la página y cómo realizar las tareas habituales
según el tipo de cuenta: administración, instructor, estudiante o portería.

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

El registro público no permite crear cuentas de administrador. Administración
puede crear esas cuentas desde el módulo **Usuarios**.

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
cuenta desde el registro original: los instructores pueden editar o eliminar
asistencias dentro de ese límite y administración puede corregirlas después.
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
   para el día actual. Puedes agregar o editar el motivo/observación y corregir
   o eliminar un registro.

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

1. Inicia sesión y abre **Progreso de estudiantes**.
2. Pulsa el selector **Ficha asignada**. Se muestran las fichas activas que
   administración le asignó; el menú presenta el programa, número y, cuando
   existe, trimestre.
3. Selecciona una ficha para ver únicamente sus aprendices.
4. El resumen de la ficha muestra el porcentaje general de asistencia, el
   gráfico circular, la barra de progreso y las cantidades de asistencias,
   faltas, retrasos y permisos.
5. En la lista, revisa el porcentaje individual de cada aprendiz. Para registrar
   la asistencia del día, usa **Asistió**, **No asistió**, **Tarde** o
   **Permiso**.
6. Para añadir o corregir un motivo, usa **Agregar motivo** o **Editar /
   motivo**. Para retirar un registro, usa **Eliminar** y confirma la acción.
   Los instructores solo pueden corregir o eliminar dentro de las horas
   configuradas desde el registro original; administración puede hacerlo
   después del vencimiento.
7. Para revisar la ocupación de espacios, pulsa **Disponibilidad semanal de
   ambientes**. Usa **Volver a aprendices** para regresar a la lista.

El instructor no ve los accesos separados a **Asistencia** ni **Novedades**:
registra la asistencia desde **Progreso de estudiantes**. Los datos y las
acciones se limitan también desde el servidor a las fichas que tiene asignadas.
Administración y los instructores asignados pueden registrar excusas en
**Novedades**, con el tipo `inasistencia`, eligiendo una asistencia ausente del
aprendiz. Debe presentarse dentro de los días hábiles configurados. Se excluyen
sábados, domingos y las fechas activas de tipo festivo o vacaciones registradas
en **Calendario académico**. Los soportes se revisan desde **Excusas**.

## 6. Flujo del estudiante

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

## 7. Flujo de portería

- **Accesos**: registra ingresos y salidas de personas mediante el QR, o
  buscando la cuenta según las opciones del formulario.
- **Visitantes**: registra los datos de la visita y su motivo.
- **Inventario**: consulta y registra equipos con su serial, categoría, estado
  y ubicación.
- **Movimientos de equipos**: registra movimientos y responsables de los
  equipos.
- **Préstamos**: registra la entrega de un equipo y su devolución.
- **Permisos temporales**: registra el periodo autorizado y el motivo para una
  persona o visitante.

Los nombres de campos pueden variar según el tipo de registro. Completa los
datos solicitados y guarda; si el servidor rechaza un dato, mostrará el motivo
para que puedas corregirlo.

## 8. Recuperar contraseña

1. En la pantalla de acceso, abre **Olvidé mi contraseña**.
2. Escribe el correo asociado a la cuenta y envía la solicitud.
3. Si el correo está registrado y el envío SMTP está configurado, recibirás un
   enlace para actualizar la contraseña. El enlace es de un solo uso y vence en
   30 minutos.

El envío requiere una cuenta remitente de Gmail o Outlook/Hotmail con SMTP
configurado en Apache. Si no llega el mensaje, pide al administrador que revise
la configuración SMTP y que el servidor de correo permita el envío.

## 9. Actualización de la información

La página consulta la API y la base de datos del servidor. El panel y los
listados se actualizan automáticamente; también puedes pulsar **Actualizar
lista** cuando ese botón aparezca. Los cambios que guardes se reflejan en la
base de datos conectada a esa instalación.

## 10. Si algo no funciona

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

## 11. Privacidad y cuidado de los datos

- No compartas el carné QR: aunque requiere el PIN, muestra datos personales.
- Cierra sesión si utilizas un computador compartido.
- No uses credenciales de demostración ni datos ficticios al trabajar con
  información real.
- Antes de cambiar una base de datos existente, genera un respaldo. No importes
  el archivo de creación completa sobre una base que quieras conservar.
