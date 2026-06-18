$projectDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$vbsPath      = "$projectDir\Tradelog.vbs"
$iconPath     = "$projectDir\public\favicon.ico"
$desktopPath  = [Environment]::GetFolderPath("Desktop")
$shortcutPath = "$desktopPath\Tradelog.lnk"

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

# Also remove old Tradalyze shortcut from desktop if it exists
$oldShortcut = "$desktopPath\Tradalyze.lnk"
if (Test-Path $oldShortcut) { Remove-Item $oldShortcut -Force }

Write-Host "Done! Tradelog shortcut created on your Desktop."
Write-Host "Right-click it and choose 'Pin to taskbar'."
