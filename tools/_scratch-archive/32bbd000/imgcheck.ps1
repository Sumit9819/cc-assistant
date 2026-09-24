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

$hdrs = @{
  'Accept' = 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
  'User-Agent' = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
  'Accept-Encoding' = 'gzip, deflate, br'
}

$rows = @()
foreach ($u in $urls) {
  $name = ($u -split '/')[-1]
  $file = Join-Path $dir ($name -replace '%E2%80%AF','_')
  try {
    $r = Invoke-WebRequest -Uri $u -Headers $hdrs -OutFile $file -PassThru -TimeoutSec 60
    $ct = $r.Headers['Content-Type']
    $cl = (Get-Item $file).Length
    $dim = ''
    try { $im = [System.Drawing.Image]::FromFile($file); $dim = "$($im.Width)x$($im.Height)"; $im.Dispose() } catch { $dim = 'n/a' }
    $rows += [pscustomobject]@{ File=$name; Type=$ct; KB=[math]::Round($cl/1024); Dim=$dim }
  } catch {
    $rows += [pscustomobject]@{ File=$name; Type="ERR $($_.Exception.Message)"; KB=0; Dim='' }
  }
}
$rows | Format-Table -AutoSize | Out-String -Width 200
