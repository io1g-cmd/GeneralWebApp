@echo off
setlocal
cd /d "%~dp0"

set MSG=%*
if "%MSG%"=="" set MSG=update

git add .
git status --short
if errorlevel 1 exit /b 1

git commit -m "%MSG%"
if errorlevel 1 (
  echo Nothing to commit or commit failed.
  exit /b 1
)

git push origin main
if errorlevel 1 (
  echo Push failed.
  exit /b 1
)

echo.
echo Done. Pushed to https://github.com/io1g-cmd/GeneralWebApp
endlocal
