# BackupPC-Viz
<h2>A Visualizer for BackupPC schedule histories.</h2>

A set of tools to create web pages which graphically present the BackupPC scheduler history as an interactive timeline, facilitating analysis of scheduling issues. Problems with BackupPC scheduling may arise from sub-optimal BackupPC configuration, network problems, or client problems. These are easily spotted with this visualization tool.

The data for the graphs is pulled from the BackupPC log files. Either the BackupPC server or the client can be display. There are 4 independent scripts that plot different data:
<ul>
  <li>timeline.php - Displays data from server log</li>
  <li>host-recent.php - Displays data from server log for a single client (data from rotated log files, so limited history)</li>
  <li>host-history.php - Displays data from client logs (no file link times) </li>
  <li>full-backups.php - Displays data from server log filtered by full backups.</li>
</ul>

<h2>Future Development</h2>
<p>There is a lot of duplicated code between the different scripts, and ideally they should probably be combined to make a single script.</p>

<p>Separate data gathering and data display functions:
<ul>
  <li>Gather: Read log files and store data into database</li>
  <li>Display: Read database and display on web page</li.
</p>
