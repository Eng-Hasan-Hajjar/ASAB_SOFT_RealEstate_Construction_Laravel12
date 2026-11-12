@echo off
setlocal
cd /d "%~dp0"
echo.
echo == ASAB SOFT - Accounts and Excel Seed Import ==
echo Verify .env and the private data files before continuing.
pause
php artisan optimize:clear || goto :error
php artisan migrate || goto :error
php artisan db:seed --class=AsabInitializeAccountsSeeder || goto :error
php artisan db:seed --class=AsabClientExcelSeeder || goto :error
php artisan asab:seed-audit || goto :error
echo.
echo Finished. Run: php artisan serve
pause
exit /b 0
:error
echo.
echo Failed: read the command output. No database reset was attempted.
pause
exit /b 1
