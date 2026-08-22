param(
    [Parameter(Mandatory = $true)]
    [string]$OutputPath,
    [Parameter(Mandatory = $true)]
    [string]$Password,
    [Parameter(Mandatory = $true)]
    [string]$GmPassword,
    [Parameter(Mandatory = $true)]
    [string]$BasePath,
    [Parameter(Mandatory = $true)]
    [string]$Language,
    [Parameter(Mandatory = $true)]
    [string]$EnableL5r,
    [Parameter(Mandatory = $true)]
    [string]$AllowedOrigins,
    [Parameter(Mandatory = $true)]
    [string]$ColorTemplate
)

$ColorTemplate = $ColorTemplate.Trim().ToLowerInvariant()

$content = @"
VTT_PASSWORD=$Password
VTT_GM_PASSWORD=$GmPassword
VTT_BASE_PATH=$BasePath
VTT_LANGUAGE=$Language
VTT_ENABLE_L5R=$EnableL5r
VTT_COLOR_TEMPLATE=$ColorTemplate
ALLOWED_ORIGINS=$AllowedOrigins
"@

$directory = Split-Path -Parent $OutputPath
if ($directory -and -not (Test-Path $directory)) {
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
}

[System.IO.File]::WriteAllText($OutputPath, $content.TrimEnd() + "`n", [System.Text.UTF8Encoding]::new($false))
Write-Host "Generated: $OutputPath"
