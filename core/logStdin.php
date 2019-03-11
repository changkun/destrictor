<?php /* -*- php -*-
  Originality
  -----------
  __   _
  |_) /|  Copyright (C) 2006  |  richard@
  | \/¯|  Richard Atterer     |  atterer.net
  ¯ '` ¯

  Migration
  ---------
  ┌- ┌-┐
  |  | |  Copyright (C) 2019  |  hi@
  |  | |  Changkun Ou         |  changkun.us
  └- └-┘
  This program is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License, version 3. See the file
  COPYING for details.

*/

/** @file
    Simply log standard input to syslog. This is a convenient way for us to
    see e.g. parse error messages - because php is usually called from within
    SVN pre/post-commit scripts, its normal stdout/stderr will not reach the
    terminal from which you issue the svn command. */

error_reporting(E_ALL | E_STRICT); // Include strict warnings, include notices
require_once($argv[1]); // Include config.php
require_once(MODULES . '/log.php');

/* This is called by the post-commit script to output to the log any error
   messages output by the previous PHP invocation */
$fd = fopen('php://stdin', 'r');
if ($fd === FALSE) {
  syslog(LOG_ERR, 'logStdin: Could not open stdin');
  exit(1);
}
while (!feof($fd)) {
  $l = rtrim(@fgets($fd, 1024));
  // Special case for xhtmlFile.php
  //if (substr($l, 0, 38) == 'Warning: DOMDocument::loadHTML(): Tag ') continue;
  if (!empty($l)) {
    syslog(LOG_ERR, '> ' . $l);
    echo $l, "\n";
  }
}
fclose($fd);

?>