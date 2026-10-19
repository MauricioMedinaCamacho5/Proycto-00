@echo off
setlocal
if "%~1"=="" (
  echo Uso: restaurar_respaldo.bat "ruta\al\respaldo.sql"
  exit /b 2
)
if not exist "%~1" (
  echo No se encontro el archivo "%~1".
  exit /b 2
)
if not defined XAMPP_DIR (
  set "XAMPP_DIR=%~dp0..\..\.."
  for %%I in ("%XAMPP_DIR%") do set "XAMPP_DIR=%%~fI"
)
if not exist "%XAMPP_DIR%\mysql\bin\mysql.exe" set "XAMPP_DIR=%SystemDrive%\xampp"
set "MYSQL=%XAMPP_DIR%\mysql\bin\mysql.exe"
if not defined MYSQL_HOST set "MYSQL_HOST=127.0.0.1"
if not defined MYSQL_USER set "MYSQL_USER=root"
if not defined DB_NAME set "DB_NAME=proyecto01"
if not exist "%MYSQL%" (
  echo No se encontro mysql.exe en "%MYSQL%".
  echo Coloca el proyecto dentro de la carpeta htdocs de XAMPP.
  exit /b 1
)
echo ADVERTENCIA: se reemplazaran las tablas y los datos actuales de "%DB_NAME%".
echo Comprueba que elegiste el respaldo correcto antes de continuar.
choice /C SN /N /M "Escribe S para continuar o N para cancelar: "
if errorlevel 2 exit /b 0
if defined MYSQL_OPTION_FILE (
  "%MYSQL%" --defaults-extra-file="%MYSQL_OPTION_FILE%" --host="%MYSQL_HOST%" --user="%MYSQL_USER%" --default-character-set=utf8mb4 < "%~f1"
) else (
  "%MYSQL%" --host="%MYSQL_HOST%" --user="%MYSQL_USER%" --default-character-set=utf8mb4 < "%~f1"
)
if errorlevel 1 (
  echo No se pudo restaurar el respaldo. Verifica que MySQL este iniciado y que las credenciales sean correctas.
  exit /b 1
)
echo Respaldo restaurado correctamente en "%DB_NAME%".
endlocal
