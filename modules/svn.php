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

/** @file Interface to the Subversion command line utilities. */

/** @hook void fileAdded(string $path) Called whenever a new file is added
    to SVN.
    @hook void fileDeleted(string $path) Called whenever a file is removed
    from SVN.
    @hook void fileUpdated(string $path) Called whenever a file is modified
    in SVN, or if the file's properties are modified/created/removed.

    @hook void dirAdded(string $path) Called whenever a new dir is added
    to SVN.
    @hook void dirDeleted(string $path) Called whenever a dir is removed
    from SVN.
    @hook void dirUpdated(string $path) Called whenever a dir is modified
    in SVN, or if the dir's properties are modified/created/removed.

    @hook void file{Added,Deleted,Updated}_xyz(string $path) As above, but is
    only called if the new/deleted/changed file has a ".xyz" extension. It is
    only called if the file's leafname contains a '.' character. */
//______________________________________________________________________

/** @hook void atExit() Called just before the post-commit PHP script
    exits. It is NOT called after the pre-commit script because this may
    cause depend_atExit attempt to attempt to regenerate stuff, which may not
    always work at that stage. */
//______________________________________________________________________

/** @hook void prefileAdded(string $path) Called before a new file is added
    to SVN. If you output anything, the pre-commit script will fail with your
    error message.
    @hook void prefileDeleted(string $path) Called before a file is removed
    from SVN. If you output anything, the pre-commit script will fail with
    your error message.
    @hook void prefileUpdated(string $path) Called before a file is modified
    in SVN, or if the file's properties are modified/created/removed. If you
    output anything, the pre-commit script will fail with your error message.

    @hook void predirAdded(string $path) Called before a new dir is added to
    SVN. If you output anything, the pre-commit script will fail with your
    error message.
    @hook void predirDeleted(string $path) Called before a dir is removed
    from SVN. If you output anything, the pre-commit script will fail with
    your error message.
    @hook void predirUpdated(string $path) Called before a dir is modified in
    SVN, or if the dir's properties are modified/created/removed. If you
    output anything, the pre-commit script will fail with your error message.

    @hook void prefile{Added,Deleted,Updated}_xyz(string $path) As above, but
    is only called if the new/deleted/changed file has a ".xyz" extension. It
    is only called if the file's leafname contains a '.' character. If you
    output anything, the pre-commit script will fail with your error
    message. */
//______________________________________________________________________

/** @hook void objectChanged(SvnChange $c) Called before any of the other
    modification notifications like fileAdded. The changed object is passed
    in $c. */
//______________________________________________________________________

/** This function is called by the SVN repository's pre-commit hook
    script. It parses through the output of "svnlook changes" for the given
    transaction and calls appropriate hooks. In case of errors, an exception
    is thrown. */
function /*void*/ svn_precommit(/*string*/ $txnName) {
  //syslog(LOG_DEBUG, 'svn_precommit: txn ' . $txnName);
  $author = svn_txnAuthor($txnName);

  $r = @svn_exec("svnlook changed -t $txnName "
                 . escapeshellarg(REPOSITORY));
  if ($r === FALSE)
    throw new Exception("svnlook failed: $php_errormsg");
  if ($r['returnValue'] > 0)
    throw new Exception("svn_precommit: svnlook inside \"" . CACHE
                      . '" failed: ' . str_replace("\n", '\n', $r['stderr']));
  $changes = svn_parseOutput($r['stdout'], TRUE);
  $changes = svn_addMovedDirContent($changes, '', "-t$txnName");
  foreach ($changes as $c) {
    $c->author = $author;
    // Nobody is allowed to commit in /destrictor
    $preDestrictor = '/destrictor';
    if ($c->path == $preDestrictor || substr($c->path,
          0, strlen($preDestrictor) + 1) == "$preDestrictor/") {
      throw new Exception("Commits under $preDestrictor are not allowed - "
        . "this directory is reserved for the Destrictor installation");
    }
    // Nobody is allowed to commit _private. files
    if (strpos($c->path, '_private.') !== FALSE) {
      throw new Exception("Commits of files named *_private.* are not "
                          . "allowed - these names are reserved.");
    }
    // Only members of group admin are allowed to commit .ht* files
    $b = basename($c->path);
    if (substr(basename($c->path), 0, 3) == '.ht') {
      auth_requireGroup("Cannot commit .ht* file \"$b\".",
                        $c->author, 'admin');
    }
    /* Only members of group admin are allowed to commit *_private.*
       files. Otherwise, ordinary users might trick Destrictor into elevating
       their privileges. */
    if (strpos($b, '_private.') !== FALSE) {
      auth_requireGroup("Cannot commit *_private.* file \"$b\".",
                        $c->author, 'admin');
    }
    /* Only members of group php are allowed to commit PHP code. We also
       disallow .php. anywhere in the filename - with some Apache
       installations, it might otherwise be possible to upload files named
       .php.utf8 (PHP code, UTF-8 encoding) or similar. */
    if (preg_match('%-php$|\.(php\d?|xht-php|html-php)(\.[^/]*)?$%',
                   $c->path) == 1) {
      auth_requireGroup("Cannot commit PHP code in \"$b\".",
                        $c->author, 'php');
    }
    svn_callChangeHook($c, 'pre');
  }
}
//______________________________________________________________________

/** This function is called by the SVN repository's post-commit hook
    script. It updates the content in CACHE. The CACHE directory does not
    actually contain a real checked-out repository, the data of the changes
    is transferred via a sequence of "svnlook" and "svn cat" commands.

    At this point, there is no way to display error messages to the user, so
    any problems are only logged.

    @param $rev The number of the revision just committed */
function /*void*/ svn_postcommit(/*string*/ $rev) {
  //syslog(LOG_DEBUG, 'svn_postcommit: rev ' . $rev);

  if (!is_dir(CACHE)) {
    // Argh - something went pear-shaped, CACHE is gone! Try to regenerate it.
    syslog(LOG_ERR, 'svn_postcommit: CACHE is gone, attempting to '
          . 'regenerate it');
    if (mkdir(CACHE)) updateAll_update();
    return;
  }

  $r = @svn_exec("svnlook changed -r $rev " . escapeshellarg(REPOSITORY));
  if ($r === FALSE) {
    syslog(LOG_ERR, 'svn_postcommit: svnlook inside "' . CACHE . '" failed');
    return; // Error
  }
  if ($r['returnValue'] > 0) {
    syslog(LOG_ERR, 'svn_postcommit: svnlook inside "' . CACHE
           . '" failed: ' . str_replace("\n", '\n', $r['stderr']));
    return; // Error
  }

  try {
    $changes = svn_parseOutput($r['stdout'], TRUE);
    $changes = svn_addMovedDirContent($changes, '-r' . (intval($rev)-1), '');
    foreach ($changes as $c) svn_callChangeHook($c, '');
  } catch (Exception $e) {
    syslog(LOG_ERR, 'svn_postcommit: Uncaught exception: ' . $e->getMessage());
  }
}
//______________________________________________________________________

/** Execute the given command with CACHE as the current directory. $cmd is
    expected to run either "svn" or "svnlook". The returned array has the
    format array('stdout' => string, 'stderr' => string, 'returnValue' =>
    int). Returns FALSE on error. */
function /*array*/ svn_exec(/*string*/ $command) {
  $descriptorSpec = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
  /* The following assumes that the locale SVNLANG exists and that it uses
     the charset that SVN should use for filenames */
  $env = array('LANG' => SVNLANG);
  $process = proc_open($command, $descriptorSpec, $pipes, CACHE, $env); // Run
  if (!is_resource($process)) {
    trigger_error("svn_exec: Could not create process: $command");
    return FALSE;
  }
  // Read svn's output
  $stdout = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[2]);
  $returnValue = proc_close($process);
  return array('stdout' => $stdout,
               'stderr' => $stderr,
               'returnValue' => $returnValue);
}
//______________________________________________________________________

/** Using "svn cat", return the latest contents of the specified file. Return
    FALSE on error. $path must not include REPOSITORYDIR, as it is
    automatically prepended. */
function /*string|FALSE*/ svn_cat(/*string*/ $path) {
  $command = 'svn cat file://' . escapeshellarg(REPOSITORY)
    . escapeshellarg(REPOSITORYDIR . $path);
  $r = @svn_exec($command);
  if ($r === FALSE) {
    trigger_error($php_errormsg);
    return FALSE; // Error
  }
  if ($r['returnValue'] > 0) {
    trigger_error("svn_info: \"$command\" failed: "
                  . str_replace("\n", '\n', $r['stderr']));
    return FALSE;
  }
  return $r['stdout'];
}
//______________________________________________________________________

/** Using "svn cat", write the latest contents of the file at $path to the
    specified $file. Return TRUE on success, FALSE on error. In case of
    error, $destFile is removed. $path must not include REPOSITORYDIR, as it
    is automatically prepended. */
function /*string|FALSE*/ svn_catToFile(/*string*/ $path,
                                        /*string*/ $destFile) {
  $command = 'svn cat file://' . escapeshellarg(REPOSITORY)
    . escapeshellarg(REPOSITORYDIR . $path);
  $descriptorSpec = array(1 => array('file', $destFile, 'w'),
                          2 => array('pipe', 'w'));
  /* The following assumes that the locale SVNLANG exists and that it uses
     the charset that SVN should use for filenames */
  $env = array('LANG' => SVNLANG);
  $process = proc_open($command, $descriptorSpec, $pipes, CACHE, $env); // Run
  if (!is_resource($process)) {
    trigger_error("svn_cat: Could not create process: $command");
    return FALSE;
  }
  // Read svn's output
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[2]);
  $returnValue = proc_close($process);
  if ($returnValue == 0) return TRUE;
  trigger_error("svn_cat: command \"$command\" failed: "
                . str_replace("\n", '\n', $stderr));
  @unlink($destFile);
  return FALSE;
}
//______________________________________________________________________

/** Return the author (user name) who committed the specified
    transaction. Returns FALSE on error. */
function /*string|FALSE*/ svn_txnAuthor(/*string*/ $txnName) {
  $author = exec("svnlook author -t $txnName "
                 . escapeshellarg(REPOSITORY), $outArr, $ret);
  if (count($outArr) != 1) {
    trigger_error('Internal error: Unexpected output from "svnlook author": '
                  . implode('\n', $outArr));
    return FALSE;
  }
  if ($ret != 0) {
    trigger_error('Internal error: "svnlook author" failed'
                  . implode('\n', $outArr));
    return FALSE;
  }
  return $author;
}
//______________________________________________________________________

/** Object representing a change made to a single file/directory inside a SVN
    repository. */
class SvnChange {

  /** The path and leafname of the file/directory that is affected - an
      absolute path from the REPOSITORYDIR inside the repository. Always
      starts with '/' */
  public /*string*/ $path;

  /** One of these single characters: Space (content unchanged), 'A' (added),
      'D' (deleted), 'U' (updated), 'C' (conflict), 'G' (merged) */
  public /*string*/ $content;

  /** Like 'content', except it provides info about modified file properties
      instead of the file content. */
  public /*string*/ $prop;

  /** 0=>UNKNOWN, 1=>is a file, 2=>is a directory. svn_parseOutput only sets
      up a non-zero value if $isSvnlook==TRUE. */
  public /*int*/ $type = 0;

  /** Name of author doing the commit. Is only non-NULL during precommit! */
  public /*string*/ $author = NULL;

  /** For use in callbacks */
  public /*bool*/ $finished = FALSE;
}

/** Parse the svn command's output.
    @param $stdout The standard output from "svn up", "svn co" or "svnlook
    changed".
    @param $isSvnlook TRUE if the output is from "svnlook", FALSE if it is
    from "svn". Unfortunately, the formatting differs slightly for "svn
    up/co" compared to "svnlook": For svn, filenames start on column 5
    (counting from 0), for svnlook on column 4.
    @return An array of SvnChange objects, one for each recognized line of
    output. */
function /*array(SvnChange)*/ svn_parseOutput(/*string*/ $stdout,
                                              /*boolean*/ $isSvnlook = TRUE) {
  $out = array();
  $stdoutLines = explode("\n", $stdout);
  // Parse output of svn command
  foreach ($stdoutLines as $line) {
    // Ignore empty lines
    if (empty($line)) continue;
    //syslog(LOG_DEBUG, "svn_parseOutput: $line");
    unset($c);
    $c = new SvnChange();
    $c->content = $line{0};
    $c->prop = $line{1};
    /* Act upon lines for added/deleted/.. content. First column is for
       changes to file content (A=Added D=Deleted U=Updated C=Conflict
       G=Merged), second column for changes to file property. 'B' in third
       column, if present, means a lock was broken/stolen. For our purposes,
       only 3 types of changes are distinguished: "Object added", "Object
       changed" (includes all changes to properties) and "Object deleted" */
    if (!$isSvnlook) {
      // svn command:     "A    filename" or  "A    dirname"
      $c->path = '/' . substr($line, 5);
    } else if (substr($line, 3, 1) == ' ') {
      /* svnlook command: "A   filename" or  "A   dirname/".
         It's not good that we distinguish A/D/U/C lines from others only by
         checking for a space in column 3 :-/ */
      if (substr($line, -1) == '/') {
        $c->path = '/' . substr($line, 4, strlen($line) - 5);
        $c->type = 2; // dir
      } else {
        $c->path = '/' . substr($line, 4);
        $c->type = 1; // file
      }
    } else {
      continue;
    }
    // Ignore files outside REPOSITORYDIR (usually "/trunk")
    //syslog(LOG_DEBUG,"X ".substr($c->path, 0, strlen(REPOSITORYDIR)).'|'.REPOSITORYDIR . '/');
    if (substr($c->path, 0, strlen(REPOSITORYDIR) + 1)
        != REPOSITORYDIR . '/') {
      syslog(LOG_DEBUG, 'svn_parseOutput: Ignoring change to ' . $c->path);
      continue;
    }
    // Remove REPOSITORYDIR prefix
    $c->path = substr($c->path, strlen(REPOSITORYDIR));
    $out[] = $c;
  }
  return $out;
}
//______________________________________________________________________

/** When renaming directories with "svn mv dir newdir", we only get two "D
    dir", "A newdir" lines from "svn changed", even if the renamed dir
    contains a lot of objects. This function iterates over the $parseOutput
    array as returned by svn_parseOutput() (i.e. integer keys increasing
    steadily from 0) and looks for that case: Two subsequent entries, first a
    "dir deleted", then a "dir added entry. Furthermore, the "A newdir" entry
    must not be followed by "A newdir/anything" - in that case, the "dir
    added" entry is for a newly checked-in dir and not the result of a
    rename.

    This function returns a copy of $parseOutput, possibly with additional
    inserted entries.  For each suspected renamed dir, it adds further
    entries by running "svn list" for the respective dir entries, both for
    the old directory (an entry "dir/file was removed" is appended to
    $parseOutput) and the new directory (any subdirs/files are appended as
    "added").

    $oldRevSwitch and $newRevSwitch are "-r45" or "-t42-1" switches for
    svnlook which give the revision or transaction to look at when generating
    the $parseOut entries of the old and new state of the repository. */
function /*array*/ svn_addMovedDirContent(/*array*/ $parseOutput,
    /*string*/ $oldRevSwitch, /*string*/ $newRevSwitch) {
  $prev = NULL;
  $ret = array();
  $parseOutputLen = count($parseOutput);
  for ($i = 0; $i < $parseOutputLen; ++$i) {
    $cur =& $parseOutput[$i];
    if ($i == $parseOutputLen - 1) { $ret[] = $cur; continue; }
    $next =& $parseOutput[$i + 1];
    if ($cur->type == 1 // Type of object must be "directory" or "don't know"
        || $next->type != $cur->type // del/add must be same object type
        || ($cur->content != 'D' && $cur->content != 'A')
        || ($next->content != 'D' && $next->content != 'A')
        || $cur->content == $next->content)
      { $ret[] = $cur; continue; }
    if ($i < $parseOutputLen - 2) {
      $afternext =& $parseOutput[$i + 2];
      // If an entry follows $next, its path must not be inside $next->path
      if (substr($afternext->path, 0, strlen($next->path) + 1)
          == $next->path . '/') { $ret[] = $cur; continue; }
    }
    // Object was probably removed and re-added
    $del = ($cur->content == 'D' ? $cur : $next);
    $add = ($cur->content == 'A' ? $cur : $next);
    syslog(LOG_DEBUG, 'svn_addMovedDirContent: D ' . $oldRevSwitch);
    $delList = svn_tree($del->path, $oldRevSwitch);
    syslog(LOG_DEBUG, 'svn_addMovedDirContent: A ' . $newRevSwitch);
    $addList = svn_tree($add->path, $newRevSwitch);
    if ($delList === FALSE || $addList === FALSE) { $ret[] = $cur; continue; }
    // Create additional entries before the "dir deleted" entry
    foreach (array_reverse($delList) as $delPath)
      $ret[] = svn_treeToChange($delPath, 'D');
    // Create additional entries after the "dir added" entry
    foreach ($addList as $addPath)
      $ret[] = svn_treeToChange($addPath, 'A');
    ++$i; // Skip over the original two "dir deleted/added" entries
  }
  return $ret;
}
//______________________________________________________________________

/** Return an array of repository-absolute paths.

    @param $path Usually a directory, in that case the returned list includes
    the directory itself and all its children. If $path is a single object,
    the returned array contains just $path. If $path does not exist, FALSE is
    returned. If you want to list the entire repository, pass $path="". $path
    MUST NOT end with a slash!
    @param $revSwitch is either empty (return current repository state) or
    something like "-r45" to return info for revision 45 or "-t32-1" to
    return info for that transaction. If $revSwitch is empty, info for the
    current revision is output.
    @return An array of paths. Directory names include a trailing slash. (NB:
    In most of the rest of destrictor, dir names do not end in slashes, you
    may need to strip them before passing on the names.)*/
function /*array(string)*/ svn_tree(/*string*/ $path,
                                    /*string*/ $revSwitch = '') {
  $d = svn_exec("svnlook tree $revSwitch "
                . escapeshellarg(REPOSITORY) . ' '
                . escapeshellarg(REPOSITORYDIR . $path));
  if ($d['returnValue'] != 0) return FALSE;
  $ret = array();
  /* The output of "svnlook tree" is something like this:
 case-001-fileop/
  dir2/
   b.txt
  dir4/
   b.txt
  c.html
     We will return array entries like:
     /case-001-fileop/
     /case-001-fileop/dir2/
     /case-001-fileop/dir2/b.txt
     /case-001-fileop/dir4/
     /case-001-fileop/dir4/b.txt
     /case-001-fileop/c.html */
  $dir = array(0 => ''); // Indentation depth => dirname/
  $lines = explode("\n", $d['stdout']);
  $lines[0] = "$path/"; // Overwrite first line "trunk/" if $path==""
  foreach ($lines as $l) {
    if ($l == '') continue;
    if (substr($l, -1) == "\r") $l = substr($l, 0, -1); // Strip any CR
    $indent = 0;
    while ($l{$indent} == ' ') ++$indent;
    $name = substr($l, $indent);
    $x = $dir[$indent] . $name;
    //syslog(LOG_DEBUG, "svn_tree: $indent $name => $x");
    $ret[] = $x;
    if (substr($name, -1) == '/') $dir[$indent + 1] = $x;
  }
  return $ret;
}
//______________________________________________________________________

/** Given one of the names returned in svn_tree()'s result, create an
    appropriate SvnChange object. $content is something like 'A' for added or
    'D' for deleted. svn_treeToChange() recognizes directories by their
    trailing slash and sets SvnChange->type accordingly. The trailing slash
    is stripped from $path. */
function /*SvnChange*/ svn_treeToChange(/*string*/ $path,
                                        /*string*/ $content) {
  $ret = new SvnChange();
  if (substr($path, -1) == '/') { // dir
    $ret->path = substr($path, 0, -1);
    $ret->type = 2;
  } else { // file
    $ret->path = $path;
    $ret->type = 1;
  }
  $ret->content = $content;
  $ret->prop = ' ';
  return $ret;
}
//______________________________________________________________________

/* Only works if $c was set up using $isSvnlook==TRUE, i.e. $c->type is set
   up. Calls appropriate hooks for changed/deleted etc. objects. $pre is
   prepended to the hook name; in practice, $pre is either '' or 'pre'. */
function svn_callChangeHook(SvnChange $c, $pre) {
  if ($c->type == 0) {
    trigger_error('Internal error: svn_callChangeHook can only handle '
                  . 'svnlook results');
  }
  hook_call('objectChanged', $c);
  $dir = ($c->type == 1 ? 'file' : 'dir');
  $ext = ''; // Non-empty if file with extension was modified
  if ($dir == 'file') {
    $n = strrpos($c->path, '.');
    if ($n !== FALSE && $n > strrpos($c->path, '/'))
      $ext = '_' . substr($c->path, $n + 1); // call hook "fileAdded_htm"
  }
  if      ($c->content == 'A') $action = 'Added';
  else if ($c->content == 'D') $action = 'Deleted';
  else if ($c->content == 'U') $action = 'Updated';
  else if ($c->content == 'G') $action = 'Updated';
  else if ($c->content == 'C') {
    // Should Not Happen (tm):
    trigger_error("svn_callChangeHook: Conflict detected for \"" . $c->path
                  . "\"! Cache at \"" . CACHE . "\" may be messed up");
    // ...but continue anyway
    return;
  } else {
    return;
  }
  syslog(LOG_DEBUG, "svn_callChangeHook: $pre$dir$action$ext " . $c->path);
  // Call "prefileAdded_html" first
  hook_call("$pre$dir$action$ext", $c);
  // If nobody picked it up, now call "prefileAdded"
  if (!empty($ext) && $c->finished !== TRUE) hook_call("$pre$dir$action", $c);
}
//______________________________________________________________________

/** Using "svn info", return info about the specified file. Return FALSE on
    error. Typical example of the returned string: <pre>
Repository Root: file:///home/richard/proj/destrictor%20CMS/destrictor/content-svn
Repository UUID: aa50c314-850b-0410-8e25-9a928b2ac459
Revision: 7
Node Kind: file
Last Changed Author: richard
Last Changed Rev: 6
Last Changed Date: 2006-01-29 21:36:57 +0100 (Sun, 29 Jan 2006) </pre>

    Note that svn_info() maintains a cache of the information that was
    returned for a particular $path. $path must not include REPOSITORYDIR, as
    it is automatically prepended.

    @return stdout from "svn info", or FALSE. */
function /*string|FALSE*/ svn_info(/*string*/ $path) {
  static $cache = array();

  $path = REPOSITORYDIR . $path;
  if (array_key_exists($path, $cache)) {
    //syslog(LOG_DEBUG, "svn_info: Using cache for $path");
    return $cache[$path];
  }

  //syslog(LOG_DEBUG, "svn_info: No cache entry for $path");
  $command = 'svn info file://' . escapeshellarg(REPOSITORY)
    . escapeshellarg($path);
  $r = @svn_exec($command);
  if ($r === FALSE) {
    trigger_error($php_errormsg);
    return FALSE; // Error
  }
  if ($r['returnValue'] > 0) {
    trigger_error("svn_info: \"$command\" failed: "
                  . str_replace("\n", '\n', $r['stderr']));
    return FALSE;
  }
  $cache[$path] = $r['stdout'];
  return $r['stdout'];
}

?>
