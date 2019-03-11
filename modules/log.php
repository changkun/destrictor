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

/** @file This sets up syslog logging to use the string 'destrictor' for any
    log messages that are logged via syslog(), and provides a convenience
    function to log data to the syslog. */

openlog('destrictor', LOG_PID, LOG_DAEMON);

/** If DEBUG_$name is defined (e.g. in config.php), use syslog to log a
    message with DEBUG priority. In the message, newline is replaced with the
    characters "\n". $name is prepended to the logged message.  You can omit
    $name and only call the function with one argument - in that case, that
    argument is the string to log. */
function dlog(/*string*/ $name, /*string*/ $message = FALSE) {
  if ($message == FALSE) {
    syslog(LOG_DEBUG, "$name");
    return;
  }
  if (defined("DEBUG_$name"))
    syslog(LOG_DEBUG, "$name$message");
}

?>