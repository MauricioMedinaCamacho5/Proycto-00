const API = 'api';
const modules = {
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
    $('#login-screen').classList.remove('hidden');
    $('#app-shell').classList.add('hidden');
    if (error.status !== 401) $('#login-message').textContent = error.message;
    return;
  }
  $('#login-screen').classList.add('hidden');
  $('#app-shell').classList.remove('hidden');
  $('#user-summary').innerHTML = `<div class="user-name">${escapeHtml(currentUser.nombres)} ${escapeHtml(currentUser.apellidos)}</div><div class="user-role">${escapeHtml(currentUser.rol)}</div>`;
  renderNavigation();
  try {
    if (currentUser.rol === 'estudiante') await showModule('schedules');
    else await showDashboard();
  } catch (error) { showError(error); }
}

function renderNavigation() {
  const entries = [];
  if (['administrador','instructor','portero'].includes(currentUser.rol)) entries.push(['dashboard','Resumen','◈']);
  Object.entries(modules).forEach(([key, item]) => {
    if (item.roles.includes(currentUser.rol)) entries.push([key,item.label,item.icon]);
  });
  $('#navigation').innerHTML = entries.map(([key,label,icon]) => `<a href="#${key}" class="nav-link" data-view="${key}"><span class="nav-icon">${icon}</span><span class="nav-label">${escapeHtml(label)}</span></a>`).join('');
  $('#navigation').querySelectorAll('[data-view]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    const key = link.dataset.view;
    key === 'dashboard' ? showDashboard() : showModule(key);
  }));
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
    if (activeModule) refreshActiveTable();
    else refreshDashboard().catch(showError);
  }, 8000);
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
      chart = new Chart(ctx, {type:'bar',data:{labels:['Asistencia','Accesos','Reservas'],datasets:[{data:values,backgroundColor:['#1da98e','#527ca7','#e3a84f'],borderRadius:7,maxBarThickness:48}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#eef2f4'}},x:{grid:{display:false}}}}});
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
  $('#table-description').textContent = 'Consulta y administra los registros del sistema.';
  $('#add-record').classList.toggle('hidden', currentUser.rol === 'estudiante' || key === 'notifications' && currentUser.rol === 'estudiante' || key === 'fichas' && currentUser.rol === 'instructor');
  $('#export-report').classList.toggle('hidden', currentUser.rol === 'estudiante' && !['schedules','attendance','notifications'].includes(key));
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
    $('#table-body').innerHTML = '<tr><td class="empty-row">No hay registros disponibles.</td></tr>';
    return;
  }
  const columns = Object.keys(rows[0]);
  const allowDelete = currentUser.rol === 'administrador' && activeModule !== 'blocks';
  const allowEdit = modules[activeModule].roles.includes(currentUser.rol) && currentUser.rol !== 'estudiante' && !(activeModule === 'fichas' && currentUser.rol === 'instructor');
  $('#table-head').innerHTML = `<tr>${columns.map(col => `<th>${escapeHtml(col.replaceAll('_',' '))}</th>`).join('')}${allowDelete || allowEdit ? '<th>Acciones</th>' : ''}</tr>`;
  const canManage = currentUser.rol === 'administrador';
  $('#table-body').innerHTML = rows.map(row => {
    const actions = [];
    if (allowEdit) actions.push(`<button class="row-action" data-edit="${Number(row.id)}">Editar</button>`);
    if (activeModule === 'blocks' && canManage && !row.desbloqueado_en) actions.push(`<button class="row-action" data-unblock="${Number(row.id)}">Desbloquear</button>`);
    if (activeModule === 'loans' && canManage && row.estado === 'activo') actions.push(`<button class="row-action" data-return="${Number(row.id)}">Marcar devolución</button>`);
    if (allowDelete) actions.push(`<button class="row-action" data-delete="${Number(row.id)}">Eliminar</button>`);
    return `<tr>${columns.map(col => `<td title="${escapeHtml(row[col])}">${escapeHtml(row[col])}</td>`).join('')}${allowDelete || allowEdit ? `<td>${actions.join(' · ')}</td>` : ''}</tr>`;
  }).join('');
  $('#table-body').querySelectorAll('[data-edit]').forEach(button => {
    const row = rows.find(record => Number(record.id) === Number(button.dataset.edit));
    button.addEventListener('click', () => openForm(row));
  });
  $('#table-body').querySelectorAll('[data-delete]').forEach(button => button.addEventListener('click', () => deleteRecord(Number(button.dataset.delete))));
  $('#table-body').querySelectorAll('[data-unblock]').forEach(button => button.addEventListener('click', () => unblockUser(Number(button.dataset.unblock))));
  $('#table-body').querySelectorAll('[data-return]').forEach(button => button.addEventListener('click', () => returnLoan(Number(button.dataset.return))));
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
    await api('auth/login',{method:'POST',body:JSON.stringify({correo:form.get('correo'),password:form.get('password')})});
    await startApp();
  } catch (error) {
    $('#login-message').textContent = error.message;
  }
});

function stopRegistrationScanner(controls = registrationControls) {
  controls?.stop();
  registrationControls = null;
  $('#registration-video').pause();
  $('#registration-video').srcObject = null;
  $('#registration-scanner').classList.add('hidden');
}

$('#open-registration').addEventListener('click', () => {
  $('#registration-form').reset();
  $('#registration-message').textContent = '';
  $('#scanner-message').textContent = 'Puedes escanear el QR o código de barras lineal con la cámara, o escribir el código manualmente.';
  $('#registration-dialog').showModal();
});

$('#close-registration').addEventListener('click', () => $('#registration-dialog').close());
$('#cancel-registration').addEventListener('click', () => $('#registration-dialog').close());
$('#registration-dialog').addEventListener('close', () => stopRegistrationScanner());

$('#scan-registration-qr').addEventListener('click', async () => {
  if (registrationStarting || registrationControls) return;
  $('#scanner-message').textContent = 'Preparando cámara para QR y códigos de barras...';
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
    const reader = new window.ZXingBrowser.BrowserMultiFormatReader();
    registrationScanCompleted = false;
    const controls = await reader.decodeFromConstraints(
      {video:{facingMode:{ideal:'environment'}},audio:false},
      $('#registration-video'),
      (result, error, controls) => {
        if (!result || !$('#registration-dialog').open) return;
        const scannedValue = result.getText().trim();
        if (!scannedValue) return;
        registrationScanCompleted = true;
        if (scannedValue.length > 36) {
          $('#scanner-message').textContent = 'El código leído supera los 36 caracteres permitidos. Escribe el identificador corto impreso en el carnet.';
          stopRegistrationScanner(controls);
          return;
        }
        $('#registration-qr').value = scannedValue;
        $('#scanner-message').textContent = 'Código leído. Comprueba que corresponda a tu carnet y crea tu cuenta.';
        stopRegistrationScanner(controls);
      }
    );
    if (!$('#registration-dialog').open || registrationScanCompleted) {
      controls.stop();
      return;
    }
    registrationControls = controls;
    $('#registration-scanner').classList.remove('hidden');
    $('#scanner-message').textContent = 'Apunta la cámara al QR o al código de barras lineal del carné.';
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
    await api('auth/register', {method:'POST',body:JSON.stringify({
      documento:values.get('documento'),
      nombres:values.get('nombres'),
      apellidos:values.get('apellidos'),
      correo:values.get('correo'),
      password:values.get('password'),
      telefono:values.get('telefono'),
      codigo_qr:values.get('codigo_qr')
    })});
    stopRegistrationScanner();
    $('#registration-dialog').close();
    form.reset();
    await startApp();
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
    $('#app-shell').classList.add('hidden');
    $('#login-screen').classList.remove('hidden');
    $('#login-form').reset();
    $('#login-message').textContent = 'Has cerrado sesión correctamente.';
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
