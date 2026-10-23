const API = 'api';
const modules = {
  'registration-requests': {label:'Solicitudes de registro', icon:'✉', roles:['administrador'], fields:[]},
  'student-progress': {label:'Progreso de estudiantes', icon:'◉', roles:['administrador','instructor'], fields:[]},
  evaluations: {label:'Juicios evaluativos', icon:'✓', roles:['administrador','instructor'], fields:[['estudiante_id','Aprendiz','select'],['resultado_id','Resultado de aprendizaje','select'],['juicio','Juicio','select']]},
  excuses: {label:'Excusas', icon:'⚑', roles:['administrador','instructor','estudiante'], fields:[]},
  formation: {label:'Mi formación', icon:'▤', roles:['estudiante'], fields:[]},
  profile: {label:'Mi perfil', icon:'♙', roles:['estudiante'], fields:[], readOnly:true},
  settings: {label:'Configuración institucional', icon:'⚙', roles:['administrador'], fields:[], readOnly:true},
  'audit-log': {label:'Auditoría', icon:'▤', roles:['administrador'], fields:[], readOnly:true},
  users: {label:'Usuarios', icon:'♙', roles:['administrador'], fields:[['rol_id','Rol','select'],['documento','Documento'],['nombres','Nombres'],['apellidos','Apellidos'],['correo','Correo','email'],['password','Contraseña (mínimo 8 caracteres)','password'],['telefono','Teléfono']]},
  environments: {label:'Ambientes', icon:'⌂', roles:['administrador','instructor'], fields:[['codigo','Código'],['nombre','Nombre'],['ubicacion','Ubicación'],['capacidad','Capacidad','number'],['estado','Estado (disponible/ocupado/mantenimiento)'],['descripcion','Descripción']]},
  programs: {label:'Programas académicos', icon:'▤', roles:['administrador'], fields:[['codigo','Código'],['nombre','Nombre'],['nivel','Nivel','select'],['duracion_trimestres','Duración en trimestres','number'],['estado','Estado','select']]},
  competencies: {label:'Competencias', icon:'◈', roles:['administrador'], fields:[['codigo','Código'],['nombre','Nombre'],['programa_id','Programa','select'],['estado','Estado','select']]},
  'learning-outcomes': {label:'Resultados de aprendizaje', icon:'✓', roles:['administrador'], fields:[['codigo','Código'],['descripcion','Descripción'],['competencia_id','Competencia','select'],['estado','Estado','select']]},
  'academic-calendar': {label:'Calendario académico', icon:'▦', roles:['administrador'], fields:[['fecha','Fecha','date'],['tipo','Tipo','select'],['descripcion','Descripción'],['estado','Estado','select']]},
  fichas: {label:'Fichas y programas', icon:'▤', roles:['administrador'], fields:[['codigo','Número de ficha (7 dígitos)'],['programa_id','Programa','select'],['fecha_inicio','Fecha de inicio','date'],['fecha_fin','Fecha de fin','date']]},
  'ficha-assignments': {label:'Instructores por ficha', icon:'♧', roles:['administrador'], fields:[['ficha_id','Ficha','number'],['instructor_id','Instructor','number']]},
  schedules: {label:'Horarios', icon:'◷', roles:['administrador','instructor','estudiante'], fields:[['ficha_id','Ficha / programa','select'],['competencia_id','Competencia','select'],['ambiente_id','Ambiente','select'],['instructor_id','Instructor','select'],['dia_semana','Día','select'],['hora_inicio','Hora inicio','time'],['hora_fin','Hora fin','time'],['asignatura','Asignatura']]},
  enrollments: {label:'Asignación de aprendices', icon:'♧', roles:['administrador'], fields:[['ficha_id','Ficha / programa','number'],['usuario_id','Aprendiz','number']]},
  cards: {label:'Carnets digitales', icon:'▣', roles:['administrador','estudiante'], fields:[['usuario_id','ID usuario','number'],['vence_en','Fecha de vencimiento','date']]},
  reservations: {label:'Reservas', icon:'▦', roles:['administrador'], fields:[['ambiente_id','ID ambiente','number'],['usuario_id','ID responsable','number'],['inicio','Inicio','datetime-local'],['fin','Fin','datetime-local'],['motivo','Motivo']]},
  attendance: {label:'Asistencia', icon:'✓', roles:['administrador','instructor','estudiante'], fields:[['usuario_id','ID estudiante','number'],['ficha_id','ID ficha','number'],['fecha','Fecha','date'],['estado','Estado (presente/ausente/tarde/permiso)'],['observacion','Observación']]},
  novelties: {label:'Novedades', icon:'⚑', roles:['administrador','instructor'], fields:[['usuario_id','ID estudiante','number'],['tipo','Tipo (retraso/permiso/inasistencia/otra)'],['descripcion','Descripción'],['fecha','Fecha','date'],['asistencia_id','Asistencia ausente vinculada (necesaria para inasistencia)','select']]},
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
let currentUser = null, activeModule = null, chart = null, editId = null, userRoleFilter = 'todos';
let tableRefreshInProgress = false;
let settingsChanged = false;
let loginScannerControls = null, loginScannerStarting = false, loginScanCompleted = false, loginExpectedRole = null;
let pendingLoginQrCode = null;
let generatedCardUrl = null, generatedCardCode = null, generatedCardPendingApproval = false;
let attendanceRoster = [], attendanceFichas = [], editingAttendance = null, progressStudent = null;
let showWeeklyAvailability = false;
let learnerScheduleView = 'week';
const $ = (selector) => document.querySelector(selector);

function bogotaDate() {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Bogota',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).format(new Date());
}

function refreshActiveTable() {
  if (tableRefreshInProgress || !activeModule || $('#record-dialog').open ||
      activeModule === 'profile' || $('#learner-excuse-dialog').open ||
      activeModule === 'settings' && settingsChanged ||
      $('#attendance-ficha-trigger').getAttribute('aria-expanded') === 'true' || document.hidden) return;
  tableRefreshInProgress = true;
  loadTable().catch(showError).finally(() => { tableRefreshInProgress = false; });
}

async function api(path, options = {}) {
  if (location.protocol === 'file:') {
    throw new Error('Abre el sitio desde XAMPP en http://localhost/gestion/; no abras index.html directamente desde el explorador.');
  }

  let response;
  try {
    const headers = {...(options.headers || {})};
    if (!(options.body instanceof FormData) && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }
    response = await fetch(`${API}/${path}`, {credentials:'include', ...options, headers});
    $('#settings-form').addEventListener('input', () => { settingsChanged = true; });
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
    if (response.status === 405) {
      throw new Error('Apache rechazó la solicitud (HTTP 405). No es un error de importación de MySQL. Inicia Apache, habilita mod_rewrite y AllowOverride All en XAMPP, y comprueba http://localhost/gestion/api/health.');
    }
    throw new Error(`El servidor respondió vacío (HTTP ${response.status}). Comprueba que Apache ejecute PHP y que la API esté disponible en http://localhost/gestion/api/health.`);
  }
  if (!response.ok) {
    const error = new Error(result.error || `Error HTTP ${response.status}`);
    error.status = response.status;
    throw error;
  }
  return result.data;
}

async function downloadAuthorizedFile(path, filename) {
  let response;
  try {
    response = await fetch(`${API}/${path}`, {credentials:'include'});
  } catch {
    throw new Error('No se pudo conectar con la API para descargar el archivo.');
  }
  if (!response.ok) {
    let message = `No se pudo descargar el archivo (HTTP ${response.status}).`;
    try {
      const result = await response.json();
      if (result.error) message = result.error;
    } catch {}
    throw new Error(message);
  }
  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  const extension = ({'application/pdf':'pdf','image/jpeg':'jpg','image/png':'png','image/webp':'webp'})[blob.type] || 'bin';
  link.download = `${filename}.${extension}`;
  document.body.append(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
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
  $('#download-my-card').classList.remove('hidden');
  renderNavigation();
  try {
    if (currentUser.rol === 'administrador') await showDashboard();
    else if (currentUser.rol === 'estudiante') await showDashboard();
    else if (currentUser.rol === 'instructor') await showModule('student-progress');
    else await showModule('accesses');
  } catch (error) { showError(error); }
}

function renderNavigation() {
  const entries = [];
  if (['administrador','estudiante'].includes(currentUser.rol)) {
    entries.push(['dashboard',currentUser.rol === 'estudiante' ? 'Inicio' : 'Resumen','◈']);
  }
  Object.entries(modules).forEach(([key, item]) => {
    if (currentUser.rol === 'instructor' && ['attendance','novelties'].includes(key)) return;
    if (item.roles.includes(currentUser.rol) && !(currentUser.rol === 'estudiante' && key === 'cards')) {
      const label = key === 'excuses' && currentUser.rol === 'estudiante' ? 'Mis excusas' : item.label;
      entries.push([key,label,item.icon]);
    }
  });
  $('#navigation').innerHTML = entries.map(([key,label,icon]) => `<a href="#${key}" class="nav-link" data-view="${key}"><span class="nav-icon">${icon}</span><span class="nav-label">${escapeHtml(label)}</span>${key === 'registration-requests' ? '<span class="nav-badge hidden" id="registration-request-count"></span>' : ''}${key === 'notifications' && currentUser.rol === 'estudiante' ? '<span class="nav-badge hidden" id="learner-notification-count"></span>' : ''}</a>`).join('');
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
  $('#page-title').textContent = currentUser.rol === 'estudiante' ? 'Inicio' : currentUser.rol === 'administrador' ? 'Panel administrativo' : 'Resumen general';
  $('#page-kicker').textContent = currentUser.rol === 'estudiante' ? 'PANEL DEL APRENDIZ' : currentUser.rol === 'administrador' ? 'ADMINISTRADOR · GESTIÓN INSTITUCIONAL' : 'PANEL INSTITUCIONAL';
  setActiveNav('dashboard');
  await refreshDashboard();
  if (!window.dashboardTimer) window.dashboardTimer = setInterval(() => {
    if (currentUser.rol === 'administrador') refreshRegistrationRequestCount().catch(showError);
    if (activeModule) refreshActiveTable();
    else refreshDashboard().catch(showError);
  }, 3000);
}

async function refreshDashboard() {
  if (currentUser.rol === 'estudiante') {
    await refreshLearnerDashboard();
    return;
  }
  $('#learner-dashboard-panel').classList.add('hidden');
  $('#stats-grid').classList.remove('hidden');
  $('.chart-panel').classList.remove('hidden');
  $('.refresh-note').classList.remove('hidden');
  const stats = await api('dashboard');
  $('#dashboard-message').textContent = '';
  $('#stats-grid').innerHTML = statConfig.map(([key,label,caption,icon]) => `<article class="stat-card"><div class="stat-label">${label}</div><div class="stat-value">${Number(stats[key] || 0).toLocaleString('es')}</div><div class="stat-caption">${caption}</div><div class="stat-icon">${icon}</div></article>`).join('');
  const canViewAlerts = ['administrador','instructor'].includes(currentUser.rol);
  $('.alerts-panel').classList.toggle('hidden', !canViewAlerts);
  if (canViewAlerts) {
    const alerts = await api('attendance/alerts');
    $('#attendance-alerts').innerHTML = alerts.length
      ? alerts.map(alert => {
        const summary = alert.tipo === 'asistencia_baja'
          ? `Asistencia: ${Number(alert.porcentaje)}%`
          : `3 faltas consecutivas: ${(alert.fechas || []).map(escapeHtml).join(', ')}`;
        return `<div class="alert-row alert-${escapeHtml(alert.nivel)}"><strong>${escapeHtml(alert.estudiante)}</strong><span>${summary} · Documento ${escapeHtml(alert.documento)}</span></div>`;
      }).join('')
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

async function refreshLearnerDashboard() {
  const dashboard = await api('learner/dashboard');
  if (currentUser.rol !== 'estudiante' || activeModule !== null) return;
  const learner = dashboard.aprendiz || {};
  const attendance = dashboard.asistencia || {};
  const percentage = Number(attendance.porcentaje || 0);
  $('#dashboard-message').textContent = '';
  $('#learner-dashboard-panel').classList.remove('hidden');
  $('#stats-grid').classList.add('hidden');
  $('.alerts-panel').classList.add('hidden');
  $('.chart-panel').classList.add('hidden');
  $('.refresh-note').classList.add('hidden');
  $('#learner-welcome').textContent = `Hola, ${learner.nombres || currentUser.nombres}`;
  $('#learner-program-summary').textContent = [
    learner.ficha_codigo ? `Ficha ${learner.ficha_codigo}` : 'Sin ficha activa',
    learner.programa || 'Programa por asignar',
    learner.trimestre ? `Trimestre ${Number(learner.trimestre)}` : ''
  ].filter(Boolean).join(' · ');
  $('#learner-home-stats').innerHTML = [
    ['Asistencia',`${percentage}%`,'Asistencia acumulada'],
    ['Notificaciones sin leer',Number(dashboard.notificaciones_sin_leer || 0),'Avisos pendientes'],
    ['Clases de hoy',(dashboard.clases_hoy || []).length,'Sesiones programadas']
  ].map(([label,value,caption]) => `<article class="attendance-stat"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong><small>${escapeHtml(caption)}</small></article>`).join('');
  const alert = attendance.alerta;
  const alertBox = $('#learner-attendance-alert');
  alertBox.classList.toggle('hidden', !alert);
  alertBox.classList.toggle('alert-red', alert?.nivel === 'roja');
  alertBox.classList.toggle('alert-yellow', alert?.nivel === 'amarilla');
  if (alert) {
    alertBox.textContent = `Alerta ${alert.nivel}: tu asistencia es ${percentage}%. Umbral institucional: ${Number(alert[alert.nivel === 'roja' ? 'roja' : 'amarilla'])}%.`;
  }
  const classes = dashboard.clases_hoy || [];
  $('#learner-classes-today').innerHTML = classes.length
    ? classes.map(item => `<article class="learner-class"><strong>${escapeHtml(String(item.hora_inicio || '').slice(0,5))}–${escapeHtml(String(item.hora_fin || '').slice(0,5))} · ${escapeHtml(item.asignatura || item.competencia || 'Clase')}</strong><span>${escapeHtml(item.competencia || 'Sin competencia')} · ${escapeHtml(item.ambiente || 'Sin ambiente')}</span><small>${escapeHtml(item.instructor || 'Instructor por asignar')} · Ficha ${escapeHtml(item.ficha_codigo || '')}</small></article>`).join('')
    : '<p class="empty-row">No tienes clases programadas para hoy.</p>';
  const badge = $('#learner-notification-count');
  if (badge) {
    badge.textContent = Number(dashboard.notificaciones_sin_leer) > 99 ? '99+' : String(Number(dashboard.notificaciones_sin_leer || 0));
    badge.classList.toggle('hidden', Number(dashboard.notificaciones_sin_leer) === 0);
  }
}

function setActiveNav(key) {
  $('#navigation').querySelectorAll('.nav-link').forEach(link => link.classList.toggle('active', link.dataset.view === key));
}

function renderUserRoleFilters() {
  $('#user-role-filters').querySelectorAll('[data-user-role]').forEach(button => {
    const selected = button.dataset.userRole === userRoleFilter;
    button.classList.toggle('active', selected);
    button.setAttribute('aria-pressed', String(selected));
  });
}

$('#user-role-filters').addEventListener('click', event => {
  const button = event.target.closest('[data-user-role]');
  if (!button) return;
  userRoleFilter = button.dataset.userRole;
  renderUserRoleFilters();
  loadTable().catch(showError);
});

$('#student-view-history').addEventListener('click', () => showModule('attendance').catch(showError));

function formatDisplayDate(value) {
  const date = String(value || '').slice(0,10).split('-');
  return date.length === 3 ? `${date[2]}/${date[1]}/${date[0]}` : String(value || '—');
}

function fichaSearchLabel(ficha) {
  return `${ficha.programa || ficha.nombre} · Ficha ${ficha.codigo}`;
}

function updateAttendanceFichaOptions(fichas, selectedId = '') {
  attendanceFichas = fichas;
  const select = $('#attendance-ficha');
  const options = attendanceFichas.map(ficha =>
    `<option value="${Number(ficha.id)}">${escapeHtml(fichaSearchLabel(ficha))}</option>`
  ).join('');
  if (select.innerHTML !== options) select.innerHTML = options;
  const selectedFicha = attendanceFichas.find(ficha => String(ficha.id) === String(selectedId)) ||
    attendanceFichas.find(ficha => String(ficha.id) === select.value) ||
    attendanceFichas[0];
  select.value = selectedFicha ? String(selectedFicha.id) : '';
  $('#attendance-ficha-value').textContent = selectedFicha
    ? fichaSearchLabel(selectedFicha)
    : 'No tienes fichas activas asignadas';
  $('#attendance-ficha-trigger').disabled = attendanceFichas.length < 2;
  $('#attendance-ficha-options').innerHTML = attendanceFichas.map(ficha => {
    const description = [ficha.trimestre ? `Trimestre ${Number(ficha.trimestre)}` : '', `Programa de formación`]
      .filter(Boolean).join(' · ');
    const isSelected = selectedFicha && Number(ficha.id) === Number(selectedFicha.id);
    return `<button class="role-picker-option${isSelected ? ' selected' : ''}" type="button" role="option" data-value="${Number(ficha.id)}" aria-selected="${Boolean(isSelected)}">
      <span class="role-option-icon" aria-hidden="true">▤</span>
      <span><strong>${escapeHtml(ficha.programa || ficha.nombre)}</strong><small>Ficha ${escapeHtml(ficha.codigo)}${description ? ` · ${escapeHtml(description)}` : ''}</small></span>
      <span class="role-option-check" aria-hidden="true">✓</span>
    </button>`;
  }).join('');
  $('#attendance-ficha-trigger').setAttribute('aria-label', selectedFicha
    ? `Ficha seleccionada: ${fichaSearchLabel(selectedFicha)}`
    : 'No tienes fichas activas asignadas');
}

function renderStaffProgressOverview(records) {
  const totals = records.reduce((summary, record) => {
    summary.presentes += Number(record.presentes || 0);
    summary.ausencias += Number(record.ausencias || 0);
    summary.retrasos += Number(record.retrasos || 0);
    summary.permisos += Number(record.permisos || 0);
    return summary;
  }, {presentes:0,ausencias:0,retrasos:0,permisos:0});
  const evaluated = totals.presentes + totals.ausencias + totals.retrasos;
  const attended = totals.presentes + totals.retrasos;
  const rate = evaluated ? Math.round(100 * attended / evaluated) : 0;
  $('#staff-progress-rate').textContent = `${rate}%`;
  $('#staff-progress-ring').style.setProperty('--attendance-rate', `${rate}%`);
  $('#staff-progress-detail').textContent = evaluated
    ? `${attended} asistencias o retrasos de ${evaluated} registros evaluados. Los permisos no cuentan para el porcentaje.`
    : 'Aún no hay asistencias registradas.';
  $('#staff-progress-bar').style.width = `${rate}%`;
  $('#staff-progress-bar').parentElement.setAttribute('aria-valuenow', String(rate));
  $('#staff-progress-stats').innerHTML = [
    ['Asistió',totals.presentes,'present'],
    ['Faltó',totals.ausencias,'absence'],
    ['Tarde',totals.retrasos,'late'],
    ['Permiso',totals.permisos,'permission']
  ].map(([label,value,kind]) =>
    `<article class="attendance-stat stat-${kind}"><span>${label}</span><strong>${value}</strong></article>`
  ).join('');
}

async function loadAdminStudentProgress() {
  if (!attendanceFichas.length) {
    const options = await api('options');
    attendanceFichas = options.fichas || [];
  }
  updateAttendanceFichaOptions(attendanceFichas);

  if (progressStudent) {
    const params = new URLSearchParams({usuario_id:String(progressStudent.id)});
    const records = await api(`attendance/progress?${params}`);
    if (activeModule !== 'student-progress' || progressStudent === null) return;
    $('#table-title').textContent = `Historial · ${progressStudent.name}`;
    $('#table-description').textContent = 'Historial de asistencia del aprendiz.';
    const headings = ['Fecha','Ficha','Programa','Trimestre','Asignatura','Horario','Ambiente','Instructor','Estado','Observación'];
    $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
    $('#table-message').innerHTML = '<button type="button" class="button secondary progress-back">← Volver al progreso</button>';
    $('#table-message').querySelector('.progress-back').addEventListener('click', () => {
      progressStudent = null;
      loadAdminStudentProgress().catch(showError);
    });
    const labels = {presente:'Asistió',ausente:'Faltó',tarde:'Llegó tarde',permiso:'Permiso'};
    $('#table-body').innerHTML = records.length
      ? records.map(record => `<tr>
          <td>${formatDisplayDate(record.fecha)}</td>
          <td>${escapeHtml(record.ficha_codigo)} · ${escapeHtml(record.ficha_nombre)}</td>
          <td>${escapeHtml(record.programa || '—')}</td>
          <td>${record.trimestre ? `Trimestre ${Number(record.trimestre)}` : '—'}</td>
          <td>${escapeHtml(record.asignatura || '—')}</td>
          <td>${record.hora_inicio ? `${escapeHtml(String(record.hora_inicio).slice(0,5))}–${escapeHtml(String(record.hora_fin).slice(0,5))}` : '—'}</td>
          <td>${escapeHtml(record.ambiente || '—')}</td>
          <td>${escapeHtml(record.instructor || '—')}</td>
          <td><span class="attendance-state state-${escapeHtml(record.estado)}">${labels[record.estado] || escapeHtml(record.estado)}</span></td>
          <td>${escapeHtml(record.observacion || '—')}</td>
        </tr>`).join('')
      : '<tr><td class="empty-row" colspan="10">Este aprendiz todavía no tiene asistencias registradas.</td></tr>';
    return;
  }
  const params = new URLSearchParams();
  if ($('#attendance-ficha').value) params.set('ficha_id', $('#attendance-ficha').value);
  const records = await api(`attendance/progress?${params}`);
  if (activeModule !== 'student-progress') return;
  renderStaffProgressOverview(records);
  $('#table-title').textContent = 'Progreso de estudiantes';
  $('#table-description').textContent = 'Porcentaje calculado con la asistencia registrada; las tareas no se registran en este módulo.';
  const headings = ['Estudiante','Documento','Ficha','Asistió','Faltó','Tarde','Permiso','Asistencia','Historial'];
  $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
  $('#table-message').textContent = records.length
    ? `${records.length} aprendices en la ficha seleccionada`
    : 'No hay aprendices inscritos en esta ficha activa.';
  $('#table-body').innerHTML = records.length
    ? records.map(record => {
      const present = Number(record.presentes || 0);
      const absent = Number(record.ausencias || 0);
      const late = Number(record.retrasos || 0);
      const evaluated = present + absent + late;
      const progress = evaluated ? Math.round(100 * (present + late) / evaluated) : 0;
      return `<tr>
        <td><strong>${escapeHtml(record.nombres)} ${escapeHtml(record.apellidos)}</strong></td>
        <td>${escapeHtml(record.documento)}</td>
        <td>${escapeHtml(record.ficha_codigo)} · ${escapeHtml(record.ficha_nombre)}</td>
        <td><span class="attendance-state state-presente">${present}</span></td>
        <td><span class="attendance-state state-ausente">${absent}</span></td>
        <td><span class="attendance-state state-tarde">${late}</span></td>
        <td><span class="attendance-state state-permiso">${Number(record.permisos || 0)}</span></td>
        <td><strong class="progress-value">${progress}%</strong></td>
        <td><button type="button" class="row-action progress-history" data-progress-student="${Number(record.usuario_id)}" data-student-name="${escapeHtml(`${record.nombres} ${record.apellidos}`)}">Ver historial</button></td>
      </tr>`;
    }).join('')
    : '<tr><td class="empty-row" colspan="9">No hay aprendices inscritos en esta ficha activa.</td></tr>';
  $('#table-body').querySelectorAll('[data-progress-student]').forEach(button => {
    button.addEventListener('click', () => {
      progressStudent = {id:Number(button.dataset.progressStudent),name:button.dataset.studentName};
      loadAdminStudentProgress().catch(showError);
    });
  });
}

async function loadInstructorAvailability() {
  const result = await api('schedules/availability');
  if (activeModule !== 'student-progress' || !showWeeklyAvailability) return;
  $('#table-title').textContent = 'Disponibilidad semanal de ambientes';
  $('#table-description').textContent = 'La disponibilidad se calcula con todos los horarios vigentes; los detalles solo muestran tus fichas asignadas.';
  const headings = ['Ambiente','Día','Disponibilidad','Horarios y fichas asignadas'];
  $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
  $('#table-message').textContent = result.length
    ? `${result.length} espacios y días consultados`
    : 'No hay ambientes registrados para consultar.';
  $('#table-body').innerHTML = result.length
    ? result.map(slot => {
      const details = slot.horarios.length
        ? slot.horarios.map(schedule => `<div class="availability-slot">
            <strong>${escapeHtml(schedule.inicio)}–${escapeHtml(schedule.fin)}</strong>
            <span>${schedule.ficha_asignada
              ? `${escapeHtml(schedule.asignatura)} · Ficha ${escapeHtml(schedule.ficha_codigo)} · ${escapeHtml(schedule.instructor)}`
              : 'Ocupado por otra ficha'}</span>
          </div>`).join('')
        : slot.estado === 'mantenimiento'
          ? 'En mantenimiento'
          : 'Disponible todo el día';
      const statusLabel = slot.estado === 'disponible'
        ? 'Disponible'
        : slot.estado === 'mantenimiento'
          ? 'Mantenimiento'
          : 'Ocupado';
      return `<tr>
        <td><strong>${escapeHtml(slot.codigo)} · ${escapeHtml(slot.ambiente)}</strong><br><span class="muted">${escapeHtml(slot.ubicacion)}</span></td>
        <td>${escapeHtml(slot.dia)}</td>
        <td><span class="attendance-state availability-state-${escapeHtml(slot.estado)}">${statusLabel}</span></td>
        <td>${details}</td>
      </tr>`;
    }).join('')
    : '<tr><td class="empty-row" colspan="4">No hay ambientes disponibles.</td></tr>';
}

async function showModule(key) {
  if (key === 'users' && activeModule !== 'users') userRoleFilter = 'todos';
  if (key !== 'student-progress') showWeeklyAvailability = false;
  activeModule = key;
  if (key === 'schedules' && currentUser.rol === 'estudiante' && !$('#learner-schedule-date').value) {
    $('#learner-schedule-date').value = bogotaDate();
  }
  $('#learner-schedule-day').classList.toggle('active', learnerScheduleView === 'day');
  $('#learner-schedule-week').classList.toggle('active', learnerScheduleView === 'week');
  if (key === 'settings') settingsChanged = false;
  $('#dashboard-view').classList.add('hidden');
  $('#resource-view').classList.remove('hidden');
  $('#page-title').textContent = modules[key].label;
  $('#page-kicker').textContent = 'GESTIÓN DE MÓDULOS';
  $('#settings-panel').classList.toggle('hidden', key !== 'settings');
  $('#table-panel').classList.toggle('hidden', ['settings','profile'].includes(key));
  $('#learner-profile-panel').classList.toggle('hidden', key !== 'profile');
  $('#learner-schedule-controls').classList.toggle('hidden', key !== 'schedules' || currentUser.rol !== 'estudiante');
  $('#table-title').textContent = modules[key].label;
  const showingStudentNotifications = key === 'notifications' && currentUser.rol === 'estudiante';
  $('#table-panel').classList.toggle('notifications-mode', showingStudentNotifications);
  $('#user-role-filters').classList.toggle('hidden', key !== 'users');
  $('#notification-list').classList.toggle('hidden', !showingStudentNotifications);
  $('#staff-progress-overview').classList.toggle('hidden', key !== 'student-progress' ||
    !['administrador','instructor'].includes(currentUser.rol) || showWeeklyAvailability);
  $('#table-description').textContent = key === 'attendance' && currentUser.rol !== 'estudiante'
    ? 'Busca una ficha y consulta o registra la asistencia de sus aprendices para hoy.'
    : key === 'student-progress' && currentUser.rol === 'instructor'
    ? 'Consulta el progreso de asistencia de los aprendices de tus fichas asignadas.'
    : key === 'student-progress'
    ? 'Consulta el resumen de asistencia de los aprendices inscritos en fichas activas del centro.'
    : key === 'registration-requests'
    ? 'Revisa las solicitudes de instructores y portería antes de activar sus cuentas.'
    : 'Consulta y administra los registros del sistema.';
  $('#student-attendance-overview').classList.toggle('hidden', currentUser.rol !== 'estudiante' || key !== 'attendance');
  const progressView = key === 'student-progress' && ['administrador','instructor'].includes(currentUser.rol);
  const instructorProgress = progressView && currentUser.rol === 'instructor';
  $('#attendance-toolbar').classList.toggle('hidden', key !== 'attendance' && !progressView || currentUser.rol === 'estudiante');
  $('#attendance-toolbar').classList.toggle('availability-mode', instructorProgress && showWeeklyAvailability);
  $('#availability-toggle').classList.toggle('hidden', !instructorProgress);
  $('#availability-toggle').textContent = showWeeklyAvailability ? 'Volver a aprendices' : 'Disponibilidad semanal de ambientes';
  $('#add-record').classList.toggle('hidden', key === 'registration-requests' || key === 'attendance' ||
    key === 'excuses' || key === 'formation' ||
    modules[key].readOnly ||
    currentUser.rol === 'estudiante' ||
    key === 'notifications' && currentUser.rol === 'estudiante' ||
    key === 'environments' && currentUser.rol === 'instructor' ||
    key === 'ficha-assignments' && currentUser.rol === 'instructor');
  $('#report-format').classList.toggle('hidden', currentUser.rol === 'estudiante' || ['registration-requests','settings','audit-log'].includes(key));
  $('#export-report').classList.toggle('hidden', currentUser.rol === 'estudiante' || ['registration-requests','student-progress','settings','audit-log','excuses','evaluations','formation','profile'].includes(key));
  setActiveNav(key);
  await loadTable();
}

async function loadTable() {
  const module = activeModule;
  if (module === 'settings') {
    try {
      const settings = await api('settings');
      if (activeModule !== module) return;
      const form = $('#settings-form');
      Object.entries(settings).forEach(([key, value]) => {
        const input = form.elements.namedItem(key);
        if (input) input.value = String(value);
      });
      $('#settings-message').textContent = '';
    } catch (error) {
      $('#settings-message').textContent = error.message;
    }
    return;
  }
  if (module === 'student-progress' && ['administrador','instructor'].includes(currentUser.rol)) {
    if (currentUser.rol === 'instructor') {
      if (showWeeklyAvailability) {
        await loadInstructorAvailability();
      } else {
        await loadAttendanceRoster();
      }
      return;
    }
    await loadAdminStudentProgress();
    return;
  }
  if (module === 'attendance' && currentUser.rol !== 'estudiante') {
    await loadAttendanceRoster();
    return;
  }
  if (module === 'attendance' && currentUser.rol === 'estudiante') {
    await loadStudentAttendance();
    return;
  }
  if (module === 'schedules' && currentUser.rol === 'estudiante') {
    await loadLearnerSchedule();
    return;
  }
  if (module === 'notifications' && currentUser.rol === 'estudiante') {
    const notifications = await api('learner/notifications');
    if (activeModule === module) renderStudentNotifications(notifications);
    return;
  }
  if (module === 'excuses') {
    const rows = await api(currentUser.rol === 'estudiante' ? 'learner/excuses' : 'excuses');
    if (activeModule === module) renderExcuses(rows, currentUser.rol === 'estudiante');
    return;
  }
  if (module === 'formation' && currentUser.rol === 'estudiante') {
    const rows = await api('learner/formation');
    if (activeModule === module) renderLearnerFormation(rows);
    return;
  }
  if (module === 'profile' && currentUser.rol === 'estudiante') {
    const profile = await api('learner/profile');
    if (activeModule !== module) return;
    const form = $('#learner-profile-form');
    form.elements.correo.value = profile.correo || '';
    form.elements.telefono.value = profile.telefono || '';
    form.elements.current_password.value = '';
    form.elements.password.value = '';
    form.elements.pin.value = '';
    $('#learner-profile-message').textContent = '';
    return;
  }
  if (module === 'evaluations') {
    const rows = await api('evaluations');
    if (activeModule === module) renderEvaluations(rows);
    return;
  }
  if (currentUser.rol === 'estudiante') {
    $('#table-title').textContent = modules[module].label;
    $('#table-description').textContent = 'Consulta la información actualizada de tu formación.';
  }
  const data = await api(module);
  if (activeModule !== module) return;
  let rows = Array.isArray(data) ? data : [];
  if (module === 'users') {
    rows = userRoleFilter === 'todos' ? rows : rows.filter(row => row.rol === userRoleFilter);
    renderUserRoleFilters();
  }
  if (module === 'ficha-assignments' && currentUser.rol === 'instructor') {
    $('#table-description').textContent = 'Aprendices inscritos en las fichas que tienes asignadas. Administración conserva su acceso general.';
  }
  if (!rows.length) {
    $('#table-head').innerHTML = '';
    const emptyMessage = module === 'registration-requests'
      ? 'No hay solicitudes pendientes de revisión.'
      : 'No hay registros disponibles.';
    $('#table-body').innerHTML = `<tr><td class="empty-row">${emptyMessage}</td></tr>`;
    return;
  }

  async function loadAttendanceRoster() {
    const selectedFicha = $('#attendance-ficha').value;
    const selectedDate = bogotaDate();
    const params = new URLSearchParams({fecha:selectedDate});
    if (selectedFicha) params.set('ficha_id', selectedFicha);
    const result = await api(`attendance/roster?${params}`);
    attendanceRoster = result.estudiantes || [];
    updateAttendanceFichaOptions(result.fichas || [], result.ficha_seleccionada || '');
    renderStaffProgressOverview(attendanceRoster);
    $('#staff-progress-overview').classList.toggle('hidden', activeModule !== 'student-progress' || showWeeklyAvailability);

    if (activeModule === 'student-progress') {
      $('#table-title').textContent = 'Aprendices de mis fichas';
      $('#table-description').textContent = 'Porcentaje de asistencia acumulada y registro diario de los aprendices de la ficha seleccionada.';
    }

    if (!attendanceFichas.length) {
      $('#table-message').textContent = currentUser.rol === 'instructor'
        ? 'Administración todavía no te ha asignado fichas activas.'
        : 'No hay fichas activas para registrar asistencia.';
      $('#table-head').innerHTML = '';
      $('#table-body').innerHTML = '';
      return;
    }

    $('#table-message').textContent = attendanceRoster.length
      ? `${attendanceRoster.length} aprendices · asistencia del ${formatDisplayDate(result.fecha)}`
      : 'Aún no hay estudiantes inscritos en esta ficha.';
    const headings = ['N.º','Foto','Nombres','Apellidos','Documento','Progreso de asistencia','Asistencia de hoy','Motivo / observación','Carné','Acciones'];
    $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
    $('#table-body').innerHTML = attendanceRoster.map((student, index) => {
      const photo = student.foto
        ? `<img class="attendance-avatar" src="${escapeHtml(student.foto)}" alt="Foto de ${escapeHtml(student.nombres)} ${escapeHtml(student.apellidos)}">`
        : `<span class="attendance-avatar attendance-avatar-placeholder" aria-label="Sin foto">♙</span>`;
      const statuses = [
        ['presente','Asistió'],
        ['ausente','No asistió'],
        ['tarde','Tarde'],
        ['permiso','Permiso']
      ];
      const selectedStatus = student.estado || '';
      const statusButtons = statuses.map(([value,label]) =>
        `<button type="button" class="attendance-status status-${value}${selectedStatus === value ? ' selected' : ''}" data-attendance-status="${value}" data-student-id="${Number(student.id)}" aria-pressed="${selectedStatus === value}">${label}</button>`
      ).join('');
      const card = student.codigo_qr
        ? `<button type="button" class="attendance-card-link" data-card-student="${Number(student.id)}">Ver carné</button>`
        : '<span class="muted">Sin carné</span>';
      const present = Number(student.presentes || 0);
      const absent = Number(student.ausencias || 0);
      const late = Number(student.retrasos || 0);
      const evaluated = present + absent + late;
      const progress = evaluated ? Math.round(100 * (present + late) / evaluated) : 0;
      const actions = student.asistencia_id
        ? `<button type="button" class="row-action attendance-edit" data-attendance-edit="${Number(student.id)}">Editar / motivo</button><button type="button" class="row-action attendance-delete" data-attendance-delete="${Number(student.id)}">Eliminar</button>`
        : `<button type="button" class="row-action attendance-edit" data-attendance-edit="${Number(student.id)}">Agregar motivo</button>`;
      return `<tr>
        <td class="attendance-number">${index + 1}</td>
        <td>${photo}</td>
        <td>${escapeHtml(student.nombres)}</td>
        <td>${escapeHtml(student.apellidos)}</td>
        <td>${escapeHtml(student.documento)}</td>
        <td><div class="learner-progress">
          <div class="attendance-ring learner-progress-ring" style="--attendance-rate:${progress}%"><span>${progress}%</span><small>asistencia</small></div>
          <div class="learner-progress-track" role="progressbar" aria-label="Asistencia de ${escapeHtml(student.nombres)} ${escapeHtml(student.apellidos)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${progress}"><span style="width:${progress}%"></span></div>
          <small class="muted">${evaluated ? `${present + late}/${evaluated} clases` : 'Sin asistencias'}</small>
        </div></td>
        <td><div class="attendance-statuses">${statusButtons}</div></td>
        <td>${escapeHtml(student.observacion || '—')}</td>
        <td>${card}</td><td><div class="attendance-actions">${actions}</div></td>
      </tr>`;
    }).join('');

    $('#table-body').querySelectorAll('[data-attendance-status]').forEach(button => {
      button.addEventListener('click', () => markRosterAttendance(Number(button.dataset.studentId), button.dataset.attendanceStatus));
    });
    $('#table-body').querySelectorAll('[data-attendance-edit]').forEach(button => {
      button.addEventListener('click', () => editRosterAttendance(Number(button.dataset.attendanceEdit)));
    });
    $('#table-body').querySelectorAll('[data-attendance-delete]').forEach(button => {
      button.addEventListener('click', () => deleteRosterAttendance(Number(button.dataset.attendanceDelete)));
    });
    $('#table-body').querySelectorAll('[data-card-student]').forEach(button => {
      button.addEventListener('click', () => showStudentRosterCard(Number(button.dataset.cardStudent)));
    });
  }

  async function loadStudentAttendance() {
    const [dashboard, records] = await Promise.all([api('learner/dashboard'), api('learner/attendance')]);
    renderStudentAttendanceSummary(dashboard.asistencia || {});
    $('#table-message').textContent = 'Historial ordenado desde la fecha más reciente.';
    $('#table-title').textContent = 'Mi historial de asistencia';
    $('#table-description').textContent = 'El porcentaje cuenta asistencias y llegadas tarde; los permisos se excluyen del total.';
    const headings = ['Fecha','Estado','Competencia','Horario','Ambiente','Instructor','Ficha','Programa','Trimestre','Observación','Excusa'];
    $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
    $('#table-body').innerHTML = records.length
      ? records.map(record => `<tr>
          <td>${formatDisplayDate(record.fecha)}</td>
          <td><span class="attendance-state state-${escapeHtml(record.estado)}">${attendanceStatusLabel(record.estado)}</span></td>
          <td>${escapeHtml(record.competencia || record.asignatura || '—')}</td>
          <td>${record.hora_inicio ? `${escapeHtml(String(record.hora_inicio).slice(0,5))}–${escapeHtml(String(record.hora_fin).slice(0,5))}` : '—'}</td>
          <td>${escapeHtml(record.ambiente || '—')}</td>
          <td>${escapeHtml(record.instructor || '—')}</td>
          <td>${escapeHtml(record.ficha_codigo)} · ${escapeHtml(record.ficha_nombre)}</td>
          <td>${escapeHtml(record.programa || '—')}</td>
          <td>${record.trimestre ? `Trimestre ${Number(record.trimestre)}` : '—'}</td>
          <td>${escapeHtml(record.observacion || '—')}</td>
          <td>${record.estado === 'ausente' && !['pendiente','aprobada'].includes(record.estado_excusa)
            ? `<button class="row-action" type="button" data-submit-excuse="${Number(record.id)}" data-absence-date="${escapeHtml(record.fecha)}">Presentar excusa</button>`
            : record.estado_excusa ? `<span class="attendance-state state-${escapeHtml(record.estado_excusa)}">${escapeHtml(record.estado_excusa)}</span>` : '—'}</td>
        </tr>`).join('')
      : '<tr><td class="empty-row" colspan="11">Aún no hay asistencias registradas.</td></tr>';
    $('#table-body').querySelectorAll('[data-submit-excuse]').forEach(button => button.addEventListener('click', () => {
      $('#learner-excuse-form').reset();
      $('#learner-excuse-form').elements.asistencia_id.value = button.dataset.submitExcuse;
      $('#learner-excuse-date').textContent = `Falta del ${formatDisplayDate(button.dataset.absenceDate)}. El plazo se valida con los días hábiles institucionales.`;
      $('#learner-excuse-message').textContent = '';
      $('#learner-excuse-dialog').showModal();
    }));
  }

  function attendanceStatusLabel(status) {
    return ({presente:'Asistió',ausente:'Faltó',tarde:'Llegó tarde',permiso:'Permiso'})[status] || status;
  }

  async function loadLearnerSchedule() {
    const date = $('#learner-schedule-date').value || bogotaDate();
    const query = new URLSearchParams({view:learnerScheduleView, date});
    const result = await api(`learner/schedule?${query}`);
    if (activeModule === 'schedules') renderStudentSchedule(result.clases || []);
  }

  function renderStudentSchedule(rows) {
    $('#table-title').textContent = 'Mi horario de clases';
    $('#table-description').textContent = learnerScheduleView === 'day'
      ? `Clases del ${formatDisplayDate($('#learner-schedule-date').value)}.`
      : 'Clases organizadas por día y hora durante la semana.';
    const headings = ['Día','Horario','Asignatura','Competencia','Ambiente','Instructor','Ficha','Programa','Trimestre'];
    $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
    const days = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
    $('#table-body').innerHTML = rows.length
      ? rows.map(row => `<tr><td><span class="schedule-day">${escapeHtml(days[Number(row.dia_semana) - 1] || '—')}</span></td><td>${escapeHtml(String(row.hora_inicio).slice(0,5))} – ${escapeHtml(String(row.hora_fin).slice(0,5))}</td><td><strong>${escapeHtml(row.asignatura || '—')}</strong></td><td>${escapeHtml(row.competencia || '—')}</td><td>${escapeHtml(row.ambiente || '—')}</td><td>${escapeHtml(row.instructor || '—')}</td><td>${escapeHtml(row.ficha_codigo)} · ${escapeHtml(row.ficha_nombre)}</td><td>${escapeHtml(row.programa || '—')}</td><td>${row.trimestre ? `Trimestre ${Number(row.trimestre)}` : '—'}</td></tr>`).join('')
      : '<tr><td class="empty-row" colspan="9">Todavía no tienes horarios asignados.</td></tr>';
  }

  function renderStudentNotifications(rows) {
    $('#table-title').textContent = 'Mis notificaciones';
    $('#table-description').textContent = 'Cada aviso, con su fecha y detalle, en un formato más claro.';
    $('#table-message').textContent = rows.length
      ? `<button id="learner-read-all" type="button" class="row-action">Marcar todas como leídas</button> · ${rows.length} ${rows.length === 1 ? 'notificación' : 'notificaciones'}`
      : '';
    $('#table-head').innerHTML = '';
    $('#table-body').innerHTML = '';
    $('#notification-list').innerHTML = rows.length
      ? rows.map(row => {
        const rawDate = String(row.creado_en || '').slice(0, 10);
        const [year, month, day] = rawDate.split('-');
        const monthYear = year && month
          ? new Date(Number(year), Number(month) - 1, 1).toLocaleDateString('es-CO', {month:'long',year:'numeric'})
          : '';
        const message = String(row.mensaje || '').trim();
        const legacyReason = message.match(/Motivo u observación:\s*([\s\S]*)$/i)?.[1]?.trim();
        const displayMessage = String(row.titulo || '').startsWith('Actualización de asistencia')
          ? legacyReason
            ? `Motivo: ${legacyReason}`
            : message.startsWith('Motivo:')
              ? message
              : 'Motivo: No se registró una observación.'
          : message;
        return `<article class="notification-card">
          <div class="notification-date" aria-label="${formatDisplayDate(rawDate)}">
            <span class="notification-date-label">FECHA</span>
            <strong>${escapeHtml(day || '—')}</strong>
            <span>${escapeHtml(monthYear)}</span>
          </div>
          <div class="notification-content">
            <p class="notification-eyebrow">${escapeHtml(row.tipo || 'sistema')} · ${escapeHtml(row.remitente || 'Sistema')}</p>
            <h3>${escapeHtml(row.titulo)}</h3>
            <p class="notification-message">${escapeHtml(displayMessage)}</p>
          </div>
          ${row.leida_en
            ? '<span class="notification-indicator" aria-label="Leída"></span>'
            : `<button class="row-action" type="button" data-read-notification="${Number(row.id)}">Marcar como leída</button>`}
        </article>`;
      }).join('')
      : '<div class="notification-empty"><span aria-hidden="true">✓</span><strong>Todo al día</strong><p>No tienes notificaciones por ahora.</p></div>';
    const unreadCount = rows.filter(row => !row.leida_en).length;
    const badge = $('#learner-notification-count');
    if (badge) {
      badge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
      badge.classList.toggle('hidden', unreadCount === 0);
    }
    $('#notification-list').querySelectorAll('[data-read-notification]').forEach(button => {
      button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          await api(`learner/notifications/${Number(button.dataset.readNotification)}/read`, {method:'PATCH',body:'{}'});
          await loadTable();
        } catch (error) {
          button.disabled = false;
          $('#table-message').textContent = error.message;
        }
      });
    });
    $('#learner-read-all')?.addEventListener('click', async () => {
      try {
        await api('learner/notifications/read-all', {method:'POST',body:'{}'});
        await loadTable();
      } catch (error) { $('#table-message').textContent = error.message; }
    });
  }

  function renderExcuses(rows, isLearner) {
    $('#table-title').textContent = isLearner ? 'Mis excusas' : 'Revisión de excusas';
    $('#table-description').textContent = isLearner
      ? 'Consulta el estado de tus excusas y las faltas asociadas.'
      : 'Revisa soportes de aprendices de tus fichas y aprueba o rechaza cada excusa pendiente.';
    $('#table-message').textContent = rows.length ? `${rows.length} excusas` : 'No hay excusas para mostrar.';
    const headings = isLearner
      ? ['Fecha de falta','Motivo','Estado','Estado de asistencia','Revisión']
      : ['Aprendiz','Ficha','Fecha de falta','Motivo','Soporte','Estado','Acciones'];
    $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
    $('#table-body').innerHTML = rows.length ? rows.map(row => isLearner
      ? `<tr><td>${formatDisplayDate(row.fecha_asistencia || row.fecha)}</td><td>${escapeHtml(row.descripcion)}</td><td>${escapeHtml(row.estado_excusa || 'Sin presentar')}</td><td>${attendanceStatusLabel(row.estado_asistencia || '')}</td><td>${row.revisado_en ? formatDisplayDate(row.revisado_en) : '—'}${row.soporte_ruta ? ` · <button class="row-action" type="button" data-excuse-file="${Number(row.id)}" data-owner="learner">Ver soporte</button>` : ''}</td></tr>`
      : `<tr><td>${escapeHtml(row.estudiante)}</td><td>${escapeHtml(row.ficha_codigo)} · ${escapeHtml(row.ficha_nombre)}</td><td>${formatDisplayDate(row.fecha_asistencia)}</td><td>${escapeHtml(row.descripcion)}</td><td>${row.soporte_ruta ? `<button class="row-action" type="button" data-excuse-file="${Number(row.id)}">Ver soporte</button>` : '—'}</td><td>${escapeHtml(row.estado_excusa)}</td><td>${row.estado_excusa === 'pendiente' ? `<button class="row-action" type="button" data-review-excuse="${Number(row.id)}" data-decision="aprobada">Aprobar</button> <button class="row-action" type="button" data-review-excuse="${Number(row.id)}" data-decision="rechazada">Rechazar</button>` : '—'}</td></tr>`
    ).join('') : `<tr><td class="empty-row" colspan="${headings.length}">No hay excusas para mostrar.</td></tr>`;
    $('#table-body').querySelectorAll('[data-review-excuse]').forEach(button => button.addEventListener('click', async () => {
      const decision = button.dataset.decision;
      if (!window.confirm(`¿${decision === 'aprobada' ? 'Aprobar' : 'Rechazar'} esta excusa?`)) return;
      button.disabled = true;
      try {
        await api(`excuses/${Number(button.dataset.reviewExcuse)}/review`, {method:'POST',body:JSON.stringify({estado:decision})});
        await loadTable();
      } catch (error) {
        button.disabled = false;
        $('#table-message').textContent = error.message;
      }
    }));
    $('#table-body').querySelectorAll('[data-excuse-file]').forEach(button => button.addEventListener('click', () => {
      const route = button.dataset.owner === 'learner'
        ? `learner/excuses/${Number(button.dataset.excuseFile)}/file`
        : `excuses/${Number(button.dataset.excuseFile)}/file`;
      downloadAuthorizedFile(route, `soporte-excusa-${button.dataset.excuseFile}`).catch(error => {
        $('#table-message').textContent = error.message;
      });
    }));
  }

  function renderLearnerFormation(rows) {
    $('#table-title').textContent = 'Mi formación';
    $('#table-description').textContent = 'Competencias y resultados de aprendizaje de tus programas, con el último juicio registrado.';
    $('#table-message').innerHTML = '<button id="download-formation-history" class="button secondary" type="button">Descargar historial PDF</button>';
    $('#table-head').innerHTML = '<tr><th>Programa</th><th>Ficha / trimestre</th><th>Competencia</th><th>Resultado de aprendizaje</th><th>Juicio evaluativo</th><th>Fecha de evaluación</th></tr>';
    $('#table-body').innerHTML = rows.length
      ? rows.map(row => `<tr><td>${escapeHtml(row.programa)} · ${escapeHtml(row.programa_codigo)}</td><td>${escapeHtml(row.ficha_codigo)}${row.trimestre ? ` · Trimestre ${Number(row.trimestre)}` : ''}</td><td>${escapeHtml(row.competencia)} · ${escapeHtml(row.competencia_codigo)}</td><td>${escapeHtml(row.resultado)} <small>${escapeHtml(row.resultado_codigo)}</small></td><td>${escapeHtml(String(row.juicio).replace(/_/g,' '))}</td><td>${row.evaluado_en ? formatDisplayDate(row.evaluado_en) : '—'}</td></tr>`).join('')
      : '<tr><td class="empty-row" colspan="6">Todavía no hay resultados de aprendizaje asociados a tu programa.</td></tr>';
    $('#download-formation-history').addEventListener('click', () => downloadAuthorizedFile('learner/formation/pdf', 'historial-academico').catch(error => {
      $('#table-message').textContent = error.message;
    }));
  }

  function renderEvaluations(rows) {
    $('#table-title').textContent = 'Juicios evaluativos';
    $('#table-description').textContent = 'Registra o consulta el último juicio de formación de aprendices de tus fichas asignadas.';
    $('#table-message').textContent = rows.length ? `${rows.length} evaluaciones recientes` : 'Aún no hay juicios evaluativos.';
    const headings = ['Aprendiz','Programa','Competencia','Resultado de aprendizaje','Juicio','Fecha','Evaluador'];
    $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
    $('#table-body').innerHTML = rows.length
      ? rows.map(row => `<tr><td>${escapeHtml(row.estudiante)}</td><td>${escapeHtml(row.programa)}</td><td>${escapeHtml(row.competencia)}</td><td>${escapeHtml(row.resultado_codigo)} · ${escapeHtml(row.resultado)}</td><td>${escapeHtml(String(row.juicio).replace(/_/g,' '))}</td><td>${formatDisplayDate(row.evaluado_en)}</td><td>${escapeHtml(row.evaluador)}</td></tr>`).join('')
      : '<tr><td class="empty-row" colspan="7">Aún no hay juicios evaluativos registrados.</td></tr>';
  }

  function formatDisplayDate(value) {
    const date = String(value || '').slice(0,10).split('-');
    return date.length === 3 ? `${date[2]}/${date[1]}/${date[0]}` : String(value || '—');
  }

  function renderStudentAttendanceSummary(summary) {
    const stats = [
      ['Asistió',summary.presentes,'present'],
      ['Faltas',summary.ausencias,'absence'],
      ['Retrasos',summary.retrasos,'late'],
      ['Permisos',summary.permisos,'permission']
    ];
    $('#student-attendance-stats').innerHTML = stats.map(([label,value,kind]) =>
      `<article class="attendance-stat stat-${kind}"><span>${label}</span><strong>${Number(value)}</strong></article>`
    ).join('');
    const rate = Number(summary.porcentaje || 0);
    $('#student-attendance-rate').textContent = `${rate}%`;
    $('#student-attendance-ring').style.setProperty('--attendance-rate', `${rate}%`);
    const attended = Number(summary.presentes || 0) + Number(summary.retrasos || 0);
    const evaluated = Number(summary.evaluadas ?? (Number(summary.presentes || 0) + Number(summary.ausencias || 0) + Number(summary.retrasos || 0)));
    $('#student-attendance-detail').textContent = evaluated
      ? `${attended} de ${evaluated} clases evaluadas con asistencia o retraso. Los permisos no cuentan para el porcentaje.`
      : 'Aún no hay clases registradas.';
  }

  async function markRosterAttendance(studentId, status) {
    const student = attendanceRoster.find(record => Number(record.id) === studentId);
    if (!student) return;
    const buttons = $('#table-body').querySelectorAll(`[data-student-id="${studentId}"]`);
    buttons.forEach(button => { button.disabled = true; });
    try {
      await api('attendance/mark', {method:'POST',body:JSON.stringify({
        usuario_id:studentId,
        ficha_id:Number($('#attendance-ficha').value),
        fecha:bogotaDate(),
        estado:status,
        observacion:student.observacion || null
      })});
      await loadAttendanceRoster();
    } catch (error) {
      $('#table-message').textContent = error.message;
    } finally {
      buttons.forEach(button => { button.disabled = false; });
    }
  }

  function editRosterAttendance(studentId) {
    editingAttendance = attendanceRoster.find(record => Number(record.id) === studentId);
    if (!editingAttendance) return;
    const form = $('#attendance-edit-form');
    form.elements.estado.value = editingAttendance.estado || 'ausente';
    form.elements.observacion.value = editingAttendance.observacion || '';
    $('#attendance-edit-student').textContent = `${editingAttendance.nombres} ${editingAttendance.apellidos} · ${formatDisplayDate(bogotaDate())}`;
    $('#attendance-edit-dialog').querySelector('h2').textContent = editingAttendance.asistencia_id ? 'Editar asistencia y motivo' : 'Registrar asistencia y motivo';
    $('#attendance-edit-message').textContent = '';
    $('#attendance-edit-dialog').showModal();
  }

  async function deleteRosterAttendance(studentId) {
    const student = attendanceRoster.find(record => Number(record.id) === studentId);
    if (!student?.asistencia_id || !window.confirm(`¿Eliminar la asistencia de ${student.nombres} ${student.apellidos}?`)) return;
    try {
      await api(`attendance/${Number(student.asistencia_id)}`, {method:'DELETE'});
      await loadAttendanceRoster();
    } catch (error) {
      $('#table-message').textContent = error.message;
    }
  }

  async function showStudentRosterCard(studentId) {
    const student = attendanceRoster.find(record => Number(record.id) === studentId);
    if (!student?.codigo_qr) return;
    try {
      await showCard({codigo_qr:student.codigo_qr,usuario:{
        documento:student.documento,nombres:student.nombres,apellidos:student.apellidos,
        correo:student.correo,telefono:student.telefono,rol:'estudiante',foto:student.foto
      }});
      $('#download-card').classList.add('hidden');
    } catch (error) {
      $('#table-message').textContent = error.message;
    }
  }
  const hiddenColumns = {
    schedules: ['id','ficha_id','competencia_id','ambiente_id','instructor_id','dia_semana'],
    enrollments: ['id','ficha_id','usuario_id','documento'],
    users: ['id','rol_id','ultimo_acceso','creado_en'],
    'ficha-assignments': ['id','ficha_id','instructor_id','estudiante_id'],
    fichas: ['id','programa_id','nombre'],
    programs: ['id'],
    competencies: ['id','programa_id'],
    'learning-outcomes': ['id','competencia_id','programa_id'],
    'academic-calendar': ['id'],
    'audit-log': ['id','entidad_id'],
  };
  const columns = Object.keys(rows[0]).filter(column => !(hiddenColumns[activeModule] || []).includes(column));
  const headings = {
    codigo: 'Código', ficha_codigo: 'Número de ficha', ficha_nombre: 'Ficha', programa: 'Programa', trimestre: 'Trimestre',
    nivel: 'Nivel', duracion_trimestres: 'Duración (trimestres)', competencia: 'Competencia',
    competencia_id: 'Competencia', programa_id: 'Programa', competencia_programa: 'Programa',
    descripcion: 'Descripción', fecha: 'Fecha', tipo: 'Tipo',
    ambiente_codigo: 'Código ambiente', ambiente: 'Ambiente', instructor: 'Instructor',
    estudiante: 'Aprendiz', documento: 'Documento', hora_inicio: 'Desde', hora_fin: 'Hasta',
    dia: 'Día', asignatura: 'Asignatura', inscrito_en: 'Asignado el',
    rol: 'Tipo de usuario', activo: 'Estado', estudiante_nombres: 'Nombres',
    estudiante_apellidos: 'Apellidos', aprendices: 'Aprendices asignados',
    fecha_hora: 'Fecha y hora', actor: 'Usuario', accion: 'Acción', entidad: 'Registro',
    detalle: 'Detalle',
  };
  const academicCatalog = ['programs','competencies','learning-outcomes','academic-calendar'].includes(activeModule);
  const allowDelete = !academicCatalog && !modules[activeModule].readOnly && (currentUser.rol === 'administrador' || currentUser.rol === 'instructor' && ['enrollments','schedules'].includes(activeModule)) && !['blocks','registration-requests'].includes(activeModule);
  const allowEdit = !modules[activeModule].readOnly && modules[activeModule].roles.includes(currentUser.rol) && currentUser.rol !== 'estudiante' &&
    !(activeModule === 'environments' && currentUser.rol === 'instructor') &&
    !(activeModule === 'ficha-assignments' && currentUser.rol === 'instructor');
  const canManage = currentUser.rol === 'administrador';
  const hasActions = allowDelete || allowEdit || activeModule === 'registration-requests' && canManage;
  $('#table-head').innerHTML = `<tr>${columns.map(col => `<th>${escapeHtml(headings[col] || col.replaceAll('_',' '))}</th>`).join('')}${hasActions ? '<th>Acciones</th>' : ''}</tr>`;
  $('#table-body').innerHTML = rows.map(row => {
    const actions = [];
    if (activeModule === 'fichas' && currentUser.rol === 'administrador') {
      actions.push(`<button class="row-action ficha-view-action" data-view-ficha="${Number(row.id)}">Ver</button>`);
    }
    if (allowEdit) actions.push(`<button class="row-action" data-edit="${Number(row.id)}">Editar</button>`);
    if (activeModule === 'registration-requests' && canManage) {
      actions.push(`<button class="row-action request-approve" data-approve="${Number(row.id)}">Aprobar</button>`);
      actions.push(`<button class="row-action request-reject" data-reject="${Number(row.id)}">Rechazar</button>`);
    }
    if (activeModule === 'blocks' && canManage && !row.desbloqueado_en) actions.push(`<button class="row-action" data-unblock="${Number(row.id)}">Desbloquear</button>`);
    if (activeModule === 'loans' && canManage && row.estado === 'activo') actions.push(`<button class="row-action" data-return="${Number(row.id)}">Marcar devolución</button>`);
    if (allowDelete) actions.push(`<button class="row-action" data-delete="${Number(row.id)}">Eliminar</button>`);
    return `<tr>${columns.map(col => {
        const value = col === 'trimestre' && row[col] ? `Trimestre ${row[col]}`
          : col === 'nivel' ? ({tecnico:'Técnico',tecnologo:'Tecnólogo'}[row[col]] || row[col])
            : row[col];
      return `<td title="${escapeHtml(value)}">${escapeHtml(value)}</td>`;
    }).join('')}${hasActions ? `<td class="actions-cell"><div class="row-actions">${actions.join('')}</div></td>` : ''}</tr>`;
  }).join('');
  $('#table-body').querySelectorAll('[data-edit]').forEach(button => {
    const row = rows.find(record => Number(record.id) === Number(button.dataset.edit));
    button.addEventListener('click', () => openForm(row).catch(showError));
  });
  $('#table-body').querySelectorAll('[data-view-ficha]').forEach(button => {
    const row = rows.find(record => Number(record.id) === Number(button.dataset.viewFicha));
    button.addEventListener('click', () => showFichaDetails(row));
  });
  $('#table-body').querySelectorAll('[data-delete]').forEach(button => button.addEventListener('click', () => deleteRecord(Number(button.dataset.delete))));
  $('#table-body').querySelectorAll('[data-unblock]').forEach(button => button.addEventListener('click', () => unblockUser(Number(button.dataset.unblock))));
  $('#table-body').querySelectorAll('[data-return]').forEach(button => button.addEventListener('click', () => returnLoan(Number(button.dataset.return))));
  $('#table-body').querySelectorAll('[data-approve]').forEach(button => button.addEventListener('click', () => reviewRegistration(Number(button.dataset.approve), 'approve')));
  $('#table-body').querySelectorAll('[data-reject]').forEach(button => button.addEventListener('click', () => reviewRegistration(Number(button.dataset.reject), 'reject')));
}

$('#settings-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const submit = $('#save-settings');
  submit.disabled = true;
  $('#settings-message').textContent = '';
  try {
    const values = Object.fromEntries([...new FormData(form)].map(([key, value]) => [key, Number(value)]));
    const result = await api('settings', {method:'PUT',body:JSON.stringify(values)});
    Object.entries(result.settings).forEach(([key, value]) => {
      const input = form.elements.namedItem(key);
      if (input) input.value = String(value);
    });
    settingsChanged = false;
    $('#settings-message').textContent = result.message;
  } catch (error) {
    $('#settings-message').textContent = error.message;
  } finally {
    submit.disabled = false;
  }
});

function fieldInput([name,label,type='text'], value='', lookups=null) {
  const relations = {
    ficha_id: lookups?.fichas?.filter(item => String(item.id) === String(value) ||
      (activeModule === 'schedules' && currentUser.rol === 'administrador'
        ? item.estado === 'activa'
        : Number(item.vigente) === 1))
      .map(item => [item.id, `${item.codigo} · ${item.programa || item.nombre}${item.trimestre ? ` · Trimestre ${Number(item.trimestre)}` : ''}`]),
    programa_id: lookups?.programas?.filter(item => item.estado === 'activo' || String(item.id) === String(value))
      .map(item => [item.id, `${item.codigo} · ${item.nombre}`]),
    competencia_id: lookups?.competencias?.filter(item => item.estado === 'activo' || String(item.id) === String(value))
      .map(item => [item.id, `${item.codigo} · ${item.nombre}`]),
    ambiente_id: lookups?.ambientes?.map(item => [item.id, `${item.codigo} · ${item.nombre}`]),
    instructor_id: lookups?.instructores?.map(item => [item.id, `${item.nombres} ${item.apellidos} · ${item.documento}`]),
    usuario_id: activeModule === 'enrollments' ? lookups?.estudiantes?.map(item => [item.id, `${item.nombres} ${item.apellidos} · ${item.documento}`]) : null,
    estudiante_id: activeModule === 'evaluations' ? lookups?.estudiantes?.map(item => [item.id, `${item.nombres} ${item.apellidos} · ${item.documento}`]) : null,
    resultado_id: activeModule === 'evaluations' ? lookups?.resultados?.map(item => [item.id, item.etiqueta]) : null,
    asistencia_id: lookups?.ausencias?.map(item => [item.id, `#${item.id} · ${item.fecha} · Aprendiz ${item.usuario_id}`]),
    rol_id: [[1,'Administrador'],[2,'Instructor'],[3,'Estudiante'],[4,'Portería']],
  };
  let selectOptions = null;
  if (relations[name]) selectOptions = relations[name];
  else if (activeModule === 'programs' && name === 'nivel') selectOptions = [['tecnico','Técnico'],['tecnologo','Tecnólogo']];
  else if (['programs','competencies','learning-outcomes','academic-calendar'].includes(activeModule) && name === 'estado') selectOptions = [['activo','Activo'],['inactivo','Inactivo']];
  else if (activeModule === 'academic-calendar' && name === 'tipo') selectOptions = [['festivo','Festivo'],['vacaciones','Vacaciones']];
  else if (activeModule === 'evaluations' && name === 'juicio') selectOptions = [['aprobado','Aprobado'],['no_aprobado','No aprobado'],['por_evaluar','Por evaluar']];
  else if (name === 'trimestre') selectOptions = [[1,'Trimestre 1'],[2,'Trimestre 2'],[3,'Trimestre 3'],[4,'Trimestre 4'],[5,'Trimestre 5'],[6,'Trimestre 6']];
  else if (name === 'dia_semana') selectOptions = [[1,'Lunes'],[2,'Martes'],[3,'Miércoles'],[4,'Jueves'],[5,'Viernes'],[6,'Sábado'],[7,'Domingo']];
  if (selectOptions) {
    const optional = ['trimestre','asistencia_id'].includes(name) ||
      name === 'competencia_id' && editId !== null && !value;
    const options = selectOptions.map(([optionValue,optionLabel]) =>
      `<option value="${escapeHtml(optionValue)}"${String(value) === String(optionValue) ? ' selected' : ''}>${escapeHtml(optionLabel)}</option>`
    ).join('');
    return `<label>${escapeHtml(label)}<select name="${escapeHtml(name)}" ${optional ? '' : 'required'}>${optional ? '<option value="">Sin asignar</option>' : '<option value="">Selecciona una opción</option>'}${options}</select></label>`;
  }
  const inputType = ['number','email','date','time','datetime-local','password'].includes(type) ? type : 'text';
  if (name === 'descripcion' && ['learning-outcomes','academic-calendar'].includes(activeModule)) {
    const maximum = activeModule === 'academic-calendar' ? 250 : 500;
    return `<label>${escapeHtml(label)}<textarea name="${escapeHtml(name)}" rows="3" maxlength="${maximum}" required>${escapeHtml(value)}</textarea></label>`;
  }
  const optionalIdentity = ['accesses','temporary-permits'].includes(activeModule) && ['usuario_id','visitante_id'].includes(name);
  const fichaNumber = activeModule === 'fichas' && name === 'codigo';
  const required = ['password','documento','nombres','apellidos','correo','rol_id','codigo','nombre','programa','ubicacion','capacidad','ficha_id','programa_id','ambiente_id','instructor_id','hora_inicio','hora_fin','asignatura','inicio','fin','motivo','estado','usuario_id','fecha','tipo','serial','categoria','equipo_id','vence_en','dia_semana','desde','hasta','fecha_inicio','fecha_fin','duracion_trimestres','descripcion'].includes(name) && !(optionalIdentity || activeModule === 'accesses' && name === 'serial');
  const maximumLength = name === 'descripcion'
    ? `maxlength="${activeModule === 'academic-calendar' ? 250 : 500}"`
    : name === 'nombre' ? `maxlength="${activeModule === 'programs' ? 150 : 180}"`
      : name === 'codigo' ? 'maxlength="40"' : '';
  const minValue = name === 'duracion_trimestres' ? 'min="1" max="65535"' : '';
  return `<label>${escapeHtml(label)}<input name="${escapeHtml(name)}" type="${fichaNumber ? 'text' : inputType}" value="${inputType === 'password' ? '' : escapeHtml(value)}" ${name === 'password' ? 'minlength="8"' : ''} ${fichaNumber ? 'inputmode="numeric" pattern="[0-9]{7}" maxlength="7" minlength="7" title="Escribe exactamente 7 dígitos numéricos."' : ''} ${maximumLength} ${minValue} ${required ? 'required' : ''}></label>`;
}

function showFichaDetails(ficha) {
  if (!ficha) return;
  $('#ficha-detail-body').innerHTML = `
    <div class="ficha-detail-number">${escapeHtml(ficha.codigo)}</div>
    <dl class="ficha-detail-list">
      <div><dt>Programa</dt><dd>${escapeHtml(ficha.programa || '—')}</dd></div>
      <div><dt>Fecha de inicio</dt><dd>${escapeHtml(ficha.fecha_inicio || '—')}</dd></div>
      <div><dt>Fecha de fin</dt><dd>${escapeHtml(ficha.fecha_fin || '—')}</dd></div>
      <div><dt>Estado</dt><dd>${escapeHtml(ficha.estado || '—')}</dd></div>
    </dl>`;
  $('#ficha-detail-dialog').showModal();
}

async function openForm(record = null) {
  editId = record ? Number(record.id) : null;
  $('#dialog-title').textContent = `${editId ? 'Editar' : 'Nuevo registro'} · ${modules[activeModule].label}`;
  $('#dialog-message').textContent = '';
  let lookups = ['schedules','enrollments','ficha-assignments','fichas','competencies','learning-outcomes','evaluations'].includes(activeModule)
    ? await api('options') : null;
  if (activeModule === 'novelties') {
    const absences = await api('attendance?estado=ausente');
    if (record?.asistencia_id && !absences.some(item => Number(item.id) === Number(record.asistencia_id))) {
      absences.unshift({id:record.asistencia_id,fecha:record.fecha,usuario_id:record.usuario_id});
    }
    lookups = {ausencias:absences};
  }
  $('#record-fields').innerHTML = modules[activeModule].fields.map(field => {
    const [name] = field;
    let value = record?.[name] ?? (name === 'estado' ? 'activo' : name === 'tipo' && activeModule === 'academic-calendar' ? 'festivo' : '');
    if (field[2] === 'datetime-local' && value) value = String(value).replace(' ', 'T').slice(0,16);
    const input = fieldInput(field, value, lookups);
    return editId && name === 'password' ? input.replace(' required', '') : input;
  }).join('');
  if (activeModule === 'schedules') {
    const fichaField = $('#record-fields [name="ficha_id"]');
    const competencyField = $('#record-fields [name="competencia_id"]');
    const refreshCompetencies = () => {
      const ficha = lookups.fichas.find(item => String(item.id) === fichaField.value);
      const existingCompetency = record?.competencia_id;
      const compatible = lookups.competencias.filter(item =>
        ficha?.programa_id && Number(item.programa_id) === Number(ficha.programa_id) &&
        (item.estado === 'activo' || Number(item.id) === Number(existingCompetency))
      );
      const selected = compatible.some(item => String(item.id) === competencyField.value)
        ? competencyField.value : '';
      competencyField.innerHTML = '<option value="">Selecciona una competencia</option>' +
        compatible.map(item => `<option value="${Number(item.id)}">${escapeHtml(`${item.codigo} · ${item.nombre}`)}</option>`).join('');
      competencyField.value = selected;
    };
    fichaField.addEventListener('change', refreshCompetencies);
    refreshCompetencies();
  }
  if (activeModule === 'novelties') {
    const typeField = $('#record-fields [name="tipo"]');
    const attendanceField = $('#record-fields [name="asistencia_id"]');
    const updateExcuseRequirement = () => {
      attendanceField.required = typeField.value.trim().toLowerCase() === 'inasistencia';
    };
    typeField.addEventListener('input', updateExcuseRequirement);
    updateExcuseRequirement();
  }
  $('#record-dialog').showModal();
}

function closeFichaDetails() {
  $('#ficha-detail-dialog').close();
}

$('#close-ficha-detail').addEventListener('click', closeFichaDetails);
$('#dismiss-ficha-detail').addEventListener('click', closeFichaDetails);

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
  const confirmation = activeModule === 'fichas'
    ? '¿Eliminar esta ficha? También se eliminarán sus aprendices inscritos, horarios, asistencias, asignaciones de instructor y solicitudes. Las cuentas de usuario se conservarán.'
    : '¿Eliminar este registro? Esta acción puede estar limitada por relaciones existentes.';
  if (!window.confirm(confirmation)) return;
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

$('#legacy-login-form').addEventListener('submit', async event => {
  event.preventDefault();
  $('#login-message').textContent = '';
  const form = new FormData(event.currentTarget);
  try {
    const authenticatedUser = await api('auth/login',{method:'POST',body:JSON.stringify({
      identificador:form.get('identificador'),
      password:form.get('password'),
      pin:form.get('pin')
    })});
    if (loginExpectedRole && authenticatedUser.rol !== loginExpectedRole) {
      await api('auth/logout', {method:'POST'});
      throw new Error('Esta cuenta no tiene el rol requerido para este acceso.');
    }
    await startApp();
    await openMyCard();
    $('#card-dialog-message').textContent = authenticatedUser.pin_configurado
      ? 'Puedes ingresar con tu documento o correo, contraseña, o con el QR de este carné y tu PIN.'
      : 'Para habilitar el ingreso con QR, vuelve a ingresar con tu documento o correo, contraseña y un PIN de 4 a 6 números.';
  } catch (error) {
    $('#login-message').textContent = error.message;
  }
});

$('#landing-student-login').addEventListener('click', () => showLogin('estudiante'));
$('#login-home').addEventListener('click', showLanding);
$('#landing-staff-login').addEventListener('click', () => showLogin());
$('#landing-admin-login').addEventListener('click', () => showLogin('administrador'));
$('#landing-register').addEventListener('click', () => openRegistration().catch(error => {
  $('#landing-message').textContent = error.message;
}));

function showLanding() {
  stopLoginScanner();
  $('#login-screen').classList.add('hidden');
  $('#landing-screen').classList.remove('hidden');
}

function showLogin(expectedRole = null) {
  loginExpectedRole = expectedRole;
  stopLoginScanner();
  pendingLoginQrCode = null;
  $('#login-pin-panel').classList.add('hidden');
  $('#login-pin-form').reset();
  $('#legacy-login-form').reset();
  $('#login-message').textContent = '';
  $('#login-screen .login-card h1').textContent = expectedRole === 'administrador' ? 'Acceso de administrador' : 'Acceso a tu cuenta';
  $('#login-screen .login-card > .muted').textContent = expectedRole === 'administrador'
    ? 'Escanea el carné QR de una cuenta administradora. Este acceso es exclusivo para administración.'
    : 'Ingresa con el código QR de tu carné. Este acceso funciona para estudiantes y equipo institucional.';
  $('#login-scan-message').textContent = expectedRole === 'administrador'
    ? 'Escanea el QR asignado a una cuenta administradora o elige una imagen del código.'
    : 'Escanea el QR de tu carné o elige una imagen que lo contenga. También necesitarás el PIN del carné.';
  $('#landing-screen').classList.add('hidden');
  $('#login-screen').classList.remove('hidden');
}

async function openRegistration(role = 'estudiante') {
  const options = await api('auth/registration-options');
  const fichaSelect = $('#registration-ficha');
  $('#registration-form').reset();
  fichaSelect.innerHTML = '<option value="">Selecciona una ficha activa</option>' + options.fichas.map(ficha =>
    `<option value="${Number(ficha.id)}">${escapeHtml(ficha.codigo)} · ${escapeHtml(ficha.programa || ficha.nombre)}${ficha.trimestre ? ` · Trimestre ${Number(ficha.trimestre)}` : ''}</option>`
  ).join('');
  fichaSelect.disabled = options.fichas.length === 0;
  $('#registration-ficha-options').innerHTML = options.fichas.map(ficha => {
    const description = [ficha.programa, ficha.trimestre ? `Trimestre ${Number(ficha.trimestre)}` : ''].filter(Boolean).join(' · ');
    return `<button class="role-picker-option ficha-picker-option" type="button" role="option" data-value="${Number(ficha.id)}" aria-selected="false"><span class="role-option-icon" aria-hidden="true">▤</span><span><strong>${escapeHtml(ficha.codigo)} · ${escapeHtml(ficha.programa || ficha.nombre)}</strong><small>${escapeHtml(description || 'Ficha de formación activa')}</small></span><span class="role-option-check" aria-hidden="true">✓</span></button>`;
  }).join('');
  syncRegistrationFichaPicker();
  updateRegistrationPhotoName();
  $('#registration-role').value = role;
  syncRegistrationRolePicker();
  closeRegistrationRolePicker();
  updateRegistrationRole();
  $('#registration-message').textContent = '';
  $('#landing-message').textContent = '';
  $('#registration-dialog').showModal();
}

function syncRegistrationRolePicker() {
  const select = $('#registration-role');
  const selected = select.selectedOptions[0];
  $('#registration-role-value').textContent = selected?.textContent.split(' · ')[0] || 'Selecciona un tipo';
  $('#registration-role-options').querySelectorAll('[data-value]').forEach(option => {
    const isSelected = option.dataset.value === select.value;
    option.setAttribute('aria-selected', String(isSelected));
    option.classList.toggle('selected', isSelected);
  });
}

function closeRegistrationRolePicker(returnFocus = false) {
  const trigger = $('#registration-role-trigger');
  const menu = $('#registration-role-options');
  menu.classList.add('hidden');
  trigger.setAttribute('aria-expanded', 'false');
  trigger.closest('.role-picker').classList.remove('is-open');
  if (returnFocus) trigger.focus();
}

function openRegistrationRolePicker() {
  closeRegistrationFichaPicker();
  const trigger = $('#registration-role-trigger');
  const menu = $('#registration-role-options');
  menu.classList.remove('hidden');
  trigger.setAttribute('aria-expanded', 'true');
  trigger.closest('.role-picker').classList.add('is-open');
  const selected = menu.querySelector('[aria-selected="true"]');
  selected?.focus();
}

function syncRegistrationFichaPicker() {
  const select = $('#registration-ficha');
  const selected = select.selectedOptions[0];
  const trigger = $('#registration-ficha-trigger');
  const value = selected?.value ? selected.textContent : 'Selecciona una ficha activa';
  $('#registration-ficha-value').textContent = value;
  trigger.setAttribute('aria-label', value);
  $('#registration-ficha-options').querySelectorAll('[data-value]').forEach(option => {
    const isSelected = option.dataset.value === select.value;
    option.setAttribute('aria-selected', String(isSelected));
    option.classList.toggle('selected', isSelected);
  });
}

function closeRegistrationFichaPicker(returnFocus = false) {
  const trigger = $('#registration-ficha-trigger');
  $('#registration-ficha-options').classList.add('hidden');
  trigger.setAttribute('aria-expanded', 'false');
  trigger.closest('.role-picker').classList.remove('is-open');
  if (returnFocus) trigger.focus();
}

function openRegistrationFichaPicker() {
  if ($('#registration-ficha').disabled) return;
  closeRegistrationRolePicker();
  const menu = $('#registration-ficha-options');
  const trigger = $('#registration-ficha-trigger');
  menu.classList.remove('hidden');
  trigger.setAttribute('aria-expanded', 'true');
  trigger.closest('.role-picker').classList.add('is-open');
  const selected = menu.querySelector('[aria-selected="true"]');
  (selected || menu.querySelector('[data-value]'))?.focus();
}

$('#registration-role-trigger').addEventListener('click', () => {
  $('#registration-role-trigger').getAttribute('aria-expanded') === 'true'
    ? closeRegistrationRolePicker()
    : openRegistrationRolePicker();
});

$('#registration-role-options').addEventListener('click', event => {
  const option = event.target.closest('[data-value]');
  if (!option) return;
  $('#registration-role').value = option.dataset.value;
  $('#registration-role').dispatchEvent(new Event('change', {bubbles:true}));
  syncRegistrationRolePicker();
  closeRegistrationRolePicker(true);
});

$('#registration-role-trigger').addEventListener('keydown', event => {
  if (['ArrowDown','ArrowUp','Enter',' '].includes(event.key)) {
    event.preventDefault();
    openRegistrationRolePicker();
  }
});

$('#registration-role-options').addEventListener('keydown', event => {
  const options = [...$('#registration-role-options').querySelectorAll('[data-value]')];
  const current = options.indexOf(document.activeElement);
  let next = current;
  if (event.key === 'ArrowDown') next = (current + 1) % options.length;
  else if (event.key === 'ArrowUp') next = (current - 1 + options.length) % options.length;
  else if (event.key === 'Home') next = 0;
  else if (event.key === 'End') next = options.length - 1;
  else if (event.key === 'Escape') {
    event.preventDefault();
    closeRegistrationRolePicker(true);
    return;
  } else if (event.key === 'Tab') {
    closeRegistrationRolePicker();
    return;
  } else {
    return;
  }
  event.preventDefault();
  options[next].focus();
});

$('#registration-ficha-trigger').addEventListener('click', () => {
  $('#registration-ficha-trigger').getAttribute('aria-expanded') === 'true'
    ? closeRegistrationFichaPicker()
    : openRegistrationFichaPicker();
});

$('#registration-ficha-options').addEventListener('click', event => {
  const option = event.target.closest('[data-value]');
  if (!option) return;
  $('#registration-ficha').value = option.dataset.value;
  $('#registration-ficha').dispatchEvent(new Event('change', {bubbles:true}));
  syncRegistrationFichaPicker();
  closeRegistrationFichaPicker(true);
});

$('#registration-ficha-trigger').addEventListener('keydown', event => {
  if (['ArrowDown','ArrowUp','Enter',' '].includes(event.key)) {
    event.preventDefault();
    openRegistrationFichaPicker();
  }
});

$('#registration-ficha-options').addEventListener('keydown', event => {
  const options = [...$('#registration-ficha-options').querySelectorAll('[data-value]')];
  const current = options.indexOf(document.activeElement);
  let next = current;
  if (event.key === 'ArrowDown') next = (current + 1) % options.length;
  else if (event.key === 'ArrowUp') next = (current - 1 + options.length) % options.length;
  else if (event.key === 'Home') next = 0;
  else if (event.key === 'End') next = options.length - 1;
  else if (event.key === 'Escape') {
    event.preventDefault();
    closeRegistrationFichaPicker(true);
    return;
  } else if (event.key === 'Tab') {
    closeRegistrationFichaPicker();
    return;
  } else {
    return;
  }
  event.preventDefault();
  options[next].focus();
});

document.addEventListener('pointerdown', event => {
  if (!event.target.closest('.role-picker')) closeRegistrationRolePicker();
  if (!event.target.closest('.ficha-picker')) closeRegistrationFichaPicker();
  if (!event.target.closest('.progress-ficha-picker')) closeAttendanceFichaPicker();
});

function updateRegistrationRole() {
  const student = $('#registration-role').value === 'estudiante';
  $('#registration-ficha-field').classList.toggle('hidden', !student);
  $('#registration-ficha').required = student;
  $('#registration-ficha').disabled = !student || $('#registration-ficha').options.length < 2;
  $('#registration-ficha-trigger').disabled = $('#registration-ficha').disabled;
  $('#submit-registration').disabled = student && $('#registration-ficha').options.length < 2;
  $('#registration-title').textContent = student ? 'Registrar como estudiante' : 'Solicitar cuenta de personal';
  $('#registration-intro').textContent = student && $('#registration-ficha').options.length < 2
    ? 'Todavía no hay fichas activas disponibles. Consulta al administrador para que cree o active una ficha.'
    : student
    ? 'Completa tus datos y selecciona una ficha activa para crear tu cuenta. Al finalizar, se generará un carné QR PNG para iniciar sesión.'
    : 'Completa tus datos y guarda el carné QR. Un administrador debe aprobar la solicitud antes de que puedas iniciar sesión.';
  $('#submit-registration').textContent = student ? 'Crear cuenta y carné' : 'Enviar solicitud y generar carné';
}

$('#registration-role').addEventListener('change', updateRegistrationRole);
$('#registration-ficha').addEventListener('change', syncRegistrationFichaPicker);

function updateRegistrationPhotoName() {
  const file = $('#registration-photo').files?.[0];
  $('#registration-photo-name').textContent = file ? file.name : 'JPG, PNG o WEBP · máximo 1 MB';
  $('#clear-registration-photo').classList.toggle('hidden', !file);
}

$('#choose-registration-photo').addEventListener('click', () => $('#registration-photo').click());
$('#registration-photo').addEventListener('change', updateRegistrationPhotoName);
$('#clear-registration-photo').addEventListener('click', () => {
  $('#registration-photo').value = '';
  updateRegistrationPhotoName();
});

function stopLoginScanner(controls = loginScannerControls) {
  controls?.stop();
  loginScannerControls = null;
  $('#login-video').pause();
  $('#login-video').srcObject = null;
  $('#login-scanner').classList.add('hidden');
}

async function loginWithQr(value, controls = loginScannerControls) {
  const qrCode = value.trim();
  if (!qrCode) return;
  loginScanCompleted = true;
  stopLoginScanner(controls);
  $('#login-scan-message').textContent = 'Validando el carné...';
  try {
    const result = await api('auth/qr-preview', {method:'POST',body:JSON.stringify({codigo_qr:qrCode})});
    const account = result.usuario;
    if (!account) throw new Error('No se pudo validar el carné.');
    pendingLoginQrCode = qrCode;
    $('#login-pin-name').textContent = `${account.nombres} ${account.apellidos}`;
    $('#login-pin-document').textContent = 'Confirma que la foto y el nombre sean tuyos.';
    const photo = $('#login-pin-photo');
    if (account.foto) {
      photo.src = account.foto;
      photo.classList.remove('hidden');
    } else {
      photo.removeAttribute('src');
      photo.classList.add('hidden');
    }
    $('#login-pin-panel').classList.remove('hidden');
    $('#login-scan-message').textContent = 'Confirma que el carné corresponde a tu cuenta e ingresa tu PIN.';
    $('#login-pin-form [name="pin"]').focus();
  } catch (error) {
    $('#login-scan-message').textContent = error.message;
  }
}

$('#login-pin-form').addEventListener('submit', async event => {
  event.preventDefault();
  if (!pendingLoginQrCode) return;
  const submit = event.currentTarget.querySelector('[type="submit"]');
  submit.disabled = true;
  $('#login-scan-message').textContent = 'Verificando el PIN...';
  try {
    const pin = new FormData(event.currentTarget).get('pin');
    const authenticatedUser = await api('auth/qr-login', {
      method:'POST',
      body:JSON.stringify({codigo_qr:pendingLoginQrCode,pin})
    });
    if (loginExpectedRole && authenticatedUser.rol !== loginExpectedRole) {
      await api('auth/logout', {method:'POST'});
      throw new Error('Este carné no corresponde a una cuenta administradora. Usa el acceso indicado para tu rol.');
    }
    pendingLoginQrCode = null;
    await startApp();
  } catch (error) {
    $('#login-scan-message').textContent = error.message;
  } finally {
    submit.disabled = false;
  }
});

$('#login-pin-cancel').addEventListener('click', () => {
  pendingLoginQrCode = null;
  $('#login-pin-form').reset();
  $('#login-pin-panel').classList.add('hidden');
  $('#login-scan-message').textContent = 'Escanea el QR de tu carné o elige una imagen que lo contenga.';
});

$('#scan-login-barcode').addEventListener('click', async () => {
  if (loginScannerStarting || loginScannerControls) return;
  $('#login-scan-message').textContent = 'Preparando cámara para escanear el QR...';
  if (!navigator.mediaDevices?.getUserMedia) {
    $('#login-scan-message').textContent = 'La cámara requiere abrir el sitio en localhost o HTTPS. También puedes elegir una imagen del QR.';
    return;
  }
  if (!window.ZXingBrowser?.BrowserMultiFormatReader) {
    $('#login-scan-message').textContent = 'No se pudo cargar el lector. Comprueba tu conexión o elige una imagen del QR.';
    return;
  }
  loginScannerStarting = true;
  loginScanCompleted = false;
  try {
    const controls = await createQrReader().decodeFromConstraints(
      {video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false},
      $('#login-video'),
      (result, error, scanControls) => {
        if (!result || loginScanCompleted) return;
        loginWithQr(result.getText(), scanControls);
      }
    );
    if (loginScanCompleted) {
      controls.stop();
      return;
    }
    loginScannerControls = controls;
    $('#login-scanner').classList.remove('hidden');
    $('#login-scan-message').textContent = 'Centra el código QR completo en la cámara, con buena luz y sin reflejos.';
  } catch (error) {
    stopLoginScanner();
    $('#login-scan-message').textContent = `No se pudo escanear: ${error.message || 'comprueba los permisos de cámara o elige una imagen del QR.'}`;
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
    $('#login-scan-message').textContent = 'No se pudo cargar el lector. Comprueba tu conexión e inténtalo de nuevo.';
    input.value = '';
    return;
  }

  loginScanCompleted = false;
  $('#login-scan-message').textContent = 'Leyendo el QR de la imagen...';
  let imageUrl;
  try {
    imageUrl = URL.createObjectURL(file);
    const image = new Image();
    image.src = imageUrl;
    await image.decode();
    const result = await createQrReader().decodeFromImageElement(image);
    await loginWithQr(result.getText());
  } catch (error) {
    const message = error?.name === 'NotFoundException'
      ? 'No se encontró un QR legible. Elige una imagen nítida con el código completo.'
      : `No se pudo leer la foto: ${error.message || 'prueba con otra imagen.'}`;
    $('#login-scan-message').textContent = message;
  } finally {
    if (imageUrl) URL.revokeObjectURL(imageUrl);
    input.value = '';
  }
});

function createQrReader() {
  if (!window.ZXingBrowser?.BrowserMultiFormatReader || !window.ZXingBrowser?.BarcodeFormat?.QR_CODE) {
    throw new Error('No se pudo cargar el lector de códigos QR. Comprueba tu conexión.');
  }
  const reader = new window.ZXingBrowser.BrowserMultiFormatReader();
  reader.possibleFormats = [window.ZXingBrowser.BarcodeFormat.QR_CODE];
  return reader;
}

function closeRegistration() {
  $('#registration-dialog').close();
}

$('#close-registration').addEventListener('click', closeRegistration);
$('#cancel-registration').addEventListener('click', closeRegistration);

function reloadAttendanceView() {
  const module = activeModule === 'student-progress' && ['administrador','instructor'].includes(currentUser?.rol)
    ? 'student-progress'
    : 'attendance';
  return showModule(module);
}

function closeAttendanceFichaPicker(returnFocus = false) {
  const trigger = $('#attendance-ficha-trigger');
  $('#attendance-ficha-options').classList.add('hidden');
  trigger.setAttribute('aria-expanded', 'false');
  trigger.closest('.progress-ficha-picker').classList.remove('is-open');
  if (returnFocus) trigger.focus();
}

function openAttendanceFichaPicker() {
  if ($('#attendance-ficha-trigger').disabled) return;
  const menu = $('#attendance-ficha-options');
  const trigger = $('#attendance-ficha-trigger');
  menu.classList.remove('hidden');
  trigger.setAttribute('aria-expanded', 'true');
  trigger.closest('.progress-ficha-picker').classList.add('is-open');
  (menu.querySelector('[aria-selected="true"]') || menu.querySelector('[data-value]'))?.focus();
}

$('#attendance-ficha-trigger').addEventListener('click', () => {
  $('#attendance-ficha-trigger').getAttribute('aria-expanded') === 'true'
    ? closeAttendanceFichaPicker()
    : openAttendanceFichaPicker();
});

$('#attendance-ficha-trigger').addEventListener('keydown', event => {
  if (['ArrowDown','ArrowUp','Enter',' '].includes(event.key)) {
    event.preventDefault();
    openAttendanceFichaPicker();
  }
});

$('#attendance-ficha-options').addEventListener('click', event => {
  const option = event.target.closest('[data-value]');
  if (!option) return;
  $('#attendance-ficha').value = option.dataset.value;
  updateAttendanceFichaOptions(attendanceFichas, option.dataset.value);
  progressStudent = null;
  closeAttendanceFichaPicker(true);
  reloadAttendanceView().catch(showError);
});

$('#attendance-ficha-options').addEventListener('keydown', event => {
  const options = [...$('#attendance-ficha-options').querySelectorAll('[data-value]')];
  const current = options.indexOf(document.activeElement);
  let next = current;
  if (event.key === 'ArrowDown') next = (current + 1) % options.length;
  else if (event.key === 'ArrowUp') next = (current - 1 + options.length) % options.length;
  else if (event.key === 'Home') next = 0;
  else if (event.key === 'End') next = options.length - 1;
  else if (event.key === 'Escape') {
    event.preventDefault();
    closeAttendanceFichaPicker(true);
    return;
  } else if (event.key === 'Tab') {
    closeAttendanceFichaPicker();
    return;
  } else {
    return;
  }
  event.preventDefault();
  options[next]?.focus();
});
$('#attendance-refresh').addEventListener('click', () => reloadAttendanceView().catch(showError));
$('#availability-toggle').addEventListener('click', () => {
  showWeeklyAvailability = !showWeeklyAvailability;
  showModule('student-progress').catch(showError);
});

function closeAttendanceEdit() {
  $('#attendance-edit-dialog').close();
  editingAttendance = null;
}

$('#close-attendance-edit').addEventListener('click', closeAttendanceEdit);
$('#cancel-attendance-edit').addEventListener('click', closeAttendanceEdit);
$('#attendance-edit-form').addEventListener('submit', async event => {
  event.preventDefault();
  if (!editingAttendance) return;
  const form = event.currentTarget;
  const submit = form.querySelector('[type="submit"]');
  submit.disabled = true;
  $('#attendance-edit-message').textContent = '';
  try {
    if (editingAttendance.asistencia_id) {
      await api(`attendance/${Number(editingAttendance.asistencia_id)}`, {method:'PATCH',body:JSON.stringify({
        estado:form.elements.estado.value,
        observacion:form.elements.observacion.value.trim()
      })});
    } else {
      await api('attendance/mark', {method:'POST',body:JSON.stringify({
        usuario_id:Number(editingAttendance.id),
        ficha_id:Number($('#attendance-ficha').value),
        fecha:bogotaDate(),
        estado:form.elements.estado.value,
        observacion:form.elements.observacion.value.trim()
      })});
    }
    closeAttendanceEdit();
    await reloadAttendanceView();
  } catch (error) {
    $('#attendance-edit-message').textContent = error.message;
  } finally {
    submit.disabled = false;
  }
});

function readFileAsDataUrl(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.addEventListener('load', () => resolve(reader.result));
    reader.addEventListener('error', () => reject(new Error('No se pudo leer la foto seleccionada.')));
    reader.readAsDataURL(file);
  });
}

function loadImage(source) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.addEventListener('load', () => resolve(image));
    image.addEventListener('error', () => reject(new Error('No se pudo cargar una imagen del carné.')));
    image.src = source;
  });
}

async function renderCard(card) {
  if (typeof window.QRCode !== 'function') {
    throw new Error('No se pudo cargar el generador QR. Comprueba tu conexión y vuelve a intentarlo.');
  }
  const qrContainer = document.createElement('div');
  new window.QRCode(qrContainer, {
    text: card.codigo_qr,
    width: 300,
    height: 300,
    correctLevel: window.QRCode.CorrectLevel.H
  });
  const qrCanvas = qrContainer.querySelector('canvas');
  const qrElement = qrCanvas || qrContainer.querySelector('img');
  if (!qrElement) throw new Error('No se pudo generar el código QR del carné.');
  const qrImage = await loadImage(qrCanvas ? qrCanvas.toDataURL('image/png') : qrElement.src);
  const logo = await loadImage('web/assets/images/portal-mark.svg');
  const canvas = document.createElement('canvas');
  canvas.width = 900;
  canvas.height = 1400;
  const context = canvas.getContext('2d');
  const roundedRect = (x, y, width, height, radius) => {
    context.beginPath();
    context.moveTo(x + radius, y);
    context.lineTo(x + width - radius, y);
    context.quadraticCurveTo(x + width, y, x + width, y + radius);
    context.lineTo(x + width, y + height - radius);
    context.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    context.lineTo(x + radius, y + height);
    context.quadraticCurveTo(x, y + height, x, y + height - radius);
    context.lineTo(x, y + radius);
    context.quadraticCurveTo(x, y, x + radius, y);
    context.closePath();
  };
  const wrapText = (text, maxWidth, maxLines) => {
    const words = String(text || '').split(/\s+/).filter(Boolean);
    const lines = [];
    let line = '';
    for (const word of words) {
      if (context.measureText(word).width > maxWidth) {
        if (line) {
          lines.push(line);
          line = '';
        }
        let chunk = '';
        for (const character of word) {
          if (chunk && context.measureText(chunk + character).width > maxWidth) {
            lines.push(chunk);
            chunk = character;
          } else {
            chunk += character;
          }
        }
        line = chunk;
        continue;
      }
      const candidate = line ? `${line} ${word}` : word;
      if (line && context.measureText(candidate).width > maxWidth) {
        lines.push(line);
        line = word;
      } else {
        line = candidate;
      }
    }
    if (line) lines.push(line);
    if (lines.length > maxLines) {
      lines.length = maxLines;
      let finalLine = lines[maxLines - 1];
      while (finalLine && context.measureText(`${finalLine}…`).width > maxWidth) {
        finalLine = finalLine.slice(0, -1);
      }
      lines[maxLines - 1] = `${finalLine.trimEnd()}…`;
    }
    return lines;
  };
  const drawField = (label, value, x, y, width) => {
    roundedRect(x, y, width, 128, 20);
    context.fillStyle = '#f5faf4';
    context.fill();
    context.strokeStyle = '#dce9dc';
    context.lineWidth = 2;
    context.stroke();
    context.fillStyle = '#718376';
    context.font = '700 17px Arial';
    context.fillText(label.toUpperCase(), x + 20, y + 34);
    context.fillStyle = '#183528';
    context.font = 'bold 22px Arial';
    wrapText(value || 'No registrado', width - 40, 2).forEach((line, index) => {
      context.fillText(line, x + 20, y + 72 + index * 29);
    });
  };

  context.fillStyle = '#f3f7f1';
  context.fillRect(0, 0, canvas.width, canvas.height);
  roundedRect(30, 30, 840, 1340, 34);
  context.save();
  context.clip();
  context.fillStyle = '#ffffff';
  context.fillRect(30, 30, 840, 1340);
  context.fillStyle = '#075b37';
  context.fillRect(30, 30, 840, 250);
  context.drawImage(logo, 72, 67, 86, 86);
  context.fillStyle = '#ffffff';
  context.font = 'bold 34px Arial';
  context.fillText('GESTIÓN INSTITUCIONAL', 180, 110, 620);
  context.font = '21px Arial';
  context.fillStyle = '#d9f0df';
  context.fillText('SERVICIO NACIONAL DE APRENDIZAJE · SENA', 180, 151, 630);
  context.fillStyle = '#ffffff';
  context.font = 'bold 27px Arial';
  context.fillText('CARNÉ DIGITAL', 74, 225);
  context.restore();

  context.strokeStyle = '#dce9dc';
  context.lineWidth = 3;
  roundedRect(30, 30, 840, 1340, 34);
  context.stroke();

  const user = card.usuario || {};
  const roleLabels = {estudiante:'APRENDIZ',instructor:'INSTRUCTOR',administrador:'ADMINISTRADOR',portero:'PORTERÍA'};
  roundedRect(74, 316, 270, 48, 24);
  context.fillStyle = '#e7f5e8';
  context.fill();
  context.fillStyle = '#087c45';
  context.font = 'bold 20px Arial';
  context.fillText(roleLabels[user.rol] || String(user.rol || 'USUARIO').toUpperCase(), 96, 348, 230);

  context.fillStyle = '#183528';
  context.font = 'bold 38px Arial';
  wrapText(`${user.nombres || ''} ${user.apellidos || ''}`, 490, 2)
    .forEach((line, index) => context.fillText(line, 76, 420 + index * 45));
  context.fillStyle = '#16894f';
  context.fillRect(76, 475, 490, 4);

  roundedRect(625, 315, 200, 235, 22);
  context.fillStyle = '#edf5ec';
  context.fill();
  context.strokeStyle = '#dce9dc';
  context.lineWidth = 2;
  context.stroke();
  if (user.foto) {
    const photo = await loadImage(user.foto);
    const scale = Math.max(180 / photo.width, 215 / photo.height);
    const sourceWidth = 180 / scale;
    const sourceHeight = 215 / scale;
    context.save();
    roundedRect(635, 325, 180, 215, 16);
    context.clip();
    context.drawImage(photo, (photo.width - sourceWidth) / 2, (photo.height - sourceHeight) / 2,
      sourceWidth, sourceHeight, 635, 325, 180, 215);
    context.restore();
  } else {
    context.fillStyle = '#16894f';
    context.font = 'bold 74px Arial';
    const initials = `${String(user.nombres || 'U')[0]}${String(user.apellidos || '')[0] || ''}`.toUpperCase();
    context.textAlign = 'center';
    context.fillText(initials, 725, 455);
    context.textAlign = 'left';
  }

  context.fillStyle = '#718376';
  context.font = 'bold 17px Arial';
  context.fillText('CENTRO DE FORMACIÓN', 76, 530);
  context.fillStyle = '#425b4c';
  context.font = '22px Arial';
  context.fillText('Centro de la Industria, la Empresa', 76, 565, 510);
  context.fillText('y los Servicios', 76, 595, 510);

  drawField('Documento', user.documento, 74, 630, 365);
  drawField('Teléfono', user.telefono, 461, 630, 365);
  drawField('Correo electrónico', user.correo, 74, 778, 752);
  drawField('Tipo de cuenta', roleLabels[user.rol] || user.rol, 74, 926, 752);

  roundedRect(74, 1080, 752, 220, 22);
  context.fillStyle = '#ffffff';
  context.fill();
  context.strokeStyle = '#dce9dc';
  context.lineWidth = 2;
  context.stroke();
  context.drawImage(qrImage, 92, 1090, 200, 200);
  context.textAlign = 'left';
  context.fillStyle = '#087c45';
  context.font = 'bold 24px Arial';
  context.fillText('ESCANEA PARA INICIAR SESIÓN', 330, 1168, 450);
  context.fillStyle = '#718376';
  context.font = '20px Arial';
  context.fillText('Usa este código QR para ingresar a tu cuenta.', 330, 1208, 450);
  context.fillText('No lo compartas con otras personas.', 330, 1242, 450);

  context.fillStyle = '#718376';
  context.font = '17px Arial';
  context.textAlign = 'center';
  context.fillText('Carné personal · Gestión Institucional SENA', 450, 1338);
  context.textAlign = 'left';
  return canvas.toDataURL('image/png');
}

async function showCard(card, pendingApproval = false) {
  generatedCardUrl = await renderCard(card);
  generatedCardCode = card.codigo_qr;
  generatedCardPendingApproval = pendingApproval;
  $('#card-preview').src = generatedCardUrl;
  const alreadyIssued = !pendingApproval && Number(currentUser?.qr_entregado) === 1;
  $('#card-dialog-title').textContent = pendingApproval ? 'Solicitud enviada · carné listo' : alreadyIssued ? 'Tu carné digital' : 'Carné generado';
  $('#card-dialog-message').textContent = pendingApproval
    ? 'Descarga y conserva tu carné. El QR permitirá iniciar sesión cuando administración apruebe tu solicitud.'
    : alreadyIssued
    ? 'Este carné ya fue descargado para tu cuenta. Consérvalo para iniciar sesión con el código QR.'
    : 'Descarga y conserva tu carné. Inicia sesión escaneando su código QR.';
  $('#download-card').classList.toggle('hidden', alreadyIssued);
  $('#card-dialog').showModal();
}

async function openMyCard() {
  const card = await api('auth/card');
  await showCard(card);
}

async function downloadCard() {
  if (!generatedCardUrl) return;
  const button = $('#download-card');
  button.disabled = true;
  try {
    await api('auth/card-issued', {method:'POST',body:JSON.stringify({codigo_qr:generatedCardCode})});
    const link = document.createElement('a');
    link.href = generatedCardUrl;
    link.download = `carne-${String(currentUser?.nombres || 'digital').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;
    link.click();
    if (currentUser && !generatedCardPendingApproval) {
      currentUser.qr_entregado = 1;
    }
    $('#download-card').classList.add('hidden');
    $('#card-dialog-message').textContent = 'Carné descargado. Guárdalo en un lugar seguro; no volveremos a mostrar el botón de descarga en esta cuenta.';
  } catch (error) {
    $('#card-dialog-message').textContent = error.message;
  } finally {
    button.disabled = false;
  }
}

$('#download-my-card').addEventListener('click', async () => {
  try {
    await openMyCard();
  } catch (error) {
    showError(error);
  }
});

$('#download-card').addEventListener('click', downloadCard);
$('#close-card').addEventListener('click', () => $('#card-dialog').close());
$('#finish-card').addEventListener('click', () => $('#card-dialog').close());

$('#registration-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const values = new FormData(form);
  $('#registration-message').textContent = '';
  const submit = $('#submit-registration');
  submit.disabled = true;
  try {
    if (values.get('password') !== values.get('confirm_password')) {
      throw new Error('Las contraseñas no coinciden.');
    }
    if (values.get('pin') !== values.get('confirm_pin')) {
      throw new Error('Los PIN no coinciden.');
    }
    const result = await api('auth/register', {method:'POST',body:JSON.stringify(await registrationRequestBody(values))});
    await finishRegistration(result, form);
  } catch (error) {
    $('#registration-message').textContent = error.message;
  } finally {
    submit.disabled = false;
  }
});

async function registrationRequestBody(values = new FormData($('#registration-form'))) {
  const photoFile = $('#registration-photo').files?.[0];
  if (photoFile && (photoFile.size > 1048576 || !['image/jpeg','image/png','image/webp'].includes(photoFile.type))) {
    throw new Error('La foto debe ser JPG, PNG o WEBP y no superar 1 MB.');
  }
  return {
      rol:values.get('rol'),
      tipo_documento:values.get('tipo_documento'),
      documento:values.get('documento'),
      nombres:values.get('nombres'),
      apellidos:values.get('apellidos'),
      correo:values.get('correo'),
      password:values.get('password'),
      pin:values.get('pin'),
      acepta_tratamiento_datos:values.get('acepta_tratamiento_datos') === 'on',
      telefono:values.get('telefono'),
      ...(values.get('rol') === 'estudiante' ? {ficha_id:Number(values.get('ficha_id'))} : {}),
      foto:photoFile ? await readFileAsDataUrl(photoFile) : null
  };
}

async function finishRegistration(result, form) {
  $('#registration-dialog').close();
  form.reset();
  updateRegistrationPhotoName();
  if (result.pending_approval) {
    $('#landing-screen').classList.remove('hidden');
    $('#login-screen').classList.add('hidden');
    $('#landing-message').textContent = result.message;
  } else {
    await startApp();
  }
  await showCard(result.card, Boolean(result.pending_approval));
}

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
    $('#legacy-login-form').reset();
    $('#download-my-card').classList.add('hidden');
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
  const submit = event.currentTarget.querySelector('[type="submit"]');
  submit.disabled = true;
  try {
    const result = await api('auth/recovery',{method:'POST',body:JSON.stringify({correo})});
    $('#recovery-dialog').close();
    $('#recovery-form').reset();
    $('#login-message').textContent = result.message || 'Si el correo está registrado, recibirás instrucciones para recuperar tu cuenta.';
  } catch (error) {
    $('#recovery-message').textContent = error.message;
  } finally {
    submit.disabled = false;
  }
});

$('#add-record').addEventListener('click', () => openForm().catch(showError));
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
  const payload = Object.fromEntries([...formData.entries()].filter(([,value]) => value !== '').map(([key,value]) => [key, ['rol_id','capacidad','ficha_id','ambiente_id','instructor_id','dia_semana','usuario_id','asistencia_id','visitante_id','equipo_id','autorizado'].includes(key) ? Number(value) : value]));
  try {
    await api(editId ? `${activeModule}/${editId}` : activeModule,{method:editId ? 'PATCH' : 'POST',body:JSON.stringify(payload)});
    $('#record-dialog').close();
    await loadTable();
    $('#table-message').textContent = 'Registro guardado correctamente.';
  } catch (error) {
    $('#dialog-message').textContent = error.message;
  }
});

$('#learner-schedule-day').addEventListener('click', () => {
  learnerScheduleView = 'day';
  $('#learner-schedule-day').classList.add('active');
  $('#learner-schedule-week').classList.remove('active');
  loadTable().catch(showError);
});
$('#learner-schedule-week').addEventListener('click', () => {
  learnerScheduleView = 'week';
  $('#learner-schedule-day').classList.remove('active');
  $('#learner-schedule-week').classList.add('active');
  loadTable().catch(showError);
});
$('#learner-schedule-date').addEventListener('change', () => {
  if (activeModule === 'schedules') loadTable().catch(showError);
});
document.addEventListener('click', event => {
  const moduleButton = event.target.closest('[data-open-module]');
  if (moduleButton && currentUser?.rol === 'estudiante') {
    showModule(moduleButton.dataset.openModule).catch(showError);
  }
});

function closeLearnerExcuseDialog() {
  $('#learner-excuse-dialog').close();
  $('#learner-excuse-form').reset();
}
$('#close-learner-excuse').addEventListener('click', closeLearnerExcuseDialog);
$('#cancel-learner-excuse').addEventListener('click', closeLearnerExcuseDialog);
$('#learner-excuse-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const submit = form.querySelector('[type="submit"]');
  submit.disabled = true;
  $('#learner-excuse-message').textContent = '';
  try {
    await api('learner/excuses', {method:'POST',body:new FormData(form)});
    closeLearnerExcuseDialog();
    await loadTable();
    $('#table-message').textContent = 'La excusa se envió y quedó pendiente de revisión.';
  } catch (error) {
    $('#learner-excuse-message').textContent = error.message;
  } finally {
    submit.disabled = false;
  }
});

$('#learner-profile-form').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const payload = {
    correo:form.elements.correo.value.trim(),
    telefono:form.elements.telefono.value.trim()
  };
  if (form.elements.password.value) payload.password = form.elements.password.value;
  if (form.elements.pin.value) payload.pin = form.elements.pin.value;
  if (payload.password || payload.pin) payload.current_password = form.elements.current_password.value;
  const submit = form.querySelector('[type="submit"]');
  submit.disabled = true;
  $('#learner-profile-message').textContent = '';
  try {
    await api('learner/profile', {method:'PATCH',body:JSON.stringify(payload)});
    form.elements.current_password.value = '';
    form.elements.password.value = '';
    form.elements.pin.value = '';
    $('#learner-profile-message').textContent = 'Tu perfil se actualizó correctamente.';
    const profile = await api('learner/profile');
    currentUser.correo = profile.correo;
    currentUser.telefono = profile.telefono;
  } catch (error) {
    $('#learner-profile-message').textContent = error.message;
  } finally {
    submit.disabled = false;
  }
});

startApp();
