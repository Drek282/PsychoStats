# How to use PsychoStats Docker setup
Purpose of this Docker setup is to unify development environment in between developers, provide fast and 
easy way to how to spin up project. 

## Architecture
Docker setup has following containers:
 - mysql - provides MariaDB server on port 3336
 - www - runs PHP 7.4 and Apache server, provides HTTP server on port 8001
 - daemon - environment for perl scripts running

All commands must be run from root folder. 

## How to
To perform first run, you have to do several things in advacen: 
 - copy PsychoStats mod files to their places
 - provide db dump into `docker-compose/mysql/dump/` to initialize Database. If there will be no dump, 
   db will initialize empty.

To spin up the project just build the images and start the containers:
```
$ docker-compose up --build
```
First run can take several minutes, since the images have to be pulled and build first. At the end, you 
should see something like:
```
Starting psychostats_mysql_1 ... done
Recreating psychostats_www_1    ... done
Recreating psychostats_daemon_1 ... done
```

Then logs from containers will appear. 

If you provided dump, you can speed up installation process with following command:
```
$ ./scritps/install.sh
```
This creates configuration files suitable for Docker setup and deletes `www/install` folder. 

If you did not provide db dump, proceed to browser and install stats manually.  


## Usage
### MySQL
To access MySQL, you can use following credentials:
- db: psychostats3_1
- user: ps3
- pwd: password
- user: root
- pwd: root_password

### WWW
You can access your psychostats server on `http://localhost:8001`.


### Daemon
You have to run log parsers in the container, you can do it manually:
```
docker run --name psychostats_daemon_1 psychostats_daemon perl stats.pl
```

or you can use script:
```
./scripts/run.sh stats.pl
```


