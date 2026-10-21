const API = 'api';
const modules = {
  'registration-requests': {label:'Solicitudes de registro', icon:'✉', roles:['administrador'], fields:[]},
  'student-progress': {label:'Progreso de estudiantes', icon:'◉', roles:['instructor'], fields:[]},
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
let tableRefreshInProgress = false;
let loginScannerControls = null, loginScannerStarting = false, loginScanCompleted = false, loginExpectedRole = null;
let generatedCardUrl = null, generatedCardCode = null, generatedCardPendingApproval = false;
let registrationVerificationPending = false, registrationResendTimer = null;
let attendanceRoster = [], attendanceFichas = [], editingAttendance = null, progressStudent = null;
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
  $('#download-my-card').classList.toggle(
    'hidden',
    currentUser.rol === 'administrador' || Number(currentUser.qr_entregado) === 1
  );
  renderNavigation();
  try {
    if (currentUser.rol === 'administrador') await showDashboard();
    else if (currentUser.rol === 'estudiante') await showModule('schedules');
    else if (currentUser.rol === 'instructor') await showModule('student-progress');
    else await showModule('accesses');
  } catch (error) { showError(error); }
}

function renderNavigation() {
  const entries = [];
  if (currentUser.rol === 'administrador') entries.push(['dashboard','Resumen','◈']);
  Object.entries(modules).forEach(([key, item]) => {
    if (item.roles.includes(currentUser.rol) && !(currentUser.rol === 'estudiante' && key === 'cards')) entries.push([key,item.label,item.icon]);
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
  $('#page-title').textContent = currentUser.rol === 'administrador' ? 'Panel administrativo' : 'Resumen general';
  $('#page-kicker').textContent = currentUser.rol === 'administrador' ? 'ADMINISTRADOR · GESTIÓN INSTITUCIONAL' : 'PANEL INSTITUCIONAL';
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

$('#student-view-history').addEventListener('click', () => showModule('attendance').catch(showError));

async function showModule(key) {
  activeModule = key;
  $('#dashboard-view').classList.add('hidden');
  $('#resource-view').classList.remove('hidden');
  $('#page-title').textContent = modules[key].label;
  $('#page-kicker').textContent = 'GESTIÓN DE MÓDULOS';
  $('#table-title').textContent = modules[key].label;
  $('#table-description').textContent = key === 'attendance' && currentUser.rol !== 'estudiante'
    ? 'Marca la asistencia de la ficha. La lista se ordena automáticamente por nombre y apellido.'
    : key === 'student-progress'
    ? 'Consulta el resumen de asistencia de los estudiantes inscritos en tus fichas asignadas.'
    : key === 'registration-requests'
    ? 'Revisa las solicitudes de instructores y portería antes de activar sus cuentas.'
    : 'Consulta y administra los registros del sistema.';
  $('#student-attendance-overview').classList.toggle('hidden', currentUser.rol !== 'estudiante');
  $('#attendance-toolbar').classList.toggle('hidden', key !== 'attendance' || currentUser.rol === 'estudiante');
  $('#add-record').classList.toggle('hidden', key === 'registration-requests' || key === 'attendance' || currentUser.rol === 'estudiante' || key === 'notifications' && currentUser.rol === 'estudiante' || key === 'fichas' && currentUser.rol === 'instructor');
  $('#export-report').classList.toggle('hidden', key === 'registration-requests' || currentUser.rol === 'estudiante' && !['schedules','attendance','notifications'].includes(key));
  $('#report-format').classList.toggle('hidden', key === 'registration-requests');
  setActiveNav(key);
  await loadTable();
}

async function loadTable() {
  const module = activeModule;
  if (module === 'student-progress' && currentUser.rol === 'instructor') {
    await loadInstructorProgress();
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
  if (currentUser.rol === 'estudiante') {
    $('#table-title').textContent = modules[module].label;
    $('#table-description').textContent = 'Consulta la información actualizada de tu formación.';
  }
  if (currentUser.rol === 'estudiante') {
    const summary = await api('attendance/summary');
    renderStudentAttendanceSummary(summary);
  }
  const data = await api(module);
  if (activeModule !== module) return;
  const rows = Array.isArray(data) ? data : [];
  if (currentUser.rol === 'estudiante' && module === 'schedules') {
    renderStudentSchedule(rows);
    return;
  }
  if (currentUser.rol === 'estudiante' && module === 'notifications') {
    renderStudentNotifications(rows);
    return;
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
    const selectedDate = $('#attendance-date').value || bogotaDate();
    const selectedFicha = $('#attendance-ficha').value;
    const selectedEnvironment = $('#attendance-environment').value;
    const params = new URLSearchParams({fecha:selectedDate});
    if (selectedFicha) params.set('ficha_id', selectedFicha);
    if (selectedEnvironment) params.set('ambiente_id', selectedEnvironment);
    const result = await api(`attendance/roster?${params}`);
    attendanceRoster = result.estudiantes || [];
    attendanceFichas = result.fichas || [];

    const environmentSelect = $('#attendance-environment');
    const environmentOptions = '<option value="">Todos los ambientes</option>' + (result.ambientes || []).map(environment =>
      `<option value="${Number(environment.id)}">${escapeHtml(environment.codigo)} · ${escapeHtml(environment.nombre)}</option>`
    ).join('');
    if (environmentSelect.innerHTML !== environmentOptions) environmentSelect.innerHTML = environmentOptions;
    environmentSelect.value = result.ambiente_seleccionado ? String(result.ambiente_seleccionado) : '';
    environmentSelect.disabled = (result.ambientes || []).length < 2;

    const fichaSelect = $('#attendance-ficha');
    const newOptions = attendanceFichas.map(ficha => `<option value="${Number(ficha.id)}">${escapeHtml(ficha.codigo)} · ${escapeHtml(ficha.nombre)}</option>`).join('');
    if (fichaSelect.innerHTML !== newOptions) fichaSelect.innerHTML = newOptions;
    if (result.ficha_seleccionada) fichaSelect.value = String(result.ficha_seleccionada);
    fichaSelect.disabled = attendanceFichas.length < 2;

    if (!attendanceFichas.length) {
      $('#table-message').textContent = 'No hay fichas activas asignadas para registrar asistencia.';
      $('#table-head').innerHTML = '';
      $('#table-body').innerHTML = '';
      return;
    }

    async function loadInstructorProgress() {
      if (progressStudent) {
        await loadInstructorStudentHistory(progressStudent);
        return;
      }
      const records = await api('attendance/progress');
      if (activeModule !== 'student-progress') return;
      $('#table-title').textContent = 'Progreso de estudiantes';
      $('#table-description').textContent = 'Resumen de asistencia en las fichas que tienes asignadas.';
      const headings = ['Estudiante','Documento','Ficha','Ambiente','Asistió','Faltó','Tarde','Permiso','Progreso','Última clase','Historial'];
      $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
      $('#table-message').textContent = records.length
        ? `${records.length} registros de progreso · solo fichas asignadas a ti`
        : 'Todavía no hay estudiantes inscritos en tus fichas asignadas.';
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
            <td>${escapeHtml(record.ambientes || 'Sin ambiente asignado')}</td>
            <td><span class="attendance-state state-presente">${present}</span></td>
            <td><span class="attendance-state state-ausente">${absent}</span></td>
            <td><span class="attendance-state state-tarde">${late}</span></td>
            <td><span class="attendance-state state-permiso">${Number(record.permisos || 0)}</span></td>
            <td><strong class="progress-value">${progress}%</strong></td>
            <td>${record.ultima_clase ? formatDisplayDate(record.ultima_clase) : 'Sin registros'}</td>
            <td><button type="button" class="row-action progress-history" data-progress-student="${Number(record.usuario_id)}" data-student-name="${escapeHtml(`${record.nombres} ${record.apellidos}`)}">Ver historial</button></td>
          </tr>`;
        }).join('')
        : '<tr><td class="empty-row" colspan="11">Todavía no hay estudiantes inscritos en tus fichas asignadas.</td></tr>';
      $('#table-body').querySelectorAll('[data-progress-student]').forEach(button => {
        button.addEventListener('click', () => {
          progressStudent = {id:Number(button.dataset.progressStudent),name:button.dataset.studentName};
          loadInstructorProgress().catch(showError);
        });
      });
    }

    async function loadInstructorStudentHistory(student) {
      const params = new URLSearchParams({usuario_id:String(student.id)});
      const records = await api(`attendance/progress?${params}`);
      if (activeModule !== 'student-progress' || progressStudent?.id !== student.id) return;
      $('#table-title').textContent = `Historial · ${student.name}`;
      $('#table-description').textContent = 'Asistencia registrada en tus fichas asignadas.';
      const headings = ['Fecha','Ficha','Ambiente','Estado','Observación'];
      $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
      $('#table-message').innerHTML = '<button type="button" class="button secondary progress-back">← Volver al progreso</button>';
      $('#table-message').querySelector('.progress-back').addEventListener('click', () => {
        progressStudent = null;
        loadInstructorProgress().catch(showError);
      });
      $('#table-body').innerHTML = records.length
        ? records.map(record => `<tr>
            <td>${formatDisplayDate(record.fecha)}</td>
            <td>${escapeHtml(record.ficha_codigo)} · ${escapeHtml(record.ficha_nombre)}</td>
            <td>${escapeHtml(record.ambientes || 'Sin ambiente asignado')}</td>
            <td><span class="attendance-state state-${escapeHtml(record.estado)}">${attendanceStatusLabel(record.estado)}</span></td>
            <td>${escapeHtml(record.observacion || '—')}</td>
          </tr>`).join('')
        : '<tr><td class="empty-row" colspan="5">Este estudiante todavía no tiene asistencias registradas en tus fichas.</td></tr>';
    }
    $('#table-message').textContent = attendanceRoster.length
      ? `${attendanceRoster.length} estudiantes · ${result.fecha}`
      : 'Aún no hay estudiantes inscritos en esta ficha.';
    const headings = ['N.º','Foto','Nombres','Apellidos','Documento','Asistencia','Carné','Acciones'];
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
      const actions = student.asistencia_id
        ? `<button type="button" class="row-action attendance-edit" data-attendance-edit="${Number(student.id)}">Editar / motivo</button><button type="button" class="row-action attendance-delete" data-attendance-delete="${Number(student.id)}">Eliminar</button>`
        : `<button type="button" class="row-action attendance-edit" data-attendance-edit="${Number(student.id)}">Agregar motivo</button>`;
      return `<tr>
        <td class="attendance-number">${index + 1}</td>
        <td>${photo}</td>
        <td>${escapeHtml(student.nombres)}</td>
        <td>${escapeHtml(student.apellidos)}</td>
        <td>${escapeHtml(student.documento)}</td>
        <td><div class="attendance-statuses">${statusButtons}</div></td>
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
    const [summary, records] = await Promise.all([api('attendance/summary'), api('attendance')]);
    renderStudentAttendanceSummary(summary);
    $('#table-message').textContent = 'Historial ordenado desde la fecha más reciente.';
    $('#table-title').textContent = 'Mi historial de asistencia';
    $('#table-description').textContent = 'Consulta la fecha y el resultado de cada clase registrada.';
    const headings = ['Fecha','Estado'];
    $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
    $('#table-body').innerHTML = records.length
      ? records.map(record => `<tr><td>${formatDisplayDate(record.fecha)}</td><td><span class="attendance-state state-${escapeHtml(record.estado)}">${attendanceStatusLabel(record.estado)}</span></td></tr>`).join('')
      : '<tr><td class="empty-row" colspan="2">Aún no hay asistencias registradas.</td></tr>';
  }

  function attendanceStatusLabel(status) {
    return ({presente:'Asistió',ausente:'Faltó',tarde:'Llegó tarde',permiso:'Permiso'})[status] || status;
  }

  function renderStudentSchedule(rows) {
    $('#table-title').textContent = 'Mi horario de clases';
    $('#table-description').textContent = 'Tus clases organizadas por día y hora.';
    const headings = ['Día','Horario','Asignatura','Ambiente','Instructor','Ficha'];
    $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
    $('#table-body').innerHTML = rows.length
      ? rows.map(row => `<tr><td><span class="schedule-day">${escapeHtml(row.dia)}</span></td><td>${escapeHtml(String(row.hora_inicio).slice(0,5))} – ${escapeHtml(String(row.hora_fin).slice(0,5))}</td><td><strong>${escapeHtml(row.asignatura)}</strong></td><td>${escapeHtml(row.ambiente)}</td><td>${escapeHtml(row.instructor)}</td><td>${escapeHtml(row.ficha)} · ${escapeHtml(row.nombre_ficha)}</td></tr>`).join('')
      : '<tr><td class="empty-row" colspan="6">Todavía no tienes horarios asignados.</td></tr>';
  }

  function renderStudentNotifications(rows) {
    $('#table-title').textContent = 'Mis notificaciones';
    $('#table-description').textContent = 'Novedades de asistencia y mensajes de la institución.';
    const headings = ['Fecha','Notificación','Detalle'];
    $('#table-head').innerHTML = `<tr>${headings.map(heading => `<th>${heading}</th>`).join('')}</tr>`;
    $('#table-body').innerHTML = rows.length
      ? rows.map(row => `<tr class="notification-row"><td>${formatDisplayDate(row.creado_en)}</td><td><strong>${escapeHtml(row.titulo)}</strong></td><td class="notification-message">${escapeHtml(row.mensaje)}</td></tr>`).join('')
      : '<tr><td class="empty-row" colspan="3">No tienes notificaciones por ahora.</td></tr>';
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
        fecha:$('#attendance-date').value || bogotaDate(),
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
    $('#attendance-edit-student').textContent = `${editingAttendance.nombres} ${editingAttendance.apellidos} · ${formatDisplayDate($('#attendance-date').value)}`;
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
    return `<tr>${columns.map(col => `<td title="${escapeHtml(row[col])}">${escapeHtml(row[col])}</td>`).join('')}${hasActions ? `<td class="actions-cell"><div class="row-actions">${actions.join('')}</div></td>` : ''}</tr>`;
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

$('#legacy-login-form').addEventListener('submit', async event => {
  event.preventDefault();
  $('#login-message').textContent = '';
  const form = new FormData(event.currentTarget);
  try {
    const authenticatedUser = await api('auth/login',{method:'POST',body:JSON.stringify({correo:form.get('correo'),password:form.get('password')})});
    if (loginExpectedRole && authenticatedUser.rol !== loginExpectedRole) {
      await api('auth/logout', {method:'POST'});
      throw new Error('Esta cuenta no tiene el rol requerido para este acceso.');
    }
    await startApp();
    await openMyCard();
    $('#card-dialog-message').textContent = 'Acceso temporal para una cuenta existente. Descarga y conserva este carné; los próximos ingresos se hacen con su QR.';
  } catch (error) {
    $('#login-message').textContent = error.message;
  }
});

$('#landing-student-login').addEventListener('click', () => showLogin('estudiante'));
$('#login-home').addEventListener('click', showLanding);
$('#landing-staff-login').addEventListener('click', () => showLogin());
$('#landing-admin-login').addEventListener('click', () => showLogin('administrador'));
$('#landing-register').addEventListener('click', () => openRegistration());

function showLanding() {
  stopLoginScanner();
  $('#login-screen').classList.add('hidden');
  $('#landing-screen').classList.remove('hidden');
}

function showLogin(expectedRole = null) {
  loginExpectedRole = expectedRole;
  stopLoginScanner();
  $('#legacy-login-form').reset();
  $('#login-message').textContent = '';
  $('#login-screen .login-card h1').textContent = expectedRole === 'administrador' ? 'Acceso de administrador' : 'Acceso a tu cuenta';
  $('#login-screen .login-card > .muted').textContent = expectedRole === 'administrador'
    ? 'Escanea el carné QR de una cuenta administradora. Este acceso es exclusivo para administración.'
    : 'Ingresa con el código QR de tu carné. Este acceso funciona para estudiantes y equipo institucional.';
  $('#login-scan-message').textContent = expectedRole === 'administrador'
    ? 'Escanea el QR asignado a una cuenta administradora o elige una imagen del código.'
    : 'Escanea el QR de tu carné o elige una imagen que lo contenga. Cualquier persona con una copia del QR podrá entrar a tu cuenta.';
  $('#landing-screen').classList.add('hidden');
  $('#login-screen').classList.remove('hidden');
}

function openRegistration(role = 'estudiante') {
  $('#registration-form').reset();
  resetRegistrationVerification();
  updateRegistrationPhotoName();
  $('#registration-role').value = role;
  syncRegistrationRolePicker();
  closeRegistrationRolePicker();
  updateRegistrationRole();
  $('#registration-message').textContent = '';
  $('#landing-message').textContent = '';
  $('#registration-dialog').showModal();
}

function resetRegistrationVerification() {
  if (registrationResendTimer) {
    clearInterval(registrationResendTimer);
    registrationResendTimer = null;
  }
  registrationVerificationPending = false;
  $('#registration-fields')?.classList.remove('hidden');
  $('#registration-verification')?.classList.add('hidden');
  $('#registration-code').value = '';
  $('#registration-code').required = false;
  $('#resend-registration-code').disabled = true;
  $('#resend-registration-code').textContent = 'Podrás solicitar otro código en 60 s';
  $('#registration-verification-email').textContent = '';
  $('#cancel-registration').textContent = 'Cancelar';
  $('#submit-registration').classList.remove('hidden');
}

function startRegistrationResendCountdown(seconds = 60) {
  if (registrationResendTimer) clearInterval(registrationResendTimer);
  const resend = $('#resend-registration-code');
  let remaining = seconds;
  resend.disabled = true;
  resend.textContent = `Podrás solicitar otro código en ${remaining} s`;
  registrationResendTimer = setInterval(() => {
    remaining -= 1;
    if (remaining <= 0) {
      clearInterval(registrationResendTimer);
      registrationResendTimer = null;
      resend.disabled = false;
      resend.textContent = 'Reenviar código';
      return;
    }
    resend.textContent = `Podrás solicitar otro código en ${remaining} s`;
  }, 1000);
}

function showRegistrationVerification(email) {
  registrationVerificationPending = true;
  $('#registration-fields').classList.add('hidden');
  $('#registration-verification').classList.remove('hidden');
  $('#registration-verification-email').textContent = email;
  $('#registration-code').required = true;
  $('#registration-intro').textContent = 'Ya casi terminas. Confirma que puedes recibir mensajes en este correo para activar tu cuenta.';
  $('#registration-message').textContent = '';
  $('#cancel-registration').textContent = 'Editar información';
  $('#submit-registration').textContent = 'Confirmar correo';
  startRegistrationResendCountdown();
  $('#registration-code').focus();
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
  if (returnFocus) trigger.focus();
}

function openRegistrationRolePicker() {
  const trigger = $('#registration-role-trigger');
  const menu = $('#registration-role-options');
  menu.classList.remove('hidden');
  trigger.setAttribute('aria-expanded', 'true');
  const selected = menu.querySelector('[aria-selected="true"]');
  selected?.focus();
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

document.addEventListener('pointerdown', event => {
  if (!event.target.closest('.role-picker')) closeRegistrationRolePicker();
});

function updateRegistrationRole() {
  const student = $('#registration-role').value === 'estudiante';
  $('#registration-title').textContent = student ? 'Registrar como estudiante' : 'Solicitar cuenta de personal';
  $('#registration-intro').textContent = student
    ? 'Completa tus datos para activar tu cuenta de estudiante de inmediato. Al finalizar, se generará un carné QR PNG que podrás descargar y usar para iniciar sesión.'
    : 'Completa tus datos y guarda el carné QR que se generará. Un administrador debe aprobar la solicitud antes de que puedas iniciar sesión.';
  $('#submit-registration').textContent = student ? 'Crear cuenta y carné' : 'Enviar solicitud y generar carné';
}

$('#registration-role').addEventListener('change', updateRegistrationRole);

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
  $('#login-scan-message').textContent = 'Verificando el carné...';
  try {
    const authenticatedUser = await api('auth/qr-login', {method:'POST',body:JSON.stringify({codigo_qr:qrCode})});
    if (loginExpectedRole && authenticatedUser.rol !== loginExpectedRole) {
      await api('auth/logout', {method:'POST'});
      throw new Error('Este carné no corresponde a una cuenta administradora. Usa el acceso indicado para tu rol.');
    }
    await startApp();
  } catch (error) {
    $('#login-scan-message').textContent = error.message;
  }
}

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
  if (registrationVerificationPending) resetRegistrationVerification();
}

$('#close-registration').addEventListener('click', closeRegistration);
$('#registration-dialog').addEventListener('close', () => {
  if (registrationVerificationPending) resetRegistrationVerification();
});
$('#cancel-registration').addEventListener('click', () => {
  if (registrationVerificationPending) {
    resetRegistrationVerification();
    updateRegistrationRole();
    $('#registration-message').textContent = '';
    return;
  }
  closeRegistration();
});

$('#attendance-ficha').addEventListener('change', () => showModule('attendance').catch(showError));
$('#attendance-environment').addEventListener('change', () => {
  $('#attendance-ficha').value = '';
  showModule('attendance').catch(showError);
});
$('#attendance-date').value = bogotaDate();
$('#attendance-date').addEventListener('change', () => showModule('attendance').catch(showError));
$('#attendance-refresh').addEventListener('click', () => showModule('attendance').catch(showError));

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
        fecha:$('#attendance-date').value || bogotaDate(),
        estado:form.elements.estado.value,
        observacion:form.elements.observacion.value.trim()
      })});
    }
    closeAttendanceEdit();
    await showModule('attendance');
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
  const canvas = document.createElement('canvas');
  canvas.width = 1000;
  canvas.height = 600;
  const context = canvas.getContext('2d');
  context.fillStyle = '#ffffff';
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.fillStyle = '#087c45';
  context.fillRect(0, 0, canvas.width, 120);
  context.fillStyle = '#ffffff';
  context.font = 'bold 35px Arial';
  context.fillText('SENA · CARNÉ DIGITAL', 48, 70, 880);
  context.font = '22px Arial';
  context.fillText('Centro de la Industria, la Empresa y los Servicios', 48, 105, 880);

  context.fillStyle = '#183528';
  context.font = 'bold 37px Arial';
  context.fillText(`${card.usuario.nombres} ${card.usuario.apellidos}`, 48, 205, 590);
  context.font = '24px Arial';
  context.fillStyle = '#087c45';
  context.fillText(String(card.usuario.rol || '').toUpperCase(), 48, 252, 590);
  context.fillStyle = '#425b4c';
  context.font = '21px Arial';
  context.fillText(`Documento: ${card.usuario.documento}`, 48, 316, 590);
  context.fillText(`Correo: ${card.usuario.correo}`, 48, 362, 590);
  context.fillText(`Teléfono: ${card.usuario.telefono || 'No registrado'}`, 48, 408, 590);
  context.font = '16px Arial';
  context.fillStyle = '#718376';
  context.fillText('Presenta este QR para iniciar sesión. No lo compartas.', 48, 540, 590);

  if (card.usuario.foto) {
    const photo = await loadImage(card.usuario.foto);
    const size = 128;
    const cropSize = Math.min(photo.width, photo.height);
    context.save();
    context.beginPath();
    context.arc(790, 257, size / 2, 0, Math.PI * 2);
    context.clip();
    context.drawImage(photo, (photo.width - cropSize) / 2, (photo.height - cropSize) / 2, cropSize, cropSize, 790 - size / 2, 193, size, size);
    context.restore();
  }
  context.drawImage(qrImage, 690, 335, 240, 240);
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
  if (Number(currentUser?.qr_entregado) === 1) return;
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
      $('#download-my-card').classList.add('hidden');
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
    if (registrationVerificationPending) {
      const codigo = $('#registration-code').value.trim();
      if (!/^\d{6}$/.test(codigo)) {
        throw new Error('Escribe el código de seis dígitos que recibiste por correo.');
      }
      const result = await api('auth/register/verify', {
        method:'POST',
        body:JSON.stringify({correo:values.get('correo'), codigo})
      });
      await finishRegistration(result, form);
      return;
    }

    if (values.get('password') !== values.get('confirm_password')) {
      throw new Error('Las contraseñas no coinciden.');
    }
    const result = await api('auth/register', {method:'POST',body:JSON.stringify(await registrationRequestBody(values))});
    showRegistrationVerification(result.correo || values.get('correo'));
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
      documento:values.get('documento'),
      nombres:values.get('nombres'),
      apellidos:values.get('apellidos'),
      correo:values.get('correo'),
      password:values.get('password'),
      telefono:values.get('telefono'),
      foto:photoFile ? await readFileAsDataUrl(photoFile) : null
  };
}

async function finishRegistration(result, form) {
  $('#registration-dialog').close();
  if (registrationResendTimer) clearInterval(registrationResendTimer);
  registrationResendTimer = null;
  registrationVerificationPending = false;
  form.reset();
  $('#registration-code').value = '';
  $('#registration-code').required = false;
  $('#registration-fields').classList.remove('hidden');
  $('#registration-verification').classList.add('hidden');
  $('#cancel-registration').textContent = 'Cancelar';
  $('#submit-registration').textContent = 'Crear cuenta';
  $('#submit-registration').classList.remove('hidden');
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

$('#resend-registration-code').addEventListener('click', async () => {
  const resend = $('#resend-registration-code');
  resend.disabled = true;
  $('#registration-message').textContent = '';
  try {
    const result = await api('auth/register', {
      method:'POST',
      body:JSON.stringify(await registrationRequestBody())
    });
    showRegistrationVerification(result.correo || new FormData($('#registration-form')).get('correo'));
    $('#registration-message').textContent = 'Enviamos un nuevo código a tu correo.';
  } catch (error) {
    $('#registration-message').textContent = error.message;
    if (error.status === 429) startRegistrationResendCountdown();
    else resend.disabled = false;
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
