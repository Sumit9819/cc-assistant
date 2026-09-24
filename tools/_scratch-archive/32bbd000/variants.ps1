$ua  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
$acc = 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'

$urls = @(
 # video poster variants (original 2842x1588)
 'https://sids-ponds.com/wp-content/uploads/2024/07/Screenshot-2024-07-22-at-6.51.54%E2%80%AFPM-scaled.png',
 'https://sids-ponds.com/wp-content/uploads/2024/07/Screenshot-2024-07-22-at-6.51.54%E2%80%AFPM-2048x1144.png',
 'https://sids-ponds.com/wp-content/uploads/2024/07/Screenshot-2024-07-22-at-6.51.54%E2%80%AFPM-1536x858.png',
 'https://sids-ponds.com/wp-content/uploads/2024/07/Screenshot-2024-07-22-at-6.51.54%E2%80%AFPM-1080x603.png',
 'https://sids-ponds.com/wp-content/uploads/2024/07/Screenshot-2024-07-22-at-6.51.54%E2%80%AFPM-1024x572.png',
 'https://sids-ponds.com/wp-content/uploads/2024/07/Screenshot-2024-07-22-at-6.51.54%E2%80%AFPM-768x429.png',
 # banner variants (originals 1920x600)
 'https://sids-ponds.com/wp-content/uploads/2025/04/Aquascape-Home-Page-Banner-10-1536x480.png',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Aquascape-Home-Page-Banner-10-1080x338.png',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Aquascape-Home-Page-Banner-10-1024x320.png',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Laguna-Home-Page-Banner-10-1536x480.png',
 'https://sids-ponds.com/wp-content/uploads/2024/05/Kichler-website-banner-final--1536x480.png',
 'https://sids-ponds.com/wp-content/uploads/2024/05/GREENHORIZONS-BANNER-LOGO-BOTTON-1536x480.png',
 'https://sids-ponds.com/wp-content/uploads/2025/04/Untitled-design-45-1024x1024.png'
)

$rows = @()
foreach ($u in $urls) {
  $meta = & curl.exe -s -o NUL -A $ua -H "Accept: $acc" -w "%{http_code}|%{content_type}|%{size_download}" $u
  $p = $meta -split '\|'
  $rows += [pscustomobject]@{ Variant = ($u -split '/')[-1]; Code = $p[0]; Type = $p[1]; KB = [math]::Round([double]$p[2]/1024) }
}
$rows | Format-Table -AutoSize | Out-String -Width 200
