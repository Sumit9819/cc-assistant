$ua  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'

# SG Optimizer stores WebP copies as <original-filename>.webp alongside the source.
# If these 404, no copy exists. If they 200, the copies exist and the rewrite/serving layer is the problem.
$urls = @(
 'https://sids-ponds.com/wp-content/uploads/2023/03/Sids-Ponds-Secure-Outdoor-Storage-Banner-min.png.webp',
 'https://sids-ponds.com/wp-content/uploads/2024/07/Screenshot-2024-07-22-at-6.51.54%E2%80%AFPM.png.webp',
 'https://sids-ponds.com/wp-content/uploads/2024/07/Screenshot-2024-07-22-at-6.51.54%E2%80%AFPM-1024x572.png.webp',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Untitled-design-45.png.webp',
 'https://sids-ponds.com/wp-content/uploads/2024/05/GREENHORIZONS-BANNER-LOGO-BOTTON.png.webp',
 'https://sids-ponds.com/wp-content/uploads/2024/05/Kichler-website-banner-final-.png.webp',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Laguna-Home-Page-Banner-10.png.webp',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Aquascape-Home-Page-Banner-10.png.webp',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Magic-Carpet-Fertilizer-25-5-10-1-600x600.png.webp',
 # control: a file that DOES serve as webp, plus one of its sized variants
 'https://sids-ponds.com/wp-content/uploads/2022/05/Max-Flo-600x591.jpg.webp'
)

$rows = @()
foreach ($u in $urls) {
  $meta = & curl.exe -s -o NUL -A $ua $u -w "%{http_code}|%{content_type}|%{size_download}"
  $p = $meta -split '\|'
  $rows += [pscustomobject]@{
    Sidecar = ($u -split '/uploads/')[-1]
    Code    = $p[0]
    Type    = $p[1]
    KB      = [math]::Round([double]$p[2]/1024)
  }
}
$rows | Format-Table -AutoSize | Out-String -Width 200
