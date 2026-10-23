import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:cookie_jar/cookie_jar.dart';
import 'package:dio/dio.dart';
import 'package:dio_cookie_manager/dio_cookie_manager.dart';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:qr_flutter/qr_flutter.dart';

const apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://192.168.1.10/gestion/api',
);
const senaGreen = Color(0xff087c45);

void main() {
  runApp(const GestionApp());
}

class ApiClient {
  ApiClient() {
    dio = Dio(BaseOptions(
      baseUrl: apiBaseUrl,
      connectTimeout: const Duration(seconds: 8),
      receiveTimeout: const Duration(seconds: 8),
      headers: {'Content-Type': 'application/json'},
    ));
    dio.interceptors.add(CookieManager(CookieJar()));
  }

  late final Dio dio;

  Future<Map<String, dynamic>> get(String path) async {
    final response = await dio.get<Map<String, dynamic>>(path);
    return Map<String, dynamic>.from(response.data?['data'] as Map);
  }

  Future<Map<String, dynamic>> post(
    String path,
    Map<String, dynamic> data,
  ) async {
    final response = await dio.post<Map<String, dynamic>>(path, data: data);
    return Map<String, dynamic>.from(response.data?['data'] as Map);
  }

  Future<List<Map<String, dynamic>>> list(String path) async {
    final response = await dio.get<Map<String, dynamic>>(path);
    return List<Map<String, dynamic>>.from(
      (response.data?['data'] as List).map((row) => Map<String, dynamic>.from(row as Map)),
    );
  }
}

class GestionApp extends StatelessWidget {
  const GestionApp({super.key});

  @override
  Widget build(BuildContext context) => MaterialApp(
        title: 'Gestión Institucional',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(
            seedColor: senaGreen,
            primary: senaGreen,
          ),
          scaffoldBackgroundColor: const Color(0xfff5f8f1),
          cardColor: Colors.white,
          appBarTheme: const AppBarTheme(
            backgroundColor: senaGreen,
            foregroundColor: Colors.white,
            centerTitle: false,
          ),
          inputDecorationTheme: InputDecorationTheme(
            filled: true,
            fillColor: Colors.white,
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: const BorderSide(color: senaGreen, width: 2),
            ),
          ),
          filledButtonTheme: FilledButtonThemeData(
            style: FilledButton.styleFrom(
              backgroundColor: senaGreen,
              foregroundColor: Colors.white,
            ),
          ),
          useMaterial3: true,
        ),
        home: const LoginPage(),
      );
}

class InstitutionMark extends StatelessWidget {
  const InstitutionMark({this.size = 48, super.key});

  final double size;

  @override
  Widget build(BuildContext context) => CustomPaint(
        size: Size.square(size),
        painter: const _PortalMarkPainter(),
      );
}

class _PortalMarkPainter extends CustomPainter {
  const _PortalMarkPainter();

  @override
  void paint(Canvas canvas, Size size) {
    canvas.save();
    canvas.scale(size.width / 100, size.height / 100);

    final background = Paint()
      ..shader = const LinearGradient(
        colors: [Color(0xff19a965), Color(0xff075b37)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ).createShader(const Rect.fromLTWH(3, 3, 94, 94));
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(3, 3, 94, 94),
        const Radius.circular(29),
      ),
      background,
    );

    final stem = Paint()
      ..color = const Color(0xfff4fceb)
      ..strokeWidth = 5
      ..strokeCap = StrokeCap.round;
    canvas.drawLine(const Offset(50, 57), const Offset(50, 36), stem);

    final leaves = Paint()..color = const Color(0xffb9f0b6);
    final leftLeaf = Path()
      ..moveTo(49, 38)
      ..cubicTo(37, 37, 32, 29, 34, 20)
      ..cubicTo(45, 21, 51, 27, 51, 37)
      ..close();
    canvas.drawPath(leftLeaf, Paint()..color = const Color(0xffddf7a8));
    final rightLeaf = Path()
      ..moveTo(52, 44)
      ..cubicTo(53, 32, 61, 27, 71, 28)
      ..cubicTo(70, 39, 64, 45, 53, 47)
      ..close();
    canvas.drawPath(rightLeaf, leaves);

    final book = Path()
      ..moveTo(22, 57)
      ..cubicTo(33, 53, 43, 55, 50, 62)
      ..cubicTo(57, 55, 67, 53, 78, 57)
      ..lineTo(78, 79)
      ..cubicTo(67, 75, 57, 77, 50, 84)
      ..cubicTo(43, 77, 33, 75, 22, 79)
      ..close();
    canvas.drawPath(book, Paint()..color = const Color(0xfffffdf5));

    final pageLine = Paint()
      ..color = const Color(0xffc6e8cf)
      ..strokeWidth = 2.5
      ..strokeCap = StrokeCap.round;
    canvas.drawLine(const Offset(50, 63), const Offset(50, 82), pageLine);
    final pageDetails = Paint()
      ..color = const Color(0xffd5ebdd)
      ..strokeWidth = 2
      ..strokeCap = StrokeCap.round;
    canvas.drawLine(const Offset(28, 63), const Offset(45, 65), pageDetails);
    canvas.drawLine(const Offset(72, 63), const Offset(55, 65), pageDetails);
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _PortalMarkPainter oldDelegate) => false;
}

class InstitutionBrand extends StatelessWidget {
  const InstitutionBrand({this.compact = false, super.key});

  final bool compact;

  @override
  Widget build(BuildContext context) => Row(
        children: [
          InstitutionMark(size: compact ? 36 : 48),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Servicio Nacional de Aprendizaje',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: compact ? 11 : 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  'SENA · Centro de la Industria, la Empresa y los Servicios',
                  maxLines: compact ? 1 : 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: compact ? const Color(0xffe0efe3) : const Color(0xff718376),
                    fontSize: compact ? 8 : 10,
                  ),
                ),
                Text(
                  'FORMACIÓN · SERVICIO · OPORTUNIDADES',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: compact ? const Color(0xffd5e8d8) : const Color(0xff43835a),
                    fontSize: compact ? 7 : 8,
                    fontWeight: FontWeight.w700,
                    letterSpacing: .4,
                  ),
                ),
              ],
            ),
          ),
        ],
      );
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _api = ApiClient();
  final _scannerController = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const [BarcodeFormat.qrCode],
  );
  bool _busy = false;
  bool _scanning = false;
  bool _showPasswordLogin = false;
  String? _error;
  final _identifierController = TextEditingController();
  final _passwordController = TextEditingController();
  final _pinController = TextEditingController();

  Future<void> _loginWithQr(String code) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _scanning = false;
      _error = null;
    });
    try {
      final preview = await _api.post('auth/qr-preview', {'codigo_qr': code});
      final profile = Map<String, dynamic>.from(preview['usuario'] as Map);
      final pin = await _requestPin(profile);
      if (pin == null) return;
      final user = await _api.post(
        'auth/qr-login',
        {'codigo_qr': code, 'pin': pin},
      );
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => HomePage(api: _api, user: user),
        ),
      );
    } on DioException catch (error) {
      setState(() => _error =
          error.response?.data?['error']?.toString() ?? 'No se pudo conectar.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<String?> _requestPin(Map<String, dynamic> profile) async {
    final controller = TextEditingController();
    String? validationError;
    final photo = profile['foto']?.toString() ?? '';
    final photoMatch = RegExp(r'^data:image/[^;]+;base64,(.+)$').firstMatch(photo);
    Uint8List? photoBytes;
    if (photoMatch != null) {
      try {
        photoBytes = base64Decode(photoMatch.group(1)!);
      } on FormatException {
        photoBytes = null;
      }
    }
    try {
      return await showDialog<String>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: Column(
              children: [
                if (photoBytes != null)
                  CircleAvatar(
                    radius: 34,
                    backgroundImage: MemoryImage(photoBytes),
                  ),
                const SizedBox(height: 10),
                Text(
                  '${profile['nombres']} ${profile['apellidos']}',
                  textAlign: TextAlign.center,
                ),
                Text(
                  'Confirma que la foto y el nombre sean tuyos.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
            content: TextField(
              controller: controller,
              autofocus: true,
              obscureText: true,
              maxLength: 6,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: 'PIN del carné',
                errorText: validationError,
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: const Text('Cancelar'),
              ),
              FilledButton(
                onPressed: () {
                  final value = controller.text.trim();
                  if (!RegExp(r'^\d{4,6}$').hasMatch(value)) {
                    setDialogState(() {
                      validationError = 'Ingresa de 4 a 6 números.';
                    });
                    return;
                  }
                  Navigator.pop(dialogContext, value);
                },
                child: const Text('Ingresar'),
              ),
            ],
          ),
        ),
      );
    } finally {
      controller.dispose();
    }
  }

  Future<void> _loginWithPassword() async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final data = <String, dynamic>{
        'identificador': _identifierController.text.trim(),
        'password': _passwordController.text,
      };
      if (_pinController.text.isNotEmpty) {
        data['pin'] = _pinController.text;
      }
      final user = await _api.post('auth/login', data);
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => HomePage(api: _api, user: user),
        ),
      );
    } on DioException catch (error) {
      setState(() => _error =
          error.response?.data?['error']?.toString() ?? 'No se pudo conectar.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    _scannerController.dispose();
    _identifierController.dispose();
    _passwordController.dispose();
    _pinController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        body: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Card(
              margin: const EdgeInsets.all(20),
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const InstitutionBrand(),
                    const SizedBox(height: 26),
                    Text('Acceso con carné QR',
                        style: Theme.of(context).textTheme.headlineSmall),
                    const SizedBox(height: 18),
                    const Text(
                      'Escanea el QR de tu carné e ingresa tu PIN. También puedes usar tu documento o correo y contraseña.',
                    ),
                    const SizedBox(height: 14),
                    if (_scanning)
                      SizedBox(
                        height: 260,
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: MobileScanner(
                            controller: _scannerController,
                            onDetect: (capture) {
                              if (capture.barcodes.isEmpty) return;
                              final code = capture.barcodes.first.rawValue;
                              if (code != null && code.isNotEmpty) {
                                _loginWithQr(code);
                              }
                            },
                          ),
                        ),
                      ),
                    const SizedBox(height: 12),
                    if (!_showPasswordLogin)
                      FilledButton.icon(
                        onPressed: _busy
                            ? null
                            : () => setState(() => _scanning = !_scanning),
                        icon: Icon(_scanning
                            ? Icons.close
                            : Icons.qr_code_scanner),
                        label: Text(_scanning ? 'Cerrar cámara' : 'Escanear QR'),
                      )
                    else ...[
                      TextField(
                        controller: _identifierController,
                        keyboardType: TextInputType.emailAddress,
                        decoration: const InputDecoration(
                          labelText: 'Documento o correo electrónico',
                        ),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _passwordController,
                        obscureText: true,
                        decoration:
                            const InputDecoration(labelText: 'Contraseña'),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _pinController,
                        obscureText: true,
                        maxLength: 6,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'PIN opcional para configurar o cambiar',
                        ),
                      ),
                      FilledButton(
                        onPressed: _busy ? null : _loginWithPassword,
                        child: const Text('Ingresar'),
                      ),
                    ],
                    TextButton(
                      onPressed: _busy
                          ? null
                          : () => setState(() {
                                _showPasswordLogin = !_showPasswordLogin;
                                _scanning = false;
                                _error = null;
                              }),
                      child: Text(_showPasswordLogin
                          ? 'Ingresar con carné QR'
                          : 'Ingresar con documento o correo'),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                    ],
                    if (_busy) ...[
                      const SizedBox(height: 12),
                      const Center(child: CircularProgressIndicator()),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      );
}

class HomePage extends StatefulWidget {
  const HomePage({required this.api, required this.user, super.key});

  final ApiClient api;
  final Map<String, dynamic> user;

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  Map<String, dynamic>? _stats;
  List<Map<String, dynamic>> _studentSchedules = [];
  List<Map<String, dynamic>> _studentAttendance = [];
  Map<String, dynamic>? _studentCard;
  String _accessType = 'ingreso';
  String? _error;
  Timer? _refreshTimer;
  bool _loading = false;

  Future<void> _load() async {
    if (_loading) return;
    _loading = true;
    try {
      if (widget.user['rol'] == 'estudiante') {
        final schedules = await widget.api.list('schedules');
        final attendance = await widget.api.list('attendance');
        final cards = await widget.api.list('cards');
        if (mounted) setState(() {
          _studentSchedules = schedules;
          _studentAttendance = attendance;
          _studentCard = cards.isEmpty ? null : cards.first;
          _error = null;
        });
        return;
      }
      final stats = await widget.api.get('dashboard');
      if (mounted) setState(() {
        _stats = stats;
        _error = null;
      });
    } on DioException catch (error) {
      if (mounted) {
        setState(() => _error =
            error.response?.data?['error']?.toString() ?? 'No se pudo cargar.');
      }
    } finally {
      _loading = false;
    }
  }

  Future<void> _scan() async {
    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute<String>(builder: (_) => const ScanPage()),
    );
    if (code == null || !mounted) return;
    try {
      await widget.api.post('accesses', {'qr': code, 'tipo': _accessType});
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${_accessType == 'ingreso' ? 'Ingreso' : 'Salida'} registrado correctamente.')),
      );
      await _load();
    } on DioException catch (error) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.response?.data?['error']?.toString() ??
              'No se pudo registrar el acceso.'),
        ),
      );
    }
  }

  Future<void> _scanEquipment() async {
    final equipmentQr = await Navigator.of(context).push<String>(
      MaterialPageRoute<String>(builder: (_) => const ScanPage()),
    );
    if (equipmentQr == null || !mounted) return;
    final responsibleQr = await Navigator.of(context).push<String>(
      MaterialPageRoute<String>(builder: (_) => const ScanPage()),
    );
    if (responsibleQr == null || !mounted) return;
    try {
      await widget.api.post('equipment-movements/scan', {
        'qr': equipmentQr,
        'responsable_qr': responsibleQr,
        'tipo': _accessType,
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Movimiento de equipo ($_accessType) registrado.')),
      );
    } on DioException catch (error) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(error.response?.data?['error']?.toString() ??
              'No se pudo registrar el movimiento.'),
        ),
      );
    }
  }

  Future<void> _logout() async {
    try {
      await widget.api.post('auth/logout', {});
    } finally {
      if (mounted) {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute<void>(builder: (_) => const LoginPage()),
          (_) => false,
        );
      }
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
    _refreshTimer = Timer.periodic(const Duration(seconds: 8), (_) => _load());
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const InstitutionBrand(compact: true),
          actions: [IconButton(onPressed: _logout, icon: const Icon(Icons.logout))],
        ),
        body: RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Text(
                'Hola, ${widget.user['nombres'] ?? ''}',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              Text('Rol: ${widget.user['rol'] ?? ''}'),
              const SizedBox(height: 18),
              if (_error != null)
                Card(
                  child: ListTile(
                    title: Text(_error!),
                    trailing: IconButton(
                        onPressed: _load, icon: const Icon(Icons.refresh)),
                  ),
                ),
              if (widget.user['rol'] == 'estudiante') ...[
                const SizedBox(height: 12),
                Text('Mi carnet digital', style: Theme.of(context).textTheme.titleLarge),
                if (_studentCard != null)
                  Card(
                    child: Column(
                      children: [
                        QrImageView(
                          data: '${_studentCard!['codigo_qr']}',
                          size: 180,
                        ),
                        const Padding(
                          padding: EdgeInsets.only(bottom: 14),
                          child: Text('Presenta este código en portería.'),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 12),
                Text('Mis horarios', style: Theme.of(context).textTheme.titleLarge),
                if (_studentSchedules.isEmpty)
                  const ListTile(title: Text('No tienes horarios asignados.')),
                ..._studentSchedules.map((schedule) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.schedule),
                        title: Text('${schedule['asignatura'] ?? 'Clase'}'),
                        subtitle: Text(
                            'Día ${schedule['dia_semana']} · ${schedule['hora_inicio']}–${schedule['hora_fin']} · Ambiente ${schedule['ambiente_id']}'),
                      ),
                    )),
                const SizedBox(height: 12),
                Text('Mi asistencia', style: Theme.of(context).textTheme.titleLarge),
                if (_studentAttendance.isEmpty)
                  const ListTile(title: Text('Aún no hay asistencias registradas.')),
                ..._studentAttendance.map((record) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.fact_check_outlined),
                        title: Text('${record['fecha']} · ${record['estado']}'),
                        subtitle: Text('${record['observacion'] ?? ''}'),
                      ),
                    )),
              ],
              if (_stats != null && widget.user['rol'] != 'estudiante')
                ..._stats!.entries.map((entry) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.analytics_outlined),
                        title: Text(_label(entry.key)),
                        trailing: Text('${entry.value}',
                            style: Theme.of(context).textTheme.titleLarge),
                      ),
                    )),
              if (['administrador', 'portero'].contains(widget.user['rol']))
                Column(
                  children: [
                    const SizedBox(height: 14),
                    DropdownButtonFormField<String>(
                      value: _accessType,
                      decoration: const InputDecoration(labelText: 'Tipo de movimiento'),
                      items: const [
                        DropdownMenuItem(value: 'ingreso', child: Text('Ingreso')),
                        DropdownMenuItem(value: 'salida', child: Text('Salida')),
                      ],
                      onChanged: (value) {
                        if (value != null) setState(() => _accessType = value);
                      },
                    ),
                    const SizedBox(height: 10),
                    FilledButton.icon(
                      onPressed: _scan,
                      icon: const Icon(Icons.qr_code_scanner),
                      label: const Text('Escanear carnet QR'),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: _scanEquipment,
                      icon: const Icon(Icons.devices_other),
                      label: const Text('Escanear equipo y responsable'),
                    ),
                  ],
                  ),
            ],
          ),
        ),
      );

  String _label(String key) => switch (key) {
        'usuarios' => 'Usuarios activos',
        'ambientes' => 'Ambientes',
        'asistencias_hoy' => 'Asistencias hoy',
        'accesos_hoy' => 'Accesos hoy',
        'equipos' => 'Equipos',
        'reservas_activas' => 'Reservas activas',
        'ocupacion_pct' => 'Ocupación (%)',
        _ => key,
      };
}

class ScanPage extends StatefulWidget {
  const ScanPage({super.key});

  @override
  State<ScanPage> createState() => _ScanPageState();
}

class _ScanPageState extends State<ScanPage> {
  bool _handled = false;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: const InstitutionBrand(compact: true),
        ),
        body: MobileScanner(
          onDetect: (capture) {
            if (_handled) return;
            final codes = capture.barcodes
                .map((barcode) => barcode.rawValue)
                .whereType<String>();
            if (codes.isNotEmpty) {
              _handled = true;
              Navigator.of(context).pop(codes.first);
            }
          },
        ),
      );
}
