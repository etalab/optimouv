#!/bin/bash

echo "Running bestplace PHP worker"

cd /optimouv
while [ 1 ]
do
	d=$(date "+%F %T")
	echo "$d Running bestplace PHP worker"
	php app/console --env=prod rabbitmq:consumer rencontre --without-signals
	sleep 5
done
