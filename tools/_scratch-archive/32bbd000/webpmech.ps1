$ua  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
$acc = 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'

# Question 1: does an image with NO .webp sidecar still get served as webp?
#   Max-Flo-600x591.jpg  -> Lighthouse recorded image/webp, but Max-Flo-600x591.jpg.webp is 404.
# Question 2: is the sidecar naming extension-REPLACED rather than appended for some files?
$tests = @(
  @{ label='Max-Flo .jpg  (Accept: webp)'; url='https://sids-ponds.com/wp-content/uploads/2022/05/Max-Flo-600x591.jpg' },
  @{ label='Max-Flo .webp (ext replaced)'; url='https://sids-ponds.com/wp-content/uploads/2022/05/Max-Flo-600x591.webp' },
  @{ label='Aquascape .webp (ext replaced)'; url='https://sids-ponds.com/wp-content/uploads/2025/04/Aquascape-Home-Page-Banner-10.webp' },
  @{ label='SecureStorage .png (Accept: webp)'; url='https://sids-ponds.com/wp-content/uploads/2023/03/Sids-Ponds-Secure-Outdoor-Storage-Banner-min.png' },
  @{ label='Aquascape .png (NO webp Accept)'; url='https://sids-ponds.com/wp-content/uploads/2025/04/Aquascape-Home-Page-Banner-10.png'; noacc=$true },
  @{ label='Trimetals-web .png (2023, Accept: webp)'; url='https://sids-ponds.com/wp-content/uploads/2023/03/Trimetals-web-600x600.png' },
  @{ label='Trimetals-web .png.webp sidecar'; url='https://sids-ponds.com/wp-content/uploads/2023/03/Trimetals-web-600x600.png.webp' },
  @{ label='iQMS 2022 .png (Accept: webp)'; url='https://sids-ponds.com/wp-content/uploads/2022/05/iQMS-362i-1-600x600.png' },
  @{ label='iQMS 2022 .png.webp sidecar'; url='https://sids-ponds.com/wp-content/uploads/2022/05/iQMS-362i-1-600x600.png.webp' }
)

$rows = @()
foreach ($t in $tests) {
  if ($t.noacc) {
    $meta = & curl.exe -s -o NUL -A $ua -H "Accept: */*" $t.url -w "%{http_code}|%{content_type}|%{size_download}"
  } else {
    $meta = & curl.exe -s -o NUL -A $ua -H "Accept: $acc" $t.url -w "%{http_code}|%{content_type}|%{size_download}"
  }
  $p = $meta -split '\|'
  $rows += [pscustomobject]@{ Test=$t.label; Code=$p[0]; Type=$p[1]; KB=[math]::Round([double]$p[2]/1024) }
}
$rows | Format-Table -AutoSize | Out-String -Width 200
