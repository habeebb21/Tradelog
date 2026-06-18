$projectDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$vbsPath      = "$projectDir\Tradelog.vbs"
$iconPath     = "$projectDir\tradelog.ico"
$desktopPath  = [Environment]::GetFolderPath("Desktop")
$shortcutPath = "$projectDir\Tradelog.lnk"

$shell    = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath       = "wscript.exe"
$shortcut.Arguments        = "`"$vbsPath`""
$shortcut.WorkingDirectory = $projectDir
$shortcut.Description      = "Open Tradelog trading journal"
$shortcut.WindowStyle      = 1

if (Test-Path $iconPath) {
    $shortcut.IconLocation = "$iconPath, 0"
} else {
    $shortcut.IconLocation = "C:\Windows\System32\imageres.dll, 97"
}

$shortcut.Save()

# Remove old shortcuts from desktop if they exist
foreach ($old in @("$desktopPath\Tradelog.lnk", "$desktopPath\Tradalyze.lnk")) {
    if (Test-Path $old) { Remove-Item $old -Force }
}

Write-Host "Done! Tradelog.lnk created in the project folder."
Write-Host "Right-click it and choose 'Pin to taskbar' or 'Send to Desktop'."
