@echo off

for /f "delims== tokens=1,2" %%G in (.env) do set %%G=%%H

echo Environment: %APP_ENV%
echo Database: %DB_DATABASE%@%DB_HOST%

if "%APP_ENV%" == "production" (
    echo ABORT! You are on production!
    exit /b 1
)

mysqladmin -u %DB_USERNAME% -h %DB_HOST% drop %DB_DATABASE%
mysqladmin -u %DB_USERNAME% -h %DB_HOST% create %DB_DATABASE%
if %errorlevel% neq 0 exit /b %errorlevel%

php artisan migrate
