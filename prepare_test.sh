#!/bin/bash

echo $DB_DATABASE

for ENVSTR in $(cat .env); do
VAR=$(echo $ENVSTR | cut -f1 -d=)
VAL=$(echo $ENVSTR | cut -f2 -d=)
export $VAR=$VAL
done

for ENVSTR in $(grep "<env name=" phpunit.xml | sed -E 's/<env name="([^"]+)"[ ]*value="([^"]+)".+>/\1=\2/'); do
VAR=$(echo $ENVSTR | cut -f1 -d=)
VAL=$(echo $ENVSTR | cut -f2 -d=)
export $VAR=$VAL
done

echo "Environment: $APP_ENV"
echo "Database: $DB_DATABASE@$DB_HOST"

mysqladmin -u $DB_USERNAME -h $DB_HOST drop $DB_DATABASE -f
mysqladmin -u $DB_USERNAME -h $DB_HOST create $DB_DATABASE
if [[ $? -ne 0 ]] ; then
    exit $?
fi

php artisan migrate -vvv

if [[ $? -ne 0 ]] ; then
    exit $?
fi

if [[ $1 == "rollback" ]]; then
    php artisan migrate:rollback -vvv
else
    php artisan db:seed -vvv
fi
