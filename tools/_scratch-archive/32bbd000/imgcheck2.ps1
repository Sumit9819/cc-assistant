Add-Type -AssemblyName System.Drawing
$dir = 'C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\32bbd000-c160-41d2-824f-454b206fe3ed\scratchpad\img'
if (-not (Test-Path $dir)) { New-Item -ItemType Directory $dir | Out-Null }

$urls = @(
 'https://sids-ponds.com/wp-content/uploads/2024/07/Screenshot-2024-07-22-at-6.51.54%E2%80%AFPM.png',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Untitled-design-45.png',
 'https://sids-ponds.com/wp-content/uploads/2024/05/GREENHORIZONS-BANNER-LOGO-BOTTON.png',
 'https://sids-ponds.com/wp-content/uploads/2024/05/Kichler-website-banner-final-.png',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Laguna-Home-Page-Banner-10.png',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Aquascape-Home-Page-Banner-10.png',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Magic-Carpet-Fertilizer-25-5-10-1-600x600.png',
 'https://sids-ponds.com/wp-content/uploads/2023/03/Sids-Ponds-Secure-Outdoor-Storage-Banner-min.png'
)

$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
$acc = 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'

$rows = @()
$i = 0
foreach ($u in $urls) {
  $i++
  $file = Join-Path $dir "img$i.bin"
  $meta = & curl.exe -s -o $file -A $ua -H "Accept: $acc" -w "%{http_code}|%{content_type}|%{size_download}" $u
  $parts = $meta -split '\|'
  $dim = ''
  if (Test-Path $file) {
    try { $im = [System.Drawing.Image]::FromFile($file); $dim = "$($im.Width)x$($im.Height)"; $im.Dispose() } catch { $dim = 'unreadable' }
  }
  $rows += [pscustomobject]@{
    File   = ($u -split '/')[-1]
    Code   = $parts[0]
    Type   = $parts[1]
    KB     = [math]::Round([double]$parts[2]/1024)
    Dim    = $dim
  }
}
$rows | Format-Table -AutoSize | Out-String -Width 200
