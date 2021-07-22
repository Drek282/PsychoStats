#!/usr/bin/env bash
command=$@

if [ -z "$command" ]
then
    echo "No command parameter given"
    exit 1
fi
docker start psychostats.daemon
docker exec -it psychostats.daemon perl $command