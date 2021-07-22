#!/usr/bin/env bash

if ! [[ -d ".git" ]]
then
    echo "This script have to be run from root!"
    exit
fi

rm -rf ./www/install
cp ./docker-compose/www/config.php ./www/config.php
cp ./docker-compose/root/stats.cfg ./root/stats.cfg