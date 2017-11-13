@echo off

php -r "foreach(file('phpunit.xml') as $l) { if (preg_match('/<env name=\"(.+?)\"\s+value=\"(.+?)\"/', $l, $m)) { echo $m[1].'='.$m[2].PHP_EOL; } }" > .env.testing

for /f "delims== tokens=1,2" %%G in (.env) do set %%G=%%H
for /f "delims== tokens=1,2" %%G in (.env.testing) do set %%G=%%H

echo Environment: %APP_ENV%
echo Database: %DB_DATABASE%@%DB_HOST%

if "%APP_ENV%" neq "testing" (
    echo ABORT! You are not on testing!
    exit /b 1
)

mysqladmin -u %DB_USERNAME% -h %DB_HOST% drop %DB_DATABASE%
mysqladmin -u %DB_USERNAME% -h %DB_HOST% create %DB_DATABASE%
if %errorlevel% neq 0 exit /b %errorlevel%

php artisan migrate
if %errorlevel% neq 0 exit /b %errorlevel%

php artisan db:seed -vvv

git clean -fxd storage/test/