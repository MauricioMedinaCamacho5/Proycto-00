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
3. Completa documento, teléfono, nombres, apellidos, correo y contraseña.
   Confirma la contraseña.
4. Si quieres, añade una foto JPG, PNG o WEBP de máximo 1 MB para el carné.
5. Pulsa **Crear cuenta** y sigue el aviso que aparece. Las solicitudes de
   instructor y portería no tendrán acceso institucional hasta ser aprobadas.

El registro público no permite crear cuentas de administrador. Administración
puede crear esas cuentas desde el módulo **Usuarios**.

## 3. Iniciar sesión y usar el carné QR

1. En la portada, selecciona **Ingresar como estudiante**, **Ingresar como
   equipo institucional** o **Ingresar como administrador**.
2. Inicia sesión escaneando el QR del carné con la cámara, o elige una imagen
   que contenga el QR.
3. Si el navegador lo solicita, permite el uso de la cámara. La cámara requiere
   `localhost` o HTTPS. También puedes elegir una imagen del QR en vez de usar
   la cámara.
4. Si una cuenta existente todavía no tiene carné, usa **Acceso temporal para
   obtener tu carné** con el correo y la contraseña de esa cuenta. Descarga el
   carné cuando aparezca.
5. Una vez emitido, guarda el carné en un lugar seguro. El QR funciona como una
   credencial de acceso: no lo compartas ni publiques. En la sesión, **Mi carné
   QR** permite abrir la información del carné.
6. Para salir, pulsa **Cerrar sesión**.

## 4. Flujo de administración

El administrador ve el resumen general y los módulos autorizados para gestionar
la institución. Los formularios se abren con **＋ Nuevo registro**; cuando el
módulo lo permite, también aparecen acciones para editar, eliminar, aprobar o
rechazar registros.

### 4.1. Aprobar solicitudes de personal

1. Abre **Solicitudes de registro**.
2. Revisa los datos y el rol solicitado.
3. Pulsa **Aprobar** para activar la solicitud o **Rechazar** si no corresponde.
4. Las solicitudes pendientes también se cuentan en el indicador del menú.

### 4.2. Crear fichas y asignarlas

1. En **Fichas y programas**, crea la ficha con su número de siete dígitos,
   programa, fecha de inicio y fecha de fin.
2. El estado se calcula con esas fechas: programada antes de empezar, activa
   durante el rango y terminada después de la fecha final.
3. En **Instructores por ficha**, asigna cada instructor a las fichas que debe
   atender.
4. En **Asignación de aprendices**, vincula las cuentas de estudiante con su
   ficha.
5. Los aprendices podrán escoger una ficha activa al registrarse. Las fichas
   asignadas son la base para limitar la información que ve cada instructor.

### 4.3. Configurar horarios y ambientes

1. Abre **Horarios** y registra la ficha, el ambiente, el instructor, el día,
   la hora de inicio y fin, y la asignatura.
2. En **Ambientes**, administra el código, nombre, ubicación, capacidad y
   estado del espacio.
3. Un instructor puede consultar ambientes, pero su gestión corresponde a
   administración.
4. La vista semanal de disponibilidad usa los horarios registrados para
   mostrar los ambientes libres, ocupados o en mantenimiento.

### 4.4. Consultar progreso y asistencia

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

### 4.5. Otros módulos de administración

- **Usuarios**: consulta y administra las cuentas y sus roles.
- **Notificaciones**: gestiona avisos institucionales.
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
7. Para revisar la ocupación de espacios, pulsa **Disponibilidad semanal de
   ambientes**. Usa **Volver a aprendices** para regresar a la lista.

El instructor no ve los accesos separados a **Asistencia** ni **Novedades**:
registra la asistencia desde **Progreso de estudiantes**. Los datos y las
acciones se limitan también desde el servidor a las fichas que tiene asignadas.

## 6. Flujo del estudiante

- **Horarios**: consulta las clases organizadas por día y hora, con asignatura,
  ambiente, instructor y ficha.
- **Asistencia**: consulta su resumen personal y el historial de estados,
  fechas, horarios y observaciones. El estudiante no puede modificar registros
  de asistencia.
- **Notificaciones**: lee sus avisos personales y el motivo u observación que
  haya registrado el instructor.
- **Mi carné QR**: abre la información del carné disponible para la cuenta.

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

- No compartas el carné QR: quien lo tenga puede intentar acceder a la cuenta.
- Cierra sesión si utilizas un computador compartido.
- No uses credenciales de demostración ni datos ficticios al trabajar con
  información real.
- Antes de cambiar una base de datos existente, genera un respaldo. No importes
  el archivo de creación completa sobre una base que quieras conservar.
