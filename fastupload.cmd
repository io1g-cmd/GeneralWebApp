@echo off
cd /d "%~dp0"

echo Working dir: %CD%
echo.
for %%F in (admin.php) do echo admin.php last modified: %%~tF
echo.

set MSG=%*
if "%MSG%"=="" set MSG=update

git add -A .
git status

git commit -m "%MSG%"
if errorlevel 1 (
  echo.
  echo No changes to commit. If you just edited admin.php, you are probably editing a DIFFERENT folder. Check Cursor path vs Working dir above.
  pause
  exit /b 0
)

git push origin main
if errorlevel 1 (
  echo Push failed.
  pause
  exit /b 1
)

echo.
echo Pushed. https://github.com/io1g-cmd/GeneralWebApp
pause
