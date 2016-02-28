# BackupPC-Viz
A Visualizer for BackupPC schedule histories.

A set of tools to create web pages to graphically present the BackupPC scheduler history as a timeline.

Either the BackupPC server or the client can be display.

The data for the graphs is pulled from the BackupPC log files.

timeline.php - Displays data from server log

host-recent.php - Displays data from server log for a single client

host-history.php - Displays data from client logs

full-backups.php - Displays data from server log filtered by full backups.

The scripts are fully functional but there is a lot of duplicated code between the different scripts.

ToDo:
  Separate data gathering and data display functions
    Gather: Read log files and store data into database
    Display: Read database and display on web page
    

