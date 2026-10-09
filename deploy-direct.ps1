<#
.SYNOPSIS
    Script deployment langsung ke VPS Hostinger (Direct SSH Deploy)
    Dijalankan dari root workspace ini: D:\MAJUBERSAMA_CURSOR
    Contoh: .\deploy-direct.ps1
#>

param (
    [string]$HostingerHost = "187.53.139.230",
    [int]$HostingerPort = 22,
    [string]$HostingerUser = "root",
    [string]$HostingerPath = "/www/wwwroot/majubersama.online",
    [string]$SshKeyPath = ""
)

$WorkspacePath = $PSScriptRoot
Set-Location $WorkspacePath

$artisanPath = Join-Path $WorkspacePath "artisan"
$gitPath = Join-Path $WorkspacePath ".git"
if (-not (Test-Path $artisanPath) -or -not (Test-Path $gitPath)) {
    Write-Host "Script ini harus berada di root workspace Maju Bersama." -ForegroundColor Red
    Write-Host "Lokasi script: $WorkspacePath" -ForegroundColor Red
    exit 1
}

$originUrl = git -C $WorkspacePath remote get-url origin
if ($originUrl -notmatch "mahakaryahutamacorp-svg/maju-bersama(\.git)?$") {
    Write-Host "Remote origin workspace ini bukan repositori Maju Bersama: $originUrl" -ForegroundColor Red
    exit 1
}

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "  Maju Bersama - Direct VPS Deployment   " -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "Workspace lokal : $WorkspacePath" -ForegroundColor Cyan
Write-Host "Tujuan server   : ${HostingerUser}@${HostingerHost}:$HostingerPath" -ForegroundColor Cyan

$RemoteCommand = @"
set -euo pipefail
echo '==> 1. Updating repository code via git pull...'
cd '$HostingerPath'
git checkout -- .
git pull origin main

echo '==> 2. Installing Composer dependencies...'
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo '==> 3. Running database migrations & seeders...'
php artisan migrate --force
php artisan db:seed --class=ChartOfAccountSeeder --force

echo '==> 4. Clearing and optimizing application caches...'
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo '==> 5. Setting file permissions...'
chown -R www:www .
chmod -R 775 storage bootstrap/cache

echo '==> 6. Reloading services...'
systemctl reload php-fpm-83 2>/dev/null || /etc/init.d/php-fpm-83 reload 2>/dev/null || true
systemctl reload nginx 2>/dev/null || /etc/init.d/nginx reload 2>/dev/null || true

echo '==> Deployment finished successfully!'
php artisan about --no-interaction
"@

# Here-string PowerShell memakai CRLF. Bash di server menolak argumen seperti "12\r".
$RemoteCommand = $RemoteCommand -replace "`r", ""

Write-Host "[1/2] Connecting to $HostingerUser@$HostingerHost on port $HostingerPort..." -ForegroundColor Yellow
$sshArgs = @(
    "-p", "$HostingerPort",
    "-o", "StrictHostKeyChecking=no"
)
if ($SshKeyPath -ne "" -and (Test-Path $SshKeyPath)) {
    $sshArgs += @("-i", $SshKeyPath)
}
$sshArgs += @("$HostingerUser@$HostingerHost", "bash", "-s")
$RemoteCommand | & ssh @sshArgs

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n[2/2] ✅ Deployment berhasil! Website aktif di https://majubersama.online" -ForegroundColor Green
} else {
    Write-Host "`n[2/2] ❌ Deployment gagal dengan exit code $LASTEXITCODE" -ForegroundColor Red
}
