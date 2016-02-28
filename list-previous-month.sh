#!/bin/bash
#========================================================================
#
#  Lists the BackupPC log files for the previous month in reverse numerical order
#
# DESCRIPTION
#
#   This script finds the log files for the previous month and
#   lists them in reverse numeric order to standard output
#
# USAGE
#
#   (cd /var/lib/backuppc/log/; /usr/share/backuppc/bin/BackupPC_zcat `list-previous-month | .... )
#
#========================================================================

START_DATE=`date -d "-1 month + 1 day" +%Y-%m-%d`
find . -name LOG.\*.z -newermt "$START_DATE" | sort -r -t. -n -k 3
