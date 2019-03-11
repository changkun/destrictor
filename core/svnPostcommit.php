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

/** @file Called whenever an SVN commit is complete. Our exit status is
    ignored at this point.

    Command line arguments: 1) Absolute path of config.php, 2) absolute path
    to SVN repository, 3) transaction name. */

define('DESTRICTOR_VERSION', '1.0.0');
{
  $sysConfig = '/etc/destrictor/config-' . DESTRICTOR_VERSION . '.php';
  if (file_exists($sysConfig))
    require_once($sysConfig);
}

if (count($argv) > 2) {
  require_once($argv[1]); // Include config.php
  require_once(CORE . '/loadModules.php');

  if ($argv[2] != REPOSITORY)
    trigger_error('Configured repository path "' . REPOSITORY
      . '" does not match svn command\'s path "' . $argv[2] . '"');

  svn_postcommit($argv[3]);
  $ignore = FALSE;
  hook_call('atExit', $ignore);
}
?>