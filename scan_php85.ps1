# scan-php85-candidates.ps1
# Usage: powershell -ExecutionPolicy Bypass -File .\scan-php85-candidates.ps1

$root = "./neo"
$maxLineLength = 85

$files = Get-ChildItem -Path $root -Recurse -Filter *.php -File

# --- Regex patterns ---

# 1. Nested function-call chains: func(func2(...)) — candidate for pipe
$pipePatterns = @(
    '\b(array_map|array_filter|array_values|array_unique|array_sum|count|implode|explode|trim|strtolower|strtoupper|ucfirst|ucwords|lcfirst|basename|dirname|str_replace|json_encode|json_decode|preg_replace|preg_replace_callback|file_get_contents|md5|bin2hex|random_bytes|rtrim|ltrim|substr|strtr|base64_decode|hash_hmac)\s*\([^()]*\b(array_map|array_filter|array_values|array_unique|array_sum|count|implode|explode|trim|strtolower|strtoupper|ucfirst|ucwords|lcfirst|basename|dirname|str_replace|json_encode|json_decode|preg_replace|preg_replace_callback|file_get_contents|md5|bin2hex|random_bytes|rtrim|ltrim|substr|strtr|base64_decode|hash_hmac)\s*\('
)

# 2. array_first / array_last candidates
$firstLastPatterns = @(
    '\[0\]\s*(;|,|\)|\?\?)',                  # $x[0]
    '\bend\s*\(\s*\$\w+',                     # end($array)
    '\breset\s*\(\s*\$\w+',                   # reset($array)
    '\barray_key_first\s*\(',
    '\barray_key_last\s*\(',
    '\[\s*count\(\$\w+\)\s*-\s*1\s*\]'        # $x[count($x)-1]
)

# --- Results collectors ---
$pipeResults      = New-Object System.Collections.Generic.List[object]
$firstLastResults = New-Object System.Collections.Generic.List[object]
$longLineResults  = New-Object System.Collections.Generic.List[object]

foreach ($file in $files) {
    $lines = Get-Content -LiteralPath $file.FullName

    for ($i = 0; $i -lt $lines.Count; $i++) {
        $line = $lines[$i]
        $lineNumber = $i + 1

        # 1. Pipe candidates
        foreach ($pattern in $pipePatterns) {
            if ($line -match $pattern) {
                $pipeResults.Add([PSCustomObject]@{
                    File = $file.FullName.Replace((Get-Location).Path, '.')
                    Line = $lineNumber
                    Content = $line.Trim()
                })
                break
            }
        }

        # 2. array_first / array_last candidates
        foreach ($pattern in $firstLastPatterns) {
            if ($line -match $pattern) {
                $firstLastResults.Add([PSCustomObject]@{
                    File = $file.FullName.Replace((Get-Location).Path, '.')
                    Line = $lineNumber
                    Content = $line.Trim()
                })
                break
            }
        }

        # 3. Long lines
        if ($line.Length -gt $maxLineLength) {
            $longLineResults.Add([PSCustomObject]@{
                File = $file.FullName.Replace((Get-Location).Path, '.')
                Line = $lineNumber
                Length = $line.Length
                Content = $line.Trim()
            })
        }
    }
}

Write-Host "`n=== 1. Pipe operator candidates ($($pipeResults.Count)) ===" -ForegroundColor Cyan
$pipeResults | Format-Table File, Line, Content -AutoSize -Wrap

Write-Host "`n=== 2. array_first() / array_last() candidates ($($firstLastResults.Count)) ===" -ForegroundColor Yellow
$firstLastResults | Format-Table File, Line, Content -AutoSize -Wrap

Write-Host "`n=== 3. Lines longer than $maxLineLength chars ($($longLineResults.Count)) ===" -ForegroundColor Red
$longLineResults | Format-Table File, Line, Length, Content -AutoSize -Wrap

# --- Optional CSV export ---
$pipeResults      | Export-Csv -Path "./pipe-candidates.csv" -NoTypeInformation -Encoding UTF8
$firstLastResults | Export-Csv -Path "./first-last-candidates.csv" -NoTypeInformation -Encoding UTF8
$longLineResults  | Export-Csv -Path "./long-lines.csv" -NoTypeInformation -Encoding UTF8

Write-Host "`nCSV files exported: pipe-candidates.csv, first-last-candidates.csv, long-lines.csv" -ForegroundColor Green