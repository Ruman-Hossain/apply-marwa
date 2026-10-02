@echo off
REM ---------------------------------------------------------------------------
REM  Links this repo's plugin and child theme into the Local WordPress site.
REM
REM  Why a link and not a copy: with a junction there is only ONE copy of the
REM  source - here in the repo. Edit it in VS Code and the running site sees the
REM  change on the next page load. Nothing to sync, nothing to drift apart.
REM
REM  /J makes a directory junction. Unlike /D symbolic links it needs no
REM  administrator rights, and Windows and WordPress both follow it silently.
REM
REM  Safe to re-run. It only ever removes things INSIDE the Local site folder,
REM  never anything in the repo, and it asks before deleting a real folder.
REM ---------------------------------------------------------------------------

setlocal enabledelayedexpansion
set "REPO=%~dp0.."
set "SITE=%REPO%\local\app\public\wp-content"

echo.
echo Repo : %REPO%
echo Site : %SITE%
echo.

if not exist "%SITE%\plugins" (
  echo ERROR: %SITE%\plugins does not exist.
  echo Is the Local site created, with its folder named "local" inside this project?
  goto :end
)

call :link "%SITE%\plugins\marwa-apply" "%REPO%\plugin\marwa-apply"
call :link "%SITE%\themes\astra-child"   "%REPO%\theme\astra-child"

echo.
echo Done. Check Plugins and Appearance ^> Themes in wp-admin.
goto :end

:link
REM %1 = the link to create inside the site, %2 = the real folder in the repo
if not exist %2 (
  echo SKIP: %2 is not in the repo.
  goto :eof
)

if exist %1 (
  REM A junction carries the reparse-point attribute; a copied folder does not.
  fsutil reparsepoint query %1 >nul 2>&1
  if errorlevel 1 (
    echo.
    echo %1
    echo is a real folder, not a link - probably a copy placed there earlier.
    echo Deleting it is safe: the source stays in the repo and is about to be
    echo linked in its place. Nothing in the repo is touched.
    echo.
    set /p ANSWER=Replace it with a link? [y/N] 
    if /i not "!ANSWER!"=="y" (
      echo kept as-is: %1
      goto :eof
    )
    rmdir /s /q %1
  ) else (
    rmdir %1
  )
)

mklink /J %1 %2 >nul
if errorlevel 1 (
  echo FAILED: %1
) else (
  echo linked: %1
)
goto :eof

:end
echo.
pause
