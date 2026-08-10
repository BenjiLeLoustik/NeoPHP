# scan-code-health.ps1
# Usage: powershell -ExecutionPolicy Bypass -File .\scan-code-health.ps1

$root = "./neo"
$maxLineLength = 120

# Files where dd()/dump()/var_dump() are expected to appear (definitions, not leftovers)
$excludeFromDebugCheck = @(
    'Dumper.php',
    'Debug.php',
    'DumpRecorder.php',
    'DumpsCollector.php'
)

$files = Get-ChildItem -Path $root -Recurse -Filter *.php -File

$results = @{
    MissingStrictTypes = New-Object System.Collections.Generic.List[object]
    DebugLeftovers     = New-Object System.Collections.Generic.List[object]
    LooseComparison    = New-Object System.Collections.Generic.List[object]
    TodoFixme          = New-Object System.Collections.Generic.List[object]
    TrailingWhitespace = New-Object System.Collections.Generic.List[object]
    MixedIndentation   = New-Object System.Collections.Generic.List[object]
    LongLines          = New-Object System.Collections.Generic.List[object]
    EmptyCatch         = New-Object System.Collections.Generic.List[object]
}

foreach ($file in $files) {
    $relativePath = $file.FullName.Replace((Get-Location).Path, '.')
    $content = Get-Content -LiteralPath $file.FullName -Raw
    $lines = Get-Content -LiteralPath $file.FullName

    # 1. Missing declare(strict_types=1);
    if ($content -notmatch 'declare\s*\(\s*strict_types\s*=\s*1\s*\)') {
        $results.MissingStrictTypes.Add([PSCustomObject]@{ File = $relativePath })
    }

    $isExcludedFromDebugCheck = $excludeFromDebugCheck | Where-Object { $file.Name -eq $_ }

    for ($i = 0; $i -lt $lines.Count; $i++) {
        $line = $lines[$i]
        $lineNumber = $i + 1
        $trimmed = $line.Trim()

        # Skip comment-only lines for most checks
        $isComment = $trimmed.StartsWith('//') -or $trimmed.StartsWith('*') -or $trimmed.StartsWith('#')

        # 2. Debug leftovers
        if (-not $isExcludedFromDebugCheck -and -not $isComment) {
            if ($line -match '\b(var_dump|print_r|var_export|dd|dump)\s*\(') {
                $results.DebugLeftovers.Add([PSCustomObject]@{
                    File = $relativePath; Line = $lineNumber; Content = $trimmed
                })
            }
        }

        # 3. Loose comparison (== / != but not === / !==)
        if (-not $isComment -and $line -match '(?<![=!<>])==(?!=)' -or $line -match '(?<!!)!=(?!=)') {
            if ($line -notmatch '===|!==') {
                $results.LooseComparison.Add([PSCustomObject]@{
                    File = $relativePath; Line = $lineNumber; Content = $trimmed
                })
            }
        }

        # 4. TODO / FIXME / XXX
        if ($line -match '\b(TODO|FIXME|XXX|HACK)\b') {
            $results.TodoFixme.Add([PSCustomObject]@{
                File = $relativePath; Line = $lineNumber; Content = $trimmed
            })
        }

        # 5. Trailing whitespace
        if ($line -match '\s+$') {
            $results.TrailingWhitespace.Add([PSCustomObject]@{
                File = $relativePath; Line = $lineNumber
            })
        }

        # 6. Mixed tabs/spaces indentation (line starts with space(s) then a tab, or vice versa mid-indent)
        if ($line -match '^\t+ | ^ +\t') {
            $results.MixedIndentation.Add([PSCustomObject]@{
                File = $relativePath; Line = $lineNumber
            })
        }

        # 7. Long lines
        if ($line.Length -gt $maxLineLength) {
            $results.LongLines.Add([PSCustomObject]@{
                File = $relativePath; Line = $lineNumber; Length = $line.Length
            })
        }

        # 8. Empty catch blocks without comment (catch (...) {} or catch (...) { })
        if ($line -match 'catch\s*\([^)]*\)\s*\{\s*\}') {
            $results.EmptyCatch.Add([PSCustomObject]@{
                File = $relativePath; Line = $lineNumber; Content = $trimmed
            })
        }
    }
}

function Show-Section {
    param($Title, $Items, $Color)
    Write-Host "`n=== $Title ($($Items.Count)) ===" -ForegroundColor $Color
    if ($Items.Count -gt 0) {
        $Items | Format-Table -AutoSize -Wrap
    }
}

Show-Section "Missing declare(strict_types=1)" $results.MissingStrictTypes Red
Show-Section "Debug leftovers (var_dump/print_r/dd/dump)" $results.DebugLeftovers Red
Show-Section "Loose comparison (== / != instead of === / !==)" $results.LooseComparison Yellow
Show-Section "TODO / FIXME / XXX / HACK markers" $results.TodoFixme Cyan
Show-Section "Trailing whitespace" $results.TrailingWhitespace DarkGray
Show-Section "Mixed tabs/spaces indentation" $results.MixedIndentation Yellow
Show-Section "Lines longer than $maxLineLength chars" $results.LongLines Red
Show-Section "Empty catch blocks" $results.EmptyCatch Yellow

foreach ($key in $results.Keys) {
    $results[$key] | Export-Csv -Path "./health-$($key).csv" -NoTypeInformation -Encoding UTF8
}

Write-Host "`nCSV files exported: health-*.csv" -ForegroundColor Green