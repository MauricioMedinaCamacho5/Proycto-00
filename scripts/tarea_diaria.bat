@echo off
setlocal
if defined PHP_CLI (
  set "PHP_EXE=%PHP_CLI%"
) else if defined XAMPP_DIR (
  set "PHP_EXE=%XAMPP_DIR%\php\php.exe"
) else (
  set "PHP_EXE=%~dp0..\..\..\php\php.exe"
  for %%I in ("%PHP_EXE%") do set "PHP_EXE=%%~fI"
  if not exist "%PHP_EXE%" set "PHP_EXE=C:\xampp\php\php.exe"
)
if not exist "%PHP_EXE%" (
  where php >nul 2>nul
  if errorlevel 1 (
    echo No se encontro PHP CLI. Define PHP_CLI o XAMPP_DIR.
    exit /b 1
  )
  set "PHP_EXE=php"
)
"%PHP_EXE%" "%~dp0tarea_diaria.php" %*
exit /b %ERRORLEVEL%
