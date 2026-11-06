#!/bin/sh
# Creates a separate database for the test suite (see phpunit.xml).
# Runs only once, when the MySQL data volume is initialised.
set -e

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`testing\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`testing\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
