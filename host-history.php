<?php
//
// Create an HTML timeline bar chart from BackupPC host log file(s) for a specified host
// Usage: 
//   Enter the URL in a browser: http://[backup-server-name]/timelines/host-history.php?host=[hostname]
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
$files = glob('/var/lib/backuppc/pc/' . $hostname . '/LOG.*\.z');
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

//
// Get the lines of the logs in chronological order (THERE SHOULD BE ONLY 1)
//
$files = glob('/var/lib/backuppc/pc/' . $hostname . '/LOG\.[[:digit:]][[:digit:]][[:digit:]][[:digit:]][[:digit:]][[:digit:]]');
usort($files, function($a, $b) {
  return filemtime($a) > filemtime($b);
});
foreach($files as $file){
  $contents = file_get_contents($file);
  $file_lines = explode("\n", $contents); //create array of lines
  unset($file_lines[count($file_lines)-1]); // remove last empty line
  $lines = array_merge($lines, $file_lines);
}

//unset($lines[count($lines)-1]); // remove last empty line
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
    // Find backup start record (there may be multiple records per backup: one for each share)
    // 2014-11-26 06:49:45 incr backup started back to 2014-10-30 08:43:14  (backup #204) for share P$
    // 2014-11-28 14:11:07 full backup started for share P$
    if ($pieces[4] === 'started') {
      $found = false;
      for ($i = $num_backups; $i >= 0; $i--) {
        if (is_null($backups[$i]->time_end)) {
          $found = true;
          break;
        }
      }
      if (!$found) { 
        $num_backups += 1;
        $backups[$num_backups] = new Backup();
        $backups[$num_backups]->hostname = $hostname;
        $backups[$num_backups]->time_start = new DateTime(substr($line, 0, 19));
        $backups[$num_backups]->has_start = true;
        $backups[$num_backups]->type = $pieces[2];
        $backups[$num_backups]->xfer = ((substr($line, -1, 1) === '$' ) ? 'smb' : 'rsync'); // Last character $?
      }
    // Find backup finished record
    // 2014-11-25 11:52:24 incr backup 225 complete, 686 files, 113235285940 bytes, 96 xferErrs (0 bad files, 0 bad shares, 96 other)
    // Backup cancelled
    // 2014-10-21 11:31:37 Aborting backup up after signal INT
    // 2014-10-21 11:31:39 Got fatal error during xfer (received signal ALRM)
    // Backup failed
    // 2014-10-22 01:41:55 Got fatal error during xfer (NT_STATUS_INSUFF_SERVER_RESOURCES listing \Users\Public\...)
    // 2014-10-22 01:42:00 Backup aborted (NT_STATUS_INSUFF_SERVER_RESOURCES listing \Users\Public\...)
    // 2014-12-08 05:19:12 Aborting backup up after signal ALRM
    // 2014-12-08 05:19:16 Got fatal error during xfer (Unexpected end of tar archive)
    } elseif ( ($pieces[5] === 'complete,') || (($pieces[2] === 'Aborting')&& ($pieces[7] === 'INT')) || ($pieces[3] === 'fatal') ) {
      $found = false;
      if (is_null($backups[$num_backups]->time_end)) {
        $backups[$num_backups]->time_end = new DateTime(substr($line, 0, 19));
        $backups[$num_backups]->has_end = true;
        if ($pieces[2] === 'Aborting') {
          $backups[$num_backups]->type = 'canceled';
        } elseif ( $pieces[3] === 'fatal' ) {
          $backups[$num_backups]->type = 'failed';
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
<span class="legend bkup-incr">Incremental Backup</span><span class="legend bkup-full">Full Backup</span><span class="legend bkup-failed">Failed Backup</span><span class="legend bkup-canceled">Cancelled Backup</span>&nbsp;
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
    if (($i == 0) || ($backups[$i]->has_start) && (($backup_start_wrapped < $last_backup_start_wrapped) || (($backup_start_minute - $last_backup_start_minute) > 1440))) {
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
      echo "', {'width': 480});" . '">';
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
