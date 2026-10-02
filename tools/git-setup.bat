@echo off
REM ---------------------------------------------------------------------------
REM  Puts this project under git and pushes it to GitHub.
REM
REM  Run it from anywhere - it works out the repo root from its own location.
REM  Safe to re-run: if the repo already exists it just adds, commits and pushes.
REM
REM  It deliberately stops and shows you what will be committed before doing it,
REM  because the Local WordPress site lives in local\ and must never be pushed.
REM  .gitignore excludes it; this is the belt to that braces.
REM ---------------------------------------------------------------------------

setlocal
cd /d "%~dp0.."
echo Repo root: %CD%
echo.

where git >nul 2>&1
if errorlevel 1 (
  echo ERROR: git is not on PATH. Run this from Git Bash, or install Git for Windows.
  goto :end
)

if not exist ".git" (
  echo Initialising repository on branch main...
  git init -b main
  echo.
)

REM --- identity check -------------------------------------------------------
for /f "delims=" %%i in ('git config user.name') do set GITNAME=%%i
if "%GITNAME%"=="" (
  echo No git user.name is set. Setting it for this repo only.
  git config user.name "Md Ruman Hossain"
  echo Also set your email, once, with:
  echo     git config --global user.email "you@example.com"
  echo.
)

REM --- stage and show -------------------------------------------------------
git add -A
echo.
echo ------------------- about to commit -------------------
git status --short
echo -------------------------------------------------------
echo.

REM --- safety: local\ must not be staged -------------------------------------
git diff --cached --name-only | findstr /b /c:"local/" >nul
if not errorlevel 1 (
  echo.
  echo STOP: files under local\ are staged. That is the WordPress install and
  echo the MySQL data directory - it must not be pushed.
  echo Check .gitignore contains a line reading   /local/
  echo Then run:  git rm -r --cached local
  goto :end
)

echo If that list looks right, press any key to commit and push.
echo Close this window instead if it does not.
pause >nul

git commit -m "Marwa Apply portal: plugin 1.2.0, child theme, docs and reference material"

git remote get-url origin >nul 2>&1
if errorlevel 1 (
  git remote add origin https://github.com/Ruman-Hossain/apply-marwa.git
)

echo.
echo Pushing to origin/main. GitHub may ask you to sign in.
git push -u origin main

:end
echo.
pause
