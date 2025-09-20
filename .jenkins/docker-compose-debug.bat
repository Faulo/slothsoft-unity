setlocal
cd %~dp0
call load-env
start "%COMPOSER_VENDOR%/%COMPOSER_MODULE% (localhost)" docker compose --project-name %COMPOSER_VENDOR%_%COMPOSER_MODULE% --file docker-compose-debug.yml up --build --force-recreate --pull always
endlocal