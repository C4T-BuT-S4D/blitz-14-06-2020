#!/bin/bash

#####
# Postgres: wait until container is created
#####

set +e
echo "[+] Checking is postgres container started"
python3 /db.check.py
# shellcheck disable=SC2181
while [[ $? != 0 ]]; do
  sleep 3
  echo "[*] Waiting for postgres container..."
  python3 /db_check.py
done
set -e

#####
# Django setup
#####

# Django: migrate
#
# Django will see that the tables for the initial migrations already exist
# and mark them as applied without running them. (Django won’t check that the
# table schema match your models, just that the right table names exist).
echo "[+] Django setup, executing: migrate"
python3 /app/manage.py migrate

echo "[+] Django setup, executing: collectstatic"
python3 /app/manage.py collectstatic --noinput -v 3

#####
# Start gunicorn
#####
echo "[+] Starting server..."
cd /app
gunicorn --worker-class gevent --worker-connections 512 --timeout 10 \
  --bind 0.0.0.0:8000 \
  divnik.wsgi:application
