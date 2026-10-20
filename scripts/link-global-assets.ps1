# Optional junction so `php -S localhost:8080` can serve /vtt/global/ as static files.
# Junctions need NTFS. On exFAT the command fails; dev still works because the
# table API streams the same folder from backend/api.php?action=global-file.
$root = Split-Path -Parent $PSScriptRoot
$link = Join-Path $root 'vtt\global'
$target = Join-Path $root 'table-manager\public\vtt\global'

if (-not (Test-Path -LiteralPath $target)) {
    Write-Error "Missing global asset folder: $target"
    exit 1
}

if (Test-Path -LiteralPath $link) {
    Write-Host "Already present: $link"
    exit 0
}

$parent = Split-Path -Parent $link
if (-not (Test-Path -LiteralPath $parent)) {
    New-Item -ItemType Directory -Path $parent -Force | Out-Null
}

$linked = $false
try {
    New-Item -ItemType Junction -Path $link -Target $target -ErrorAction Stop | Out-Null
    $linked = $true
} catch {
    cmd /c "mklink /J `"$link`" `"$target`"" | Out-Null
    if (Test-Path -LiteralPath $link) {
        $linked = $true
    }
}

if ($linked) {
    Write-Host "Linked $link -> $target"
    exit 0
}

if ((Get-ChildItem -LiteralPath $parent -Force | Measure-Object).Count -eq 0) {
    Remove-Item -LiteralPath $parent -Force
}

Write-Host "No junction (this volume is not NTFS). Dev loads global graphics through backend/api.php?action=global-file. Production and php artisan serve still use /vtt/global/."
exit 0
