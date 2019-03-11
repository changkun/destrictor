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

/** @file This file does some basic setup when the requestTimePhp hook is
    called by xhtmlFile_writeConfigPhp. The "globalcode" and "filecode" data
    will be written to the file CACHE.".config_private.php" after each
    commit. This code writes to it the current values of a number of
    variables (e.g. CACHE), as set up in the global config-x.y.z.php file and
    the installation-specific destrictor/config.php
    file. ".config_private.php" is read by all PHP files when they are run by
    Apache. */

hook_subscribe('requestTimePhp', 'requestTimeDefs_requestTimePhp',
               100); // Call at very start

/** Called by hook, writes the header and the definitions of some variables
    to CACHE.".config_private.php". The following variables will be available
    to PHP code in .xhtml(-php) files: BASEDIR, REPOSITORY, CACHE, CORE,
    MODULES, LANGUAGES, SVNLOCATION, USERFILE, GROUPFILE. Furthermore,
    DESTRICTOR_PATH contains path of the file from the top of the
    repository. */
function /*void*/ requestTimeDefs_requestTimePhp(&$arr) {
  /* Create 'globalcode' config values, aggregated from the global and
     installation-specific config files */
  $arr['globalcode'] .= "/* requestTimeDefs.php */\n";
  foreach (array('BASEDIR', 'REPOSITORY', 'CACHE', 'CORE', 'MODULES',
                 'LANGUAGES', 'SVNLOCATION', 'USERFILE', 'GROUPFILE') as $n)
    $arr['globalcode'] .= requestTimeDefs_getDef($n);
  $arr['globalcode'] .= "\n";

  // 'filecode' code - set up DESTRICTOR_PATH variable
  $arr['filecode'] .= requestTimeDefs_getDef('DESTRICTOR_PATH', $arr['path']);
}

function /*string*/ requestTimeDefs_getDef(/*string*/ $defineName,
                                           /*bool*/ $value = FALSE) {
  if ($value == FALSE) {
    if (!defined($defineName)) return '';
    $value = constant($defineName);
  }
  return "define('$defineName', '"
    . str_replace("'", "\\'", str_replace("\\", "\\\\", $value)) . "');\n";
}
