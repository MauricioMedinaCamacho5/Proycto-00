const API = 'api';
const modules = {
  'registration-requests': {label:'Solicitudes de registro', icon:'✉', roles:['administrador'], fields:[]},
  users: {label:'Usuarios', icon:'♙', roles:['administrador'], fields:[['rol_id','Rol (1 admin, 2 instructor, 3 estudiante, 4 portero)','number'],['documento','Documento'],['nombres','Nombres'],['apellidos','Apellidos'],['correo','Correo','email'],['password','Contraseña (mínimo 8 caracteres)','password'],['telefono','Teléfono']]},
  environments: {label:'Ambientes', icon:'⌂', roles:['administrador'], fields:[['codigo','Código'],['nombre','Nombre'],['ubicacion','Ubicación'],['capacidad','Capacidad','number'],['estado','Estado (disponible/ocupado/mantenimiento)'],['descripcion','Descripción']]},
  fichas: {label:'Fichas', icon:'▤', roles:['administrador','instructor'], fields:[['codigo','Código'],['nombre','Nombre'],['fecha_inicio','Fecha inicio','date'],['fecha_fin','Fecha fin','date'],['estado','Estado (activa/finalizada/suspendida)']]},
  schedules: {label:'Horarios', icon:'◷', roles:['administrador','instructor','estudiante'], fields:[['ficha_id','ID ficha','number'],['ambiente_id','ID ambiente','number'],['instructor_id','ID instructor','number'],['dia_semana','Día (1=lun, 7=dom)','number'],['hora_inicio','Hora inicio','time'],['hora_fin','Hora fin','time'],['asignatura','Asignatura']]},
  enrollments: {label:'Inscripciones a fichas', icon:'♧', roles:['administrador'], fields:[['ficha_id','ID ficha','number'],['usuario_id','ID estudiante','number']]},
  cards: {label:'Carnets digitales', icon:'▣', roles:['administrador','estudiante'], fields:[['usuario_id','ID usuario','number'],['vence_en','Fecha de vencimiento','date']]},
  reservations: {label:'Reservas', icon:'▦', roles:['administrador'], fields:[['ambiente_id','ID ambiente','number'],['usuario_id','ID responsable','number'],['inicio','Inicio','datetime-local'],['fin','Fin','datetime-local'],['motivo','Motivo']]},
  attendance: {label:'Asistencia', icon:'✓', roles:['administrador','instructor','estudiante'], fields:[['usuario_id','ID estudiante','number'],['ficha_id','ID ficha','number'],['fecha','Fecha','date'],['estado','Estado (presente/ausente/tarde/permiso)'],['observacion','Observación']]},
  novelties: {label:'Novedades', icon:'⚑', roles:['administrador','instructor'], fields:[['usuario_id','ID estudiante','number'],['tipo','Tipo (retraso/permiso/inasistencia/otra)'],['descripcion','Descripción'],['fecha','Fecha','date']]},
  accesses: {label:'Accesos', icon:'⇄', roles:['administrador','portero'], fields:[['qr','Código QR del carnet'],['usuario_id','ID usuario (opcional)','number'],['visitante_id','ID visitante (opcional)','number'],['serial','Serial de equipo (opcional)'],['tipo','Tipo (ingreso/salida)'],['observacion','Observación']]},
  visitors: {label:'Visitantes', icon:'♧', roles:['administrador','portero'], fields:[['documento','Documento'],['nombres','Nombre completo'],['entidad','Entidad'],['motivo','Motivo'],['anfitrion_id','ID anfitrión','number']]},
  equipment: {label:'Inventario', icon:'▣', roles:['administrador','portero'], fields:[['serial','Serial'],['nombre','Nombre del equipo'],['categoria','Categoría'],['estado','Estado (disponible/asignado/prestado/mantenimiento/baja)'],['ubicacion','Ubicación'],['autorizado','Autorizado (1/0)','number']]},
  'equipment-movements': {label:'Movimientos de equipos', icon:'↔', roles:['administrador','portero'], fields:[['equipo_id','ID equipo','number'],['usuario_id','ID responsable','number'],['tipo','Tipo (ingreso/salida/traslado/asignacion/devolucion)'],['ubicacion_origen','Ubicación origen'],['ubicacion_destino','Ubicación destino'],['observacion','Observación']]},
  loans: {label:'Préstamos', icon:'⇢', roles:['administrador','portero'], fields:[['equipo_id','ID equipo','number'],['usuario_id','ID responsable','number'],['vence_en','Fecha de devolución','datetime-local']]},
  'temporary-permits': {label:'Permisos temporales', icon:'◷', roles:['administrador','portero'], fields:[['usuario_id','ID usuario','number'],['visitante_id','ID visitante','number'],['desde','Válido desde','datetime-local'],['hasta','Válido hasta','datetime-local'],['motivo','Motivo']]},
  notifications: {label:'Notificaciones', icon:'◉', roles:['administrador','instructor','estudiante','portero'], fields:[['usuario_id','ID usuario','number'],['titulo','Título'],['mensaje','Mensaje']]},
  blocks: {label:'Bloqueos de usuarios', icon:'⊘', roles:['administrador'], fields:[['usuario_id','ID usuario','number'],['motivo','Motivo del bloqueo']]}
};
const icons = ['◉','⌂','✓','⇄','▣','▦'];
let currentUser = null, activeModule = null, chart = null, editId = null;
let registrationControls = null, registrationStarting = false, registrationScanCompleted = false, tableRefreshInProgress = false;
let loginScannerControls = null, loginScannerStarting = false, loginScanCompleted = false;
let loginMode = 'standard';
const $ = (selector) => document.querySelector(selector);

function refreshActiveTable() {
  if (tableRefreshInProgress || !activeModule || $('#record-dialog').open || document.hidden) return;
  tableRefreshInProgress = true;
  loadTable().catch(showError).finally(() => { tableRefreshInProgress = false; });
}

async function api(path, options = {}) {
  if (location.protocol === 'file:') {
    throw new Error('Abre el sitio desde XAMPP en http://localhost/gestion/; no abras index.html directamente desde el explorador.');
  }

  let response;
  try {
    response = await fetch(`${API}/${path}`, {
      credentials: 'include',
      headers: {'Content-Type':'application/json', ...(options.headers || {})},
      ...options
    });
  } catch {
    throw new Error('No se pudo conectar con la API. Inicia Apache y MySQL en XAMPP y abre http://localhost/gestion/.');
  }

  if (response.status === 204) return null;
  const responseText = await response.text();
  let result;
  try {
    result = responseText ? JSON.parse(responseText) : null;
  } catch {
    throw new Error(`El servidor devolvió una respuesta que no es JSON (HTTP ${response.status}). Comprueba que Apache/PHP funcione y que la URL empiece por http://localhost/gestion/.`);
  }
  if (!result || typeof result !== 'object') {
    throw new Error(`El servidor respondió vacío (HTTP ${response.status}). Comprueba Apache/PHP y que la base de datos esté importada en phpMyAdmin.`);
  }
  if (!response.ok) {
    const error = new Error(result.error || `Error HTTP ${response.status}`);
    error.status = response.status;
    throw error;
  }
  return result.data;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
}

async function startApp() {
  $('#login-message').textContent = '';
  try {
    currentUser = await api('auth/me');
  } catch (error) {
    $('#landing-screen').classList.remove('hidden');
    $('#login-screen').classList.add('hidden');
    $('#app-shell').classList.add('hidden');
    if (error.status !== 401) $('#login-message').textContent = error.message;
    return;
  }
  stopLoginScanner();
  $('#landing-screen').classList.add('hidden');
  $('#login-screen').classList.add('hidden');
  $('#app-shell').classList.remove('hidden');
  $('#user-summary').innerHTML = `<div class="user-name">${escapeHtml(currentUser.nombres)} ${escapeHtml(currentUser.apellidos)}</div><div class="user-role">${escapeHtml(currentUser.rol)}</div>`;
  renderNavigation();
  try {
    if (currentUser.rol === 'administrador') await showDashboard();
    else if (currentUser.rol === 'estudiante') await showModule('schedules');
    else if (currentUser.rol === 'instructor') await showModule('fichas');
    else await showModule('accesses');
  } catch (error) { showError(error); }
}

function renderNavigation() {
  const entries = [];
  if (currentUser.rol === 'administrador') entries.push(['dashboard','Resumen','◈']);
  Object.entries(modules).forEach(([key, item]) => {
    if (item.roles.includes(currentUser.rol)) entries.push([key,item.label,item.icon]);
  });
  $('#navigation').innerHTML = entries.map(([key,label,icon]) => `<a href="#${key}" class="nav-link" data-view="${key}"><span class="nav-icon">${icon}</span><span class="nav-label">${escapeHtml(label)}</span>${key === 'registration-requests' ? '<span class="nav-badge hidden" id="registration-request-count"></span>' : ''}</a>`).join('');
  $('#navigation').querySelectorAll('[data-view]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    const key = link.dataset.view;
    key === 'dashboard' ? showDashboard() : showModule(key);
  }));
  if (currentUser.rol === 'administrador') refreshRegistrationRequestCount().catch(showError);
}

async function refreshRegistrationRequestCount() {
  if (currentUser?.rol !== 'administrador') return;
  const count = await api('registration-requests/count');
  const badge = $('#registration-request-count');
  if (!badge) return;
  badge.textContent = Number(count) > 99 ? '99+' : String(count);
  badge.classList.toggle('hidden', Number(count) === 0);
}

const statConfig = [
  ['usuarios','Usuarios activos','personas registradas',icons[0]],
  ['ambientes','Ambientes','espacios institucionales',icons[1]],
  ['asistencias_hoy','Asistencias hoy','registros de asistencia',icons[2]],
  ['accesos_hoy','Accesos hoy','movimientos en portería',icons[3]],
  ['equipos','Equipos inventariados','activos tecnológicos',icons[4]],
  ['reservas_activas','Reservas activas','en curso ahora',icons[5]],
  ['ocupacion_pct','Ocupación de ambientes','porcentaje de ambientes ocupados','%']
];

async function showDashboard() {
  activeModule = null;
  $('.view#dashboard-view').classList.remove('hidden');
  $('#resource-view').classList.add('hidden');
  $('#page-title').textContent = 'Resumen general';
  $('#page-kicker').textContent = 'PANEL INSTITUCIONAL';
  setActiveNav('dashboard');
  await refreshDashboard();
  if (!window.dashboardTimer) window.dashboardTimer = setInterval(() => {
    if (currentUser.rol === 'administrador') refreshRegistrationRequestCount().catch(showError);
    if (activeModule) refreshActiveTable();
    else refreshDashboard().catch(showError);
  }, 3000);
}

async function refreshDashboard() {
  const stats = await api('dashboard');
  $('#dashboard-message').textContent = '';
  $('#stats-grid').innerHTML = statConfig.map(([key,label,caption,icon]) => `<article class="stat-card"><div class="stat-label">${label}</div><div class="stat-value">${Number(stats[key] || 0).toLocaleString('es')}</div><div class="stat-caption">${caption}</div><div class="stat-icon">${icon}</div></article>`).join('');
  const canViewAlerts = ['administrador','instructor'].includes(currentUser.rol);
  $('.alerts-panel').classList.toggle('hidden', !canViewAlerts);
  if (canViewAlerts) {
    const alerts = await api('attendance/alerts');
    $('#attendance-alerts').innerHTML = alerts.length
      ? alerts.map(alert => `<div class="alert-row"><strong>${escapeHtml(alert.estudiante)}</strong><span>Documento ${escapeHtml(alert.documento)} · Ausencias: ${alert.fechas.map(escapeHtml).join(', ')}</span></div>`).join('')
      : '<p class="empty-row">No hay alertas de inasistencia consecutiva.</p>';
  }
  if (window.Chart) {
    const ctx = $('#activity-chart');
    const values = [stats.asistencias_hoy || 0, stats.accesos_hoy || 0, stats.reservas_activas || 0];
    if (chart) {
      chart.data.datasets[0].data = values;
      chart.update();
    } else {
      chart = new Chart(ctx, {type:'bar',data:{labels:['Asistencia','Accesos','Reservas'],datasets:[{data:values,backgroundColor:['#087c45','#16894f','#75ad70'],borderRadius:7,maxBarThickness:48}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#e7eee4'}},x:{grid:{display:false}}}}});
    }
  }
}

function setActiveNav(key) {
  $('#navigation').querySelectorAll('.nav-link').forEach(link => link.classList.toggle('active', link.dataset.view === key));
}

async function showModule(key) {
  activeModule = key;
  $('#dashboard-view').classList.add('hidden');
  $('#resource-view').classList.remove('hidden');
  $('#page-title').textContent = modules[key].label;
  $('#page-kicker').textContent = 'GESTIÓN DE MÓDULOS';
  $('#table-title').textContent = modules[key].label;
  $('#table-description').textContent = key === 'registration-requests'
    ? 'Revisa las solicitudes de instructores y portería antes de activar sus cuentas.'
    : 'Consulta y administra los registros del sistema.';
  $('#add-record').classList.toggle('hidden', key === 'registration-requests' || currentUser.rol === 'estudiante' || key === 'notifications' && currentUser.rol === 'estudiante' || key === 'fichas' && currentUser.rol === 'instructor');
  $('#export-report').classList.toggle('hidden', key === 'registration-requests' || currentUser.rol === 'estudiante' && !['schedules','attendance','notifications'].includes(key));
  $('#report-format').classList.toggle('hidden', key === 'registration-requests');
  setActiveNav(key);
  await loadTable();
}

async function loadTable() {
  const module = activeModule;
  const data = await api(module);
  if (activeModule !== module) return;
  const rows = Array.isArray(data) ? data : [];
  if (!rows.length) {
    $('#table-head').innerHTML = '';
    const emptyMessage = module === 'registration-requests'
      ? 'No hay solicitudes pendientes de revisión.'
      : 'No hay registros disponibles.';
    $('#table-body').innerHTML = `<tr><td class="empty-row">${emptyMessage}</td></tr>`;
    return;
  }
  const columns = Object.keys(rows[0]);
  const allowDelete = currentUser.rol === 'administrador' && !['blocks','registration-requests'].includes(activeModule);
  const allowEdit = modules[activeModule].roles.includes(currentUser.rol) && currentUser.rol !== 'estudiante' && !(activeModule === 'fichas' && currentUser.rol === 'instructor');
  const canManage = currentUser.rol === 'administrador';
  const hasActions = allowDelete || allowEdit || activeModule === 'registration-requests' && canManage;
  $('#table-head').innerHTML = `<tr>${columns.map(col => `<th>${escapeHtml(col.replaceAll('_',' '))}</th>`).join('')}${hasActions ? '<th>Acciones</th>' : ''}</tr>`;
  $('#table-body').innerHTML = rows.map(row => {
    const actions = [];
    if (allowEdit) actions.push(`<button class="row-action" data-edit="${Number(row.id)}">Editar</button>`);
    if (activeModule === 'registration-requests' && canManage) {
      actions.push(`<button class="row-action request-approve" data-approve="${Number(row.id)}">Aprobar</button>`);
      actions.push(`<button class="row-action request-reject" data-reject="${Number(row.id)}">Rechazar</button>`);
    }
    if (activeModule === 'blocks' && canManage && !row.desbloqueado_en) actions.push(`<button class="row-action" data-unblock="${Number(row.id)}">Desbloquear</button>`);
    if (activeModule === 'loans' && canManage && row.estado === 'activo') actions.push(`<button class="row-action" data-return="${Number(row.id)}">Marcar devolución</button>`);
    if (allowDelete) actions.push(`<button class="row-action" data-delete="${Number(row.id)}">Eliminar</button>`);
    return `<tr>${columns.map(col => `<td title="${escapeHtml(row[col])}">${escapeHtml(row[col])}</td>`).join('')}${hasActions ? `<td>${actions.join(' · ')}</td>` : ''}</tr>`;
  }).join('');
  $('#table-body').querySelectorAll('[data-edit]').forEach(button => {
    const row = rows.find(record => Number(record.id) === Number(button.dataset.edit));
    button.addEventListener('click', () => openForm(row));
  });
  $('#table-body').querySelectorAll('[data-delete]').forEach(button => button.addEventListener('click', () => deleteRecord(Number(button.dataset.delete))));
  $('#table-body').querySelectorAll('[data-unblock]').forEach(button => button.addEventListener('click', () => unblockUser(Number(button.dataset.unblock))));
  $('#table-body').querySelectorAll('[data-return]').forEach(button => button.addEventListener('click', () => returnLoan(Number(button.dataset.return))));
  $('#table-body').querySelectorAll('[data-approve]').forEach(button => button.addEventListener('click', () => reviewRegistration(Number(button.dataset.approve), 'approve')));
  $('#table-body').querySelectorAll('[data-reject]').forEach(button => button.addEventListener('click', () => reviewRegistration(Number(button.dataset.reject), 'reject')));
}

function fieldInput([name,label,type='text'], value='') {
  const inputType = ['number','email','date','time','datetime-local','password'].includes(type) ? type : 'text';
  const optionalIdentity = ['accesses','temporary-permits'].includes(activeModule) && ['usuario_id','visitante_id'].includes(name);
  const required = ['password','documento','nombres','apellidos','correo','rol_id','codigo','nombre','ubicacion','capacidad','ficha_id','ambiente_id','instructor_id','hora_inicio','hora_fin','asignatura','inicio','fin','motivo','estado','usuario_id','fecha','tipo','serial','categoria','equipo_id','vence_en','dia_semana','desde','hasta'].includes(name) && !(optionalIdentity || activeModule === 'accesses' && name === 'serial');
  return `<label>${escapeHtml(label)}<input name="${escapeHtml(name)}" type="${inputType}" value="${inputType === 'password' ? '' : escapeHtml(value)}" ${name === 'password' ? 'minlength="8"' : ''} ${required ? 'required' : ''}></label>`;
}

function openForm(record = null) {
  editId = record ? Number(record.id) : null;
  $('#dialog-title').textContent = `${editId ? 'Editar' : 'Nuevo registro'} · ${modules[activeModule].label}`;
  $('#dialog-message').textContent = '';
  $('#record-fields').innerHTML = modules[activeModule].fields.map(field => {
    const [name] = field;
    let value = record?.[name] ?? '';
    if (field[2] === 'datetime-local' && value) value = String(value).replace(' ', 'T').slice(0,16);
    const input = fieldInput(field, value);
    return editId && name === 'password' ? input.replace(' required', '') : input;
  }).join('');
  $('#record-dialog').showModal();
}

function reviewRegistration(id, decision) {
  const action = decision === 'approve' ? 'aprobar' : 'rechazar y eliminar';
  if (!window.confirm(`¿Seguro que desea ${action} esta solicitud?`)) return;
  api(`registration-requests/${id}/${decision}`, {method:'POST',body:'{}'})
    .then(async result => {
      await loadTable();
      await refreshRegistrationRequestCount();
      $('#table-message').textContent = result.message;
    })
    .catch(showError);
}

async function deleteRecord(id) {
  if (!window.confirm('¿Eliminar este registro? Esta acción puede estar limitada por relaciones existentes.')) return;
  try {
    await api(`${activeModule}/${id}`, {method:'DELETE'});
    await loadTable();
  } catch (error) { showError(error); }
}

async function unblockUser(id) {
  try {
    await api(`blocks/${id}`, {method:'PATCH',body:JSON.stringify({desbloqueado_en:new Date().toISOString().slice(0,19).replace('T',' ')})});
    await loadTable();
  } catch (error) { showError(error); }
}

async function returnLoan(id) {
  try {
    await api(`loans/${id}`, {method:'PATCH',body:JSON.stringify({estado:'devuelto',devuelto_en:new Date().toISOString().slice(0,19).replace('T',' ')})});
    await loadTable();
  } catch (error) { showError(error); }
}

function showError(error) {
  const target = activeModule ? $('#table-message') : $('#dashboard-message');
  target.textContent = error.message || String(error);
}

$('#login-form').addEventListener('submit', async event => {
  event.preventDefault();
  $('#login-message').textContent = '';
  const form = new FormData(event.currentTarget);
  try {
    if (loginMode === 'staff') {
      const documentNumber = $('#login-staff-document').value;
      if (!documentNumber) {
        $('#login-message').textContent = 'Escanea primero el código lineal de tu cédula.';
        return;
      }
      await api('auth/staff-barcode-login',{method:'POST',body:JSON.stringify({
        documento:documentNumber,
        correo:form.get('correo'),
        password:form.get('password')
      })});
    } else {
      await api('auth/login',{method:'POST',body:JSON.stringify({correo:form.get('correo'),password:form.get('password')})});
    }
    await startApp();
  } catch (error) {
    $('#login-message').textContent = error.message;
  }
});

$('#landing-admin-login').addEventListener('click', showLogin);
$('#landing-admin-open').addEventListener('click', showLogin);
$('#landing-student-login').addEventListener('click', showLogin);
$('#login-home').addEventListener('click', showLanding);
$('#landing-staff-login').addEventListener('click', () => showLogin('staff'));
$('#switch-login-mode').addEventListener('click', () => showLogin('standard'));
$('#landing-student-register').addEventListener('click', () => openRegistration('estudiante'));
$('#landing-register').addEventListener('click', () => openRegistration('estudiante'));
$('#landing-staff-register').addEventListener('click', () => openRegistration('instructor'));

function showLanding() {
  stopLoginScanner();
  $('#login-screen').classList.add('hidden');
  $('#landing-screen').classList.remove('hidden');
}

function showLogin(mode = 'standard') {
  stopLoginScanner();
  loginMode = mode;
  $('#login-form').reset();
  $('#login-staff-document').value = '';
  $('#login-message').textContent = '';
  $('#login-scan-title').textContent = mode === 'staff' ? 'Escaneo para personal y administración' : 'Acceso para estudiantes';
  $('#login-scan-message').textContent = mode === 'staff'
    ? 'Escanea la cédula con la cámara o elige una foto. Después confirma tu correo y contraseña.'
    : 'Escanea o selecciona una foto del código lineal de tu documento. Cualquier persona con una copia podrá entrar a esta cuenta.';
  $('#login-mode-warning').classList.toggle('hidden', mode !== 'staff');
  $('#switch-login-mode').classList.toggle('hidden', mode !== 'staff');
  $('#login-screen h1').textContent = mode === 'staff' ? 'Equipo institucional' : 'Acceso a tu cuenta';
  $('#landing-screen').classList.add('hidden');
  $('#login-screen').classList.remove('hidden');
}

function showLoginWithScannedStaffDocument(value) {
  const documentNumber = value.trim();
  if (!documentNumber) return;
  loginScanCompleted = true;
  stopLoginScanner();
  $('#login-staff-document').value = documentNumber;
  $('#login-scan-message').textContent = 'Cédula leída. Completa tu correo y contraseña para continuar.';
  $('#login-form input[name="correo"]').focus();
}

function showLoginWithBarcode(value, controls = loginScannerControls) {
  if (loginMode === 'staff') {
    stopLoginScanner(controls);
    showLoginWithScannedStaffDocument(value);
    return;
  }
  loginWithBarcode(value, controls);
}

function showLoginWithBarcodePhoto(value) {
  if (loginMode === 'staff') {
    showLoginWithScannedStaffDocument(value);
    return;
  }
  loginWithBarcode(value);
}

function openRegistration(role = 'estudiante') {
  $('#registration-form').reset();
  $('#registration-role').value = role;
  updateRegistrationRole();
  $('#registration-message').textContent = '';
  $('#landing-message').textContent = '';
  $('#scanner-message').textContent = 'Usa la cámara o una foto para leer el número del documento impreso en el código de barras.';
  $('#registration-dialog').showModal();
}

function updateRegistrationRole() {
  const student = $('#registration-role').value === 'estudiante';
  if (!student) stopRegistrationScanner();
  $('#registration-title').textContent = student ? 'Registrar como estudiante' : 'Solicitar cuenta de personal';
  $('#registration-intro').textContent = student
    ? 'Completa tus datos y escanea el código lineal de tu carné o elige una foto. El sistema genera tu QR digital y activa tu cuenta de estudiante.'
    : 'Completa tus datos y escanea el código lineal de tu cédula o elige una foto. Tu cuenta quedará pendiente hasta que un administrador la apruebe en la pestaña Solicitudes de registro del panel de administración.';
  $('#submit-registration').textContent = student ? 'Crear cuenta' : 'Enviar solicitud';
}

$('#registration-role').addEventListener('change', updateRegistrationRole);

function stopLoginScanner(controls = loginScannerControls) {
  controls?.stop();
  loginScannerControls = null;
  $('#login-video').pause();
  $('#login-video').srcObject = null;
  $('#login-scanner').classList.add('hidden');
}

async function loginWithBarcode(value, controls = loginScannerControls) {
  const documentNumber = value.trim();
  if (!documentNumber) return;
  loginScanCompleted = true;
  stopLoginScanner(controls);
  $('#login-scan-message').textContent = 'Verificando el código...';
  try {
    await api('auth/barcode-login', {method:'POST',body:JSON.stringify({documento:documentNumber})});
    await startApp();
  } catch (error) {
    $('#login-scan-message').textContent = error.message;
  }
}

$('#scan-login-barcode').addEventListener('click', async () => {
  if (loginScannerStarting || loginScannerControls) return;
  $('#login-scan-message').textContent = 'Preparando cámara para escanear tu carné...';
  if (!navigator.mediaDevices?.getUserMedia) {
    $('#login-scan-message').textContent = 'La cámara requiere abrir el sitio en localhost o HTTPS. También puedes elegir una foto.';
    return;
  }
  if (!window.ZXingBrowser?.BrowserMultiFormatReader) {
    $('#login-scan-message').textContent = 'No se pudo cargar el lector. Comprueba tu conexión o elige una foto.';
    return;
  }
  loginScannerStarting = true;
  loginScanCompleted = false;
  try {
    const controls = await createBarcodeReader().decodeFromConstraints(
      {video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false},
      $('#login-video'),
      (result, error, scanControls) => {
        if (!result || loginScanCompleted) return;
        showLoginWithBarcode(result.getText(), scanControls);
      }
    );
    if (loginScanCompleted) {
      controls.stop();
      return;
    }
    loginScannerControls = controls;
    $('#login-scanner').classList.remove('hidden');
    $('#login-scan-message').textContent = 'Centra el código de barras completo en la cámara, con buena luz y sin reflejos.';
  } catch (error) {
    stopLoginScanner();
    $('#login-scan-message').textContent = `No se pudo escanear: ${error.message || 'comprueba los permisos de cámara o elige una foto.'}`;
  } finally {
    loginScannerStarting = false;
  }
});

$('#choose-login-photo').addEventListener('click', () => $('#login-photo').click());

$('#login-photo').addEventListener('change', async event => {
  const input = event.currentTarget;
  const file = input.files?.[0];
  if (!file) return;
  if (!window.ZXingBrowser?.BrowserMultiFormatReader) {
    $('#login-scan-message').textContent = 'No se pudo cargar el lector. Comprueba tu conexión o escribe correo y contraseña.';
    input.value = '';
    return;
  }

  loginScanCompleted = false;
  $('#login-scan-message').textContent = 'Leyendo el código de barras de la foto...';
  let imageUrl;
  try {
    imageUrl = URL.createObjectURL(file);
    const image = new Image();
    image.src = imageUrl;
    await image.decode();
    const result = await createBarcodeReader().decodeFromImageElement(image);
    showLoginWithBarcodePhoto(result.getText());
  } catch (error) {
    const message = error?.name === 'NotFoundException'
      ? 'No se encontró un código legible. Elige una foto nítida con el código completo.'
      : `No se pudo leer la foto: ${error.message || 'prueba con otra imagen.'}`;
    $('#login-scan-message').textContent = message;
  } finally {
    if (imageUrl) URL.revokeObjectURL(imageUrl);
    input.value = '';
  }
});

function stopRegistrationScanner(controls = registrationControls) {
  controls?.stop();
  registrationControls = null;
  $('#registration-video').pause();
  $('#registration-video').srcObject = null;
  $('#registration-scanner').classList.add('hidden');
}

function createBarcodeReader() {
  const reader = new window.ZXingBrowser.BrowserMultiFormatReader();
  reader.possibleFormats = [
    window.ZXingBrowser.BarcodeFormat.CODE_128,
    window.ZXingBrowser.BarcodeFormat.CODE_39,
    window.ZXingBrowser.BarcodeFormat.CODE_93,
    window.ZXingBrowser.BarcodeFormat.CODABAR,
    window.ZXingBrowser.BarcodeFormat.EAN_8,
    window.ZXingBrowser.BarcodeFormat.EAN_13,
    window.ZXingBrowser.BarcodeFormat.ITF,
    window.ZXingBrowser.BarcodeFormat.UPC_A,
    window.ZXingBrowser.BarcodeFormat.UPC_E
  ];
  return reader;
}

function applyRegistrationBarcode(value, controls = registrationControls) {
  const scannedValue = value.trim();
  if (!scannedValue) return;
  registrationScanCompleted = true;
  const student = $('#registration-role').value === 'estudiante';
  if (scannedValue.length > 30) {
    $('#scanner-message').textContent = 'El documento leído supera los 30 caracteres permitidos. Escribe el número manualmente.';
    stopRegistrationScanner(controls);
    return;
  }
  $('#registration-document').value = scannedValue;
  $('#scanner-message').textContent = student
    ? 'Documento leído. Comprueba que el número sea correcto y crea tu cuenta.'
    : 'Documento leído. Comprueba el número y envía tu solicitud para que un administrador la revise.';
  stopRegistrationScanner(controls);
}

$('#close-registration').addEventListener('click', () => $('#registration-dialog').close());
$('#cancel-registration').addEventListener('click', () => $('#registration-dialog').close());
$('#registration-dialog').addEventListener('close', () => stopRegistrationScanner());

$('#choose-registration-photo').addEventListener('click', () => $('#registration-photo').click());

$('#registration-photo').addEventListener('change', async event => {
  const input = event.currentTarget;
  const file = input.files?.[0];
  if (!file) return;
  if (!window.ZXingBrowser?.BrowserMultiFormatReader) {
    $('#scanner-message').textContent = 'No se pudo cargar el lector. Comprueba tu conexión o escribe el documento manualmente.';
    input.value = '';
    return;
  }

  stopRegistrationScanner();
  $('#scanner-message').textContent = 'Leyendo el código de barras de la foto...';
  let imageUrl;
  try {
    imageUrl = URL.createObjectURL(file);
    const image = new Image();
    image.src = imageUrl;
    await image.decode();
    const result = await createBarcodeReader().decodeFromImageElement(image);
    applyRegistrationBarcode(result.getText());
  } catch (error) {
    const message = error?.name === 'NotFoundException'
      ? 'No se encontró un código legible en la foto. Elige una imagen nítida, bien iluminada y con el código completo.'
      : `No se pudo leer la foto: ${error.message || 'prueba con otra imagen o escribe el documento manualmente.'}`;
    $('#scanner-message').textContent = message;
  } finally {
    if (imageUrl) URL.revokeObjectURL(imageUrl);
    input.value = '';
  }
});

$('#scan-registration-qr').addEventListener('click', async () => {
  if (registrationStarting || registrationControls) return;
  $('#scanner-message').textContent = 'Preparando cámara para leer el código de barras...';
  if (!navigator.mediaDevices?.getUserMedia) {
    $('#scanner-message').textContent = 'La cámara requiere abrir el sitio en localhost o HTTPS. También puedes escribir el código manualmente.';
    return;
  }
  if (!window.ZXingBrowser?.BrowserMultiFormatReader) {
    $('#scanner-message').textContent = 'No se pudo cargar el lector. Comprueba tu conexión o escribe el código del carnet manualmente.';
    return;
  }
  registrationStarting = true;
  try {
    const reader = createBarcodeReader();
    registrationScanCompleted = false;
    const controls = await reader.decodeFromConstraints(
      {video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false},
      $('#registration-video'),
      (result, error, controls) => {
        if (!result || !$('#registration-dialog').open) return;
        applyRegistrationBarcode(result.getText(), controls);
      }
    );
    if (!$('#registration-dialog').open || registrationScanCompleted) {
      controls.stop();
      return;
    }
    registrationControls = controls;
    $('#registration-scanner').classList.remove('hidden');
    $('#scanner-message').textContent = 'Centra el código de barras completo en la cámara, con buena luz y sin reflejos.';
  } catch (error) {
    stopRegistrationScanner();
    $('#scanner-message').textContent = `No se pudo escanear: ${error.message || 'comprueba los permisos de cámara o escribe el código manualmente.'}`;
  } finally {
    registrationStarting = false;
  }
});

$('#registration-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const values = new FormData(form);
  $('#registration-message').textContent = '';
  if (values.get('password') !== values.get('confirm_password')) {
    $('#registration-message').textContent = 'Las contraseñas no coinciden.';
    return;
  }
  const submit = $('#submit-registration');
  submit.disabled = true;
  try {
    const result = await api('auth/register', {method:'POST',body:JSON.stringify({
      rol:values.get('rol'),
      documento:values.get('documento'),
      nombres:values.get('nombres'),
      apellidos:values.get('apellidos'),
      correo:values.get('correo'),
      password:values.get('password'),
      telefono:values.get('telefono')
    })});
    stopRegistrationScanner();
    $('#registration-dialog').close();
    form.reset();
    if (result.pending_approval) {
      $('#landing-screen').classList.remove('hidden');
      $('#login-screen').classList.add('hidden');
      $('#landing-message').textContent = result.message;
    } else {
      await startApp();
    }
  } catch (error) {
    $('#registration-message').textContent = error.message;
  } finally {
    submit.disabled = false;
  }
});

async function logout() {
  const buttons = [$('#logout-button'), $('#logout-top-button')].filter(Boolean);
  buttons.forEach(button => { button.disabled = true; });
  try {
    await api('auth/logout',{method:'POST',body:'{}'});
    if (window.dashboardTimer) {
      clearInterval(window.dashboardTimer);
      window.dashboardTimer = null;
    }
    currentUser = null;
    activeModule = null;
    stopLoginScanner();
    $('#app-shell').classList.add('hidden');
    $('#login-screen').classList.add('hidden');
    $('#landing-screen').classList.remove('hidden');
    $('#login-form').reset();
    $('#landing-message').textContent = 'Has cerrado sesión correctamente.';
  } catch (error) {
    showError(error);
  } finally {
    buttons.forEach(button => { button.disabled = false; });
  }
}

$('#logout-button').addEventListener('click', logout);
$('#logout-top-button').addEventListener('click', logout);

$('#forgot-password').addEventListener('click', () => {
  $('#recovery-message').textContent = '';
  $('#recovery-dialog').showModal();
});

$('#recovery-form').addEventListener('submit', async event => {
  if (event.submitter?.value === 'cancel') return;
  event.preventDefault();
  const correo = new FormData(event.currentTarget).get('correo');
  try {
    const result = await api('auth/recovery',{method:'POST',body:JSON.stringify({correo})});
    if (result.local_reset_url) {
      $('#recovery-message').innerHTML = `<span class="success-message">Ya puedes continuar con la recuperación de tu contraseña.</span><a class="button primary recovery-action" href="${escapeHtml(result.local_reset_url)}">Ir a corregir la contraseña</a><small class="recovery-expiry">${escapeHtml(result.message)}</small>`;
    } else {
      $('#recovery-dialog').close();
      $('#login-message').textContent = result.message || 'Si el correo existe, recibirás instrucciones para recuperar tu cuenta.';
    }
  } catch (error) {
    $('#recovery-message').textContent = error.message;
  }
});

$('#add-record').addEventListener('click', openForm);
$('#export-report').addEventListener('click', async () => {
  try {
    const format = $('#report-format').value;
    const response = await fetch(`${API}/reports/${activeModule}?format=${encodeURIComponent(format)}`, {credentials:'include'});
    if (!response.ok) {
      const result = await response.json();
      throw new Error(result.error || 'No se pudo exportar el reporte.');
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${activeModule}.${format}`;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) { showError(error); }
});
$('#record-form').addEventListener('submit', async event => {
  if (event.submitter?.value === 'cancel') return;
  event.preventDefault();
  const formData = new FormData(event.currentTarget);
  const payload = Object.fromEntries([...formData.entries()].filter(([,value]) => value !== '').map(([key,value]) => [key, ['rol_id','capacidad','ficha_id','ambiente_id','instructor_id','dia_semana','usuario_id','visitante_id','equipo_id','autorizado'].includes(key) ? Number(value) : value]));
  try {
    await api(editId ? `${activeModule}/${editId}` : activeModule,{method:editId ? 'PATCH' : 'POST',body:JSON.stringify(payload)});
    $('#record-dialog').close();
    await loadTable();
    $('#table-message').textContent = 'Registro guardado correctamente.';
  } catch (error) {
    $('#dialog-message').textContent = error.message;
  }
});

startApp();
