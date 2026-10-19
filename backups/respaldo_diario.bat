@echo off
setlocal
if not defined XAMPP_DIR (
  set "XAMPP_DIR=%~dp0..\..\.."
  for %%I in ("%XAMPP_DIR%") do set "XAMPP_DIR=%%~fI"
)
if not exist "%XAMPP_DIR%\mysql\bin\mysqldump.exe" set "XAMPP_DIR=%SystemDrive%\xampp"
set "MYSQLDUMP=%XAMPP_DIR%\mysql\bin\mysqldump.exe"
set "BACKUP_DIR=%~dp0"
if not defined MYSQL_HOST set "MYSQL_HOST=127.0.0.1"
if not defined MYSQL_USER set "MYSQL_USER=root"
if not defined DB_NAME set "DB_NAME=proyecto01"
if not exist "%MYSQLDUMP%" (
  echo No se encontro mysqldump en "%MYSQLDUMP%".
  echo Coloca el proyecto dentro de la carpeta htdocs de XAMPP.
  exit /b 1
)
for /f %%I in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "TIMESTAMP=%%I"
if not defined TIMESTAMP (
  echo No se pudo generar la fecha del respaldo.
  exit /b 1
)
set "BACKUP_FILE=%BACKUP_DIR%%DB_NAME%_%TIMESTAMP%.sql"
if defined MYSQL_OPTION_FILE (
  "%MYSQLDUMP%" --defaults-extra-file="%MYSQL_OPTION_FILE%" --host="%MYSQL_HOST%" --user="%MYSQL_USER%" --default-character-set=utf8mb4 --single-transaction --routines --triggers --databases "%DB_NAME%" --result-file="%BACKUP_FILE%"
) else (
  "%MYSQLDUMP%" --host="%MYSQL_HOST%" --user="%MYSQL_USER%" --default-character-set=utf8mb4 --single-transaction --routines --triggers --databases "%DB_NAME%" --result-file="%BACKUP_FILE%"
)
if errorlevel 1 (
  if exist "%BACKUP_FILE%" del /q "%BACKUP_FILE%"
  echo No se pudo crear el respaldo. Verifica que MySQL este iniciado y que las credenciales sean correctas.
  exit /b 1
)
echo Respaldo creado: "%BACKUP_FILE%"
forfiles /p "%BACKUP_DIR%" /m "%DB_NAME%_*.sql" /d -30 /c "cmd /c del @path" 2>nul
endlocal
