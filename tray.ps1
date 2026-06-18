param([string]$ProjectDir)

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

# Single-instance guard using a named mutex
Add-Type -TypeDefinition @"
using System;
using System.Threading;
public class SingleInstance {
    private static Mutex mutex;
    public static bool IsAlreadyRunning() {
        bool createdNew;
        mutex = new Mutex(true, "TradelogTrayWindow", out createdNew);
        if (!createdNew) {
            // Bring existing window to front
            var proc = System.Diagnostics.Process.GetCurrentProcess();
            foreach (var p in System.Diagnostics.Process.GetProcessesByName(proc.ProcessName)) {
                if (p.Id != proc.Id && p.MainWindowHandle != IntPtr.Zero) {
                    SetForegroundWindow(p.MainWindowHandle);
                }
            }
            return true;
        }
        return false;
    }
    [System.Runtime.InteropServices.DllImport("user32.dll")]
    private static extern bool SetForegroundWindow(IntPtr hWnd);
}
"@

if ([SingleInstance]::IsAlreadyRunning()) {
    exit
}

$form = New-Object System.Windows.Forms.Form
$form.Text = 'Tradelog'
$form.Size = New-Object System.Drawing.Size(300, 160)
$form.StartPosition = 'CenterScreen'
$form.FormBorderStyle = 'FixedSingle'
$form.MaximizeBox = $false
$form.MinimizeBox = $true
$form.BackColor = [System.Drawing.Color]::FromArgb(15, 23, 42)

# Icon
$iconFile = Join-Path $ProjectDir 'tradelog.ico'
if (Test-Path $iconFile) {
    $form.Icon = New-Object System.Drawing.Icon($iconFile)
}

# "Tradelog is running" label
$lbl = New-Object System.Windows.Forms.Label
$lbl.Text = 'Tradelog is running'
$lbl.ForeColor = [System.Drawing.Color]::White
$lbl.Font = New-Object System.Drawing.Font('Segoe UI', 11, [System.Drawing.FontStyle]::Bold)
$lbl.AutoSize = $true
$lbl.Location = New-Object System.Drawing.Point(20, 18)

# Clickable URL
$link = New-Object System.Windows.Forms.LinkLabel
$link.Text = 'http://localhost:8080'
$link.LinkColor = [System.Drawing.Color]::FromArgb(251, 146, 60)
$link.ActiveLinkColor = [System.Drawing.Color]::White
$link.Font = New-Object System.Drawing.Font('Segoe UI', 9)
$link.AutoSize = $true
$link.BackColor = [System.Drawing.Color]::Transparent
$link.Location = New-Object System.Drawing.Point(20, 48)
$link.Add_LinkClicked({ Start-Process 'http://localhost:8080' })

# Exit button
$btn = New-Object System.Windows.Forms.Button
$btn.Text = 'Exit'
$btn.Size = New-Object System.Drawing.Size(100, 34)
$btn.Location = New-Object System.Drawing.Point(90, 82)
$btn.FlatStyle = 'Flat'
$btn.BackColor = [System.Drawing.Color]::FromArgb(234, 88, 12)
$btn.ForeColor = [System.Drawing.Color]::White
$btn.Font = New-Object System.Drawing.Font('Segoe UI', 10, [System.Drawing.FontStyle]::Bold)
$btn.FlatAppearance.BorderSize = 0
$btn.Cursor = [System.Windows.Forms.Cursors]::Hand

$stopped = $false

$btn.Add_Click({
    $stopped = $true
    $btn.Text = 'Stopping...'
    $btn.Enabled = $false
    Set-Location $ProjectDir
    docker compose down | Out-Null
    $form.Close()
})

$form.Add_FormClosing({
    if (-not $stopped) {
        Set-Location $ProjectDir
        docker compose down | Out-Null
    }
})

$form.Controls.AddRange(@($lbl, $link, $btn))
[System.Windows.Forms.Application]::Run($form)
