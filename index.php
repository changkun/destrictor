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

  Installation script for Destrictor

  Note: This script might be available to the whole world via HTTP. Thus,
  never allow destructive operations to be done - always require the user to
  edit config.php or to modify the filesystem in some way. When asking for
  input from the user, treat user-supplied strings as unsafe! Do not leak
  information by outputting filesystem paths.

*/
//header('Content-Type: application/xhtml+xml; charset=utf-8');
header('Content-Type: text/html; charset=utf-8');
echo '<'; ?>?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
  <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
  <meta http-equiv="Content-Script-Type" content="text/javascript" />
  <meta name="Robots" content="noindex,nofollow" />
  <title>Destrictor Installation</title>
  <style type="text/css">
    body { font-family: sans-serif; }
    .path { font-family: monospace; }
    .ok { color: #000; background: #8e8;
          padding-left: .3em; padding-right: .3em; }
    .prob { color: #000; background: #ee8;
            padding-left: .3em; padding-right: .3em; }
    .err { color: #000; background: #f88;
           padding-left: .3em; padding-right: .3em; }
  </style>
</head>

<body>

<h1>Installation</h1>

<p>This page will guide you through the Destrictor installation
process. Mostly, this involves checking that the server setup is
correct, but it also includes things like setting up the SVN
repository. You can <?php echo linkAnchor(array('run'=>1), 'restart
the installation') ?> at any time - it will always proceed as far as
possible and then prompt you for further input or give you an error
message.</p>

<ul>
<?php doInstall(); ?>
</ul>

</body>
</html>
<?php 
//======================================================================

/* Make an HTTP request. Returns the server response in a string
   @param $host Host to connect to
   @param $url Host-absolute URL, e.g. '/' for the top-level URL
   @param $headers Entries of the form "Accept"=>"text/*"
   @return An array with the following entries:
   'request' => The complete HTTP request that was made
   'response' => The complete HTTP response received, i.e. headers and body
   'headers' => response headers
   'body' => response body
   'status' => HTTP response status code as an integer , e.g. 404
   'header:' => For any HTTP header named "Header", its contents.
                NB "header" is the lowercased "Header". */
function /*array(string=>string)*/ httpReq(
    /*string*/ $host, /*string*/ $port, /*string*/ $url,
    /*array(string=>string)*/ $headers = NULL,
    /*string*/ $proto = 'HTTP/1.1') {
  $ret = array();

  if ($headers === NULL) $headers = array();
  if (!array_key_exists('Host', $headers))
    $headers['Host'] = $host;
  if (!array_key_exists('User-Agent', $headers))
    $headers['User-Agent'] = 'destrictor-installer/1';
  if (!array_key_exists('Connection', $headers))
    $headers['Connection'] = 'close';
  if (!array_key_exists('Accept', $headers))
    $headers['Accept'] = '*/*';

  if (preg_match('/^[a-zA-Z0-9.-]+$/', $host) == 0) {
    echo 'Invalid host name "' . htmlspecialchars($host) . '"';
    return FALSE;
  }
  // Based on RFC 2396
  if (preg_match('/^\/([a-zA-Z0-9\/_.!~*\'():@&=+$,-]|%[0-9a-fA-F][0-9a-fA-F])+$/', $url) == 0) {
    echo 'Invalid URL "' . htmlspecialchars($url) . '"';
    return FALSE;
  }

  $ip = gethostbyname($host);
  $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
  if ($sock === FALSE) {
    echo 'Could not create socket: '
      . htmlspecialchars(socket_strerror(socket_last_error($sock)));
    return FALSE;
  }
  if (socket_connect($sock, $ip, $port/*HTTP*/) === FALSE) {
    echo 'Could not connect to ' . $host . ': '
      . htmlspecialchars(socket_strerror(socket_last_error($sock)));
    socket_close($sock);
    return FALSE;
  }
  $request = "GET $url $proto\r\n";
  foreach ($headers as $hdr => $val)
    $request .= "$hdr: $val\r\n";
  $request .= "\r\n";
  $ret['request'] = $request;
  socket_write($sock, $request);
  $response = '';
  while (true) {
    $data = socket_read($sock, 2048);
    if ($data === FALSE || $data === '') break;
    $response .= $data;
  }
  socket_close($sock);

  $ret['response'] = $response;
  $bodyOff = strpos($response, "\r\n\r\n");
  if ($bodyOff === FALSE) $bodyOff = strlen($response);
  $ret['headers'] = substr($response, 0, $bodyOff);
  $ret['body'] = substr($response, $bodyOff + 4);
  $firstLine = TRUE;
  foreach (preg_split('/\r\n(?!\s)/', $ret['headers']) as $line) {
    if ($firstLine) {
      $firstLine = FALSE;
      $words = explode(' ', $line);
      $ret['proto'] = $words[0];
      $ret['status'] = intval($words[1]);
    }
    $n = strpos($line, ':');
    if ($n === FALSE) continue;
    $ret[strtolower(substr($line, 0, $n + 1))] = trim(substr($line, $n + 1));
  }
  return $ret;
}
//______________________________________________________________________

/* Given an array returned by httpReq(), output an error message with the
   HTTP request and response. */ 
function httpDump(/*array*/ $ret) {
  return 'The request I sent was:<pre>'
    . htmlspecialchars(trim($ret['request'])) . '</pre>'
    . 'The server\'s response to this request was:<pre>'
    . htmlspecialchars(trim($ret['response'])) . '</pre>';
}
//______________________________________________________________________

// Output a link back to this page, with GET variables appended
function /*string*/ linkStr(/*array(string=>string)*/ $args) {
  $sep = '?';
  $s = 'index.php';
  foreach ($args as $name => $val) {
    $s .= "$sep$name=$val";
    $sep = '&';
  }
  return $s;
}
// Output link as above, but inside <a href="">$text</a>
function /*string*/ linkAnchor(/*array(string=>string)*/ $args,
                               /*string*/ $text) {
  return "<a href=\"" . htmlspecialchars(linkStr($args)) . "\">$text</a>";
}

function /*void*/ li(/*string*/ $htmlContent) {
  echo "<li>$htmlContent</li>\n";
}
function /*void*/ ok(/*string*/ $htmlContent) {
  li("<span class=\"ok\">OK:</span> $htmlContent");
}
function /*void*/ prob(/*string*/ $htmlContent) {
  li("<span class=\"prob\">Possible problem:</span> $htmlContent");
}
function /*void*/ err(/*string*/ $htmlContent) {
  li("<span class=\"err\">Error:</span> $htmlContent");
}
function /*string*/ path(/*string*/ $pathName) {
  return '<span class="path">' . htmlspecialchars($pathName) . '</span>';
}
// Enclose $s in "" and also escape any " in the string as \"
function /*string*/ apacheEscape(/*string*/ $s) {
  return '"' . str_replace('"', '\"', $s) . '"';
}
// Reverse the above transformation
function /*string*/ apacheUnescape(/*string*/ $s) {
  if ($s{0} != '"' || $s{-1} != '"')
    return $s; // Not generated by apacheEscape()?!
  return substr(str_replace('\"', '"', $s), 1, -1);
}
//______________________________________________________________________

/* Add a user to a group. Returns FALSE in case of error, else TRUE.
   Usernames containing spaces or quotes are not allowed. */
function /*bool*/ addToGroup(/*string*/ $groupFile, /*string*/ $groupName,
                             /*string*/ $newUser) {
  if (strpos($newUser, ' ') || strpos($newUser, '"')) return FALSE;
  $g = array(); // group_name_string=>array(member_name_string=>TRUE)
  if (is_file($groupFile)) {
    // Load existing group file
    $groupData = file_get_contents($groupFile);
    if ($groupData === FALSE) return FALSE;
    $groupLines = explode("\n", $groupData);
    foreach ($groupLines as $l) {
      $n = strpos($l, ': ');
      if ($n === FALSE) continue;
      $group = substr($l, 0, $n);
      if (!array_key_exists($group, $g)) $g[$group] = array();
      foreach (explode(' ', substr($l, $n + 2)) as $u)
        $g[$group][$u] = TRUE;
    }
  }

  // Add user (and also group if it doesn't exist)
  if (!array_key_exists($groupName, $g)) $g[$groupName] = array();
  $g[$groupName][$newUser] = TRUE;

  // Write new group file
  $groupData = '';
  foreach ($g as $group => $users) {
    $groupData .= $group . ':';
    foreach ($users as $u => $trueVal) $groupData .= ' ' . $u;
    $groupData .= "\n";
  }
  if (file_put_contents("$groupFile~", $groupData) != strlen($groupData))
    return FALSE;
  @unlink($groupFile);
  return rename("$groupFile~", $groupFile);
}
//______________________________________________________________________

// Clean up previous test files
function removeTestDir() {
  if (file_exists(CACHE . '/test/file.txt')
      && file_get_contents(CACHE . '/test/file.txt') == "123destrictor456") {
    $dh = opendir(CACHE . '/test');
    if ($dh) {
      $files = array();
      while (FALSE !== ($f = readdir($dh)))
        if ($f != '.' && $f != '..') $files[] = $f;
      closedir($dh);
      foreach ($files as $f)
        unlink(CACHE . '/test/' . $f);
    }
    rmdir(CACHE . '/test');
  }
}
//______________________________________________________________________

function doInstall() {
  
  $scriptFile = $_SERVER["SCRIPT_FILENAME"];
  $scriptDir = dirname($scriptFile);
  define('DESTRICTOR_VERSION', '1.0.0');
  $sysConfig = '/etc/destrictor/config-' . DESTRICTOR_VERSION . '.php';
  $user = '';
  if (function_exists('posix_getuid') && function_exists('posix_getpwuid')) {
    $userInfo = posix_getpwuid(posix_getuid());
    $user = $userInfo['name'];
  }
  //______________________________________________________________________

  // Variable names and values to output for GET-type links back to this page
  $vars = array(/*string=>string*/);

  // Intro
  $vars['run'] = 1;
  if (!array_key_exists('run', $_GET)) {
    li('<strong>Before you start, ensure you have the following things:</strong>');
    li('The Linux operating system. A Windows port of Destrictor is possible, but not done.');
    li('The Apache WWW server');
    li('PHP 5 or later - both the Apache module and the command-line <tt>php</tt> program');
    li('The <tt>tidy</tt> command, i.e. <a href="http://tidy.sourceforge.net/">HTML Tidy</a>');
    li('<a href="http://subversion.tigris.org/">Subversion</a>. This installation needs the <tt>svnadmin</tt> and <tt>svn</tt> commands. Later on, you may need either Apache&nbsp;<tt>mod_dav_svn</tt> or Subversion\'s stand-alone <tt>svnserve</tt> server.');
    li('Access to the server: You either need to be able to modify Apache\'s ' . path('httpd.conf') . ' for SVN access via <tt>mod_dav_svn</tt>, or you must be able to run the <tt>svnserve</tt> daemon, or you must be able to run the <tt>svn</tt> command on the server.');
    li(linkAnchor($vars, '<strong>Start installation</strong>'));
    return;
  }
  //______________________________________________________________________

  // Output top-level URL
  $host = $_SERVER['HTTP_HOST'];
  $installDirname = basename(dirname($_SERVER['SCRIPT_NAME'])); // Usually "destrictor"
  $absPath = dirname(dirname($_SERVER['SCRIPT_NAME']));
  if ($absPath == '/') $absPath = '';
  $absPath = str_replace('%2F', '/', rawurlencode($absPath));
  $siteUrl = "http://$host$absPath/";
  $siteUrlX = htmlspecialchars($siteUrl);
  li("The top-level directory where content will appear corresponds to the following URL:<br /><a href=\"$siteUrlX\">$siteUrlX</a><br />If this is not correct, move the " . path($installDirname) . " directory which contains this " . path('index.php') . " to the correct location. The " . path($installDirname) . " directory must be inside the directory for the topmost level of the site.");
  //______________________________________________________________________

  // PHP new enough?
  if (version_compare(phpversion(), '5.0', '>=')) {
    ok('Destrictor requires PHP 5.0 or later, you have version '
       . phpversion() . '.');
  } else {
    err('Destrictor requires PHP 5.0 or later, but you only have version '
        . phpversion() . '.');
    return;
  }
  //______________________________________________________________________

  // config.php present and set up?
  if (file_exists("$scriptDir/config.php")) {
    ok(path('config.php') . ' found in the same directory as this ' . path('index.php') . ', loading settings from it.');
    require_once("$scriptDir/config.php");
  } else {
    err(path('config.php') . ' not found - copy the ' . path('config.php') . ' of the Destrictor distribution into the same directory as this ' . path('index.php') . ' installer file.');
  }
  //______________________________________________________________________

  /* Load system-wide settings. They can only add to the user settings, any
     define() calls which the user settings of the user will be retained */
  if (file_exists($sysConfig)) {
    li('Loading system-wide settings from ' . path($sysConfig) . '<br />These can only <em>add</em> to the settings you made, they cannot override any variable you define() in your ' . path('config.php') . '.');
    require_once($sysConfig);
  } else {
    li('No system-wide settings loaded - ' . path($sysConfig) . ' is not present.');
  }
  //______________________________________________________________________

  // CLI PHP present and same version?
  $cmd = PHPCLI . ' -r "echo phpversion();" 2>&1';
  $out = array();
  $ret = -1;
  exec($cmd, $out, $ret);
  if ($ret == 0 && $out[0] == phpversion()) {
    ok(path(PHPCLI) . ' can be invoked and is also version ' . phpversion()
       . '.');
  } else {
    if ($ret != 0) {
      err('Could not invoke the command-line PHP interpreter - the following '
          . 'command failed with error code ' . $ret . ':<br /><tt>'
          . htmlspecialchars($cmd) . '</tt><br />The output was: <tt>'
          . htmlspecialchars(implode("\n", $out)) . '</tt><br />'
          . 'The command-line PHP interpreter is needed by Destrictor, '
          . 'it is called from the SVN repository hook scripts.');
      return;
    } else {
      err('Version mismatch: The PHP used by Apache is version ' . phpversion()
          . ', but the command-line PHP interpreter is version ' . $out[0]);
      return;
    }
  }
  //______________________________________________________________________

  // Variables set in config.php?
  $allVarsDefined = TRUE;
  foreach (array('BASEDIR', 'REPOSITORY', 'CACHE', 'CORE', 'MODULES',
                 'LANGUAGES', 'SVNLANG', 'SVNLOCATION', 'USERFILE',
                 'GROUPFILE') as $configVarName) {
    if (!defined($configVarName)) {
      err("The configuration variable $configVarName has not been set "
          . "up in the config.php file.");
      $allVarsDefined = FALSE;
    }
  }
  if (!$allVarsDefined) return;
  //______________________________________________________________________

  // CACHE correctly set?
  if (CACHE == dirname($scriptDir) . '/mimuc' ) {
    ok('CACHE variable is set to the correct directory path.');
  } else {
    $vars['weirdcache'] = 1;
    err('Value of CACHE should be<br /><tt>' . htmlspecialchars(dirname($scriptDir)) . '</tt><br />but it is<br /><tt>' . htmlspecialchars(CACHE) . '</tt><br />Please correct the setting in ' . path('config.php') . '. ' . linkAnchor($vars, 'I know what I am doing - skip this check!'));
    if (!array_key_exists('weirdcache', $_GET)) return;
  }
  //______________________________________________________________________

  // Format of LANGUAGES correct?
  if (preg_match('/^([a-z][a-z](-[a-z][a-z])?( [a-z][a-z](-[a-z][a-z])?)*)?$/',
                 LANGUAGES) == 1) {
    ok('Format of LANGUAGES variable is correct.');
  } else {
    err('Format of LANGUAGES variable is not correct. Please edit ' . path('config.php') . ' and correct the setting. The variable is a list of language codes separated with single spaces, there must not be a space at the start or end of the string. Each language code consists of two lower-case letters (e.g. "en" for English), optionally followed by a hyphen and another two lower-case letters (e.g. "en-us" for English as spoken in the USA). The current value is "<tt>' . str_replace(' ', '&nbsp;', htmlspecialchars(LANGUAGES)) . '</tt>".');
    return;
  }
  //______________________________________________________________________

  removeTestDir();

  // Can we create files with web server privileges?
  if (mkdir(CACHE . '/test') === TRUE
      && file_put_contents(CACHE . '/test/file.txt', "123destrictor456") > 0) {
    ok('This PHP script has write access inside the CACHE directory.');
  } else {
    $u = '.';
    if ($user != '') $u = ", \"$user\".";
    err('This PHP script was unable to write inside the CACHE directory. '
        . 'Make sure that write access is possible for the webserver user'
        . $u);
    return;
  }
  //______________________________________________________________________

  // Can we fetch the test file we just created?
  $x = httpReq($host, WEBSITEPORT, "$absPath/test/file.txt");
  if ($x['status'] == 200 && $x['body'] == '123destrictor456') {
    ok('Things which we write into CACHE are accessible via HTTP.');
  } else {
    prob("The <a href=\"http://$host$absPath/test/file.txt\">test file</a>"
         . ' is not '
         . 'accessible. I wanted a response code of 200 and the file '
         . 'contents "123destrictor456".<br />' . httpDump($x));
  }
  //______________________________________________________________________

  // Can we fetch .htaccess/.htpasswd files? This should give an error!
  foreach (array('.htaccess', '.htpasswd', '.htgroup') as $filename) {
    file_put_contents(CACHE . "/test/$filename", "# 123destrictor456");
    $x = httpReq($host, WEBSITEPORT, "$absPath/test/$filename");
    if (($x['status'] == 403 || $x['status'] == 500)
        && strpos($x['response'], '123destrictor456') === FALSE) {
      ok(path($filename) . ' files are not accessible via HTTP.');
    } else {
      $vars['nopasswd'] = 1;
      err("<a href=\"http://$host$absPath/test/$filename\" "
          . "class=\"path\">$filename</a>"
          . ' files appear to be accessible via HTTP, this is a security '
          . 'problem. I wanted a response code of 403.<br />' . httpDump($x)
          . linkAnchor($vars, 'I know what I am doing - skip this check!'));
      if (!array_key_exists('nopasswd', $_GET)) return;
    }
    @unlink(CACHE . "/test/$filename");
  }
  //______________________________________________________________________

  // Can we create .htpasswd files?
  // htpasswd -c /absolute/path/to/some/.htpasswd destrictor
  $cmd = 'htpasswd -bc ' . escapeshellarg(CACHE . '/test/.htpasswd')
    . ' admin password 2>&1';
  $fd = popen($cmd, 'r');
  $out = '';
  $ret = -1;
  if ($fd !== FALSE) {
    while (!feof($fd)) $out .= fread($fd, 2048);
    $ret = pclose($fd);
  }
  if ($ret == 0) {
    ok('Can create password files using the '
       . path('htpasswd') . ' utility.');
  } else {
    prob('Unable to create ' . path('.htpasswd')
         . ' files - the following command failed with error code '
         . $ret . ':<br /><tt>' . htmlspecialchars($cmd)
         . '</tt><br />The output was: <tt>' . htmlspecialchars($out)
         . '</tt>');
  }
  //______________________________________________________________________

  // Can we password-protect the test directory?
  file_put_contents(CACHE . '/test/.htaccess',
"AuthType Basic
AuthName \"Destrictor install test\"
AuthUserFile " . apacheEscape(CACHE . '/test/.htpasswd') . "
AuthGroupFile " . apacheEscape(CACHE . '/test/.htgroup') . "
Require group testgroup
");
  file_put_contents(CACHE . '/test/.htgroup', 'testgroup: admin');
  $x = httpReq($host, WEBSITEPORT, "$absPath/test/file.txt");
  if ($x['status'] == 401) {
    ok('After password-protecting the ' . path('test') . ' directory, it can '
       . 'no longer be accessed directly.');
  } else {
    $vars['nopasswd'] = 1;
    err('After password-protecting the ' . path('test') . ' directory, it can '
        . 'still be accessed directly. I created an ' . path('.htaccess')
        . ' file in the directory - maybe the server does not load it? '
        . 'Check Apache\'s <tt>AllowOverride</tt> setting for the directory, '
        . 'it must not be set to <tt>None</tt>.<br />'
        . 'I wanted a response code of 401. ' . httpDump($x)
        . linkAnchor($vars, 'I know what I am doing - skip this check!'));
    if (!array_key_exists('nopasswd', $_GET)) return;
  }

  $x = httpReq($host, WEBSITEPORT, "$absPath/test/file.txt", array('Authorization'
               => 'Basic ' . base64_encode("admin:password")));
  if ($x['status'] == 200) {
    ok('After password-protecting the ' . path('test') . ' directory, it can '
       . 'be accessed using the right password.');
  } else {
    $vars['nopasswd'] = 1;
    err('After password-protecting the ' . path('test') . ' directory, it '
        . 'cannot be accessed using basic authentication. '
        . 'I wanted a response code of 200.<br />'
        . httpDump($x)
        . linkAnchor($vars, 'I know what I am doing - skip this check!'));
    if (!array_key_exists('nopasswd', $_GET)) return;
  }
  @unlink(CACHE . '/test/.htaccess');
  //______________________________________________________________________

  /* Is the "destrictor" directory password-protected? If not, offer to do
     so. NB, the password file is global, so it will be written inside the
     top-level dir, not the destrictor dir. */
  $x = httpReq($host, WEBSITEPORT, "$absPath/$installDirname/");
  if ($x['status'] == 401 && is_file(CACHE . "/$installDirname/.htaccess")) {
    ok('The ' . path($installDirname) . ' directory is password-protected.'
      . '<br/> If you want to remove the password (not recommended!), '
      . 'delete the ' . path('.htaccess') . ' file inside the directory.');
  } else {
    // Directory is not password-protected
    if (!array_key_exists('pass1', $_POST)
        || !array_key_exists('pass1', $_POST)
        || $_POST['pass1'] != $_POST['pass2']
        || $_POST['pass1'] == ''
        || !ctype_print($_REQUEST['user'])
        || !ctype_print($_POST['pass1'])
        || strpos($_REQUEST['user'], ' ')
        || strpos($_REQUEST['user'], '"')) {
      // Ask for admin user/password
      if ($_POST['pass1'] != $_POST['pass2'])
        err('The passwords you entered below did not match:');
      else if (array_key_exists('pass1', $_POST)
               && $_POST['pass1'] == '')
        err('The password you entered below was empty:');
      else if (strpos($_REQUEST['user'], ' ')
               || strpos($_REQUEST['user'], '"'))
        err('The username must not contain spaces or quotes:');
      $adminUser = 'admin';
      if (array_key_exists('user', $_REQUEST)) $adminUser = $_REQUEST['user'];
      $form = '<form action="' . linkStr($vars) . '" method="post">'
        . 'Username:&nbsp;<input name="user" type="text" size="10" value="'
        . htmlspecialchars($adminUser) . '" /> Password:&nbsp;'
        . '<input name="pass1" type="password" size="10" value="" /> '
        . 'Repeat&nbsp;password:&nbsp;<input name="pass2" type="password" '
        . 'size="10" value="" /> <input type="submit" value="Set password"'
        . ' /></form>';
      err("The <a href=\"http://$host$absPath/$installDirname/\">"
          . path($installDirname) . ' directory</a> is not password-protected.'
          . ' Enter a username/password for the directory below. '
          . 'This username/password will also be usable for SVN access.'
          . '<br />' . $form);
      return;
    } else {
      // Password-protect the destrictor/ dir
      // First, an .htaccess file
      $adminUser = $_REQUEST['user'];
      if (get_magic_quotes_gpc()) $adminUser = stripslashes($adminUser);
      $pass = $_POST['pass1'];
      if (get_magic_quotes_gpc()) $pass = stripslashes($pass);
      
      $htAccess = "AuthType Basic
AuthName \"Destrictor\"
AuthUserFile " . apacheEscape(USERFILE) . "
AuthGroupFile " . apacheEscape(GROUPFILE) . "
Require group \"admin\"
# Might leak information during install, but it is very useful for debugging:
php_flag display_errors on\n";
      if (file_put_contents(CACHE . "/$installDirname/.htaccess", $htAccess) <= 0) {
        $u = '.';
        if ($user != '') $u = ", \"$user\".";
        err('This PHP script was unable to create a ' . path('.htpasswd') . ' file inside the '
            . path($installDirname) . ' directory. '
            . 'Make sure that write access is possible for the webserver user'
            . $u);
        return;
      }
      // Next, a group file
      addToGroup(GROUPFILE, 'admin', $adminUser);
      addToGroup(GROUPFILE, 'php', $adminUser);
      $create = (file_exists(USERFILE) ? '' : 'c');
      // Finally, an .htpasswd file
      $cmd = "htpasswd -b$create " . escapeshellarg(CACHE . '/.htpasswd')
        . ' ' . escapeshellarg($adminUser) . ' ' . escapeshellarg($pass)
        . ' 2>&1';
      $fd = popen($cmd, 'r');
      $out = '';
      $ret = -1;
      if ($fd !== FALSE) {
        while (!feof($fd)) $out .= fread($fd, 2048);
        $ret = pclose($fd);
      }
      if ($ret == 0) {
        ok('Successfully created ' . path('.htaccess')
           . ' and user/password/group files for the ' . path($installDirname)
           . ' directory: <tt>' . htmlspecialchars($out) . '</tt><br />'
           . linkAnchor($vars, 'Restart the installation now')
           . ' - you should be asked to enter the new password.');
        return;
      } else {
        @unlink(CACHE . "/$installDirname/.htaccess");
        err('Unable to create ' . path('.htpasswd')
            . ' file - the following command failed with error code '
            . $ret . ':<br /><tt>' . htmlspecialchars($cmd)
            . '</tt><br />The output was: <tt>' . htmlspecialchars($out)
            . '</tt>');
        return;
      }
    }
  }
  //______________________________________________________________________

  li('<strong>Please add further users!</strong> It is recommended that you add further users and use these to actually commit data. First, create a user with the command <tt>htpasswd&nbsp;<em>/path/to/</em>.htpasswd&nbsp;<em>user.name</em></tt>. The location of the ' . path('.htpasswd') . ' file is the USERFILE setting in your ' . path('config.php') . '. Next, only if that user should be able to commit PHP code (i.e. ' . path('.php') . ' or ' . path('-php') . ' files), append the <tt><em>user.name</em></tt> to the "<tt>php:</tt>" line in the ' . path('.htgroup') . ' file (GROUPFILE setting).');
  //______________________________________________________________________

  li('Writing an ' . path('.htaccess') . ' file to the top-level directory where content will appear...');
  /* Split up the default apache.conf file into config chunks. We simply
     split whenever there is an empty line. */
  $confFile = file_get_contents(CORE . '/apache.conf');
  if ($confFile === FALSE) { err(path('apache.conf') . ' not found'); return; }
  echo "<ul>\n";
  $confChunks = explode("\n\n", $confFile);
  //foreach ($confChunks as $ch)
  //  li('<pre><span></span>' . htmlspecialchars($ch) . '</pre>');

  /* Now check for each chunk whether we can write it to .htaccess without
     getting back a 500 Internal Server Error */
  $confFile = ''; // Known-to-work contents for .htaccess
  $hint = FALSE;
  foreach ($confChunks as $ch) {
    file_put_contents(CACHE . '/.htaccess', "$confFile\n\n$ch");
    $x = httpReq($host, WEBSITEPORT, "$absPath/test/file.txt");
    if ($x['status'] != 200) {
      $err = 'The following chunk of configuration could not be added to ' . path('.htaccess') . ' in the top-level directory:<pre>' . htmlspecialchars(trim($ch)) . '</pre>I have commented out this chunk with "<tt>#!</tt>" for now. ';
      if ($x['status'] == 500) {
        if (!$hint) // Only include this hint once
          $err .= 'The server response code of "500 Internal Server Error" can have several reasons. It can be an indication that Apache\'s <tt>AllowOverride</tt> setting does not permit adding these options for the directory. Specify <tt>AllowOverride&nbsp;All</tt> (inside an appropriate <tt>&lt;Directory&gt;</tt>) to fix this. Alternatively, it can mean that a certain Apache module is not loaded - for example, the <tt>Header</tt> directive needs <tt>mod_header</tt>.';
        else
          $err .= 'Again, the server responded with "500 Internal Server Error".';
        $hint = TRUE;
      } else {
        $err .= httpDump($x);
      }
      prob($err);
      if ($confFile != '') $confFile .= "\n\n";
      $confFile .= '#! ';
      $confFile .= str_replace("\n", "\n#! ", $ch);
      file_put_contents(BASEDIR . '/.htaccess', "$confFile\n\n$ch");
    } else {
      if ($confFile != '') $confFile .= "\n\n";
      $confFile .= $ch;
    }
  }
  echo '</ul>';
  ok('The ' . path('.htaccess') . ' file has been written. '
     . 'If you do not want to use ' . path('.htaccess') . ' files, you can '
     . 'optionally move the configuration to Apache\'s main '
     . path('httpd.conf') . ' file now. <strong>Note:</strong> The '
     . path('.htaccess') . ' file sets <tt>display_errors off</tt> and '
     . '<tt>error_log syslog</tt>, so warnings/errors of your PHP scripts '
     . 'will only appear in your system logfile.');
  //______________________________________________________________________

  // Further tests of Apache config

  // *_private.* should not be accessible
  file_put_contents(CACHE . '/test/x.html_private.orig', 'Gie3feis');
  $x = httpReq($host, WEBSITEPORT, "$absPath/test/x.html_private.orig");
  if (strpos($x['response'], 'Gie3feis') == FALSE) {
    ok('Destrictor\'s internal files, whose names contain the string '
       . '"<tt>_private.</tt>", cannot be accessed via HTTP.');
  } else {
    prob('Destrictor\'s internal files, whose names contain the string "<tt>_private.</tt>", can be accessed via HTTP. If these files are publically available, this could reduce the security of your installation, e.g. because PHP code that is committed to SVN can be viewed by any site visitor.');
  }

  /* No check for *_private.err, as ATM the default Apache config allows
     anonymous access from localhost. */
  //______________________________________________________________________

  // Content-Type for .html and .xhtml
  $testFailCount = 0;
  foreach (array('html' => 'text/html; charset=utf-8',
                 'xhtml' => 'application/xhtml+xml; charset=utf-8',
                 'xht-php' => 'text/x-set-by-xht-php',
                 'html-php' => 'text/x-set-by-html-php')
           as $ext => $ctype) {
    file_put_contents(CACHE . "/test/a.$ext",
      "<?php header('Content-Type: text/x-set-by-$ext'); ?>");
    $x = httpReq($host, WEBSITEPORT, "$absPath/test/a.$ext");
    if ($x['content-type:'] != $ctype) {
      ++$testFailCount;
      prob('Wrong Content-Type returned for ' . path(".$ext") . " files - I expected \"<tt>$ctype</tt>\", but I received \"<tt>" . htmlspecialchars($x['content-type:']) . "</tt>\". " . httpDump($x));
    }
  }
  if ($testFailCount == 0)
    ok('The correct Content-Type is returned for our file extensions.');
  //______________________________________________________________________

  // Content-negotiation: text/html vs application/xhtml+xml
  $testFailCount = 0;
  file_put_contents(CACHE . '/test/b.html', 'is_html');
  file_put_contents(CACHE . '/test/b.xhtml', 'is_xhtml');
  foreach (array('text/html' => 'text/html; charset=utf-8', // MSIE-like
                 'application/xhtml+xml,text/html;q=0.9' => 
                   'application/xhtml+xml; charset=utf-8') // Firefox-like
           as $acceptHeader => $ctype) {
    $x = httpReq($host, WEBSITEPORT, "$absPath/test/b", // NB no extension requested
                 array('Accept' => $acceptHeader));
    if ($x['content-type:'] == $ctype) continue;
    prob('Content-negotiation with MultiViews does not seem to work for '
         . '<tt>text/html</tt> vs. <tt>application/xhtml+xml</tt>. '
         . "I expected a Content-Type of <tt>$ctype</tt>, but got <tt>"
         . htmlspecialchars($x['content-type:']) . '</tt> instead. '
         . httpDump($x));
  }
  if ($testFailCount == 0)
    ok('Content-negotiation with MultiViews works for '
       . '<tt>text/html</tt> vs. <tt>application/xhtml+xml</tt>.');
  //______________________________________________________________________

  // Content-negotiation: Languages
  $testFailCount = 0;
  $languages = explode(' ', LANGUAGES);
  foreach ($languages as $lang)
    file_put_contents(CACHE . "/test/lang.xhtml.$lang.html", "is_lang_$lang");
  foreach ($languages as $lang) {
    $x = httpReq($host, WEBSITEPORT, "$absPath/test/lang.xhtml",
                 array('Accept-Language' => $lang));
    if ($x['content-language:'] == $lang) continue;
    prob('Content-negotiation of the document language with MultiViews does '
         . 'not seem to work. '
         . "I expected a Content-Language of \"<tt>$lang</tt>\", but got "
         . '"<tt>' . htmlspecialchars($x['content-language:'])
         . '</tt>" instead. ' . httpDump($x));
    break; // Break after first error; there might be many languages
  }
  if ($testFailCount == 0)
    ok('Content-negotiation of the document language with MultiViews works.');
  //______________________________________________________________________

  // Content-negotiation: Language selection via cookie
  $lang1 = $lang2 = FALSE;
  // Find two language codes which only have two letters
  foreach ($languages as $lang) {
    if (strlen($lang) != 2 || $lang == $lang1 || $lang == $lang2) continue;
    $lang1 = $lang2;
    $lang2 = $lang;
  }
  if ($lang1 !== FALSE && $lang2 !== FALSE) {
    $x = httpReq($host, WEBSITEPORT, "$absPath/test/lang.xhtml",
                 array('Accept-Language' => $lang2,
                       'Cookie' => 'language=' . $lang1));
    // OK if cookie overrides Accept-Language header
    if ($x['content-language:'] == $lang1) {
      ok('Content-negotiation of the document language with a cookie works.');
    } else {
      prob('Content-negotiation of the document language with a cookie does '
           . 'not seem to work. '
           . "I expected a Content-Language of \"<tt>$lang1</tt>\", but got "
           . '"<tt>' . htmlspecialchars($x['content-language:'])
           . '</tt>" instead. ' . httpDump($x));
    }
  }
  //______________________________________________________________________

  /* If MSIE is configured to use HTTP/1.0, then returned pages must
     (unfortunately!) use "Pragma: no-cache", otherwise cookie-based language
     selection will not work; MSIE will cache the file in one language and
     not re-fetch it when the cookie value changes. */
  $msieHeaders = array(
    'Accept' => 'image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, */*',
    'Accept-Language' => 'en-us',
    'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows 98)',
    'Host' => $host,
    'Connection' => 'Close' /* Actually MSIE sends 'Keep-Alive' */);
  $x = httpReq($host, WEBSITEPORT, "$absPath/test/lang.xhtml", $msieHeaders, 'HTTP/1.0');
  if ($x['status'] == 200 && $x['pragma:'] == 'no-cache') {
    ok('Internet Explorer in HTTP 1.0 mode will be prevented from caching '
       . 'different language variants of a page.');
  } else {
    prob('Internet Explorer in HTTP 1.0 mode is not prevented from caching '
         . 'different language variants of a page. I wanted a response code '
         . 'of 200 and a "Pragma: no-cache" header.<br />' . httpDump($x));
  }
  //______________________________________________________________________

  /* If MSIE is configured to use HTTP/1.1, then Apache _must_not_ send
     HTTP/1.0 responses (via force-response-1.0), or the cookie-based
     language selection will not work. It would be possible also to return
     "Pragma: no-cache" in this case, but instead, we'll just tell the user
     to remove the "force-response-1.0" from his Apache config. */
  $x = httpReq($host, WEBSITEPORT, "$absPath/test/lang.xhtml", $msieHeaders, 'HTTP/1.1');
  if ($x['status'] == 200 && $x['proto'] == 'HTTP/1.1') {
    ok('Internet Explorer in HTTP 1.1 mode receives HTTP/1.1 responses, '
       . 'which causes correct caching of language variants.');
  } else {
    prob('Internet Explorer in HTTP 1.1 mode receives HTTP/1.0 responses. This will prevent correct caching of language variants because the "Vary:" header will not be honoured. This problem is caused by an Apache configuration which includes the <tt>downgrade-1.0</tt> and <tt>force-response-1.0</tt> directives, possibly set by your <tt>mod_ssl</tt> configuration. I wanted a "HTTP/1.1 200 OK" response.<br />' . httpDump($x));    
  }
  //______________________________________________________________________

  // SVN repository

  // Putting the repo below CACHE is a no-no
  $realCachePath = realpath(CACHE) . '/';
  if (substr(realpath(REPOSITORY) . '/', 0, strlen($realCachePath))
      == $realCachePath) {
    err('Wrong setting for REPOSITORY - the SVN repository must not be located anywhere inside CACHE! This is because it must not be publically accessible from the Web. Furthermore, any user would be able to damage the repository by overwriting arbitrary files inside it.');
    return;
  }
  //______________________________________________________________________

  // If REPOSITORY present as dir, check whether it is empty
  $repoEntryCount = -1;
  if (is_dir(REPOSITORY)) {
    $dh = opendir(REPOSITORY);
    if ($dh) {
      $repoEntryCount = 0;
      while (FALSE !== ($f = readdir($dh)))
        if ($f != '.' && $f != '..') ++$repoEntryCount;
      closedir($dh);
    }
  }

  // Create SVN repository
  if (!file_exists(REPOSITORY) || $repoEntryCount == 0) {
    if ($repoEntryCount == 0) {
      ok('REPOSITORY directory exists and is empty.');
    } else {
      if (mkdir(REPOSITORY) === FALSE) {
        err('Could not create the directory given by REPOSITORY, which will '
            . 'contain the SVN repository');
        return;
      }
      ok('Created directory for REPOSITORY.');
    }
    // Invoke svnadmin to create the repository
    $cmd = 'svnadmin create ' . escapeshellarg(REPOSITORY) . ' 2>&1';
    $fd = popen($cmd, 'r');
    $out = '';
    $ret = -1;
    if ($fd !== FALSE) {
      while (!feof($fd)) $out .= fread($fd, 2048);
      $ret = pclose($fd);
    }
    if ($ret == 0) {
      mkdir(REPOSITORY . '/destrictor');
      file_put_contents(REPOSITORY . '/destrictor/created', '');
      ok("Successfully created an SVN repository inside REPOSITORY. <em>Important note:</em> The repository is owned by the web server user \"$user\". This is the right choice if you are going to use Apache's <tt>mod_dav_svn</tt> to commit data to the repository via HTTP. If other users than \"$user\" need to access the repository, you will need to tweak its access rights to grant them write access to all files in the directory.");
    } else {
      err('Unable to create an SVN repository inside REPOSITORY - '
          . 'the following command failed with error code ' . $ret
          . ':<br /><tt>' . htmlspecialchars($cmd)
           . '</tt><br />The output was: <tt>' . htmlspecialchars($out)
           . '</tt>');
    }
  } else {
    /* Directory REPOSITORY already exists and is non-empty. Do some basic
       checks to ensure it contains an SVN repository. */
    if (!is_dir(REPOSITORY . '/conf') || !is_dir(REPOSITORY . '/hooks')
        || !is_file(REPOSITORY . '/format')
        || !is_file(REPOSITORY . '/conf/svnserve.conf')) {
      err('The directory given by REPOSITORY is not empty, and it does not appear to contain an SVN repository. Delete the directory or move it out of the way so I can create a repository.');
      return;
    } else {
      ok('The directory given by REPOSITORY already appears to contain an SVN repository.');
    }
  }
  //______________________________________________________________________

  // At this point, we assume that an SVN repository exists
  // Warn if repository was not created by us
  if (!is_file(REPOSITORY . '/destrictor/created')) {
    $vars['alienrepo'] = 1;
    prob('The directory given by REPOSITORY appears to contain an SVN repository which was <em>not</em> created by this installation script. This script will now overwrite files in the repository\'s ' . path('hooks') . ' subdirectory and create a ' . path('destrictor') . ' subdirectory, but only if you choose to ' . linkAnchor($vars, 'continue') . '.');
    if (!array_key_exists('alienrepo', $_GET)) return;
  }
  
  // Create our dir even in alien repo
  if (!file_exists(REPOSITORY . '/destrictor'))
    mkdir(REPOSITORY . '/destrictor');
  //______________________________________________________________________

  // Overwrite hook scripts
  $hookScriptStart = "#! /bin/sh
# This file is overwritten by Destrictor's installer script
hookDir=" . escapeshellarg(REPOSITORY . "/hooks") . "
configFile=" . escapeshellarg("$scriptDir/config.php") . "
coreDir=" . escapeshellarg(CORE) . "
php=" . PHPCLI . "\n";
  $preCommit = "$hookScriptStart
test -f \"\$hookDir/pre-commit0\" && . \"\$hookDir/pre-commit0\" \"$@\"
\$php -f \"\$coreDir/svnPrecommit.php\" \"\$configFile\" 1>&2 \"$@\"
";
  $postCommit = "$hookScriptStart
test -f \"\$hookDir/post-commit0\" && . \"\$hookDir/post-commit0\" \"$@\"
\$php -f \"\$coreDir/svnPostcommit.php\" \"\$configFile\" 2>&1 \"$@\" \
| \$php -f \"\$coreDir/logStdin.php\" \"\$configFile\"
";
  $statPre = @stat(REPOSITORY . '/hooks/pre-commit');
  $statPost = @stat(REPOSITORY . '/hooks/post-commit');
  if (is_array($statPre) && ($statPre['mode'] & 07777) == 0755
      && is_array($statPost) && ($statPost['mode'] & 07777) == 0755
      && file_get_contents(REPOSITORY . '/hooks/pre-commit') == $preCommit
      && file_get_contents(REPOSITORY . '/hooks/post-commit') == $postCommit) {
    /* Need not overwrite hook scripts. This is checked for as a convenience;
       The user may have changed the access rights so we might no longer be
       able to actually change the scripts. */
    ok('No changes necessary to the ' . path('hooks/pre-commit') . ' and ' . path('hooks/post-commit') . ' scripts inside the REPOSITORY directory.');
  } else if (
         file_put_contents(REPOSITORY . '/hooks/pre-commit', $preCommit) > 0
      && chmod(REPOSITORY . '/hooks/pre-commit', 0755) === TRUE
      && file_put_contents(REPOSITORY . '/hooks/post-commit', $postCommit) > 0
      && chmod(REPOSITORY . '/hooks/post-commit', 0755) === TRUE) {
    // Overwriting hook scripts OK
    ok('Successfully created ' . path('hooks/pre-commit') . ' and ' . path('hooks/post-commit') . ' scripts inside the REPOSITORY directory. The location of ' . path('config.php') . ' and the contents of the REPOSITORY, PHPCLI and CORE variables are hardcoded into the files - re-run this installation script whenever you change any of these settings!');
  } else {
    // Overwriting hook scripts failed
    err('Could not write to the ' . path('hooks/pre-commit') . ' and ' . path('hooks/post-commit') . ' scripts inside the REPOSITORY directory. ');
    return;
  }
  //______________________________________________________________________

  // Create httpd.conf snippet for mod_dav_svn
  $httpdConf = "<Location " . apacheEscape(SVNLOCATION) . ">
  DAV svn
  SVNPath " . apacheEscape(REPOSITORY) . "
  AuthType Basic
  AuthName \"Destrictor\"
  AuthUserFile " . apacheEscape(CACHE . '/.htpasswd') . "
  AuthGroupFile " . apacheEscape(CACHE . '/.htgroup') . "
  Require valid-user
  Allow from all
  Options Indexes
</Location>
";
  $absPath = str_replace('%2F', '/', rawurlencode(SVNLOCATION));
  $httpdInfo = 'If you want to use <tt>mod_dav_svn</tt>, put an <tt>Include&nbsp;<em>/path-to-REPOSITORY/</em>destrictor/httpd.conf</tt> directive into your ' . path('httpd.conf') . ' to make the SVN repository accessible via HTTP. After that, the repository should be available via '
    . "<a href=\"$absPath\">http://$host$absPath</a>. "
    . 'Note that using <tt>mod_dav_svn</tt> is not mandatory - '
    . 'you can also use <tt>svnserve</tt> or directly commit to the '
    . 'repository from the command line on the server.';
  if (is_file(REPOSITORY . '/destrictor/httpd.conf')
      && file_get_contents(REPOSITORY . '/destrictor/httpd.conf')
         == $httpdConf) {
    ok('No changes necessary to the ' . path('destrictor/httpd.conf')
       . ' file inside the REPOSITORY directory. ' . $httpdInfo);
  } else {
    if (file_put_contents(REPOSITORY . '/destrictor/httpd.conf',
                          $httpdConf) > 0) {
      ok('Configuration written to the ' . path('destrictor/httpd.conf')
         . ' file inside the REPOSITORY directory. ' . $httpdInfo);
    } else {
      err('Could not write to the ' . path('destrictor/httpd.conf')
          . ' file inside the REPOSITORY directory.');
      return;
    }
  }
  //______________________________________________________________________

  li('End of installation code');
  removeTestDir();
  
}
?>
