Add-Type -AssemblyName System.Drawing

$size = 256
$bmp = New-Object System.Drawing.Bitmap($size, $size)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

# Dark background
$bgBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 15, 23, 42))
$g.FillRectangle($bgBrush, 0, 0, $size, $size)

# Rounded corners via clipping
$clipPath = New-Object System.Drawing.Drawing2D.GraphicsPath
$r = 48
$clipPath.AddArc(0, 0, $r*2, $r*2, 180, 90)
$clipPath.AddArc($size-$r*2, 0, $r*2, $r*2, 270, 90)
$clipPath.AddArc($size-$r*2, $size-$r*2, $r*2, $r*2, 0, 90)
$clipPath.AddArc(0, $size-$r*2, $r*2, $r*2, 90, 90)
$clipPath.CloseFigure()
$g.SetClip($clipPath)
$g.FillRectangle($bgBrush, 0, 0, $size, $size)

# Orange top bar
$orange = [System.Drawing.Color]::FromArgb(255, 234, 88, 12)
$orangeBrush = New-Object System.Drawing.SolidBrush($orange)
$g.FillRectangle($orangeBrush, 0, 0, $size, 16)

# Candlestick data: cx, wickTop, bodyTop, bodyBot, wickBot, isBull
$candles = @(
    @(48,  65, 95,  158, 178, $false),
    @(92,  52, 72,  132, 158, $true),
    @(136, 42, 58,  108, 132, $true),
    @(178, 58, 82,  128, 148, $false),
    @(218, 36, 52,  92,  118, $true)
)

$bullColor = [System.Drawing.Color]::FromArgb(255, 34, 197, 94)
$bearColor = [System.Drawing.Color]::FromArgb(255, 239, 68, 68)
$bullBrush = New-Object System.Drawing.SolidBrush($bullColor)
$bearBrush = New-Object System.Drawing.SolidBrush($bearColor)
$bullPen   = New-Object System.Drawing.Pen($bullColor, 3)
$bearPen   = New-Object System.Drawing.Pen($bearColor, 3)
$bw = 26

foreach ($c in $candles) {
    $cx=$c[0]; $wt=$c[1]; $bt=$c[2]; $bb=$c[3]; $wb=$c[4]; $bull=$c[5]
    $pen   = if ($bull) { $bullPen }   else { $bearPen }
    $brush = if ($bull) { $bullBrush } else { $bearBrush }
    $g.DrawLine($pen, $cx, $wt, $cx, $wb)
    $g.FillRectangle($brush, ($cx - $bw/2), $bt, $bw, ($bb - $bt))
}

# Dashed trend line going up-right
$trendPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(180, 251, 146, 60), 3)
$trendPen.DashStyle = [System.Drawing.Drawing2D.DashStyle]::Dash
$tPts = [System.Drawing.Point[]]@(
    [System.Drawing.Point]::new(20, 190),
    [System.Drawing.Point]::new(90, 152),
    [System.Drawing.Point]::new(165, 112),
    [System.Drawing.Point]::new(245, 68)
)
$g.DrawCurve($trendPen, $tPts)

$g.Dispose()

# Build multi-size ICO
$sizes = @(256, 48, 32, 16)
$ms = New-Object System.IO.MemoryStream
$writer = New-Object System.IO.BinaryWriter($ms)

$writer.Write([uint16]0)
$writer.Write([uint16]1)
$writer.Write([uint16]$sizes.Count)

$imgStreams = @()
foreach ($s in $sizes) {
    $resized = New-Object System.Drawing.Bitmap($bmp, [System.Drawing.Size]::new($s, $s))
    $imgMs = New-Object System.IO.MemoryStream
    $resized.Save($imgMs, [System.Drawing.Imaging.ImageFormat]::Png)
    $imgStreams += $imgMs
    $resized.Dispose()
}

$offset = 6 + ($sizes.Count * 16)
for ($i = 0; $i -lt $sizes.Count; $i++) {
    $s = $sizes[$i]
    $writer.Write([byte]$(if ($s -ge 256) { 0 } else { $s }))
    $writer.Write([byte]$(if ($s -ge 256) { 0 } else { $s }))
    $writer.Write([byte]0)
    $writer.Write([byte]0)
    $writer.Write([uint16]1)
    $writer.Write([uint16]32)
    $writer.Write([uint32]$imgStreams[$i].Length)
    $writer.Write([uint32]$offset)
    $offset += $imgStreams[$i].Length
}

foreach ($imgMs in $imgStreams) {
    $writer.Write($imgMs.ToArray())
    $imgMs.Dispose()
}

$writer.Flush()
[System.IO.File]::WriteAllBytes("h:\Tradelog\tradelog.ico", $ms.ToArray())
$ms.Dispose()
$writer.Dispose()
$bmp.Dispose()

Write-Host "Done! tradelog.ico created."
