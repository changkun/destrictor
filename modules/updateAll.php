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

    Re-create entire cache contents. When an attempt is made to commit the
    file REPOSITORYDIR/.updateall, then this module will cause all files to
    be regenerated. It does this by listing the contents of the SVN
    repository and faking an "SVN added" for each file and directory.

    It does not matter what data you attempt to commit to the .updateall
    file: This module will always make the attempt fail to avoid that the SVN
    repository is cluttered with the contents of the file and the revisions
    which update it.

    Adding an .updateall file in your working copy is a bit awkward, as you
    will have to delete it again afterwards (because the commit attempt will
    always fail). Thus, it is recommended you attempt to update the file with
    a command which does not require a working copy. The "svn mkdir"
    sub-command comes in handy: It performs a commit in a single command and
    it can take an URL as its argument. Example invocation:

    <tt>svn mkdir --username admin --password secret -m "" svn://svn.example.org/trunk/.updateall</tt>

    Only members of the "admin" group can trigger the update. Additionally,
    if a group "updateall" exists, its members can also trigger the update.

    Note: The contents of CACHE are <em>not</em> deleted before everything is
    regenerated. Thus, old clutter, in particular old _private.depend files,
    will not get cleaned up. If you want to start with a clean directory, you
    can delete the contents of CACHE before you start the update. However, be
    careful <em>not</em> to delete the "destrictor" sub-directory and the
    "dot-files" in the CACHE root, e.g. ".htaccess", ".htgroup" and
    ".htpasswd".
*/

hook_subscribe('predirAdded', 'updateAll_preadded');
hook_subscribe('prefileAdded_updateall', 'updateAll_preadded');

/** Called by hook. */
function /*void*/ updateAll_preadded(SvnChange $c) {
  // Allow commit if it is not for our trigger path
  if ($c->path != '/.updateall') return;

  auth_requireGroup('Cannot regenerate entire CACHE',
                    $c->author, 'admin', 'updateall');

  updateAll_update();
  /* Because we are in the pre-commit script, atExit will not be called. Call
     depend_atExit manually. */
  depend_atExit();
  throw new Exception('updateAll: OK, regenerating all files in CACHE');
}

/** Perform the update: Get the complete list of files in SVN, then call the
    fileAdded_xyz hook, using the right xyz depending on the file
    extension. */
function /*void*/ updateAll_update() {
  $paths = svn_tree('');
  $c = new SvnChange();
  foreach ($paths as $path) {
    $c->content = 'A';
    $c->prop = 'A';
    $c->author = NULL;
    $c->finished = FALSE;
    if (substr($path, -1) == '/') {
      // Directory
      $c->path = substr($path, 0, -1);
      $c->type = 2;
      syslog(LOG_DEBUG, "updateAll: dirAdded " . $c->path);
      hook_call('dirAdded', $c);
    } else {
      // Regular file
      $c->path = $path;
      $c->type = 1;
      $ext = ''; // Non-empty if file with extension was modified
      $n = strrpos($c->path, '.');
      if ($n !== FALSE && $n > strrpos($c->path, '/'))
        $ext = '_' . substr($c->path, $n + 1); // call hook "fileAdded_htm"
      syslog(LOG_DEBUG, "updateAll: fileAdded$ext " . $c->path);
      hook_call("fileAdded$ext", $c);
      // If nobody picked it up, now call "fileAdded"
      if (!empty($ext) && $c->finished !== TRUE) hook_call("fileAdded", $c);
    }
  }
}

?>
