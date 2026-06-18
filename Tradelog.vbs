Set objShell = CreateObject("WScript.Shell")
objShell.Run """" & Left(WScript.ScriptFullName, InStrRev(WScript.ScriptFullName, "\")) & "start.bat""", 0, False
