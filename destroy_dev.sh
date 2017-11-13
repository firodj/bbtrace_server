#!/bin/bash

echo $DB_DATABASE

while read ENVSTR; do
VAR=$(echo "$ENVSTR" | cut -f1 -d=)
VAL=$(echo "$ENVSTR" | cut -f2 -d=)
if [[ -z $VAR ]]; then continue; fi
export $VAR="$VAL"
done <.env

echo "Environment: $APP_ENV"
echo "Database: $DB_DATABASE@$DB_HOST"

if [[ $APP_ENV == "production" ]]; then
    echo "ABORT! You are on production!"
    exit 1
fi

mysqladmin -u $DB_USERNAME -h $DB_HOST drop $DB_DATABASE
mysqladmin -u $DB_USERNAME -h $DB_HOST create $DB_DATABASE
if [[ $? -ne 0 ]] ; then
    exit $?
fi

php artisan migrate
