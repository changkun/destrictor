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

/** @file Management of dependencies. Since Destrictor creates static
    content, there must be a way of updating this content whenever that
    becomes necessary. To achieve this, a list of dependencies is associated
    with each object (file or directory) by Destrictor. The list consists of
    the paths .xhtml or .xhtml-php pages which need to be regenerated
    whenever the object changes. This concept is similar to the dependencies
    used in C(++) Makefiles. */

/** @hook void fileDependUpdated(array('path'=>string))
    @hook void fileDependUpdated_xyz(array('path'=>string))

    This hook is called when a file (indicated by the "path" entry in the
    array parameter) should be updated due to changes of one of its
    dependencies.

    If the path ends in an extension, the _xyz variant is tried first, where
    xyz is substituted with the actual file extension.

    Any subscriber which has successfully dealt with the call must set the
    "finished" array entry to TRUE on exit.
*/
//______________________________________________________________________

/* Private, do not modify directly. */
$depend_queue = array(/*string=>TRUE*/);
$depend_seenInQueue = array(/*string=>int*/);

/* We must maintain the queue on disk as a backup. This is because of PHP's
   stupid behaviour of terminating the entire PHP script if eval()ed code
   (e.g. in templates) triggers a fatal error. The list of
   yet-to-be-regenerated files would be lost in that case. */
if (file_exists(CACHE . '/._private.dependqueue')) {
  $depend_queue = depend_loadDependencyFile(
                    CACHE . '/._private.dependqueue');
}
//______________________________________________________________________

hook_subscribe('atExit', 'depend_atExit');

/** Executed before PHP exits, flushes depend queue. */
function /*void*/ depend_atExit() {
  depend_flushQueue();
  // All's well, so remove on-disk queue file
  if (file_exists(CACHE . '/._private.dependqueue'))
    unlink(CACHE . '/._private.dependqueue');
}
//______________________________________________________________________

/** The depend module maintains a FIFO queue of files that still need to be
    regenerated. This function adds a file to the end of the queue. Later on,
    depend_flushQueue() will call the hook fileDependUpdated_ext if $path has
    an .ext extension. If nobody handles that hook call, a generic
    fileDependUpdated call is made. */
function /*void*/ depend_addToQueue(/*string*/ $path) {
  global $depend_queue;
  //syslog(LOG_DEBUG, 'depend_addToQueue ' . $path);
  $depend_queue[$path] = TRUE;
  // Append to on-disk backup
  $fd = fopen(CACHE . '/._private.dependqueue', 'a');
  if ($fd !== FALSE) {
    fwrite($fd, "$path\n");
    fclose($fd);
  }
}
//______________________________________________________________________

/** Indicate to the system that during the generation of $path, the contents
    of $dependencyPath were used. IOW, the content of $path depends on the
    content of $dependencyPath. "Depends" means that $path should be
    regenerated when $dependencyPath changes (i.e. it is created, deleted, or
    its content changes). Thus, $path should e.g. not declare a dependency on
    an image if that image is only referenced via an &lt;img src="url"&gt;
    tag.

    $path must be a filename - usually it will have an .xhtml or .xhtml-php
    extension. $dependencyPath may also be a directory name which *must* end
    in a slash. For dependencies on directories, $path will be regenerated
    when the contents of the directory change, i.e. files are added, deleted
    or renamed. $path will not be regenerated if the content of any file in
    the directory changes - for this to work, you need another explicit
    dependency on each file.

    <strong>Important:</strong> Both $path and $dependencyPath must be
    CACHE-absolute names, starting with a slash.

    The above results in the following rules for code which creates page
    content which depends on other files:

      - If your code checks for the existence of a file, declare a dependency
        on this file - depend_dependencyExists() can do both things at the
        same time. It is important to declare the dependency even if the file
        does not exist ATM, because it might get added later on.

      - If your code uses file_get_contents() or fopen() on any file, declare
        a dependency on this file

      - If your code uses opendir(), declare a dependency on the
        directory. The convenience function depend_dir() will both declare it
        and return the contents of the directory.

    depend_addDependency() will cause "$path" to be appended to the file
    "${dependencyPath}_private.depend" (sic! NOT the other way
    round). Multiple calls with the same arguments will only cause the
    dependency to be added once. If either argument is NULL, this function
    does nothing.

    For convenience, this function returns the string CACHE.$dependencyPath
    which allows you to write things like this:
    if(file_exists(depend_addDependency($a,$dependencyPath)) ...

    Dependency relations are not inherently transitive with this system: If
    file "A" depends on the contents of file "B" and file "B" depends on file
    "C", then a call depend_fileChanged("C") will *only* cause "B" to be
    regenerated, not "A". Thus, all direct and indirect dependencies may need
    to be specified explicitly. On the other hand, note that if A/B/C are
    .xhtml files, a change to "C" will trigger the regeneration of "B" which
    in turn triggers the regeneration of "A" - this is due to the xhtmlFile
    implementation: xhtmlFile_fileDependUpdated_xhtml() makes a call to
    depend_fileChanged() in all cases.

    It makes sense to declare dependencies for non-existing objects. For
    example, the menu generation code will look first for foo.xhtml.navmenu
    and only then for index.navmenu in the same directory. In the case that
    foo.xhtml.navmenu is not present, it *still* adds a dependency for
    it. That way, things will be regenerated correctly if the
    foo.xhtml.navmenu file is added at a later time.
 
    Note: There is no direct way to remove a dependency! This way, more data
    than necessary may sometimes be regenerated. However, dependencies which
    are no longer present will eventually disappear, because
    "${dependencyPath}_private.depend" is removed and rebuilt whenever
    depend_fileChanged($dependencyPath) is called. Thus, in a way the system
    is "self-healing" because wrong dependency information will only be used
    at most once for regeneration.

    The downside of the system is that when files are removed from SVN,
    _private.depend files can get left behind. These do not cause any harm,
    but may clobber up the cache directory. However, in practice only a
    couple of unnecessary files per directory get left behind
    (index.navmenu(-php)_private.depend and
    index.template(-php)_private.depend), so this is no big problem. */
function /*string*/ depend_addDependency(/*string*/ $path,
                                         /*string*/ $dependencyPath) {
  if ($path === NULL || $dependencyPath === NULL)
    return CACHE . $dependencyPath;
  // Load existing dependency info
  $file = CACHE . $dependencyPath . '_private.depend';
  if (!is_dir(dirname($file))) {
    syslog(LOG_ERR, 'depend_addDependency: Failed to record dependency of "'
           . $path . '" on "' . $dependencyPath . '": Directory "'
           . dirname($dependencyPath) . '" does not exist inside CACHE');
    return CACHE . $dependencyPath;
  }
  if (file_exists($file))
    $depend = depend_loadDependencyFile($file);
  else
    $depend = array();
  // Add new entry
  $depend[$path] = TRUE;
  // Write dependency info
  //syslog(LOG_DEBUG, "depend_$dependencyPath: Writing to $file.tmp, cwd=" . getcwd());
  $fd = fopen("$file.tmp", 'w');
  if ($fd === FALSE) {
    syslog(LOG_ERR, "depend_$dependencyPath: Could not write to $file.tmp");
    return CACHE . $dependencyPath;
  }
  foreach ($depend as $line => $x) {
    fwrite($fd, $line);
    fwrite($fd, "\n");
  }
  fclose($fd);
  if (file_exists($file)) @unlink($file);
  rename("$file.tmp", $file);
  return CACHE . $dependencyPath;
}
//______________________________________________________________________

/** Convenience function: Declare a dependency of $path on $dependencyPath
    just like depend_addDependency(). Next, return TRUE iff the file
    CACHE."$dependencyPath$ext" exists, otherwise return FALSE. The test uses
    PHP's file_exists(), so it will also return TRUE for symlinks and
    directories. Note: The dependency is always added on "$dependencyPath"
    and not "$dependencyPath$ext". */
function /*bool*/ depend_dependencyExists(/*string*/ $path,
    /*string*/ $dependencyPath, /*string*/ $ext = '_private.orig') {
  depend_addDependency($path, $dependencyPath);
  return file_exists(CACHE . "$dependencyPath$ext");
}
//______________________________________________________________________

/** Convenience function: Get contents exactly as anyDirOrFile_dir() would,
    but additionally add dependencies: Rebuild $path whenever the directory
    is created/removed or files are created/deleted inside it. Return FALSE
    on error, an array of filenames on success. $dirPath is the
    CACHE-absolute directory name, not the path in the filesystem! */
function /*array(string)|FALSE*/ depend_dir(/*string*/ $path,
                                            /*string*/ $dirPath) {
  depend_addDependency($path, $dirPath); // dep on existence of dir
  if (!file_exists(CACHE . $dirPath)) return FALSE;
  $d = depend_addDependency($path, $dirPath . '/'); // dep on dir content chg.
  return anyDirOrFile_dir($d);
}
//______________________________________________________________________

/** Flush the queue of files to regenerate. This happens as follows: Until
    the queue is empty, the first entry is removed and the hook
    fileDependUpdated_ext($path) is called. The code called via the hook may
    make further calls to depend_addToQueue(). Internally, the function
    maintains a set of already seen files; it will not regenerate a file
    infinitely if there is a dependency loop. Instead, each file of a cycle
    is regenerated twice and only one file of a cycle is regenerated three
    times. (It might be risky only to regenerate the files in a cycle once
    because the system sometimes regenerates more files than needed (due to
    stale dependency info), so loops become more likely and a double
    regeneration may actually be necessary. (?? not sure) */
function /*void*/ depend_flushQueue() {
  global $depend_queue, $depend_seenInQueue;
  //syslog(LOG_DEBUG, 'depend_flushQueue ' . count($depend_queue));
  $hookArg = array();
  while (!empty($depend_queue)) {
    // Set $path to the key of the first array entry:
    foreach ($depend_queue as $path => $ignored) break;
    unset($depend_queue[$path]);

    if (!array_key_exists($path, $depend_seenInQueue))
      $depend_seenInQueue[$path] = 1;
    else
      $depend_seenInQueue[$path] += 1;

    syslog(LOG_DEBUG, 'depend_flushQueue: Generating ' . $path . ' #'
           . $depend_seenInQueue[$path]);
    $ext = ''; // Non-empty if file has extension
    $n = strrpos($path, '.');
    if ($n !== FALSE && $n > strrpos($path, '/'))
      $ext = '_' . substr($path, $n + 1); // call hook "fileDependUpdated_html"
    // Call "fileDependUpdated_html" first
    $hookArg['path'] = $path;
    $hookArg['finished'] = FALSE;
    hook_call("fileDependUpdated$ext", $hookArg);
    // If nobody picked it up, now call "fileDependUpdated"
    if (!empty($ext) && $hookArg['finished'] !== TRUE)
      hook_call('fileDependUpdated', $hookArg);
  }
  $depend_seenInQueue = array();
}
//______________________________________________________________________

/** Notify the system that the file at $dependencyPath (CACHE-absolute path)
    has changed (or was removed). This function reads the file
    "${dependencyPath}_private.depend" and calls depend_addToQueue() for each
    of its unique entries. The .depend file is then overwritten with an empty
    file, as it will be rebuilt while the files are rebuilt.  If no .depend
    file is present, this function does nothing. */
function /*void*/ depend_fileChanged(/*string*/ $dependencyPath) {
  global $depend_seenInQueue;
  if (array_key_exists($dependencyPath, $depend_seenInQueue)
      && $depend_seenInQueue[$dependencyPath] > 2) {
    syslog(LOG_DEBUG, "depend_fileChanged: Dependency loop - "
           . "will not regenerate deps of " . $dependencyPath);
    /* Already seen, ignore. Important detail: Do not delete the
       _private.depend file contents, or dependency info would be lost! */
    return;
  }

  $file = CACHE . $dependencyPath . '_private.depend';
  if (!file_exists($file)) return;
  // Load existing dependency info
  $depend = depend_loadDependencyFile($file);
  foreach ($depend as $line => $x) {
    syslog(LOG_DEBUG, "depend_fileChanged: Will regenerate $line because of "
           . "change to $dependencyPath");
    depend_addToQueue($line);
  }
  // Only truncate to zero length once during a single run of the queue
  if (!array_key_exists($dependencyPath, $depend_seenInQueue)) {
    $fd = fopen($file, 'w');
    fclose($fd);
  }
}
//________________________________________

/* Private: Load dependecy info from $file, return it as array */
function /*array(string=>)*/ depend_loadDependencyFile(/*string*/ $file) {
  $depend = array();
  $fd = fopen($file, 'r');
  if ($fd === FALSE) return $depend;
  while (!feof($fd)) {
    $line = fgets($fd, 8192);
    if ($line !== FALSE && substr($line, -1) == "\n")
      $depend[substr($line, 0, -1)] = TRUE;
  }
  fclose($fd);
  return $depend;
}

?>