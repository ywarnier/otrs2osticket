# OTRS to OsTicket migration script

## Introduction

This is a simple (and incomplete) script aiming at migrating data from OTRS 3 
to OsTicket 1.18 using direct databases connections through PDO.

There is no particular reason to be defended here. We developed this script as
we needed to offer maintenance to the ticketing system of a customer and had
no particular Perl skills (OTRS is developed in Perl) but a significant
skillset in PHP (OsTicket is developed in PHP).

This migration script is based on a little reverse-engineering of the database
and a little documentation reading.

It allows you to migrate (excluding passwords) staff/agents and customers/users
from the OTRS database to the OsTicket database after configuring a few things
in the configuration file (config/config.php).

The focus here is *not* on security as this script should be run on the command
line and requires read access to the OTRS database and write access to a 
recently-installed OsTicket system with no previous staff or customers.

The PDO assumes local *or remote* read access to the OTRS database and write
access to the OsTicket database, with default "ost_" prefixes for table names.
If you don't have that, please refer to your database documentation or be ready
to update the code.

Feel free to re-use, modify or send PRs if you feel your changes could help
others.

## Installation and configuration

No installation needed. This script runs on standard PHP 8 with PDO::MySQL.
Just copy the config/config.dist.php file to config/config.php and update the
self-documented file as necessary.

## Execution

Once your config file is ready, take a backup of the OsTicket database (see 
below) just in case, then execute:
```bash
php migrate.php
```

To take a backup of your database, use the same credentials as the ones you
used in config.php to replace the variables in brackets below, and execute:
```bash
mysqldump -h [host] -u [user] -p [database_name] > dump.sql
# you will be prompted for the password 
```
