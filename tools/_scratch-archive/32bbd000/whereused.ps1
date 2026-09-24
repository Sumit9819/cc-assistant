$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36'
$out = 'C:\Users\sumit\AppData\Local\Temp\claude\c--Users-sumit-Local-Sites-plugintesting-app-public\32bbd000-c160-41d2-824f-454b206fe3ed\scratchpad\home.html'
& curl.exe -s -A $ua -H 'Accept: text/html' -o $out 'https://sids-ponds.com/?ccbust=speed1'
$html = Get-Content $out -Raw
"HTML bytes: $($html.Length)"
""

$targets = @(
 'Untitled-design-45',
 'Aquascape-Home-Page-Banner-10',
 'Laguna-Home-Page-Banner-10',
 'Kichler-website-banner-final-',
 'GREENHORIZONS-BANNER-LOGO-BOTTON',
 'Magic-Carpet-Fertilizer',
 'Screenshot-2024-07-22'
)

foreach ($t in $targets) {
  $matches = [regex]::Matches($html, [regex]::Escape($t))
  "### $t  -- occurrences: $($matches.Count)"
  $shown = 0
  foreach ($m in $matches) {
    if ($shown -ge 2) { break }
    $start = [Math]::Max(0, $m.Index - 260)
    $len = [Math]::Min(360, $html.Length - $start)
    $ctx = $html.Substring($start, $len) -replace '\s+',' '
    "    ...$ctx..."
    $shown++
  }
  ""
}
