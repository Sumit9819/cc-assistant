$ua  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
$dir = 'C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\32bbd000-c160-41d2-824f-454b206fe3ed\scratchpad\img'

function Get-WebPDim($path) {
  $b = [System.IO.File]::ReadAllBytes($path)
  if ($b.Length -lt 32) { return 'too small' }
  if ([System.Text.Encoding]::ASCII.GetString($b,0,4) -ne 'RIFF') { return 'not riff' }
  $cc = [System.Text.Encoding]::ASCII.GetString($b,12,4)
  switch ($cc) {
    'VP8X' { $w=([int]$b[24] + ([int]$b[25]*256) + ([int]$b[26]*65536))+1
             $h=([int]$b[27] + ([int]$b[28]*256) + ([int]$b[29]*65536))+1
             return "$w x $h (VP8X)" }
    'VP8L' { $bits=[int64]$b[21] + ([int64]$b[22]*256) + ([int64]$b[23]*65536) + ([int64]$b[24]*16777216)
             return "$([int]($bits -band 0x3FFF)+1) x $([int](($bits -shr 14) -band 0x3FFF)+1) (VP8L)" }
    'VP8 ' { $w=(([int]$b[26]) + ([int]$b[27]*256)) -band 0x3FFF
             $h=(([int]$b[28]) + ([int]$b[29]*256)) -band 0x3FFF
             return "$w x $h (VP8 lossy)" }
    default { return "chunk $cc" }
  }
}

"=== 1. THE FILE YOU LINKED ==="
$u='https://sids-ponds.com/wp-content/uploads/2026/08/popup-wildflower-meadow-border.webp'
$f=Join-Path $dir 'final.webp'
$meta = & curl.exe -s -o $f -A $ua -H 'Accept: image/webp,image/*,*/*' -w "%{http_code}|%{content_type}|%{size_download}" $u
$p=$meta -split '\|'
"  HTTP $($p[0])  $($p[1])  $([math]::Round([double]$p[2]/1KB,1)) KB  $(Get-WebPDim $f)"
""
"=== 2. IS A -scaled SIBLING STILL BEING GENERATED? ==="
$u2='https://sids-ponds.com/wp-content/uploads/2026/08/popup-wildflower-meadow-border-scaled.webp'
$f2=Join-Path $dir 'final-scaled.webp'
$m2 = & curl.exe -s -o $f2 -A $ua -H 'Accept: image/webp,image/*,*/*' -w "%{http_code}|%{content_type}|%{size_download}" $u2
$p2=$m2 -split '\|'
if ($p2[0] -eq '200') { "  HTTP 200 - scaled STILL exists: $([math]::Round([double]$p2[2]/1KB,1)) KB  $(Get-WebPDim $f2)" }
else { "  HTTP $($p2[0]) - no -scaled file (this is what we want)" }
""
"=== 3. WHAT THE LIVE HOMEPAGE NOW REFERENCES ==="
$html = Join-Path $dir 'home2.html'
& curl.exe -s -A $ua -H 'Accept: text/html' -o $html 'https://sids-ponds.com/?ccbust=popupcheck9'
$h = Get-Content $html -Raw
"  homepage bytes: $($h.Length)"
foreach ($needle in @('Untitled-design-45','popup-wildflower-meadow-border','brave_popup__step__overlay__img')) {
  $c = [regex]::Matches($h, [regex]::Escape($needle)).Count
  "  '$needle' occurrences: $c"
}
""
"  overlay <img> tags as rendered:"
$imgs = [regex]::Matches($h, '<img[^>]*brave_popup__step__overlay__img[^>]*>')
foreach ($i in $imgs) { "    " + ($i.Value -replace '\s+',' ') }
