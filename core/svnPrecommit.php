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

/** @file Called whenever an SVN commit is about to happen. At this stage, we
    can prevent it from happening by returning a non-zero exit status. Our
    stderr is returned to the user via his SVN client.

    Command line arguments: 1) Absolute path of config.php, 2) absolute path
    to SVN repository, 3) transaction name.

    For any errors that occur, the error message is output to stderr. This
    will cause the message to be sent back to the client and will cause the
    commit to fail. */

define('DESTRICTOR_VERSION', '1.0.0');
{
  $sysConfig = '/etc/destrictor/config-' . DESTRICTOR_VERSION . '.php';
  if (file_exists($sysConfig))
    require_once($sysConfig);
}

require_once($argv[1]); // Include config.php
require_once(CORE . '/loadModules.php');

try {
  if ($argv[2] != REPOSITORY)
    throw new Exception('Configured repository path "' . REPOSITORY
                 . '" does not match svn command\'s path "' . $argv[2] . '"');

  // Handle the pre-commit: Peek at txn with "svnlook", call appr. hooks etc.
  svn_precommit($argv[3]);
} catch (Exception $e) {
  // Re-output to stderr, make commit fail
  $fd = fopen('php://stderr', 'w');
  fwrite($fd, $e->getMessage());
  fclose($fd);
  exit(1);
}

?>