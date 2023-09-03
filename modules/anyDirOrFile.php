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

/** @file Default action when a directory or file is added/modified/removed
    and no other handler has taken care of it: Put a copy of the file/dir in
    the right directory inside CACHE. The registered hooks are executed with
    a low priority of -50, so that handlers for specific file extensions
    (which register with a priority of 0) will be able to prevent it from
    happening. */

hook_subscribe('dirAdded', 'anyDirOrFile_dirAdded', -50);
hook_subscribe('dirDeleted', 'anyDirOrFile_dirDeleted', -50);

hook_subscribe('fileAdded', 'anyDirOrFile_fileAdded', -50);
hook_subscribe('fileUpdated', 'anyDirOrFile_fileUpdated', -50);
hook_subscribe('fileDeleted', 'anyDirOrFile_fileDeleted', -50);

/** When any dir gets added, create it inside CACHE */
function anyDirOrFile_dirAdded(SvnChange $c) {
  // Two dep changes: The dir listing changes and the new dir itself changes
  depend_fileChanged(dirname($c->path) . '/'); // Sloppy: arg can become "//"
  depend_fileChanged($c->path);
  //syslog(LOG_DEBUG, "anyDirOrFile_dirAdded " . CACHE . $c->path);
  if (!file_exists(CACHE . $c->path)) mkdir(CACHE . $c->path);
}

/** When any dir gets deleted, remove it inside CACHE */
function anyDirOrFile_dirDeleted(SvnChange $c) {
  /* Three dep changes: The contents both of the deleted dir and its parent
     change, and the deleted dir itself changes. */
  depend_fileChanged(dirname($c->path) . '/');
  depend_fileChanged($c->path . '/');
  depend_fileChanged($c->path);
  anyDirOrFile_rmdirRecursive(CACHE . $c->path);
}
//____________________

/** Recursively delete a directory and its contents, like "rm -rf". As a
    security precaution, will refuse to delete anything outside CACHE. */
function /*void*/ anyDirOrFile_rmdirRecursive($dirname) {
  //syslog(LOG_DEBUG, "anyDirOrFile_rmdirRecursive: $dirname");
  if (substr($dirname, 0, strlen(CACHE) + 1) != (CACHE . '/'))
    throw new Exception('anyDirOrFile_rmdirRecursive: Will not delete '
                        . 'anything outside CACHE');

  $files = anyDirOrFile_dir($dirname);
  if ($files === FALSE) return;
  foreach ($files as $f) {
    $x = "$dirname/$f";
    if (is_dir($x) && !is_link($x)) anyDirOrFile_rmdirRecursive($x);
    else unlink($x);
  }
  rmdir($dirname);
}
//______________________________________________________________________

/** Any file added, put it into CACHE exactly as committed. */
function anyDirOrFile_fileAdded(SvnChange $c) {
  // Two dep changes: The dir listing changes and the new file "changes"
  depend_fileChanged(dirname($c->path) . '/');
  depend_fileChanged($c->path);
  $f = CACHE . $c->path;
  svn_catToFile($c->path, $f . '_private.tmp');
  @unlink($f);
  rename($f . '_private.tmp', $f);
}

/** Any file updated, put the updated version into CACHE. */
function anyDirOrFile_fileUpdated(SvnChange $c) {
  depend_fileChanged($c->path);
  $f = CACHE . $c->path;
  svn_catToFile($c->path, $f . '_private.tmp');
  @unlink($f);
  rename($f . '_private.tmp', $f);
  anyDirOrFile_performAutoDel($c->path, '', FALSE, FALSE);
}

/** Any file deleted, also delete it from CACHE. */
function anyDirOrFile_fileDeleted(SvnChange $c) {
  // Two dependency changes: The dir listing changes and the file "changes"
  depend_fileChanged(dirname($c->path) . '/');
  /* Later rebuild any files which depend on this file. Note that
     anyDirOrFile_performAutoDel() below will actually remove the .depend
     file, so we need to call this function first. */
  depend_fileChanged($c->path);
  anyDirOrFile_performAutoDel($c->path);
}
//______________________________________________________________________

/** Automatically delete generated files if the original file is removed from
    SVN: When $path disappears from SVN, $delPath is deleted. All paths must
    be CACHE-absolute. Internally, this is implemented by recording the list
    of files to be removed in the file "{$path}_private.autodel".  This
    function currently only works for files (it calls unlink()), not for
    directories. Neither $path nor any {$path}_private.* files need to be
    added to the list of files to delete, they will be removed automatically.
    $label is a string which can be used to identify a group of files. For
    example xhtmlFile.php uses the string "xhtmlFile". You can delete only
    the files for a particular label with anyDirOrFile_performAutoDel(). A
    single path cannot be registered multiple times for multiple labels - in
    that case, the label used in the most recent call to this function
    wins. */
function /*void*/ anyDirOrFile_autoDel(/*string*/ $path, /*string*/ $delPath,
                                       /*string*/ $label = '') {
  $file = CACHE . $path . '_private.autodel';
  // Load old data, if any
  $autoDel = anyDirOrFile_loadAutoDel($file);

  // Add new entry
  $autoDel[$delPath] = $label;

  // Write data again
  $fd = @fopen("$file.tmp", 'w');
  if ($fd === FALSE) {
    syslog(LOG_ERR, "anyDirOrFile_autoDel: Could not write to $file.tmp");
    return;
  }
  foreach ($autoDel as $line => $label)
    fwrite($fd, urlencode($label) . " $line\n");
  fclose($fd);
  if (file_exists($file)) @unlink($file);
  rename("$file.tmp", $file);
}
//____________________

/** Perform deletion for the CACHE-absolute $path: Delete $path itself, any
    {$path}_private.* files, and also all files which were specified with
    anyDirOrFile_autoDel(). If the files which are to be deleted do not
    exist, no error is returned.
    @param $label Pass '' to delete all autodel files, or pass the label to
    delete only autodel files with this label, or FALSE not to delete any
    autodel files. The _private.autodel file is not modified.
    @param $delPrivate If TRUE, delete all {$path}_private.* files
    @param $delPath If TRUE, delete CACHE.$path */
function /*void*/ anyDirOrFile_performAutoDel(/*string*/ $path,
    /*string*/ $label = '', /*bool*/ $delPrivate = TRUE,
    /*bool*/ $delPath = TRUE) {
  /* NB: In the following, do not use file_exists() as this will return FALSE
     for dangling symlinks. */
  //syslog(LOG_DEBUG, "anyDirOrFile_performAutoDel: $path");
  // Delete autodel files
  $file = CACHE . $path;
  if ($label !== FALSE) {
    $autoDel = anyDirOrFile_loadAutoDel($file . '_private.autodel');
    //syslog(LOG_DEBUG, "anyDirOrFile_performAutoDel: " . count($autoDel));
    if ($label == '') {
      foreach ($autoDel as $f => $l) {
        //syslog(LOG_DEBUG, "anyDirOrFile_performAutoDel: DEL $f");
        @unlink(CACHE . $f);
      }
    } else {
      foreach ($autoDel as $f => $l)
        if ($l == $label) {
          //syslog(LOG_DEBUG, "anyDirOrFile_performAutoDel: DEL $f ($l)");
          @unlink(CACHE . $f);
        }
    }
  }

  // Delete $path itself
  if ($delPath)
    @unlink($file);

  // Delete {$path}_private.*
  if (!$delPrivate) return;
  $dir = dirname($file);
  $files = anyDirOrFile_dir($dir);
  if ($files === FALSE) return;
  $leafPrivate = basename($path) . '_private.';
  foreach ($files as $f)
    if (substr($f, 0, strlen($leafPrivate)) == $leafPrivate)
      unlink("$dir/$f");
}
//____________________

/** Delete all autodel information. You should call this whenever the file
    content has changed in SVN, before regenerating the files from the newly
    committed data. */
function /*void*/ anyDirOrFile_eraseAutoDel(/*string*/ $path) {
  $autodelFile = CACHE . $path . '_private.autodel';
  if (file_exists($autodelFile)) {
    //syslog(LOG_DEBUG, "anyDirOrFile_eraseAutoDel: DEL $autodelFile");
    @unlink($autodelFile);
  }
}
//____________________

/** Helper function: Load autodel data into array. The returned array has
    entries of the form "/dir/index.xhtml.en.xhtml"=>"langTag" or
    "/dir/index.xhtml.xhtml"=>"xhtmlFile", i.e. the filenames are the array
    keys. */
function /*array(string=>string)*/ anyDirOrFile_loadAutoDel(
    /*string*/ $filename) {
  $autoDel = array();
  if (!file_exists($filename)) return $autoDel;
  $fd = fopen($filename, 'r');
  if ($fd === FALSE) return $autoDel;
  while (!feof($fd)) {
    $line = fgets($fd, 8192);
    if ($line === FALSE || substr($line, -1) != "\n") continue;
    $n = strpos($line, ' ');
    if ($n === FALSE) continue;
    $autoDel[substr($line, $n + 1, -1)] = urldecode(substr($line, 0, $n));
  }
  fclose($fd);
  return $autoDel;
}
//______________________________________________________________________

/** Convenience function: Return the entries of the directory as an array,
    sorted by name. The entries for '.' and '..' are not returned. Returns
    FALSE on error. */
function /*array(string)*/ anyDirOrFile_dir($dirname) {
  $dh = opendir($dirname);
  if (!$dh) return FALSE;
  $files = array();
  while (FALSE !== ($f = readdir($dh)))
    if ($f != '.' && $f != '..') $files[] = $f;
  closedir($dh);
  sort($files);
  return $files;
}

?>