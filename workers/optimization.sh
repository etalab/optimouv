#!/bin/bash

echo "Running optimization Python worker"

cd /optimouv/python

while [ 1 ]
do
	d=$(date "+%F %T")
	echo "$d Running optimization Python worker $$"
	python -u main.py -c config.py
	sleep 10
done
