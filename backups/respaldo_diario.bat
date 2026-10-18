@echo off
setlocal
set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
set "BACKUP_DIR=C:\xampp\backups\gestion"
set "DB_NAME=proyecto01"
if not exist "%MYSQLDUMP%" (
  echo No se encontro mysqldump en "%MYSQLDUMP%".
  exit /b 1
)
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
"%MYSQLDUMP%" --host=127.0.0.1 --user=root --single-transaction --routines --triggers "%DB_NAME%" > "%BACKUP_DIR%\%DB_NAME%_%DATE:~-4%%DATE:~3,2%%DATE:~0,2%.sql"
if errorlevel 1 exit /b 1
forfiles /p "%BACKUP_DIR%" /m "%DB_NAME%_*.sql" /d -30 /c "cmd /c del @path"
endlocal
