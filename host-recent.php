<?php
//
// Create an HTML timeline bar chart from BackupPC server log file(s)
// Usage: 
//   Enter the URL in a browser: http://[backup-server-name]/timelines/host-recent.php?host=[hostname]
//
// NOTE that an inaccurate entry will be displayed for a host if the log contains a record for starting a backup of a second resource, but does not contain the record for starting the backup of the first resource
//


// class definition
class Backup {
    // define properties
    public $hostname;
    public $time_start;
    public $has_start;
    public $time_end;
    public $has_end;
    public $type;
    public $xfer;
    public $time_link_start;
    public $has_link_start;
    public $time_link_end;
    public $has_link_end;
}
$start_hour = 9; // This represents the hour that the day is cycled and is probably best set to the same time that the logs are rotated
$num_backups = -1;
if (PHP_SAPI === 'cli') {
  $hostname = $argv[1];
} else {
  if (isset($_GET['host'])) {
    $hostname = $_GET['host'];
  }else{
    $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
    $hostname = substr($hostname, 0, strpos($hostname, ".") );
  }
}

//
// Get the lines of the old compressed logs in chronological order
//
$files = glob('/var/lib/backuppc/log/LOG\.[[:digit:]]*\.z');
usort($files, function($a, $b) {
  return filemtime($a) > filemtime($b);
});

$lines = array();
foreach($files as $file){
  $contents = shell_exec('/usr/share/backuppc/bin/BackupPC_zcat ' . $file);
  $file_lines = explode("\n", $contents); //create array of lines
  unset($file_lines[count($file_lines)-1]); // remove last empty line
  $lines = array_merge($lines, $file_lines);
}

$contents = file_get_contents("/var/lib/backuppc/log/LOG");
$file_lines = explode("\n", $contents); //create array of lines
unset($file_lines[count($file_lines)-1]); // remove last empty line
$lines = array_merge($lines, $file_lines);

//var_dump($lines);
$log_start = new DateTime(substr($lines[0], 0, 19));
if (PHP_SAPI === 'cli') {
  $log_end = new DateTime(substr($lines[count($lines)-1], 0, 19));
} else {
  $log_end = new DateTime();
}
//var_dump($log_start);
//var_dump($log_end);

  // array to hold the info for each backup: host name, start time, end time, backup type, xfer method
  $backups= array();

  // loop through data
  foreach ($lines as $line) {
    $pieces = preg_split("/[\s]+/", $line);
    // Find BackupPC started records
    // 2014-10-30 09:29:31 BackupPC started, pid 1333
    // Set the end time for all backups that haven't finished yet
    if (($pieces[2] === 'BackupPC') && ($pieces[3] === 'started,')) {
      $server_start = new DateTime(substr($line, 0, 19));
      for ($i = 0; $i <= $num_backups; $i++) {
        if (is_null($backups[$i]->time_end)) {
          $backups[$i]->time_end = clone $server_start;
          $backups[$i]->type = "canceled";
        }
        if (is_null($backups[$i]->time_link_end) && $backups[$i]->has_link_start === true) {
          $backups[$i]->time_link_end = clone $server_start;
        }
      }
    // Find backup start record (there may be multiple records per backup: one for each share)
    // 2014-10-21 09:21:51 Started full backup on suf-wdoppel (pid=28948, share=c$)
    // 2014-10-21 17:06:35 Started incr backup on suf-icollab (pid=15771, share=/)
    } elseif (($pieces[2] === 'Started')&& ($pieces[4] === 'backup') && ($pieces[6] === $hostname)) {
      $found = false;
      for ($i = $num_backups; $i >= 0; $i--) {
        if (($backups[$i]->hostname === $pieces[6]) && (is_null($backups[$i]->time_end))){
          $found = true;
          break;
        }
      }
      if (!$found) { 
        $num_backups += 1;
        $backups[$num_backups] = new Backup();
        $backups[$num_backups]->hostname = $pieces[6];
        $backups[$num_backups]->time_start = new DateTime(substr($line, 0, 19));
        $backups[$num_backups]->has_start = true;
        $backups[$num_backups]->type = $pieces[3];
        $backups[$num_backups]->xfer = ((substr($line, -2, 1) === '$' ) ? 'smb' : 'rsync');
      }
    // Find backup finished record
    // 2014-10-21 09:03:55 Finished full backup on suf-wcheng
    // 2014-10-22 01:42:00 Backup failed on suf-wtinkerer (NT_STATUS_INSUFF_SERVER_RESOURCES listing [some_location])
    // 2014-10-21 11:31:39 Backup canceled on suf-wdoppel (received signal ALRM)
    } elseif ((($pieces[2] === 'Finished') && ($pieces[4] === 'backup') && ($pieces[6] === $hostname)) ||
              (($pieces[2] === 'Backup') && (($pieces[3] === 'failed') || ($pieces[3] === 'canceled')) && ($pieces[5] === $hostname))) {
      $host = ($pieces[2] === 'Finished'? $pieces[6]: $pieces[5]);
        
      $found = false;
      for ($i = $num_backups; $i >= 0; $i--) {
        if (($backups[$i]->hostname === $host) && (is_null($backups[$i]->time_end))) {
          $found = true;
          $backups[$i]->time_end = new DateTime(substr($line, 0, 19));
          $backups[$i]->has_end = true;
          if ($pieces[2] === 'Backup') {
            $backups[$i]->type = $pieces[3];
          }
          break;
        }
      }
      if (!$found) {
        $num_backups += 1;
        $backups[$num_backups] = new Backup();
        $backups[$num_backups]->hostname = $host;
        $backups[$num_backups]->time_start = $log_start;
        $backups[$num_backups]->has_start = false;
        $backups[$num_backups]->time_end = new DateTime(substr($line, 0, 19)); 
        $backups[$num_backups]->has_end = true;
        $backups[$num_backups]->type = $pieces[3];
//var_dump($backups[$num_backups]);
      }
    // Find BackupPC_link starting record
    // 2014-11-10 10:54:13 Running BackupPC_link suf-wcheng (pid=13649)
    } elseif (($pieces[2] === 'Running') && ($pieces[3] === 'BackupPC_link') && ($pieces[4] === $hostname)) {
      $host = $pieces[4];

      $found = false;
      for ($i = $num_backups; $i >= 0; $i--) {
        if ($backups[$i]->hostname === $host) {
          if (is_null($backups[$i]->time_link_start)) {
            $found = true;
            $backups[$i]->time_link_start = new DateTime(substr($line, 0, 19));
            $backups[$i]->has_link_start = true;
          }
          break;
        }
      }
    // Find BackupPC_link finished record
    // 2014-11-10 10:54:23 Finished suf-wcheng (BackupPC_link suf-wcheng)
    } elseif (($pieces[2] === 'Finished') && ($pieces[4] === '(BackupPC_link') && ($pieces[3] === $hostname)) {
      $host = $pieces[3];
      $found = false;
      for ($i = $num_backups; $i >= 0; $i--) {
        if ($backups[$i]->hostname === $host) {
          if (is_null($backups[$i]->time_link_end)) {
            $found = true;
            $backups[$i]->time_link_end = new DateTime(substr($line, 0, 19));
            $backups[$i]->has_link_end = true;
          }
          break;
        }
      }
    } // end of if...elseif... search for matching record
  }

  // Set the end time for all backups that haven't finished yet
  for ($i = 0; $i <= $num_backups; $i++) {
    if (is_null($backups[$i]->time_end)) {
//echo "\nNot finished: ", $backups[$i]->hostname;
      $backups[$i]->time_end = $log_end;
      $backups[$i]->has_end = false;
    }
  }

  // Set the end link time for all backups that have started but haven't finished the link phase yet
  for ($i = 0; $i <= $num_backups; $i++) {
    if (!is_null($backups[$i]->time_link_start) && is_null($backups[$i]->time_link_end)) {
//echo "\nNot finished: ", $backups[$i]->hostname;
      $backups[$i]->time_link_end = $log_end;
      $backups[$i]->has_link_end = false;
    }
  }
?>

 
<html><head profile="http://www.w3.org/2005/10/profile">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"/>
  <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
  <link rel="stylesheet" type="text/css" href="/timelines/assets/timeline.css" />
  <link rel="icon" type="image/ico" href="/timelines/assets/timeline-icons.ico">
  <script type="text/javascript" src="/timelines/assets/jquery-2.1.1.min.js"></script>
  <script type="text/javascript" src="/timelines/assets/nhpup_1.1.js"></script>
    <title>BackupPC Timeline</title>
</head> 
<body>
<span id="nav-buttons"> </span>
<?php
  echo $num_backups+1 . " backups from " . $log_start->format("r") . " to " . $log_end->format("r") . PHP_EOL;
  $graph_start = (9 - $start_hour) *60; // Shift for the background image from 9:00 to the desired start hour
  $graph_width = 0;
  for ($i = 0; $i <= $num_backups; $i++) {
    $backup_minutes = intval(((int)$backups[$i]->time_end->format("U") - (int)$backups[$i]->time_start->format("U"))/60);
    $backup_start_wrapped = (int)$backups[$i]->time_start->format("G") * 60 + (int)$backups[$i]->time_start->format("i");
    if ($backup_start_wrapped < $start_hour*60) {
      $backup_start_wrapped = $backup_start_wrapped + (24-$start_hour)*60;
    } else {
      $backup_start_wrapped = $backup_start_wrapped - $start_hour*60;
    }
     
    $graph_width = max($graph_width, $backup_start_wrapped + $backup_minutes);
  }
?>
<span id="help"><img id="help-icon" src="/timelines/assets/help-icon.png" />
  <div><strong>There are two types of backups:</strong>
    <hr>
    <span class="legend bkup-full">Full Backups</span> are complete backups of all configured resources (drives and/or directories)<br>
    <hr>
    <span class="legend bkup-incr">Incremental Backups</span> transfer only the files that have been created or modified since the last backup.<br>
Microsoft Windows systems use the SMB protocol which relies solely on the modification date timestamp of a file when checking for new or modified files.<br>
Linux and Mac OS X systems use the rsync protocol which performs a thorough comparison of every file and will backup any file that did not exist or has changed since the last backup regardless of what the timestamp says.<br>
    <hr>
<strong>Backups may be incomplete for one of two reasons:</strong>
<hr>
<span class="legend bkup-failed">Failed Backups</span> can be caused by a variety of things. They can occur occasionally due to intermittant problems on the server, PC, or the networkand should not be a concern. If they are occurring frequently it probably indicates a problem on the PC that should be investigated and fixed. Typically a reboot will resolve the problem if the system is currently suffering from a series of repeated failures.
<hr>
<span class="legend bkup-canceled">Cancelled Backups</span>are backups that have been cancelled by an administrator or user of the PC.
<hr>
<strong>After a backup has completed successfully some processing on the server is performed:</strong>
<hr>
<span class="legend linkphase">File Linking</span>processes every file from the backup that did not already exist on the server. The length of time is linearly proportional to the number of files and is not dependant on the size of the files.
  </div></span>&nbsp;
<span class="legend bkup-full">Full Backup</span><span class="legend bkup-incr">Incremental Backup</span><span class="legend bkup-failed">Failed Backup</span><span class="legend bkup-canceled">Cancelled Backup</span>&nbsp;<span class="legend linkphase">File Linking</span>
  <div id="lineholder" class="timeline" style="background-position:<?php echo $graph_start ?>px 0px; width:<?php echo $graph_width; ?>;">
  <div id="vertical"></div>
    <ul class="events">
<?php

  for ($i = 0; $i <= $num_backups; $i++) {
    $backup_minutes = intval(((int)$backups[$i]->time_end->format("U") - (int)$backups[$i]->time_start->format("U"))/60);
    $backup_start_minute = (int)$backups[$i]->time_start->format("U")/60;
    $backup_start_wrapped = (int)$backups[$i]->time_start->format("G") * 60 + (int)$backups[$i]->time_start->format("i");
    $backup_day = clone $backups[$i]->time_start;
    if ($backup_start_wrapped < $start_hour*60) {
      $backup_day = $backup_day->sub(new DateInterval('P1D')); // Backup is from previous day cycle
      $backup_start_wrapped = $backup_start_wrapped + (24-$start_hour)*60;
    } else {
      $backup_start_wrapped = $backup_start_wrapped - $start_hour*60;
    }
    $backup_day->setTime( $start_hour, 0);

    $bar_size = $backup_minutes;
    if (($i == 0) || ($backups[$i]->has_start) && (($backup_start_wrapped < $last_backup_start_wrapped) || (($backup_start_minute - $last_backup_start_minute) > 1439))) {
      if ((int)$backup_day->format("j") & 1) {
        $dateparity = 'odd';
      } else {
        $dateparity = 'even';
      }
      if ($i != 0) {echo '</div></div>';}
      echo '<div class="date-' . $dateparity . '"><div class="date-' . $dateparity . ' oneday"><a class="datelink" href="/timelines/archives/allbackups/daily/timeline-daily-' . $backup_day->format("Y-m-d") . '.html">' . $backup_day->format("D Y-m-d") . '</a>';
    }
    $last_backup_start_wrapped = $backup_start_wrapped;
    $last_backup_start_minute = $backup_start_minute;

    echo '<li class="bkup-' . $backups[$i]->type . ' xfer-' . $backups[$i]->xfer . '" style="width: ' . $bar_size . '; left: ' . $backup_start_wrapped . ';" ';
    echo 'onmouseover="nhpup.popup(' . "'<strong>" . $backups[$i]->hostname . '</strong>';
      if ($backups[$i]->has_start) { echo '<br>S:' . str_replace(" ", "&nbsp;", $backups[$i]->time_start->format("D M j H:i:s T")); }
      if ($backups[$i]->has_end)   { echo '<br>E:' . str_replace(" ", "&nbsp;", $backups[$i]->time_end->format("D M j H:i:s T")); }
      if ($backups[$i]->has_link_start) { echo '<br>L:' . str_replace(" ", "&nbsp;", $backups[$i]->time_link_start->format("D M j H:i:s T")); }
      if ($backups[$i]->has_link_end)   { echo '<br>F:' . str_replace(" ", "&nbsp;", $backups[$i]->time_link_end->format("D M j H:i:s T")); }
      echo "', {'width': 480});" . '">';
    if ($backups[$i]->has_link_start) {
      $link_start_on_graph = intval(((int)$backups[$i]->time_link_start->format("U") - (int)$backups[$i]->time_start->format("U"))/60);
      $link_minutes = intval(((int)$backups[$i]->time_link_end->format("U") - (int)$backups[$i]->time_link_start->format("U"))/60);
      $bar_size = $link_minutes;
      echo '<span class="linkphase" style="width: ' . $bar_size . '; left: ' . $link_start_on_graph . ';">&nbsp;</span>';
    }
    echo '<em><a href="/backuppc/index.cgi?host=' . $backups[$i]->hostname . '">';
    echo $backups[$i]->hostname . "</a></em>";
    echo "</li>\n";
  }
?>
      </div></div> <!-- end .date-??? and oneday divs -->
    </ul> <!-- end .events -->
  </div> <!-- end .timeline -->
  <!-- end #lineholder -->
<br><br> <br><br>
  <script type="text/javascript" src="/timelines/assets/timeline.js"></script>
</body></html>
