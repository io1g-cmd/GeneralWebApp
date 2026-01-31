@echo off
setlocal
cd /d "%~dp0"

set MSG=%*
if "%MSG%"=="" set MSG=update

git add .
echo.
git status
echo.

git diff --cached --quiet 2>nul
if errorlevel 1 (
  goto :do_commit
)
echo [fastupload] 沒有變更可提交 (working tree clean)
echo.
echo 若你確定有改程式碼，請檢查：
echo   1. 檔案是否已儲存 (Ctrl+S)
echo   2. 是否在正確的專案目錄：%~dp0
echo   3. 改的檔案是否被 .gitignore 排除 (例如 data^)
echo.
exit /b 0

:do_commit
git commit -m "%MSG%"
if errorlevel 1 (
  echo Commit failed.
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
