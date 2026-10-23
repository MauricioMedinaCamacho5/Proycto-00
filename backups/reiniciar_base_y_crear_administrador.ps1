[CmdletBinding()]
param(
    [string]$BackupPath
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$workerPath = Join-Path $PSScriptRoot 'reiniciar_base_worker.php'
$deployRoot = $env:GESTION_DEPLOY_ROOT
if (-not $deployRoot) { $deployRoot = '' }

$phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
if ($phpCommand) {
    $phpPath = $phpCommand.Source
} else {
    $candidates = @(
        'C:\xampp\php\php.exe'
    )
    $phpPath = $candidates | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } | Select-Object -First 1
}
if (-not $phpPath) {
    throw 'No se encontro php.exe. Instala PHP o define GESTION_PHP.'
}
if ($env:GESTION_PHP -and (Test-Path -LiteralPath $env:GESTION_PHP -PathType Leaf)) {
    $phpPath = $env:GESTION_PHP
}
if (-not (Test-Path -LiteralPath $workerPath -PathType Leaf)) {
    throw "No se encontro el worker: $workerPath"
}

if (-not $BackupPath) {
    $BackupPath = Get-ChildItem -LiteralPath $PSScriptRoot -Filter 'proyecto01_full_before_reset_*.sql' -File |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1 -ExpandProperty FullName
}
if (-not $BackupPath -or -not (Test-Path -LiteralPath $BackupPath -PathType Leaf)) {
    throw 'No se encontro un respaldo completo. Indica -BackupPath con el dump reciente de mysqldump.'
}
$backup = Get-Item -LiteralPath $BackupPath
if ($backup.Length -lt 1000 -or $backup.LastWriteTime -lt (Get-Date).AddDays(-1)) {
    throw 'El respaldo es demasiado pequeno o tiene mas de 24 horas. Crea y verifica un nuevo mysqldump antes de continuar.'
}

function Quote-ProcessArgument([string]$Value) {
    return '"' + $Value.Replace('"', '\"') + '"'
}

function Invoke-ResetWorker([string]$Mode, $Payload = $null) {
    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $phpPath
    $arguments = @(
        (Quote-ProcessArgument $workerPath),
        $Mode,
        (Quote-ProcessArgument $projectRoot),
        (Quote-ProcessArgument $deployRoot),
        (Quote-ProcessArgument $BackupPath)
    )
    $startInfo.Arguments = $arguments -join ' '
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo
    if (-not $process.Start()) {
        throw 'No se pudo iniciar el proceso PHP.'
    }
    if ($null -ne $Payload) {
        $json = ConvertTo-Json -InputObject $Payload -Depth 20 -Compress
        $process.StandardInput.WriteLine($json)
    }
    $process.StandardInput.Close()
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    if ($process.ExitCode -ne 0) {
        $message = $stderr.Trim()
        if (-not $message) {
            $message = "El proceso PHP termino con el codigo $($process.ExitCode)."
        }
        throw $message
    }
    try {
        return $stdout | ConvertFrom-Json
    } catch {
        throw 'PHP devolvio una respuesta que no es JSON valido.'
    }
}

function Convert-SecureStringToPlainText([Security.SecureString]$SecureValue) {
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

Write-Host 'Validando el alcance del reinicio (solo lectura)...'
$plan = Invoke-ResetWorker '--plan'

Write-Host ''
Write-Host "RESPALDO COMPLETO: $($backup.FullName)"
Write-Host "Tamano: $($backup.Length) bytes"
Write-Host "SHA-256: $((Get-FileHash -LiteralPath $backup.FullName -Algorithm SHA256).Hash)"
Write-Host ''
Write-Host 'TABLAS CUYOS REGISTROS SE BORRARAN (la estructura se conserva):'
foreach ($entry in $plan.tables.PSObject.Properties | Sort-Object Name) {
    Write-Host ('  - {0}: {1} registros' -f $entry.Name, $entry.Value)
}
Write-Host ''
Write-Host 'CONTADORES AUTO_INCREMENT QUE SE REINICIARAN:'
Write-Host ('  ' + (($plan.auto_increment_tables | Sort-Object) -join ', '))
Write-Host ''
Write-Host 'FOTOS Y ARCHIVOS DE PERFIL:'
if ($plan.profile_files.Count -eq 0) {
    Write-Host '  - No hay archivos en las carpetas de perfiles.'
} else {
    foreach ($file in $plan.profile_files) {
        Write-Host ('  - {0} ({1} bytes)' -f $file.path, $file.bytes)
    }
}
Write-Host '  - Tambien se borraran las fotos guardadas dentro de usuarios.foto y los datos de registro pendientes.'
Write-Host ('  - Fotos incrustadas en usuarios.foto: {0} cuentas' -f $plan.embedded_profile_photos)
Write-Host ''
Write-Host 'SOPORTES DE EXCUSAS:'
if ($plan.excuse_files.Count -eq 0) {
    Write-Host '  - No hay soportes subidos.'
} else {
    foreach ($file in $plan.excuse_files) {
        Write-Host ('  - {0} ({1} bytes)' -f $file.path, $file.bytes)
    }
}
Write-Host ''
Write-Host 'SE CONSERVARAN:'
Write-Host '  - La estructura de todas las tablas y los archivos SQL de migracion.'
Write-Host ('  - Los roles de referencia: ' + (($plan.roles | ForEach-Object { $_.nombre }) -join ', '))
Write-Host '  - configuracion_sistema, que se restablecera a estos valores predeterminados:'
foreach ($entry in $plan.configuration_defaults.PSObject.Properties | Sort-Object Name) {
    Write-Host ('  - {0}={1}' -f $entry.Name, $entry.Value)
}
if ($plan.configuration_current.PSObject.Properties.Count -gt 0) {
    Write-Host '  - Los valores actuales de configuracion seran sustituidos por los predeterminados anteriores.'
}
Write-Host '  - Las imagenes y recursos propios del portal fuera de api\uploads\profiles.'
Write-Host ''
Write-Host 'Se borraran tambien programas, competencias, resultados de aprendizaje y calendario academico.'
Write-Host 'Las excusas se guardan como novedades; sus soportes se borraran de api\uploads\excuses.'
Write-Host ''
Write-Host 'Se recreara una sola cuenta administradora para mauriciomedinacamacho5@gmail.com,'
Write-Host 'reutilizando su tipo/numero de documento, nombres, apellidos y telefono actuales; la foto quedara vacia.'
Write-Host 'Su contrasena y PIN se solicitaran ocultos en esta terminal y solo se guardaran como hashes de verificacion.'
Write-Host 'Se generara un carne activo con un nuevo token QR.'
Write-Host ''

$confirmation = Read-Host 'Para borrar los datos y continuar, escribe exactamente BORRAR TODO proyecto01'
if ($confirmation -cne 'BORRAR TODO proyecto01') {
    Write-Host 'Cancelado. No se modifico la base de datos ni se borraron archivos.'
    exit 0
}

$passwordSecure = Read-Host 'Crea la contrasena del administrador (minimo 8 caracteres)' -AsSecureString
$passwordConfirmSecure = Read-Host 'Repite la contrasena' -AsSecureString
$pinSecure = Read-Host 'Crea el PIN del carne (4 a 6 digitos)' -AsSecureString
$pinConfirmSecure = Read-Host 'Repite el PIN' -AsSecureString
$password = Convert-SecureStringToPlainText $passwordSecure
$passwordConfirm = Convert-SecureStringToPlainText $passwordConfirmSecure
$pin = Convert-SecureStringToPlainText $pinSecure
$pinConfirm = Convert-SecureStringToPlainText $pinConfirmSecure
$passwordSecure.Dispose()
$passwordConfirmSecure.Dispose()
$pinSecure.Dispose()
$pinConfirmSecure.Dispose()

if ($password.Length -lt 8) {
    throw 'La contrasena debe tener al menos 8 caracteres. No se borro nada.'
}
if ($password -cne $passwordConfirm) {
    throw 'Las contrasenas no coinciden. No se borro nada.'
}
if ($pin -notmatch '^\d{4,6}$') {
    throw 'El PIN debe tener de 4 a 6 digitos. No se borro nada.'
}
if ($pin -cne $pinConfirm) {
    throw 'Los PIN no coinciden. No se borro nada.'
}

$payload = @{
    expected_plan = $plan
    password = $password
    pin = $pin
}
try {
    $result = Invoke-ResetWorker '--execute' $payload
} finally {
    $password = $null
    $passwordConfirm = $null
    $pin = $null
    $pinConfirm = $null
    $payload = $null
}

Write-Host ''
Write-Host 'Reinicio terminado.'
Write-Host "Administrador creado: $($result.email) (ID $($result.admin_id))"
Write-Host "Carne con QR creado: $($result.qr_created)"
Write-Host "Fotos de perfil eliminadas: $($result.profile_files_removed)"
Write-Host "Soportes de excusas eliminados: $($result.excuse_files_removed)"
Write-Host 'Inicia sesion con la contrasena y el PIN que acabas de introducir; el QR esta disponible en "Ver mi carne".'
