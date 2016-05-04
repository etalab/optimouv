#!/bin/bash

LOG_FILE="/optimouv/var/logs/mailer.log"

cd /optimouv/ >> ${LOG_FILE} 2>&1 || exit 1
while [ 1 ]
do
	d=$(date "+%F %T")
	echo "=== sending emails, $d ===" >> ${LOG_FILE} 2>&1 || exit 1
	php app/console --env=prod swiftmailer:spool:send >> ${LOG_FILE} 2>&1 || exit 1
	sleep 12
done
