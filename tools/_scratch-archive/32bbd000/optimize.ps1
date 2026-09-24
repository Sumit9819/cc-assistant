Add-Type -AssemblyName System.Drawing
$dir = 'C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\32bbd000-c160-41d2-824f-454b206fe3ed\scratchpad\img'
$src = Join-Path $dir 'untitled-design-45.png'

# --- 1. FULL alpha scan: is the alpha channel actually used anywhere? ---
$bmp  = New-Object System.Drawing.Bitmap($src)
$rect = New-Object System.Drawing.Rectangle(0,0,$bmp.Width,$bmp.Height)
$data = $bmp.LockBits($rect,[System.Drawing.Imaging.ImageLockMode]::ReadOnly,[System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
$len  = $data.Stride * $bmp.Height
$buf  = New-Object byte[] $len
[System.Runtime.InteropServices.Marshal]::Copy($data.Scan0,$buf,0,$len)
$bmp.UnlockBits($data)

$transparent = 0; $minA = 255
for ($i = 3; $i -lt $len; $i += 4) {
  $a = $buf[$i]
  if ($a -lt 255) { $transparent++; if ($a -lt $minA) { $minA = $a } }
}
$total = [int]($len / 4)
"ALPHA SCAN (all $total pixels)"
"  pixels with alpha < 255 : $transparent"
"  lowest alpha value      : $minA"
if ($transparent -eq 0) { "  => alpha channel is UNUSED. JPEG is safe." } else { "  => real transparency present. JPEG would flatten it; use WebP or PNG." }
""

# --- 2. Produce real replacement candidates and MEASURE them ---
function Save-Resized($srcPath,$outPath,$w,$fmt,$quality) {
  $img = [System.Drawing.Image]::FromFile($srcPath)
  $h = [int]([math]::Round($img.Height * ($w / $img.Width)))
  $nb = New-Object System.Drawing.Bitmap($w,$h)
  $g  = [System.Drawing.Graphics]::FromImage($nb)
  $g.InterpolationMode  = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
  $g.SmoothingMode      = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
  $g.PixelOffsetMode    = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
  $g.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
  $g.Clear([System.Drawing.Color]::White)
  $g.DrawImage($img,0,0,$w,$h)
  if ($fmt -eq 'jpg') {
    $codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
    $ep = New-Object System.Drawing.Imaging.EncoderParameters(1)
    $ep.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality,[int]$quality)
    $nb.Save($outPath,$codec,$ep)
  } else {
    $nb.Save($outPath,[System.Drawing.Imaging.ImageFormat]::Png)
  }
  $g.Dispose(); $nb.Dispose(); $img.Dispose()
  [pscustomobject]@{ File=(Split-Path $outPath -Leaf); Width=$w; Height=$h; KB=[math]::Round((Get-Item $outPath).Length/1KB,1) }
}

$rows = @()
$rows += Save-Resized $src (Join-Path $dir 'popup-wildflower-meadow-border-1200-q85.jpg') 1200 'jpg' 85
$rows += Save-Resized $src (Join-Path $dir 'popup-wildflower-meadow-border-1000-q85.jpg') 1000 'jpg' 85
$rows += Save-Resized $src (Join-Path $dir 'popup-wildflower-meadow-border-1000-q80.jpg') 1000 'jpg' 80
$rows += Save-Resized $src (Join-Path $dir 'popup-wildflower-meadow-border-800-q85.jpg')   800 'jpg' 85
$rows += Save-Resized $src (Join-Path $dir 'popup-wildflower-meadow-border-1000.png')     1000 'png' 0

"REPLACEMENT CANDIDATES (original: 2048x2048 PNG, 2621 KB)"
$rows | Format-Table -AutoSize | Out-String -Width 120
"Files written to: $dir"
