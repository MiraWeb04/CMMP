# Auto-sync CMMP from GitHub (for local XAMPP copies).
# Schedule with Windows Task Scheduler (see comment block at bottom).
param(
    [string]$RepoPath = 'C:\xampp\htdocs\CMMP',
    [string]$Branch = '',
    [string]$LogFile = ''
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $RepoPath)) {
    Write-Error "Repo path not found: $RepoPath"
}

Set-Location -LiteralPath $RepoPath

if (-not (Test-Path -LiteralPath '.git')) {
    Write-Error "Not a git repository: $RepoPath"
}

function Write-Log([string]$Message) {
    $line = ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message)
    Write-Host $line
    if ($LogFile -ne '') {
        Add-Content -LiteralPath $LogFile -Value $line -Encoding UTF8
    }
}

Write-Log "Fetching from origin..."
& git fetch origin 2>&1 | ForEach-Object { Write-Log $_ }

if ($Branch -eq '') {
    $Branch = (& git rev-parse --abbrev-ref HEAD).Trim()
}

Write-Log "Pulling branch: $Branch"
& git pull origin $Branch 2>&1 | ForEach-Object { Write-Log $_ }

Write-Log 'Done.'

# Task Scheduler (run as your Windows user, every 15–60 minutes):
#   Program: powershell.exe
#   Arguments: -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\CMMP\scripts\windows-auto-pull.ps1" -LogFile "C:\xampp\htdocs\CMMP\logs\auto-pull.log"
# Create logs folder first. Use branch name if you stay on a feature branch:
#   ... -Branch "cursor/uccp-upgrades-d518" ...
