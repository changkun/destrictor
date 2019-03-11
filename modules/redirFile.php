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

/** @file Simple and convenient way of setting up HTTP redirects on a
    website. */

/** @fileext .redir

    HTTP redirects. The file contains a single line of text (and optionally
    empty lines and comment lines beginning with "#"). That line can be 
    <!-- /* --> either an absolute URL ("scheme://site/path") or a
    site-absolute ("/path/to/file") or a relative one ("../../otherdir/").

    For a foobar.redir file, a PHP file named foobar.php is created in
    CACHE. It sends a "302 Found" response (temporary redirect).

    If the string "@path@" is appended to the redirection line, then any
    additional path in the request is also appended to the redirection
    destination. For example, if "foobar/extra/path.xhtml" is requested and
    "foobar.redir" contains "http://x@path@", then the visitor will be
    redirected to "http://x/extra/path.xhtml".

    This functionality is similar to just using .htaccess files, with the
    differences that 1) normal users are allowed to create redirects, 2)
    relative paths are allowed, making this easier to handle.

*/

hook_subscribe('fileAdded_redir', 'redirFile_fileAdded_redir');
hook_subscribe('fileUpdated_redir', 'redirFile_fileUpdated_redir');

/** Called by hook. */
function /*void*/ redirFile_fileAdded_redir(SvnChange $c) {
  depend_fileChanged(dirname($c->path) . '/');
  depend_fileChanged($c->path);
  $c->finished = TRUE;
  redirFile_write($c->path);
}

/** Called by hook. */
function /*void*/ redirFile_fileUpdated_redir(SvnChange $c) {
  //dlog("redirFile_fileUpdated_redir");
  depend_fileChanged($c->path);
  $c->finished = TRUE;
  anyDirOrFile_performAutoDel($c->path, '', FALSE, FALSE);
  redirFile_write($c->path);
}

/** Write the PHP code that performs the redirect. */
function /*void*/ redirFile_write(/*string*/ $path) {
  //dlog("redirFile_write $path");
  $data = svn_cat($path);
  // Remove UTF-8 byte order mark
  if (substr($data, 0, 3) == "\xef\xbb\xbf")
    $data = substr($data, 3);
  $out = FALSE;
  foreach (explode("\n", $data) as $l) {
    $l = rtrim($l);
    if ($l == '' || $l{0} == '#') continue;
    if (!ctype_graph($l)) {  // Security check
      $out = xhtmlTemplate_errDocument($path, 'Error in .redir file',
        'The redirection destination contains non-printable or whitespace '
        . 'characters.', FALSE);
      break;
    }
    if ($out !== FALSE) {
      $out = xhtmlTemplate_errDocument($path, 'Error in .redir file',
        'The .redir file may only contain a single non-comment line, but '
          . 'there is another one, "' . htmlspecialchars($l) . '"', FALSE);
      break;
    }
    if (preg_match('%^[a-z]+://%i', $l) != 1 && $l{0} != '/') {
      // Relative URL, turn it into a site-absolute one
      $l = navmenuTag_absoluteURL($path, $l);
      if ($l === FALSE) {
        $out = xhtmlTemplate_errDocument($path, 'Error in .redir file',
          'The relative URL is invalid.', FALSE);
        break;
      }
    }
    $l = str_replace("\\", "\\\\", $l);
    $l = str_replace("\"", "\\\"", $l);
    $arr = xhtmlFile_writeConfigPhp($path);
    $out = "<?php " . $arr['filecode'] . "\n"
      . requestTimeDefs_getDef('DESTRICTOR_PATH', $path)
      . "redir(\"$l\");\n?>";
  }
  if ($out === FALSE) {
    $out = xhtmlTemplate_errDocument($path, 'Error in .redir file',
      'The .redir file does not define a redirection destination.', FALSE);
  }

  // $out contains the data to write to CACHE
  $newPath = substr($path, 0, -strlen('redir')) . 'php';
  file_put_contents(CACHE . $newPath, $out);

  // Delete the file when the .redir file is removed from SVN
  anyDirOrFile_autoDel($path, $newPath, 'redirFile');
}
//______________________________________________________________________

hook_subscribe('requestTimePhp', 'redirFile_requestTimePhp');

/** Called by hook, adds the PHP function that gets called whenever a
    redirect is made. */
function /*void*/ redirFile_requestTimePhp(&$arr) {
  $arr['globalcode'] .= '/* redirFile.php */
function redir(/*string*/ $url) { // $url can be "http://..." or "/path/x..."
  if ($url{0} == "/") {
    // Get URL of top level of site; will be "/" if whole site is ours
    $siteUrl = $_SERVER["SCRIPT_NAME"];
    for ($i = substr_count(DESTRICTOR_PATH, "/"); $i > 0; --$i)
      $siteUrl = dirname($siteUrl);
    if ($siteUrl == "/") $siteUrl = "";
    $url = "http://" . $_SERVER["HTTP_HOST"] . $siteUrl . $url;
  }
  if (substr($url, -6) == "@path@")
    $url = substr($url, 0, -6) . $_SERVER["PATH_INFO"];
  if (ctype_print($url))
    header("Location: $url");
  else
    exit("Invalid redirection");
}

';
}

?>