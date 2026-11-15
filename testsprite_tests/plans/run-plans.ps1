param(
    [string]$ProjectId = '8c1aec7e-58c1-4451-a7da-4a21d51a1641',
    [Parameter(Mandatory = $true)][ValidateSet('publik', 'kasir', 'admin-cabang', 'master')][string]$Role,
    [string]$Username = '',
    [string]$PasswordFile = '',
    [switch]$IncludeWrite,
    [string]$WriteTargetConfirmed = '',
    [switch]$Run
)

# Windows PowerShell treats the CLI's stderr receipts as errors; keep going and check exit codes.
$ErrorActionPreference = 'Continue'
$planDir = $PSScriptRoot

# Write plans create sales, returns, payments, users, and journals on the target.
# They must only run against staging or a dedicated test branch, never live books.
if ($IncludeWrite -and $WriteTargetConfirmed -ne 'staging-or-test-branch') {
    throw "Write plans need -WriteTargetConfirmed staging-or-test-branch. They post real transactions."
}

# The Free plan allows one environment per project, so each role swaps the
# sign-in account of the 'default' environment before its batch runs.
if ($Run) {
    if ($Role -eq 'publik') {
        & testsprite project sign-in set $ProjectId --mode public --env default
    } else {
        if (-not $Username -or -not (Test-Path $PasswordFile)) {
            throw "Role '$Role' needs -Username and an existing -PasswordFile."
        }
        & testsprite project sign-in set $ProjectId --mode account --env default --username $Username --password-file $PasswordFile
    }
    if ($LASTEXITCODE -ne 0) { throw "Could not switch the sign-in account for '$Role'." }
}

$kinds = @('readonly')
if ($IncludeWrite) { $kinds += 'write' }

foreach ($kind in $kinds) {
    $source = Join-Path $planDir "$Role.$kind.jsonl"
    if (-not (Test-Path $source)) { continue }

    $resolved = Join-Path $env:TEMP "testsprite-$Role.$kind.jsonl"
    (Get-Content $source -Raw).Replace('__PROJECT_ID__', $ProjectId) |
        Set-Content -Path $resolved -Encoding utf8 -NoNewline

    # Write plans share one test account, shift, and stock, so they must not overlap.
    $concurrency = if ($kind -eq 'write') { '1' } else { '3' }
    $arguments = @('test', 'create-batch', '--plans', $resolved, '--output', 'json')
    if ($Run) {
        $arguments += @('--run', '--wait', '--env', 'default', '--max-concurrency', $concurrency, '--timeout', '900')
    }

    Write-Host "== $Role / $kind ==" -ForegroundColor Cyan
    & testsprite @arguments
}
