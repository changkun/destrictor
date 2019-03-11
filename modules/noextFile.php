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

/** @file Allows you to host files with file extensions which are normally
    reserved by Destrictor. */

/** @fileext .noext

    "No extension" file extension.

    Destrictor assigns special meaning to quite a few file extensions. From
    time to time, this may cause problem if you just want to serve such a
    file. You can do this by appending ".noext" to the filename. For example,
    to serve a file under an .xhtml extension without its being interpreted
    in any way, commit it as .xhtml.noext.

    Exception: You cannot commit files named *.php.noext or *-php.noext
    unless you have the authorization to commit PHP code.
*/

hook_subscribe('prefileAdded_noext', 'noextFile_prefileAdded_noext');
hook_subscribe('fileAdded_noext', 'noextFile_fileAdded_noext');
hook_subscribe('fileUpdated_noext', 'noextFile_fileUpdated_noext');

/** Called by hook, checks whether commit is allowed. */
function /*void*/ noextFile_prefileAdded_noext(SvnChange $c) {
  $newPath = substr($c->path, 0, -strlen('.noext'));
  if (preg_match('%-php$|\.(php\d?|xht-php|html-php)(\.[^/]*)?$%',
                 $newPath) == 1) {
    auth_requireGroup('Cannot commit PHP code in "' . basename($c->path)
                      . '" (noext).', $c->author, 'php');
  }
}

/** Called by hook, copies the file to CACHE and removes the .noext
    extension. */
function /*void*/ noextFile_fileAdded_noext(SvnChange $c) {
  depend_fileChanged(dirname($c->path) . '/');
  depend_fileChanged($c->path);
  $c->finished = TRUE;

  $newPath = substr($c->path, 0, -strlen('.noext'));
  $f = CACHE . $c->path;
  svn_catToFile($c->path, $f . '_private.tmp');
  @unlink(CACHE . $newPath);
  rename($f . '_private.tmp', CACHE . $newPath);

  // Delete the file when the .noext file is removed from SVN
  anyDirOrFile_autoDel($c->path, $newPath, 'noextFile');
}

/** Called by hook, updates the file in CACHE and removes the .noext
    extension. */
function /*void*/ noextFile_fileUpdated_noext(SvnChange $c) {
  depend_fileChanged($c->path);
  $c->finished = TRUE;

  $newPath = substr($c->path, 0, -strlen('.noext'));
  if (substr($newPath, -4) == '.php' || substr($newPath, -4) == '-php') {
    return; // FIXME: Only disallow if no auth
  }
  $f = CACHE . $c->path;
  svn_catToFile($c->path, $f . '_private.tmp');
  @unlink(CACHE . $newPath);
  rename($f . '_private.tmp', CACHE . $newPath);

  // Delete the file when the .noext file is removed from SVN
  anyDirOrFile_autoDel($c->path, $newPath, 'noextFile');

  anyDirOrFile_performAutoDel($c->path, '', FALSE, FALSE);
}

?>