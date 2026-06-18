$projectDir   = Split-Path -Parent $MyInvocation.MyCommand.Path
$vbsPath      = "$projectDir\Tradalyze.vbs"
$iconPath     = "$projectDir\public\favicon.ico"
$desktopPath  = [Environment]::GetFolderPath("Desktop")
$shortcutPath = "$desktopPath\Tradalyze.lnk"

$shell    = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath       = "wscript.exe"
$shortcut.Arguments        = "`"$vbsPath`""
$shortcut.WorkingDirectory = $projectDir
$shortcut.Description      = "Open Tradalyze trading journal"
$shortcut.WindowStyle      = 1

if (Test-Path $iconPath) {
    $shortcut.IconLocation = "$iconPath, 0"
} else {
    $shortcut.IconLocation = "C:\Windows\System32\imageres.dll, 97"
}

$shortcut.Save()
Write-Host "Done! Tradalyze shortcut created on your Desktop."
Write-Host "Right-click it and choose 'Pin to taskbar'."
