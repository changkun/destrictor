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

/** @hook void xhtmlDom(array('path'=>string, 'source'=>DOMDocument,
    'result'=>DOMDocument)

    Filtering of XHTML content at DOM tree level. The "result" DOMDocument
    may be modified or replaced as required. The "source" document is the
    representation of the parsed XHTML and must not be modified, it is only
    available for inspection by filters. Initially, "result" is a deep copy
    of "source".

    Other filters may add further entries to the array. For example, the
    templating filter will add a 'main' entry with a reference to the
    DOMElement which contains the page's main content. */

/** @hook requestTimePhp(array('globalcode'=>string, 'filecode'=>string,
                         'path'=>string, 'dir'=>string, 'up'=>string)

    Whenever the xhtmlFile code writes any file which contains PHP code
    (i.e. an .xht-php or a .html-php file, NOT a normal .php file), it calls
    this hook to allow other modules to specify additional request-time code
    to prepend to the file. Any subscriber should always <em>append</em> its
    code to the supplied 'globalcode' or 'filecode' string, these strings
    should never be altered in any other way than appending.

    The commands appended to 'globalcode' are written to the file
    CACHE."/.config_private.php" and a require_once() for that file is added
    at the very start of the .xht-php/.html-php file. Thus, this code will be
    executed by all destrictor-generated PHP code, and what you append to
    'globalcode' should not be dependent on the 'path' variable. If more than
    one file is regenerated during one commit due to dependencies,
    .config_private.php is only updated for the first file.

    The commands appended to 'filecode' are written directly to the
    .xht-php/.html-php file, immediately after the require_once() of
    .config_private.php. Thus, you can specify individually for each file the
    PHP code to prepend to its contents.  Note: The code you supply here is
    only updated whenever the respective file is updated, usually only in
    response to a commit which changes the content. Thus, it is unwise to add
    code which changes often, e.g. on the next destrictor upgrade, because
    all the code snippets in the individual files will then have to be
    updated in some way. Use 'globalcode' whenever possible.

    requestTimeDefs.php subscribes to this hook with a high priority and
    arranges for variables like CACHE to be set up by the time your code is
    called.

    Another note: Newlines in the 'filecode' you supply will be replaced with
    spaces. This is necessary to avoid that the line number information in
    error messages becomes incorrect.

    Please prepend your code with a comment containing the name of the file
    which added the code, to aid debugging!

    The 'path' argument must not be modified, it gives the path of the file
    for which the code is generated.

    The 'dir' argument contains the path of the directory that contains
    'path'.

    The 'up' argument is the string "../" repeated zero or more times, as
    many as necessary for reaching the top-level CACHE directory from the
    current path. This is useful for require_once(), to include one global
    file with code from the CACHE root.
 */
//______________________________________________________________________

hook_subscribe('fileAdded_xhtml', 'xhtmlFile_fileAdded_xhtml');
hook_subscribe('fileUpdated_xhtml', 'xhtmlFile_fileUpdated_xhtml');
hook_subscribe('fileAdded_xhtml-php', 'xhtmlFile_fileAdded_xhtml');
hook_subscribe('fileUpdated_xhtml-php', 'xhtmlFile_fileUpdated_xhtml');

hook_subscribe('xhtmlDom', 'xhtmlFile_xhtmlDom', -100); // Call at very end

/** @fileext .xhtml, .xhtml-php

    The extensions .xhtml and .xhtml-php are "magic" in that when a file with
    one of these extensions is added to the SVN repository, destrictor will
    modify it before serving it via Apache. This is in contrast to other
    types of files (e.g. .html files, images) which will be served by Apache
    in exactly the way you committed them.

    The character encoding of the page is expected to be Windows 1252 (a
    superset of ISO-8859-1). If this is not acceptable, you can also use
    UTF-8 and indicate this by using the following meta header: <br/><tt>
    &lt;meta http-equiv="Content-Type" content="application/xhtml+xml;
    charset=utf-8" /&gt;</tt><br/>Alternatively, an XML processing
    instruction at the start will also cause UTF-8 to be used: <tt>&lt;?xml
    version="1.0" encoding="utf-8"?&gt;</tt>

    A number of different things happens to an .xhtml file before it is given
    to Apache for serving:

    - Using <a href="http://tidy.sourceforge.net/">HTML Tidy</a>, the
      HTML is cleaned up by tidy.php and turned into well-formed XHTML. For
      example, &lt;br&gt; is turned into &lt;br/&gt;, the ugly things done by
      Microsoft Word's HTML export are fixed, and other common HTML mistakes
      are automatically corrected. Furthermore, the page is converted to
      UTF-8 encoding.

    - Only for an extension of .xhtml-php, commit-time PHP code (inside
      &lt;?php ?&gt; processing instructions) is executed. This code is given
      full access to all internal destrictor functions and can influence
      creation of the page in various ways - see below.

    - All registered filters (hook "xhtmlFile_xhtmlDom") are applied to the
      content. This includes adding a template with navigation/header/footer
      etc. around the content (see description for <a
      href="$fileext.template">.template</a> files), and adding menus and
      breadcrumb trails (see <a
      href="$fileext.navmenu">.navmenu</a>). Furthermore, all special tags
      implemented by modules are substituted with appropriate XHTML code.

    - Several variants of the document are created and made available via
      Apache's MultiViews mechanism. This includes variants which are served
      with a Content-Type of "text/html" (for Internet Explorer 6) and
      "application/xhtml+xml" (Firefox and others). There are also variants
      for all the different languages used by the page (see
      langTag.php). Finally, there is a gzip-compressed version of each
      variant.

    The first step (tidy) will not be performed if there is fully compliant
    XHTML header at the start of the input, e.g. something like the
    following:

    <pre>
&lt;?xml version="1.0"?&gt;
&lt;!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"&gt;
&lt;html xmlns="http://www.w3.org/1999/xhtml"&gt;
    </pre>

    <em>Important:</em> If "tidy" is not run, this also means that no
    conversion to UTF-8 takes place. Your input document must already be
    UTF-8, or you will encounter errors later on, when PHP's XML parser tries
    to parse the file content.

    <h2>Request-time PHP code: &lt;? ... ?&gt;</h2>

    Two types of PHP code are allowed in .xhtml-php files: Commit-time code
    is executed the moment the file is committed in the SVN repository, and
    also whenever the file is regenerated by Destrictor because one of its
    dependencies changed.

    Request-time code is the same thing as with normal .php files: If any
    &lt;? ?&gt; instructions are present on the page, Destrictor writes the
    code they contain to a .php file which is executed whenever a browser
    visits the page.

    <h2>Commit-time PHP code: &lt;?php ... ?&gt;</h2>

    When the commit-time PHP code (a &lt;?php ?&gt; processing instruction)
    is executed, all settings from config.php, such as CACHE, are available.

    Additionally, the variable $info is set up for input and output. It is an
    array with the following entries:

    - 'path' => string: CACHE-absolute path of .xhtml-php file

    - 'up' => The string "../" repeated zero or more times, suitable for going
    up from 'path' to the top of CACHE.

    - You can set $info['notemplate']=TRUE to prevent that xhtmlTemplate
    applies the template data to the document.

    <h3>Setting a callback function: $info['callback']</h3>

    You can also create an entry like $info['callback']='myDomFunction' - the
    value is the name of a function which will be called immediately after
    the new XHTML document (created from the input .xhtml) has successfully
    been parsed, i.e. before the template is applied or anything else happens
    to the file. The function will be called as myDomFunction($info),
    i.e. with the same $info array that was previously given to the
    .xhtml-php code. However, this time there are additional entries:
    $info['source'] is the DOMDocument for the original .xhtml file, it must
    not be modified. $info['result'] is a deep copy of $info['source'], it is
    the basis for later template substitution and can be modified.  (To
    execute code later, e.g. after template substitution, <a
    href="$fileext.template#dom-code-late">see the .template
    documentation</a>).

    Before the code is executed, the current directory is changed to the
    directory in CACHE which contains the input .xhtml(-php) document (and
    not the dir which contains the .template-php file).

    When the commit-time code is executed, the variable $firstTime is set to
    TRUE if this file is executed for the first time by this instance of the
    PHP interpreter. You should use it to ensure that e.g. functions are only
    declared once if the file is executed multiple times. For example:
    <pre>
    if ($firstTime) {
      function myFunc() { ... }
    }</pre>
    [Aside: It is fairly unlikely that some .xhtml-php code is executed more
    than once. However, this can happen in a few cases, e.g. if circular
    dependencies exist between files.]

    <h3>Preventing generation of output in CACHE: $info['finished']</h3>

    There is another modification that the code can make to $info: If
    $info['finished'] is set to TRUE, then destrictor will simply stop
    processing the file any further. No output files will be created in
    CACHE. Any registered callback function will not be called because
    processing stops before an attempt is even made to parse the data as
    XHTML.

    This is useful if you do not want to create an XHTML page, but want to
    create other static files. For example, for efficiency reasons you may
    want to concatenate your multiple .css files into just one style.css file
    inside CACHE. This could be done by creating a style.xhtml-php file whose
    code creates the concatenated file, writes it to style.css, registers it
    for auto-deletion once style.xhtml-php is deleted (via
    anyDirOrFile_autoDel($info['path'], '/style.css')) and also registers for
    style.css to be regenerated whenever one of the source .css files changes
    (via depend_addDependency()).

    <h3>Always serve content as text/html: $info['xmlcontent']</h3>

    Finally, the code can set $info['xmlcontent'] to FALSE. In this case,
    when the final CACHE entries are written, xhtmlFile_write() will
    <em>not</em> create the .xhtml symlinks pointing to .html files, and also
    not the .xht-php symlinks pointing to .html-php files. You should set the
    array entry to FALSE if you cannot be sure that the XHTML that is output
    by the system at the end is actually valid XML. If it is not valid, some
    browsers (notably Firefox with our setup) will only display an error
    message instead of the page - usually not what you want! By setting
    $info['xmlcontent']=FALSE, you instruct Destrictor always to serve such
    content as text/html and never as application/xhtml+xml.

    <h3>Further $info entries</h3>

    Other filters may interpret further entries of the $info array. For
    example, <a href="$fileext.template">see the .template
    documentation</a> for $info['notemplate'].
*/

/** @file

    This file is called when .xhtml or .xhtml-php files are committed to the
    SVN repository. Via the hook "xhtmlDom", it causes it to be processed by
    a number of other files before the content is written to the CACHE
    directory as a number of files.

    <h2>Layout and contents of the CACHE directory</h2>

    When a file is added, repair any HTML problems, clean up messy Microsoft
    Word markup and generally tidy it up with <a
    href="http://tidy.sourceforge.net/">HTML Tidy</a>. Next, apply all
    registered filters (hook "xhtmlFile_xhtmlDom") to its content. Finally,
    create several files/symlinks as outlined below.

    Naming convention: When a file named "x" is committed to SVN, then all
    auto-generated private files which have anything to do with it begin with
    "x_private." - this makes it easy to see which auto-generated files
    belong where, and there cannot be any conflicts between committed
    filenames which are prefixes of other committed filenames (e.g. "f.xhtml"
    and f.xhtml.xhtml"). <em>Why "_private." and not ".private."?</em> It
    used to be ".private." in an earlier version of Destrictor. However, it
    turned out that this would cause Apache to fill its error.log with huge
    amounts of "client denied by server configuration" error messages,
    because when serving x.html, Apache MultiViews would always attempt to
    access all files named "x.html.*", which included the .private. files.

    (Before reading the stuff below, make sure you have an idea of how Apache
    MultiViews works.)

    Files are created as follows:

    1) For a file committed under the name "f.xhtml" or "f.xhtml-php", create
    one file "f.xhtml.xhtml" OR "f.xhtml-php.xht-php". The double .xhtml
    extension is necessary because if the user requests the URL "f.xhtml" and
    a file "f.xhtml" is present, MultiViews content negotiation will not be
    used. In the Apache config, both ".xhtml" and ".xht-php" are assigned a
    Content-Type of application/xhtml+xml, so they will be chosen if the
    browser says (in its Accept header) that it prefers application/xhtml+xml
    over text/html. Only Firefox does this - the different browsers send
    these Accept headers:

    - Firefox 1.5: Accept: text/xml,application/xml,application/xhtml+xml, text/html;q=0.9,text/plain;q=0.8,image/png,*<span/>/*;q=0.5

    - Konqueror 3.5.1: Accept: text/html, image/jpeg, image/png, text/*, image/*, *<span/>/*

    - Opera 8.5: Accept: text/html, application/xml;q=0.9, application/xhtml+xml, image/png, image/jpeg, image/gif, image/x-xbitmap, *<span/>/*;q=0.1

    - MSIE 6.0: Accept: image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, *<span/>/*

    We use MultiViews to ship application/xhtml+xml to Firefox, text/html
    to the others.

    2) Create a symlink called ".xhtml" which points to the ".html" file, or
    a symlink called ".xht-php" which points to the ".html-php". In the
    Apache config, both ".html" and ".html-php" are assigned a Content-Type
    of text/html, so this variant will generally be served to non-Firefox
    browsers by Apache. This step is skipped if the template or the PHP code
    in the file requested it by setting $info['xmlcontent']=FALSE

    Special attention must be given to the following issue: MSIE <7 does not
    understand application/xhtml+xml at all - if Apache serves such a file to
    MSIE, it will pop up a "Save as" dialog, which is not what we
    want. Unfortunately, MSIE's Accept header does not even include
    text/html, only *<span/>/*, which means that according to the standard,
    MSIE says to the server "I understand everything, application/xhtml+xml
    and text/html have equal priority to me". Grr. --- Apache comes to the
    rescue: In the case that two MIME types have identical priority and
    neither one appears in the Accept header, Apache will serve the MIME type
    whose file extension sorts earlier alphabetically. Thus, MSIE will be
    given the ".html" file as text/html (because it sorts earlier than
    ".xhtml"), and MSIE will be given the ".html-php" file as text/html
    (because it sorts earlier than ".xht-php").

    3) Now both the extensions ".html-php" and ".xht-php" are assigned the
    handler "php-script" in the Apache config. Important: The extensions
    must not be assigned the MIME type application/x-httpd-php, or the entire
    content negotiation stuff above will fail.

    A MIME type of application/x-httpd-php in combination with
    MultiViews-based content-negotiation leads to the problem that the site
    may not get indexed by Google, because most Googlebots send an "Accept:
    text/*" header which will not match application/x-httpd-php - <a
    href="http://tranchant.plus.com/notes/multiviews">discussion of this
    issue</a>.

    Instead of using the standard ".php" extension, the two new PHP
    extensions ".html-php" and ".xht-php" are introduced because lots of
    existing setups have the evil application/x-httpd-php MIME type in many
    places so that removing it would be difficult. Furthermore, if the
    changes for ".xht-php" were made to ".php", this might interfere in small
    ways with other parts of a big server setup.

    Why the new ".xht-php" extension - why can't we reuse ".xhtml-php" for
    this? The reason is that when an ".xhtml-php" file is committed, it will
    sometimes only contain commit-time PHP code and no request-time PHP
    code. In this case, we want to serve the data statically without the
    overhead of mod_php5. However, if ".xhtml-php" were given the handler
    php-script, files with this extension anywhere in their filename would
    <em>always</em> be passed to mod_php5.

    PHP always outputs a simple "Content-Type: text/html". We add a small
    piece of code at the start of each PHP file which makes it return the
    right Content-Type depending on the script's file extension: If the
    script was called via a ".xht-php" filename, "Content-Type:
    application/xhtml+xml; charset=utf-8" is output.

    4) Only for static content (".html" with its ".xhtml" symlink), also
    create a gzipped version with an extension of ".gz.xhtml". Similar to
    above, create a symlink with a ".gz.xhtml" extension which points to the
    ".gz.html".

    5) Finally, if the input contains language tags for >= 1 languages,
    create multiple files, one for each language. An extension with a
    language code like "en" or "en-us" is added immediately after the
    ".xhtml" extension. For example, for English PHP code
    ".en.xht-php" would be used, and the the symlink for the
    compressed variant of German content would have a
    ".de.gz.xhtml" extension.

    6) If the input contains language tags for >= 1 languages AND a version
    of the page is created for a certain language "xx" (e.g. "en") AND
    another language "xx-yy" (e.g. "en-us") is registered, but <em>no</em>
    tags for that variant are in the input, THEN a lot of symlinks are
    created in such a way that for every "xx" file/symlink, there is an
    otherwise identically named "xx-yy" file/symlink.

    In a nutshell, this means that whenever you have an English version
    (names with ".en." in them), symlinks for a US-English version (names
    with ".en-us.") will automatically be created, unless the document
    explicitly contains &lt;en-us&gt; tags for an en-us version.

    This is necessary because a) Apache's implementation is 100% compliant
    with the HTTP RFC - Apache will not send "en" content when the browser
    only requests "en-us", and b) most browsers violate the HTTP standard:
    They send e.g. an "Accept-Language: en-us" header, but they should really
    also automatically be sending the non-country-specific language code,
    i.e. "Accept-Language: en-us, en".

    With this whole system, the number of symlinks can become quite
    impressive. If your languages list is "de de-de de-at en en-gb en-us it
    fr" and you have a document which uses the four languages "de en it fr",
    then you'll end up with 32 links and files - for a single input file!


    <h2>Creation of output XHTML from input XHTML and template data</h2>

    When a file called "file.xhtml(-php)" is added, the following happens:

    - svn.php calls the hook "fileAdded_xhtml", which executes
      xhtmlFile_fileAdded_xhtml(). This function writes the .xhtml data to a
      file.xhtml_private.orig file, then it calls tidy_xhtml() to turn the
      .orig data into valid XHTML, and writes the tidied data to a
      file.xhtml_private.tidy file. Finally, it forces a call to the hook
      "fileDependUpdated_xhtml" using depend_fileChanged(). That call will
      also cause other files to be queued for a rebuild if they depend on the
      current .xhtml file.

    - langTag_fileDependUpdated_xhtml() gets called as a subscriber of the
      hook "fileDependUpdated_xhtml". It deletes old file.xhtml.en.xhtml and
      similar files.

    - xhtmlFile_fileDependUpdated_xhtml() gets called as a subscriber of the
      hook "fileDependUpdated_xhtml". It deletes old non-language-specific
      files, e.g. file.xhtml.xhtml. Next, it uses
      xhtmlTemplate_getContentOrEval() to load the file.xhtml data or
      load+execute the file.xhtml-php data. The $info array that is given to
      the .xhtml-php code contains entries for 'path' and 'up'. Finally, the
      array is passed to xhtmlFile_filter() together with the XHTML content
      in string format, unless the code has set $info['finished'] to TRUE, in
      which case processing for this file stops.

    - xhtmlFile_filter() creates a DOMDocument from the XHTML string. It adds
      two new entries to the $info array: 'source' is the DOMDocument, by
      convention, it will not be modified from now on. 'result' is a deep
      copy of the DOMDocument, only it may be modified, and it is also the
      DOM tree which wil ultimately be converted back into string format. If
      the .xhtml-php file has set the $info['callback'] entry to a function
      name string, it attempts to call the function. Finally, the hook
      "xhtmlDom" is called.

    - Quite a few subscribers to the "xhtmlDom" hook exist. Most of them only
      take the 'result' tree and modify it a little, e.g. to replace a
      &lt;lastmodified/&gt; tag with some text. Below, we will only cover the
      most interesting ones: Adding template data, and writing the final data
      to files inside CACHE.

    - xhtmlTemplate_xhtmlDom() gets called as a subscriber of the "xhtmlDom"
      hook. It finds the right .template(-php) file and loads/executes
      it. Next, it creates a DOM tree for the template data and combines the
      XHTML document tree and the template tree into just one document which
      becomes the new $info['result']. If the .template-php file has set the
      $info['callback'] entry to a function name string, it attempts to call
      the function.

    - xhtmlFile_xhtmlDom() is the lowest-priority subscriber to the
      "xhtmlDom" hook, it is called at the very end. (If langTag.php detects
      language tags like &lt;en&gt;, it is not called at all.) It uses
      xhtmlFile_write() to write non-language-specific files into CACHE.

    - langTag_xhtmlDom() is called before xhtmlFile_xhtmlDom(). If it finds
      that it needs to create more than one language version of the file, it
      will do so with multiple calls to xhtmlFile_write() before prematurely
      terminating the hook call, so that xhtmlFile_xhtmlDom() does not get
      called.

 */
//______________________________________________________________________

/** Called by hook. */
function /*void*/ xhtmlFile_fileAdded_xhtml(SvnChange $c) {
  // Create data from .xhtml later
  depend_addToQueue($c->path);
  // Two dependency changes: The dir listing changes and the new file "changes"
  depend_fileChanged(dirname($c->path) . '/');
  depend_fileChanged($c->path);
  // Write .orig (original data) and .tidy (data piped through HTML tidy)
  $file = svn_cat($c->path);
  /* It is perfectly possible and no error that the file is not actually
     there - this happens if the .xhtml declares a dependency, then the
     .xhtml is removed and then the dependency is modified. */
  if ($file !== FALSE) {
    file_put_contents(CACHE . $c->path . '_private.orig', $file);
    $xhtml = tidy_xhtml($c->path, $file);
    /* Allow people to add custom tags anywhere by writing them as
       &lt;tag&gt; instead of <tag> */
    $xhtml = xhtmlFile_unescapeCustomTags($xhtml);
    file_put_contents(CACHE . $c->path . '_private.tidy', $xhtml);
  }
  $c->finished = TRUE;
}

/** Called by hook. */
function /*void*/ xhtmlFile_fileUpdated_xhtml(SvnChange $c) {
  depend_addToQueue($c->path);
  depend_fileChanged($c->path);
  // Write .orig (original data) and .tidy (data piped through HTML tidy)
  $file = svn_cat($c->path);
  if ($file !== FALSE) {
    file_put_contents(CACHE . $c->path . '_private.orig', $file);
    $xhtml = tidy_xhtml($c->path, $file);
    $xhtml = xhtmlFile_unescapeCustomTags($xhtml);
    file_put_contents(CACHE . $c->path . '_private.tidy', $xhtml);
  }
  $c->finished = TRUE;
}
//________________________________________

hook_subscribe('fileDependUpdated_xhtml',
               'xhtmlFile_fileDependUpdated_xhtml');
hook_subscribe('fileDependUpdated_xhtml-php',
               'xhtmlFile_fileDependUpdated_xhtml');

/** Callback to regenerate an .xhtml page if one of its data sources
    (i.e. files which it depends on) changes. Additionally, this is also
    called when the file itself changes, due to the fact that
    xhtmlFile_fileAdded_xhtml() calls depend_addToQueue() directly. */
function /*void*/ xhtmlFile_fileDependUpdated_xhtml(/*array('path'=>)*/ $arr) {
  $path = $arr['path'];

  /* Also delete non-multilingual versions - langTag_fileDependUpdated_xhtml
     has already deleted the language-specific variants. */
  anyDirOrFile_performAutoDel($path, 'xhtmlFile', FALSE, FALSE);
  // Delete autodel info, do not let it accumulate forever!
  anyDirOrFile_eraseAutoDel($path);

  /* Make dependency relations between .xhtml(-php) files transitive:
     Unconditionally state that this file (rather, the output generated from
     it) will change in response to a change to one of the files it depends
     on. */
  depend_fileChanged($path);

  /* This .xhtml file may actually have been removed at the same time as the
     template it depended on, so it may be gone now. */
  if (!file_exists(CACHE . $path . '_private.orig'))
    return;

  // Create data from tidied xhtml
  try {
    $info = array('path' => $path,
                  'dir' => dirname($path),
                  'up' => str_repeat('../', substr_count($path, '/') - 1),
                  'xmlcontent' => TRUE);
    $xhtml = xhtmlTemplate_getContentOrEval(CACHE . $path . '_private.tidy',
                                            $info, CACHE . dirname($path));
  } catch (Exception $e) {
    $xhtml = xhtmlTemplate_errDocument(
      $path,
      'Error while executing commit-time PHP code',
      htmlentities($e->getMessage())
        . '<br/>(Line number information is correct for the <em>tidied</em> '
        . 'file "' . htmlentities(basename("{$path}_private.tidy")) . ')',
      file_get_contents(CACHE . $path . '_private.tidy'));
  }

  // Maybe the file contents now include <? sections. Turn them into <?php
  $xhtml = preg_replace('/<\?(\s)/', '<?php$1', $xhtml);

  // Debugging aid: Write commit-time interpreted data to _private.afterphp
  //file_put_contents(CACHE . $path . '_private.afterphp', $xhtml);

  if (array_key_exists('finished', $info) && $info['finished'] === TRUE)
    return;

  xhtmlFile_filter($info, $xhtml);
}
//________________________________________

/** Given XHTML as input, transform it into XHTML output by running it
    through a series of registered filters.

    @param $arr Array containing path of file, repository-absolute
    (i.e. starts with a '/'), or NULL if HTML code does not belong to any
    file.
    @param $htmlSource String containing HTML markup
    @return DOMDocument object for transformed parse tree */
function /*DOMDocument*/ xhtmlFile_filter(/*array('path'=>string)*/ &$arr,
                                          /*string*/ $htmlSource) {
  $path = $arr['path'];

  /* Pre-process $htmlSource. PHP's DOM parser does not like PIs before any
     opening < ? xml decl in the document. However, having code at the very
     start of the file is frequently needed (e.g. to modify HTTP
     headers). Move any < ? xml to the top if present. Later on,
     xhtmlFile_write() will get the order right again. */
  $rePhp = '(?:<\?(?:php)?\s(?:[^?]|\?(?!>))*\?>\n?)+'; // 1 or more PHP PIs
  $reXmlDecl = '<\?xml\s+version="\d\.\d"(?:\s+encoding="[^"]+")?\?>';
  if (preg_match("%^($rePhp)($reXmlDecl)%", $htmlSource, $m) == 1) {
    $htmlSource = $m[2] . $m[1]
      . substr($htmlSource, strlen($m[1]) + strlen($m[2]));
  }

  $source = new DOMDocument();
  $source->substituteEntities = FALSE;
  $source->preserveWhiteSpace = TRUE;
  $source->resolveExternals = FALSE; // Or php will contact w3.org
  $ok = $source->loadXML($htmlSource);
  if ($ok === FALSE) {
    syslog(LOG_ERR, "xhtmlFile_filter: XHTML Parser failed for $path");
    $source->loadXML(xhtmlTemplate_errDocument(
        $path, 'Parsing of XHTML failed', htmlspecialchars($php_errormsg),
        $htmlSource));
  }
  $source->substituteEntities = FALSE;
  $result = $source->cloneNode(TRUE); // Deep copy of $source
  $arr['source'] = $source;
  $arr['result'] = $result;
  $arr['result']->substituteEntities = FALSE;

  // Did the .xhtml-php code register a callback?
  if (array_key_exists('callback', $arr)) {
    if (function_exists($arr['callback'])) {
      call_user_func_array($arr['callback'], array(&$arr));
      unset($arr['callback']);
    } else {
      syslog(LOG_ERR, 'xhtmlFile_filter: Callback function "'
             . $arr['callback'] . '" not found');
      $xhtml = xhtmlTemplate_errDocument(
        $path, 'Callback function does not exist',
        'Error: The callback function "' . htmlentities($arr['callback'])
          . '()" does not exist.', FALSE);
      $arr['result']->loadXML($xhtml);
    }
  }

  hook_call('xhtmlDom', $arr);

  // Debugging aid: Write post-xhtmlDom data to _private.afterphp
  //file_put_contents(CACHE . $path . '_private.afterxhtmldom', $arr['result']->saveXML());

  //return $arr['result'];
}
//______________________________________________________________________

/** Write $content into cache at path $path. Actually creates several objects
    for Apache MultiViews (variant html/xhtml and variant
    compressed/non-compressed.
    @param $path Website-absolute path of file
    @param $content The (X)HTML data to write
    @param $fileExt Further MultiViews file extension(s) for the created
    cache entry. Pass e.g. "de" or "de.utf8".
    @param $label For anyDirOrFile_autoDel(), the label to attach to the
    generated files. In practice, this will be "xhtmlFile" or "langTag".
    @param $xmlContent Pass TRUE (which is also the default) if you are sure
    that $content is valid XML. In this case, symlinks named .xhtml and
    .xht-php will be created, which will cause the content to be served as
    application/xhtml+xml to some browsers. Pass FALSE to prevent creation of
    the symlinks, i.e. always to serve content as text/html. FALSE is useful
    because Firefox will only display an error message if a
    application/xhtml+xml document turns out to be invalid XML.
*/
function /*void*/ xhtmlFile_write(/*string*/ $path, /*string*/ $content,
    /*string*/ $fileExt = '', /*string*/ $label = 'xhtmlFile',
    /*boolean*/ $xmlContent = TRUE) {
  if (!empty($fileExt)) $fileExt = ".$fileExt";

  /* If PHP code present, write .php files. Note: In the case that an .xhtml
    file contained < ? php sections, these have already been escaped as
    &lt;?php at this point. However, an .xhtml file can have a .template-php
    which inserts PHP code. */
  if (strpos($content, '<?php ') !== FALSE) {
    // LET PHP CODE EXECUTE!
    xhtmlFile_writePhp($path, $content, $fileExt, $label, $xmlContent);
    return;
  }

  $f = CACHE . $path;
  $basename = basename($path);
  //syslog(LOG_DEBUG,"xhtmlFile_write: writing $f$fileExt.xhtml");
  anyDirOrFile_autoDel($path, "$path$fileExt.html", $label);
  file_put_contents("$f$fileExt.html", $content);
  if ($xmlContent) {
    /* Hard or symbolic link is better than another file because then Apache
       will issue identical "ETag:" headers => more efficient caching. */
    //@unlink("$f$fileExt.xhtml");
    anyDirOrFile_autoDel($path, "$path$fileExt.xhtml", $label);
    symlink("$basename$fileExt.html", "$f$fileExt.xhtml");
    //link("$f$fileExt", "$f.html$fileExt");
  }

  $gzip = gzencode($content, 9/*max compression*/);
  anyDirOrFile_autoDel($path, "$path$fileExt.gz.html", $label);
  file_put_contents("$f$fileExt.gz.html", $gzip);
  if ($xmlContent) {
    //@unlink("$f$fileExt.gz.xhtml");
    anyDirOrFile_autoDel($path, "$path$fileExt.gz.xhtml", $label);
    symlink("$basename$fileExt.gz.html", "$f$fileExt.gz.xhtml");
  }
}
//________________________________________

/* Private helper function for xhtmlFile_write() - write PHP executable
   data */
function /*void*/ xhtmlFile_writePhp(/*string*/ $path, /*string*/ $content,
    /*string*/ $fileExt = '', /*string*/ $label = 'xhtmlFile',
    /*boolean*/ $xmlContent = TRUE) {
  $f = CACHE . $path;
  $basename = basename($path);

  /* Due to a restriction of the PHP DOM parser, it is not possible to
     represent a file where PHP processing instructions appear before the
     opening "< ? xml" of an XHTML file. (Actually, the XML standard does not
     allow this, so the parser is not to blame.) Quite ugly hack: Manually
     move the "< ? xml" after any PI sections that immediately follow it. */
  $reXmlDecl = '<\?xml\s+version="\d\.\d"(?:\s+encoding="[^"]+")?\?>\s*';
  $rePhp = '(?:<\?(?:php)?\s(?:[^?]|\?(?!>))*\?>\n?)+'; // 1 or more PHP PIs
  if (preg_match("%^($reXmlDecl)($rePhp)%", $content, $m) == 1) {
    //syslog(LOG_DEBUG,"xhtmlFile_write: PHP at start, offset "
    //       . strlen($m[1]) . ", len " . strlen($m[2]));
    $content = $m[2] . $m[1] // swap < ? xml and < ? php
      . substr($content, strlen($m[1]) + strlen($m[2]));
  }

  $arr = xhtmlFile_writeConfigPhp($path);

  // File-specific code
  $fileCode = '<?php ' . str_replace("\n", ' ', $arr['filecode']) . '?>';
  //syslog(LOG_DEBUG,"xhtmlFile_writePhp: writing $f$fileExt.html-php");
  anyDirOrFile_autoDel($path, "$path$fileExt.html-php", $label);
  file_put_contents("$f$fileExt.html-php", $fileCode . $content);
  if ($xmlContent) {
    anyDirOrFile_autoDel($path, "$path$fileExt.xht-php", $label);
    symlink("$basename$fileExt.html-php", "$f$fileExt.xht-php");
  }
}
//____________________

/** Create .config_private.php with global PHP code, and return file-specific
    code for the given $path in $arr['filecode']. Calls the requestTimePhp
    hook. */
function /*array(string=>string)*/ xhtmlFile_writeConfigPhp(/*string*/$path) {
  /* Add PHP code at start. With the initial code below, when the script is
     executed as the .xht-php file, it sends a "Content-Type:
     application/xhtml+xml" header. If, however, it is called via the
     .html-php symlink, it sends "Content-Type: text/html"  */
  $up = str_repeat('../', substr_count($path, '/') - 1);
  $arr = array('path' => $path,
               'up' => $up,
               'filecode' => "/* destrictor " . DESTRICTOR_VERSION . " */ require_once('$up.config_private.php'); ",
               'globalcode' =>
"/* xhtmlFile.php */
if (substr(\$_SERVER['SCRIPT_FILENAME'], -8) == '.xht-php')
  header('Content-Type: application/xhtml+xml; charset=utf-8');
else
  header('Content-Type: text/html; charset=utf-8');\n\n");
  hook_call('requestTimePhp', $arr);

  // Global code
  static $globalCode = FALSE;
  if ($globalCode == FALSE) {
    $globalCode = $arr['globalcode'];
    file_put_contents(CACHE . '/.config_private.php',
                      "<?php\n\n" . $globalCode . '?>');
  } else if ($globalCode != $arr['globalcode']) {
    syslog(LOG_WARN, 'xhtmlFile_writePhp: requestTimePhp \'globalcode\' '
           . 'changed for this file, but is being ignored');
  }
  return $arr;
}
//________________________________________

/** Assuming that xhtmlFile_write($path,$content,$fileExtExisting) has been
    called previously, now create a symlink to the files with
    $fileExtExisting, but instead of $fileExtExisting, use the extension
    $fileExtNew. For example, langTag.php uses this to create symlinks to
    "de" (German) files from names like "de-de" (German in Germany). Symlinks
    will only be created if the symlinked-to files actually exist. */
function /*void*/ xhtmlFile_link(/*string*/ $path,
    /*string*/ $fileExtExisting,
    /*string*/ $fileExtNew, /*string*/ $label = 'xhtmlFile') {
  $f = CACHE . $path;
  $basename = basename($path);
  foreach (array('xhtml', 'html', 'gz.xhtml', 'gz.html',
                 'xht-php', 'html-php') as $contentExt) {
    if (!file_exists("$f.$fileExtExisting.$contentExt")) continue;
    anyDirOrFile_autoDel($path, "$path.$fileExtNew.$contentExt", $label);
    symlink("$basename.$fileExtExisting.$contentExt",
                   "$f.$fileExtNew.$contentExt");
  }
}
//______________________________________________________________________

/** Subscriber of xhtmlDom hook with lowest priority. All this function does
    is to create a string from the DOMDocument and to write it to disc. This
    is not called for all files - when language tags are present in the
    document, the langTag code will save its own files and prevent this hook
    from being called. */
function /*void*/ xhtmlFile_xhtmlDom(&$arr) {
  $path = $arr['path'];
  dom_ripXhtmlNS($arr['result']);
  $xhtmlFinal = $arr['result']->saveXML();
  xhtmlFile_write($path, $xhtmlFinal, '', 'xhtmlFile',
                  $arr['xmlcontent'] !== FALSE);
  $arr['finished'] = TRUE; // Do not process further subscribers, we are last
}
//______________________________________________________________________

/** If the tag &lt;foo&gt; has been registered with tidy, replace any
    occurrances of &amp;lt;foo&amp;gt; (maybe with attributes) with the
    unescaped tag. You can <tt>define(ESCAPED_TAGS, FALSE);</tt> in your
    config.php to prevent this. */
function /*string*/ xhtmlFile_unescapeCustomTags(/*string*/ $xhtml) {
  if (defined('ESCAPED_TAGS') && ESCAPED_TAGS === FALSE) return $xhtml;
  static $searchRe = NULL;
  static $attrRe = '[a-zA-Z]+=("[^"]*"|\'[^\']*\')'; // XML attribute
  if ($searchRe == NULL) {
    $tagNames = tidy_tagNames();
    $tagRe = implode('|', $tagNames);
    $searchRe = "%&lt;(/($tagRe)\\s*|($tagRe)(\\s+$attrRe)*\\s*/?\\s*)&gt;%";
  }
  //syslog(LOG_DEBUG,"xhtmlFile_unescapeCustomTags: $searchRe");
  return preg_replace($searchRe, '<\1>', $xhtml);
}
//______________________________________________________________________

/** Mostly for debugging: Serialize the DOMNode $node as a string */
function /*string*/ xhtmlFile_saveXML(DOMNode $node) {
  if ($node instanceof DOMElement) {
    // FIXME: Output attributes
    $ret = '<' . $node->tagName . '>';
    $children = $node->childNodes;
    for ($i = 0; $i < $children->length; ++$i)
      $ret .= xhtmlFile_saveXML($children->item($i));
    $ret .= '</' . $node->tagName . '>';
    return $ret;
  } else if ($node instanceof DOMCharacterData) { // and DOMText
    return $node->data;
  } else if ($node instanceof DOMAttr) {
    return '[DOMAttr ' . $node->name . ' unsupported]';
  } else if ($node instanceof DOMComment) {
    return '<!-- ' . $node->data . ' -->';
  } else if ($node instanceof DOMDocument) {
    // NB we do not output an initial xml decl or doctype decl
    return xhtmlFile_saveXML(documentElement);
  } else if ($node instanceof DOMDocumentType) {
    return '[DOMDocumentType unsupported]';
  } else if ($node instanceof DOMEntity) {
    return '[DOMEntity unsupported]';
  } else if ($node instanceof DOMEntityReference) {
    return '[DOMEntityReference unsupported]';
  } else if ($node instanceof DOMNotation) {
    return '[DOMNotation unsupported]';
  } else if ($node instanceof DOMProcessingInstruction) {
    return '<?' . $node->target . $node->data . '?>';
  } else {
    return '[Unknown: ' . $node->nodeName . ']';
  }
}

?>