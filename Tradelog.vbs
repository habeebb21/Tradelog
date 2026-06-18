Set objShell = CreateObject("WScript.Shell")
Dim projectDir
projectDir = Left(WScript.ScriptFullName, InStrRev(WScript.ScriptFullName, "\"))

' Start the app silently in background
objShell.Run """" & projectDir & "start.bat""", 0, False

' Open a PowerShell console — closing it will always run docker compose down
Dim psScript
psScript = "try { " & _
    "Write-Host '============================================'; " & _
    "Write-Host '  Tradelog is running at http://localhost:8080'; " & _
    "Write-Host '============================================'; " & _
    "Write-Host ''; " & _
    "Write-Host '  Close this window to stop the app.'; " & _
    "Write-Host ''; " & _
    "while ($true) { Start-Sleep -Seconds 5 } " & _
    "} finally { " & _
    "Write-Host ''; " & _
    "Write-Host 'Stopping Tradelog...'; " & _
    "Set-Location '" & projectDir & "'; " & _
    "docker compose down; " & _
    "Write-Host 'Tradelog has been stopped.'; " & _
    "Start-Sleep -Seconds 2 " & _
    "}"

objShell.Run "powershell.exe -NoProfile -NoExit -Command """ & psScript & """", 1, False
