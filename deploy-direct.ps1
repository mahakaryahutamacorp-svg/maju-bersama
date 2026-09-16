<#
.SYNOPSIS
    Script deployment langsung ke VPS Hostinger (Direct SSH Deploy)
    Dijalankan dari lokal Windows PowerShell tanpa bergantung pada GitHub Actions.
#>

param (
    [string]$HostingerHost = "187.53.139.230",
    [int]$HostingerPort = 22,
    [string]$HostingerUser = "root",
    [string]$HostingerPath = "/www/wwwroot/majubersama.online",
    [string]$SshKeyPath = ""
)

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "  Maju Bersama - Direct VPS Deployment   " -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan

$KeyArg = ""
if ($SshKeyPath -ne "" -and (Test-Path $SshKeyPath)) {
    $KeyArg = "-i `"$SshKeyPath`""
}

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
php artisan about --no-interaction | head -n 12
"@

Write-Host "[1/2] Connecting to $HostingerUser@$HostingerHost on port $HostingerPort..." -ForegroundColor Yellow
if ($KeyArg -ne "") {
    ssh $KeyArg -p $HostingerPort -o StrictHostKeyChecking=no "$HostingerUser@$HostingerHost" $RemoteCommand
} else {
    ssh -p $HostingerPort -o StrictHostKeyChecking=no "$HostingerUser@$HostingerHost" $RemoteCommand
}

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n[2/2] ✅ Deployment berhasil! Website aktif di https://majubersama.online" -ForegroundColor Green
} else {
    Write-Host "`n[2/2] ❌ Deployment gagal dengan exit code $LASTEXITCODE" -ForegroundColor Red
}
