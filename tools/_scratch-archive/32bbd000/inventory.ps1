$ua  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
$acc = 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
$dir = 'C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\32bbd000-c160-41d2-824f-454b206fe3ed\scratchpad'
$h   = Get-Content (Join-Path $dir 'img\home2.html') -Raw

# every uploads URL the homepage references: src, data-src, srcset, CSS background-image
$urls = [regex]::Matches($h, 'https://sids-ponds\.com/wp-content/uploads/[^"''\)\s,\\]+\.(?:png|jpe?g|webp|gif)') |
        ForEach-Object { $_.Value } | Sort-Object -Unique

"Distinct uploads images referenced on the homepage: $($urls.Count)"
""

$rows = @()
foreach ($u in $urls) {
  $meta = & curl.exe -s -I -A $ua -H "Accept: $acc" -w "%{http_code}|%{content_type}|%{size_download}|%{header_json}" -o NUL $u 2>$null
  # header_json is verbose; fall back to a GET for the byte count
  $g = & curl.exe -s -o NUL -A $ua -H "Accept: $acc" -w "%{http_code}|%{content_type}|%{size_download}" $u
  $p = $g -split '\|'
  $rows += [pscustomobject]@{
    KB   = [math]::Round([double]$p[2]/1KB,1)
    Type = ($p[1] -replace 'image/','')
    File = ($u -split '/uploads/')[-1]
  }
}

$sorted = $rows | Sort-Object KB -Descending
"=== ALL HOMEPAGE IMAGES, LARGEST FIRST ==="
$sorted | Format-Table -AutoSize | Out-String -Width 130

$ceiling = 357
$over  = $sorted | Where-Object { $_.KB -ge $ceiling }
$under = $sorted | Where-Object { $_.KB -lt $ceiling }
""
"TOTAL homepage image weight : $([math]::Round(($sorted | Measure-Object KB -Sum).Sum)) KB"
"  above the ~$ceiling KB conversion ceiling : $($over.Count) files, $([math]::Round(($over | Measure-Object KB -Sum).Sum)) KB"
"  below it (already converting)            : $($under.Count) files, $([math]::Round(($under | Measure-Object KB -Sum).Sum)) KB"
