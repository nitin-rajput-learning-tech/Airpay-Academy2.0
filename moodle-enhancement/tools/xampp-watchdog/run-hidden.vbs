' run-hidden.vbs - launches xampp-watchdog.ps1 with no console window.
' Task Scheduler runs this every minute on an interactive session; a raw
' powershell.exe action would flash a console window on every run.
Dim fso, sh, dir
Set fso = CreateObject("Scripting.FileSystemObject")
Set sh  = CreateObject("WScript.Shell")
dir = fso.GetParentFolderName(WScript.ScriptFullName)
sh.Run "powershell.exe -NoProfile -ExecutionPolicy Bypass -File """ & dir & "\xampp-watchdog.ps1""", 0, False
