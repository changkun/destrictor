<?php

// Main destrictor configuration file
// NOTE: All _directory_names_ should *not* have a trailing slash

// Location of checked-out SVN repository, i.e. root of website.
define('CACHE', '/var/www/your-website-cache-folder');

/* Location of SVN repository. For example, the repository's svnserve.conf
   will be at REPOSITORY.'/conf/svnserve.conf' */
define('REPOSITORY', '/var/www/svn/your-svn-folder');

/* Port of the website. The destrictor installation requires port 80 and 433, and 
   the website accessability will be tested at this port.
   For instance, the destrictor can be accessed via 127.0.0.1:80 (or port 433), 
   the website can be accessed by 127.0.0.1:8080 */
define('WEBSITEPORT', '8080');

/* There should be no need to edit the following three if you unpacked the
   Destrictor code into the root of your website */
/* Top-level directory of destrictor installation.
   install.php requires that this file (config.php) is accessible as
   BASEDIR.'/config.php' */
define('BASEDIR', CACHE . '/destrictor');
define('CORE', BASEDIR . '/core'); // Directory containing main PHP code
define('MODULES', BASEDIR . '/modules'); // Directory containing modules

/* Space-separated list of two-letter language codes. Any item in this list
   results in a respective language tag to be supported. For example, 'en'
   results in support for <en></en>. IMPORTANT: Some care must be taken to
   avoid that Apache gives back responses like "300 Multiple Choices" or "406
   Not Acceptable" for some browser configurations. It is recommended to use
   the Apache config options "ForceLanguagePriority Prefer Fallback" and
   "LanguagePriority en fr de", where the list after "LanguagePriority"
   should contain all languages in use on the site and all language variants
   like "en-us", with the first being the default language.

   Due to misconfiguration of most popular browsers, you should include
   language variants like "en-us" in addition to the simple codes like
   "en". The variants for a language *must* appear after the language
   itself.

   Should you ever want to remove any language from the list, first make sure
   that the respective <xx> language tag is no longer used by any page in
   SVN. Otherwise, stale content may get left behind. */
define('LANGUAGES', 'de de-de de-at en en-gb en-us it fr');

/* Value for the "LANG" environment variable when the "svn" program is
   called. Necessary for SVN to be able to work with filenames with non-ASCII
   characters in them. A locale of that name must have been defined on the
   system, otherwise you will get errors like this in your syslog:
   svn: error: cannot set LC_ALL locale
   svn: error: environment variable LANG is en_US
   svn: error: please check that your locale name is correct
   IMPORTANT: The value should start with "en_" - we parse the output of the
   svn command for our purposes, and this has only been tested with the
   standard English output. */
define('SVNLANG', 'en_US');

// How to invoke the command-line php interpreter
define('PHPCLI', '/usr/bin/php');

/* _Server_-absolute path where mod_dav_svn should offer SVN access
   (DAV/XDelta). Important: The location defined here must not overlap with
   other Locations defined in the server configuration. For example, if your
   main DocumentRoot is /www, do not export a Subversion repository in
   <Location /www/repos>. If a request comes in for the URI /www/repos/foo.c,
   Apache won't know whether to look for a file repos/foo.c in the
   DocumentRoot, or whether to delegate mod_dav_svn to return foo.c from the
   Subversion repository. This string must start with a slash, but must not
   end with a slash. */
define('SVNLOCATION', '/svn');

/* Locations of the main user/password and group member files. Do not change
   this unless you are paranoid about security and do not want these files to
   reside in the default location, i.e. in a publically served directory, but
   with filenames which are protected from being downloaded because they
   start with ".ht". */
define('USERFILE', CACHE . '/.htpasswd');
define('GROUPFILE', CACHE . '/.htgroup');

/* Location of the website content inside the repository. The default value
   "/trunk" means that if you commit a file "/trunk/index.xhtml" in the
   repository, that file will be served as the top-level page of your web
   space. This value must not end in a slash - if you want the top-level SVN
   directory to be the top-level content directory, use a value of "", not
   "/". */
define('REPOSITORYDIR', '/trunk');

?>
