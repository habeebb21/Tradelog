Set objShell = CreateObject("WScript.Shell")
Dim projectDir
projectDir = Left(WScript.ScriptFullName, InStrRev(WScript.ScriptFullName, "\"))

' Start the app silently in background
objShell.Run """" & projectDir & "start.bat""", 0, False

' Launch GUI window (no console shown)
objShell.Run "powershell.exe -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File """ & projectDir & "tray.ps1"" -ProjectDir """ & projectDir & """", 0, False
