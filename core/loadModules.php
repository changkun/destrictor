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

/** @file Loads all PHP files in the MODULES directory. */

error_reporting(E_ALL | E_STRICT); // Include strict warnings, include notices
ini_set('display_errors', FALSE);
ini_set('log_errors', TRUE);
ini_set('track_errors', TRUE);
ini_set('error_log', 'syslog');

/** Load all php files in MODULES directory */
$files = array();
if ($handle = opendir(MODULES)) {
  while (FALSE !== ($f = readdir($handle))) {
    /* Only record *.php files. Also, skip '.' and '..'.
       Skip emacs '.#' backup files */
    if (substr($f, -4) == '.php'
        && substr($f, 0, 2) != '.#') $files[] = $f;
  }
  closedir($handle);
}

require_once(MODULES . '/hook.php'); // Need this first
sort($files);
foreach ($files as $f) {
  //syslog(LOG_DEBUG, "Loading $f");
  require_once(MODULES . '/' . $f);
}

unset($files);

?>