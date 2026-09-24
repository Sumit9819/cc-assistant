$ua  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
$dir = 'C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\32bbd000-c160-41d2-824f-454b206fe3ed\scratchpad\img'

function Get-WebPInfo($path) {
  if (-not (Test-Path $path)) { return 'no file' }
  $b = [System.IO.File]::ReadAllBytes($path)
  if ($b.Length -lt 32) { return 'too small' }
  $riff = [System.Text.Encoding]::ASCII.GetString($b,0,4)
  $webp = [System.Text.Encoding]::ASCII.GetString($b,8,4)
  if ($riff -ne 'RIFF' -or $webp -ne 'WEBP') { return 'not webp' }
  $fourcc = [System.Text.Encoding]::ASCII.GetString($b,12,4)
  switch ($fourcc) {
    'VP8X' {
      $w = ($b[24] -bor ($b[25] -shl 8) -bor ($b[26] -shl 16)) + 1
      $h = ($b[27] -bor ($b[28] -shl 8) -bor ($b[29] -shl 16)) + 1
      return "$w x $h  (VP8X extended)"
    }
    'VP8L' {
      $bits = [uint32]$b[21] -bor ([uint32]$b[22] -shl 8) -bor ([uint32]$b[23] -shl 16) -bor ([uint32]$b[24] -shl 24)
      $w = [int]($bits -band 0x3FFF) + 1
      $h = [int](($bits -shr 14) -band 0x3FFF) + 1
      return "$w x $h  (VP8L lossless)"
    }
    'VP8 ' {
      $w = ([int]$b[26] -bor ([int]$b[27] -shl 8)) -band 0x3FFF
      $h = ([int]$b[28] -bor ([int]$b[29] -shl 8)) -band 0x3FFF
      return "$w x $h  (VP8 lossy)"
    }
    default { return "unknown chunk: $fourcc" }
  }
}

$targets = @(
 'popup-wildflower-meadow-border-scaled.webp',
 'popup-wildflower-meadow-border.webp',
 'popup-wildflower-meadow-border-1024x1024.webp',
 'popup-wildflower-meadow-border-1536x1536.webp',
 'popup-wildflower-meadow-border-2048x2048.webp',
 'popup-wildflower-meadow-border-600x600.webp'
)

$rows = @()
foreach ($t in $targets) {
  $url  = "https://sids-ponds.com/wp-content/uploads/2026/08/$t"
  $file = Join-Path $dir $t
  $meta = & curl.exe -s -o $file -A $ua -H 'Accept: image/webp,image/*,*/*' -w "%{http_code}|%{content_type}|%{size_download}" $url
  $p = $meta -split '\|'
  $dim = if ($p[0] -eq '200') { Get-WebPInfo $file } else { '-' }
  $rows += [pscustomobject]@{
    File = $t
    Code = $p[0]
    Type = $p[1]
    KB   = [math]::Round([double]$p[2]/1KB,1)
    Dimensions = $dim
  }
}
$rows | Format-Table -AutoSize | Out-String -Width 140
