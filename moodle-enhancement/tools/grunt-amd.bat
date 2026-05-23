@echo off
REM Wrapper to run Moodle's grunt amd build using portable Node 22.
REM Avoids needing to install Node 22 system-wide when current shell
REM has Node 24 (which fails the Moodle Gruntfile engine check).
REM
REM Usage:
REM   moodle-enhancement\tools\grunt-amd.bat                  → all amd
REM   moodle-enhancement\tools\grunt-amd.bat --root=public\theme\airpayux
REM   moodle-enhancement\tools\grunt-amd.bat --force          → skip eslint failures
REM
REM Per ADR-010 P3 — Node 22 was a Moodle 5.1+ requirement we couldn't
REM meet without admin rights. Portable Node 22 in .tools/ unblocks
REM AMD rebuilds (and the P5 modal_factory migrations + P0 borrows
REM #6/#7/#8 which all need fresh AMD bundles).

setlocal
set NODE_DIR=%~dp0..\..\.tools\node-v22.18.0-win-x64
set NODE_EXE=%NODE_DIR%\node.exe
set MOODLE_ROOT=C:\xampp\htdocs\moodle5

if not exist "%NODE_EXE%" (
    echo ERROR: portable Node 22 not found at %NODE_EXE%
    echo Run: cd %~dp0..\..\.tools ^&^& powershell -Command "Invoke-WebRequest -Uri https://nodejs.org/dist/v22.18.0/node-v22.18.0-win-x64.zip -OutFile node-22.zip; Expand-Archive node-22.zip -DestinationPath ."
    exit /b 2
)

cd /d "%MOODLE_ROOT%"
"%NODE_EXE%" node_modules\grunt\bin\grunt amd %*
endlocal
