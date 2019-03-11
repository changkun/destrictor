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

/** @file This file provides a powerful templating engine, based on PHP's DOM
    functionality. The basic idea is that the input document is a valid XHTML
    document. Certain parts of that document, in particular the contents of
    the &lt;body&gt; tag, are copied into certain places of the template
    document, which have been marked - in the case of the &lt;body&gt; tag,
    with the string "@body@". */

/** @fileext .template, .template-php

    Template file, i.e. a file with the page structure for navigation,
    header, footer areas etc., but without the page content. Whenever an
    .xhtml file is added or changed, the template code tries to locate a
    .template file for the .xhtml file. This is done by first looking for
    $path.template-php, then $path.template, then index.template-php and
    index.template in the same dir, then index.template(-php) in each parent
    dir, going upward toward the top of the site. If no index template is
    found in the top-level dir, a default template is used.

    If the file that is found is a .template file, its data is parsed
    directly. If it is a .template-php file, it is treated as X(HT)ML with
    embedded PHP, and is executed before the result is parsed.

    Note: In .xhtml-php files, you can set $info['notemplate']=TRUE to
    prevent that xhtmlTemplate applies the template data to the document.

    In contrast to <a href="$fileext.xhtml">.xhtml(-php)</a> files, templates
    are directly passed to the PHP XML parser, they are not given to HTML
    tidy to correct mistakes. Thus, the data in the .template file (or the
    data produced by the .template-php) must be well-formed XML.

    Various parts of the input document are copied to the template: If the
    template document contains strings of the form "@sometag@", then any
    content in the input document which is enclosed in
    &lt;sometag&gt;&lt;/sometag&gt; tags replaces the "@sometag@".

    If no template file is found, the following built-in default template is
    used:<pre>
&lt;?xml version="1.0" encoding="utf-8"?&gt;
&lt;!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"&gt;
&lt;html xmlns="http://www.w3.org/1999/xhtml"&gt;
&lt;head&gt;
  &lt;meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/&gt;
  &lt;meta http-equiv="Content-Script-Type" content="text/javascript"/&gt;
  &lt;title&gt;@title@ - destrictor&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;' . $defaultTemplateContent . '
@body@
&lt;hr/&gt;&lt;p&gt;Destrictor default page template&lt;/p&gt;&lt;/body&gt;
&lt;/html&gt;
    </pre>

    In general, your template will at least use @title@ and @body@, but it is
    also possible for you to define "custom tags" by using something like
    @sidebar@. (FIXME: The last point won't work because tidy will give an
    error at the &lt;sidebar&gt; tags in the input document.)

    After the general substitutions above, this function also performs a
    number of special-case modification: If any &lt;script&gt;, &lt;style&gt;
    and &lt;link&gt; elements are present inside the input document's
    &lt;head&gt; element, these elements are copied inside the output
    document's &lt;head&gt; element.  &lt;meta&gt; elements are not copied
    over at present. Finally, a number of attributes of the source document,
    including style="" and onload="", are copied over. See
    navmenuTag_copyAttributes() for the complete list.  Any &lt;?&nbsp;?&gt;
    PHP code sections found inside &lt;head&gt; are also copied over, but
    their location may change slightly in the output document - to be safe,
    put your PHP code at the very start of the document.

    For .template-php files, the data is executed with eval(), see
    xhtmlTemplate_getTemplateData() and xhtmlTemplate_getContentOrEval(). The
    $info array passed to the eval()ed code contains:

    - 'templatePath' => CACHE-absolute path of .template-php file (without
    _private.orig extensions)

    - 'path' => CACHE-absolute path of the .xhtml file for which the
    template is executed

    - 'dir' => CACHE-absolute path of the directory containing 'path'.

    - 'up' => A string consisting of an appropriate number (>=0) of "../"
    elements, suitable for going up from 'path' to the top of CACHE.

    <h3>Setting a callback function: $info['callback']</h3>

    You can also create an entry like $info['callback']='myDomFunction' - the
    value is the name of a function which will be called immediately after
    the new XHTML document (created from the input .xhtml and the template
    data) has successfully been parsed. The function will be called as
    myDomFunction($info), i.e. with the same $info array that was previously
    given to the .template-php code. However, this time there are additional
    entries: $info['source'] is the DOMDocument for the original .xhtml file,
    it must not be modified. $info['result'] is the DOMDocument for the
    combined template and .xhtml data, it can be modified further and will be
    output as files in CACHE in the end.

    $info will later become the array argument for the xhtmlDom hook. If you
    want, you can add further array entries for your own purposes and pick
    them up again later.

    Before the code is executed, the current directory is changed to the
    directory in CACHE which contains the input document (NB not the dir
    which contains the .template-php file).

    <h3 id="dom-code">DOM Transformations With ".template-php" Files</h3>

    In addition to being able to output arbitrary markup, .template-php files
    can be made to operate on the DOM tree representation of a document
    <em>after all content has been copied into it</em>. For this, the PHP
    code in the .template-php must declare a function and register it as a
    callback as described above. The following example code checks whether an
    attribute with an id of "special" exists (e.g. &lt;a id="special"&gt;)
    and appends a string to its contents:

<pre>
&lt;?php
$info['callback'] = 'org_example_domCallback';
if ($firstTime) {
  function org_example_domCallback(&$info) {
    $document = $info['result']; // The DOMDocument for the current file
    $path = $info['path']; // The CACHE-absolute path of the current file
    &nbsp;
    // OUR DOM CODE: Look whether id="special" is present
    $elem = getElementById($document, 'special');
    if ($elem === NULL) return; // No element with that id found
    // Found an element. Append a new child with text content
    $newNode = $document-&gt;createTextNode(' Really special!');
    $elem-&gt;appendChild($newNode);
  }
}
?>
</pre>

    For your own template, you should replace any occurrences of
    "org_example" above with a unique string - I recommend you start it with
    the reversed name of an Internet site you own (e.g. "example.org" =>
    "org_example"). Additionally, it is important to use unique function
    names in different template files, otherwise you will get fatal PHP
    errors if two conflicting templates are loaded at the same time.

    The "if ($firstTime)" around the function definition ensures that even if
    the template code is used for the generation of more than one page (which
    means it is executed more than once), the code inside it is only executed
    the first time. An alternative way of achieving this is to put the
    once-only code into a separate .php file and to use require_once() to
    load it.

    The code above does not use the DOMDocument->getElementById() function
    because that function does not work. A special-purpose global
    getElementById() function is instead provided by destrictor. (The problem
    with DOMDocument->getElementById() is that DOMDocument->validate() would
    have to be called to make it work, but that function actually makes
    Internet connections to download DTDs, which would affect performance and
    make things fail if w3.org is not reachable.)

    While debugging code, it may be useful to use syslog(): Unfortunately,
    destrictor is unable to create an HTML page with an error message if your
    code causes a fatal PHP error. Examples for fatal errors are: 1)
    attempting to call nonexistent functions, 2) calling $elem->appendChild()
    when $elem is NULL.

    <h3 id="dom-code-late">DOM Transformations Of the Final Output XHTML</h3>

    The above example has one drawback: The callback function is called
    immediately after the .xhtml(-php) input file has been parsed,
    i.e. before the template has been applied to it and before special tags
    like &lt;include/&gt; have been substituted with XHTML code.

    To execute your DOM code later during the transformation process, the
    following code is needed. Exactly when your function
    "org_example_xhtmlDom()" is called depends on the last argument to the
    hook_subscribe() call. A higher value results in a higher priority,
    i.e. the function is called earlier. For example, a value of 95 will call
    it immediately after the template has been applied, and a value of -85
    will call it almost last, immediately before multi-language pages are
    split and written to disk.

    This code is a bit more complex than the one above, due to the fact that
    it is designed to work even if a website uses different templates for
    different pages.

    <pre>
    $info['org_example'] = TRUE;
    global $org_example_subscribed;
    if (!isset($org_example_subscribed)) {
      $org_example_subscribed = TRUE;
      // Low priority, run late
      hook_subscribe('xhtmlDom', 'org_example_xhtmlDom', -80);
      function org_example_xhtmlDom(&$info) {
        // Only change DOM tree if this template is used for the current page
        if (!array_key_exists('org_example', $info)) return;
        // OUR DOM CODE HERE
      }
    }
    </pre>

    When this template code is executed, it sets an $info array entry. Much
    later, the registered function org_example_xhtmlDom() will be called with
    this same $info array as its argument, and the presence of the entry will
    indicate to it that the "org_example" template indeed applies to this
    page. This check is necessary because org_example_xhtmlDom() will be
    called for <em>all</em> pages regardless of which template is applied to
    them. A single SVN commit can result in the regeneration of multiple
    pages due to dependencies, so the function can still be registered if
    another page with a different template is regenerated after the page of
    our "org_example" template.

    A global variable $org_example_subscribed is used to check whether the
    callback function has already been defined and registered. An "<tt>if
    ($firstTime)</tt>" could be used instead, as in the first example above,
    but unfortunately, $firstTime may not always work as expected with
    complicated template setups.
*/

hook_subscribe('xhtmlDom', 'xhtmlTemplate_xhtmlDom', 100); // Run earlier

// function htmlent(/*string*/ $str) {
//   $r = '';
//   for ($i = 0; $i < strlen($str); ++$i) {
//     $c = ord($str{$i});
//     if ($c >= 127 || $c == 38 /*&*/ || $c == 60 /*<*/ || $c == 62 /*>*/)
//       $r .= '&#' . $c . ';';
//     else
//       $r .= $str{$i};
//   }
//   return $r;
// }

$xhtmlTemplate_errorStyle = 'background-color: #faa; color: #000; border: 2px solid #f00;';

/** This is run early in the DOM tree transformation process. It takes the
    input document and copies various parts of it to a copy of the template
    document for that input. Subsequently, the new template+content becomes
    the DOM document which is passed on to further subscribers of the
    "xhtmlDom" hook.

    This function adds an entry $arr['templatePath'] to the $arr array. Its
    value is the site-absolute path of the template file. */
function /*void*/ xhtmlTemplate_xhtmlDom(&$arr) {
  if (array_key_exists('notemplate', $arr) === TRUE
      && $arr['notemplate'] === TRUE)
    return;

  //syslog(LOG_DEBUG, "EEEEEE1: ".$arr['result']);
  $input = $arr['result'];
  $path = $arr['path'];
  $arr['dir'] = dirname($path);

  // Load template document
  /*if (array_key_exists('callback', $arr))*/ unset($arr['callback']);
  $templateXhtml = xhtmlTemplate_getTemplateData($arr);
  // Debugging aid: Write interpreted data to _private.afterphp
  //file_put_contents(CACHE . $arr['templatePath'] . '_private.afterphp', $xhtml);

  $doc = new DOMDocument();
  $doc->substituteEntities = FALSE;
  $doc->preserveWhiteSpace = TRUE;
  $doc->resolveExternals = FALSE; // Or php will contact w3.org
  $r = @$doc->loadXML($templateXhtml);
  if ($r === FALSE)
    $doc->loadXML(xhtmlTemplate_errDocument($path,
      'Parsing of template file as XHTML failed',
      htmlspecialchars($php_errormsg), $templateXhtml));

  // Make copy of template
  //$doc = $doc->cloneNode(TRUE);
  // Perform general template substitutions
  xhtmlTemplate_recurseThroughTemplate($doc->documentElement, $input);

  /* Modify $doc's <head>, adding any <script>, <style>, <link> and PHP code
     from $input's <head> */
  $elems = $input->getElementsByTagName('head');
  foreach ($elems as $inputHead) {
    $elems = $doc->getElementsByTagName('head');
    foreach ($elems as $docHead) {
      // The following will execute at most once. $docHead/$inputHead set up
      foreach ($inputHead->childNodes as $inputChild) {
        if ($inputChild instanceof DOMProcessingInstruction) {
          $docHead->appendChild($doc->importNode($inputChild, TRUE));
          continue;
        }
        if (!$inputChild instanceof DOMElement) continue;
        if ($inputChild->tagName != 'script'
            && $inputChild->tagName != 'style'
            && $inputChild->tagName != 'link') continue;
        // Append $inputChild to contents of $docHead
        $docHead->appendChild($doc->importNode($inputChild, TRUE));
      }
      break;
    }
    break;
  }

  /* If $input begins with any "< ? php" sections, copy them
     over. Unfortunately, the PHP DOM parser will *always* prepend a "< ?
     xml" processing instruction before the very first php section. This is
     bad because scripts may want to modify headers, enable output buffering
     or similar before the first HTML code is output. For this reason, a
     small hack in xhtmlFile_write() will swap any "< ? xml" followed
     immediately by >=1 "<? php" sections. */
  $inputNodes = $input->childNodes;
  $insertBeforeNode = $doc->firstChild;
  // Insert document's PHP code *after* template's PHP code
  while ($insertBeforeNode instanceof DOMProcessingInstruction
         && $insertBeforeNode->nextSibling !== NULL)
    $insertBeforeNode = $insertBeforeNode->nextSibling;
  // Copy over document's PHP code
  for ($i = 0; $i < $inputNodes->length; ++$i) {
    $phpCode = $inputNodes->item($i);
    //syslog(LOG_DEBUG, 'NODE '.$path.' '. $phpCode->nodeName);
    if ($phpCode instanceof DOMElement) break;
    if ($phpCode instanceof DOMProcessingInstruction
        && ($phpCode->target == 'php' || $phpCode->target == 'xml')) {
      $doc->insertBefore($doc->importNode($phpCode),
                         $insertBeforeNode);
    }
  }
  // If $input ends with any "< ? php" sections, copy them over
  $node = $input->lastChild;
  while ($node !== NULL && !($node instanceof DOMElement))
    $node = $node->previousSibling;
  if ($node !== NULL) {
    $node = $node->nextSibling;
    while ($node !== NULL) {
      if ($node instanceof DOMProcessingInstruction
          && $node->target == 'php') {
        $doc->appendChild($doc->importNode($node));
      }
      $node = $node->nextSibling;
    }
  }

  $elems = $input->getElementsByTagName('body');
  foreach ($elems as $inputBody) {
    $elems = $doc->getElementsByTagName('body');
    foreach ($elems as $docBody) {
      // Copy things like onload="..." from input document's body tag
      navmenuTag_copyAttributes($inputBody, $docBody);
      break;
    }
    break;
  }

  /* $doc->validate() would be necessary here to make
     DOMDocument->getElementById() work. Unfortunately, we cannot really do
     this, because validate() will actually load any DTDs from the Internet,
     which means that 1) things will break if w3.org cannot be reached, and
     2) things will get very slow because there is no caching and the DTD
     will be fetched again for each validate() call. Argh, this is stupid.
     Workaround: Use our own getElementById() function. */
  $arr['result'] = $doc;

  // Did the .template-php code register a callback?
  if (array_key_exists('callback', $arr)) {
    if (function_exists($arr['callback'])) {
      call_user_func_array($arr['callback'], array(&$arr));
      unset($arr['callback']);
    } else {
      syslog(LOG_ERR, 'xhtmlTemplate_xhtmlDom: Callback function "'
             . $arr['callback'] . '" not found');
      $xhtml = xhtmlTemplate_errDocument(
        $path, 'Callback function does not exist',
        'Error: The callback function "' . htmlentities($arr['callback'])
          . '()" registered by the template does not exist.', FALSE);
      $arr['result']->loadXML($xhtml);
    }
  }
}
//______________________________________________________________________

/* When a .template file is committed, actually put a .template_private.orig
   file into CACHE, to prevent public access to the file contents. The .orig
   extension is not registered with Apache, so the file will not be
   considered during MultiViews content negotiation. */
hook_subscribe('fileAdded_template', 'xhtmlTemplate_fileAdded_template');
hook_subscribe('fileUpdated_template', 'xhtmlTemplate_fileUpdated_template');

hook_subscribe('fileAdded_template-php', 'xhtmlTemplate_fileAdded_template');
hook_subscribe('fileUpdated_template-php', 'xhtmlTemplate_fileUpdated_template');

/* Called by hook. */
function /*void*/ xhtmlTemplate_fileAdded_template(SvnChange $c) {
  // Two dependency changes: The dir listing changes and the new file "changes"
  depend_fileChanged(dirname($c->path) . '/');
  depend_fileChanged($c->path);
  $f = CACHE . $c->path . '_private.orig';
  svn_catToFile($c->path, $f);
  $c->finished = TRUE;
}
/* Called by hook. */
function /*void*/ xhtmlTemplate_fileUpdated_template(SvnChange $c) {
  depend_fileChanged($c->path);
  $f = CACHE . $c->path . '_private.orig';
  svn_catToFile($c->path, $f);
  $c->finished = TRUE;
}
//______________________________________________________________________

/* Private helper function: Load contents of template for document at the
   path given via $arr['path']. This is done by first looking for
   $path.template-php, then $path.template, then index.template-php and
   index.template in the same dir, then index.template(-php) in the parent
   dir(s). If no index template is found in the top-level dir, a default
   template is used.

   Note: While users actually commit "foo.template" to SVN, our hook for
   template files uses the name "foo.template_private.orig", which
   means that the file is not publically accessible.

   This function adds an entry $arr['templatePath'] to the $arr array. Its
   value is the site-absolute path of the template file.

   The called code of a template file may add a $arr['callback'] entry, which
   is the name of a function to call after the template data has been
   parsed and a DOM tree is present. */
function /*string*/ xhtmlTemplate_getTemplateData(&$arr) {
  $templatePath = FALSE;
  $path = $arr['path'];

  // For a file called foo.xhtml:
  // first look for foo.xhtml.template-php
  if (depend_dependencyExists($path, "$path.template-php")) {
    $templatePath = "$path.template-php";
    // then look for foo.xhtml.template:
  } else if (depend_dependencyExists($path, "$path.template")) {
    $templatePath = "$path.template";
  } else {
    // then scan upwards for the directory which contains foo.xhtml,
    $dir = dirname($path);
    while (TRUE) { // and for each dir on the way upwards
      if ($dir == '/') $dir = '';
      // first look for index.template-php in the dir
      if (depend_dependencyExists($path, "$dir/index.template-php")) {
        $templatePath = "$dir/index.template-php";
        break;
        // then look for index.template in the dir:
      } else if (depend_dependencyExists($path, "$dir/index.template")) {
        $templatePath = "$dir/index.template";
        break;
      }
      if ($dir == '') break;
      $dir = dirname($dir);
    }
  }

  // If no .template-php or .template found, return default 
  if ($templatePath === FALSE)
    return xhtmlTemplate_defaultTemplate();

  $arr['templatePath'] = $templatePath;
  $arr['up'] = str_repeat('../', substr_count($path, '/') - 1);
  try {
    // Load/execute .template(-php)
    $templateData = xhtmlTemplate_getContentOrEval(
      CACHE . "${templatePath}_private.orig", $arr,
      CACHE . dirname($path));

    // Maybe the file contents now include <? sections. Turn them into <?php
    $templateData = preg_replace('/<\?(\s)/', '<?php$1', $templateData);

    /* Pre-process $templateData. PHP's DOM parser does not like PIs before
       any opening < ? xml decl in the document. However, having code at the
       very start of the file is frequently needed (e.g. to modify HTTP
       headers). Move any < ? xml to the top if present. Later on,
       xhtmlFile_write() will get the order right again. */
    $rePhp = '(?:<\?(?:php)?\s(?:[^?]|\?(?!>))*\?>\n?)+'; // 1 or more PHP PIs
    $reXmlDecl = '<\?xml\s+version="\d\.\d"(?:\s+encoding="[^"]+")?\?>';
    if (preg_match("%^($rePhp)($reXmlDecl)%", $templateData, $m) == 1) {
      $templateData = $m[2] . $m[1]
        . substr($templateData, strlen($m[1]) + strlen($m[2]));
    }

  } catch (Exception $e) {
    // Create default template with added error message
    $htmlErr = htmlentities($e->getMessage());
    $extraMsg = '';
    // For XML templates short_open_tag=On may be necessary in the CLI php.ini
    if (ini_get('short_open_tag')) {
      $fileData = @file_get_contents(CACHE . $templatePath .'_private.orig');
      if ($fileData !== FALSE && strpos($fileData, '<?xml') !== FALSE)
        $extraMsg = '<br/>(Try setting short_open_tag=Off in your CLI php.ini'
          . ', ' . htmlentities(get_cfg_var('cfg_file_path')) . ')';
    }
    global $xhtmlTemplate_errorStyle;
    return xhtmlTemplate_defaultTemplate(
        "<h1 style=\"$xhtmlTemplate_errorStyle\">Error while parsing "
      . "template</h1><p>The template could not be executed: "
      . "$htmlErr.$extraMsg</p><hr/>");
  }
  return $templateData;
}
//______________________________________________________________________

class XhtmlTemplate_getContentOrEvalException extends Exception {
  public function __construct($msg) { parent::__construct($msg); }
}

$xhtmlTemplate_getContentOrEval_canary = FALSE;

/** To be called from within the PHP code in a .template-php file: Output the
    supplied string, but disable the security feature that any &lt;?php
    processing instructions inside it are escaped. Instead, the PIs will
    appear in the final output exactly as in $str. */
function /*string*/ enablePhp(/*string*/ $str) {
  global $xhtmlTemplate_getContentOrEval_canary;
  return str_replace('<?', '<?'.$xhtmlTemplate_getContentOrEval_canary, $str);
}

/** Potentially dangerous function - use with care, it will execute
    user-supplied PHP code! This works like file_get_contents, it just reads
    and returns the data of the supplied file. However, there is one
    exception: If the supplied filename ends with '-php_private.SOMEEXT', the
    data is passed to eval() before it is returned. SOMEEXT can be any file
    extension which consists of the characters a to z.

    The supplied file must exist and must not be a symbolic link pointing
    outside CACHE. In case of symlinks, both the symlink name and the
    symlinked-to file's name must end with the special suffix. An exception
    is thrown in case of errors.

    When the PHP code is executed, the $filename and $info variables are
    available to it, and $info can be modified. Furthermore, the boolean
    variable $firstTime is set to TRUE if the code is executed for the first
    time by this instance of the PHP interpreter. Additionally, due to the
    use of eval(), all global destrictor functions can be called. As the code
    may be executed more than once, function definitions and other
    global/once-only operations should be put inside a "if ($firstTime) { }"
    statement by the called code.

    Additional safety precaution: In the case where the file data is not
    executed, the beginnings of processing instructions (PIs) are escaped,
    i.e. &lt;? becomes &amp;lt;?. Since the ends of the PIs are not escaped,
    this will often lead to parse errors later on. As a special exception,
    &lt;?xml is not affected. The reason for doing this is that the data
    which is returned might eventually get copied into another document where
    those PIs suddenly make sense and are interpreted. For example, if
    index.template could contain PIs, they would be copied into the final
    output XHTML of any file.xhtml-php in the same directory, and would thus
    become executable when the XHTML data is put into CACHE with a .php
    extension. That way, someone without the privileges to commit .php and
    -php files would still be able to have code of his choice run on the
    server, by committing an index.template somewhere.

    Security improvement: -php files can contain both commit-time PHP code
    ("&lt;?php") and request-time code ("&lt;?"). Commit-time code written by
    users will often combine data from several other documents into its
    output document. These other documents may not have been committed by
    users who are authorized to commit -php files (i.e. run PHP code of their
    choosing), so they should not be able to simply insert "&lt;?" code into
    their document, and then wait for it to be copied somewhere else by some
    -xhtml-php file, at which point their code will become executable as
    request-time PHP. Our solution: Request-time PHP code will only be output
    for those "&lt;?" processing instructions which are in the input data
    _before_ the commit-time PHP is called. If the commit-time PHP simply
    does an "echo '&lt;?...?&gt;'", this will not work, the "&lt;?" will be
    turned into "&amp;lt;?". The only way to output the processing
    instruction is to explicitly use a special function,
    enablePhp('&lt;?...?&gt;').

    Sometimes, you may want to execute a parent "index.template-php" from a
    child "index.template-php", for example because all pages in a certain
    subdirectory differ slightly from other parts of the site and you do not
    want to duplicate most of the template content. You must also use
    enablePhp() in this case, as follows:
    <pre>
// Page also depends on master template, not just the child template
depend_addDependency($info['path'], '/index.template-php');
// Output master template
ob_start();
require(CACHE . '/index.template-php_private.orig');
echo enablePhp(ob_get_clean());
    </pre>

    @param $filename Path of the file to return content of, or to execute
    @param $info An array with anything which is passed to the called code,
    May be modified by the called code.
    @param $chDir If non-null, directory to change to before executing code. */
function /*string*/ xhtmlTemplate_getContentOrEval(
    /*string*/ $filename, /*array*/ &$info, /*string*/ $chdir = FALSE) {
  // Security checks
  $realname = @realpath($filename);
  if (!isset($php_errormsg))
    $php_errormsg = "Could not resolve path \"$filename\"";
  if ($realname === FALSE) throw new Exception($php_errormsg);
  /* We do not want this to fail in the case where any part of the pathname
     of CACHE itself is a symbolic link, so also use realpath() on CACHE. */
  $cache = @realpath(CACHE);
  if ($cache === FALSE) throw new Exception($php_errormsg);
  if (!is_file($filename))
    throw new Exception("File \"$filename\" not found");
  if (substr($realname, 0, strlen($cache) + 1) != ($cache . '/'))
    throw new Exception("\"$filename\" is located outside CACHE");

  // Load data
  $fileData = file_get_contents($realname);
  $r = preg_match('/-php_private\.[a-z]+$/', $filename, $matches,
                  PREG_OFFSET_CAPTURE);
  if ($r !== 1
      || substr($realname, -strlen($matches[0][0])) != $matches[0][0]) {
    // No executable suffix, so just return data
    /* But first disable processing instructions in case the data is
       eventually copied into other data which *is* executed. The following
       is the simplest way of substituting &lt;? for <? except as part of
       <?xml, without turning &lt;?xml in the input into <?xml */
    $chunks = explode('<?xml', $fileData);
    $fileData = ''; // Save memory
    foreach ($chunks as $i => $c)
      $chunks[$i] = str_replace('<?', '&lt;?', $c);
    return implode('<?xml', $chunks);
  }

  $errorPath = substr($realname, strlen($cache),
                      $matches[0][1] + 4 - strlen($cache));

  // PHP data is executed!

  /* We prevent the commit-time PHP from outputting "{?" directly by adding a
     special, random, unguessable string immediately after the "{?". Only
     instances of "{?" which include the correct string will not be escaped
     as "&lt;?" later */
  global $xhtmlTemplate_getContentOrEval_canary;
  do {
    $xhtmlTemplate_getContentOrEval_canary = mt_rand();
  } while (strpos($fileData,
                  $xhtmlTemplate_getContentOrEval_canary) !== FALSE);
  $fileData = preg_replace('/<\?(?!php\s)/i',
      "<?$xhtmlTemplate_getContentOrEval_canary$1", $fileData);

  ob_start();
  $firstTime = xhtmlTemplate_firstTime($filename);
  //syslog(LOG_DEBUG, "chdir(".dirname($filename).")");
  if ($chdir !== FALSE) chdir($chdir);
  $r = '';
  /* No idea why this should be so, but in case of _parse_ errors in the
     eval()ed code, our error handler will not be called. The only way to get
     to the error message/line is to set display_errors=TRUE and then to
     parse the output if eval() returns FALSE. */
  ini_set('display_errors', TRUE);
  set_error_handler('xhtmlTemplate_getContentOrEval_errhandler');
  $exc = NULL;
  try {
    $r = eval('?>' . $fileData); // The Evil Eval (TM)
  } catch (XhtmlTemplate_getContentOrEvalException $e) {
    /* Just re-throw our own error handler's exception. Ugly: If the string
       starts with 'line ' only, prepend path. */
    if (substr($e->getMessage(), 0, 5) == 'line ')
      $exc = new Exception("\"$errorPath\", " . $e->getMessage());
    else
      $exc = $e;
  } catch (Exception $e) {
    // Called code should probably have handled this exception itself
    $exc = new Exception("Exception not caught by \"$errorPath\": "
                         . $e->getMessage());
  }
  $data = ob_get_contents();
  ob_end_clean();
  ini_set('display_errors', FALSE);
  restore_error_handler();
  if ($exc !== NULL) {
    $xhtmlTemplate_getContentOrEval_canary = FALSE;
    throw $exc;
  }
  if ($r !== FALSE) {
    $data = str_replace('<?', '&lt;?', $data);
// static $ww = 0;
// file_put_contents('/tmp/ww'.(++$ww),
//                   $xhtmlTemplate_getContentOrEval_canary . $data);
    $data = str_replace('&lt;?' . $xhtmlTemplate_getContentOrEval_canary,
                        '<?', $data);
    $xhtmlTemplate_getContentOrEval_canary = FALSE;
    return $data;
  }

  /* eval() returned FALSE, either because of an error which did not result
     in our error handler being called, or because the eval()ed code did an
     explicit "return FALSE;". If $data contains a standard parse error
     message, it is output. Otherwise, the first 1k of output is included in
     the error message. */
  // Example string to match:
  // Parse error: syntax error, unexpected '}' in /home/user/somedir/destrictor/modules/xhtmlTemplate.php(334) : eval()'d code on line 2
  $shortData = str_replace(MODULES . '/', '', substr($data, 0, 1024));
  // \nParse error: syntax error, unexpected '[', expecting ')' in xhtmlTemplate.php(409) : eval()'d code on line 7\n
  if (preg_match('%Parse error: (.*) in (.+/)?xhtmlTemplate\.php\([0-9]+\) : eval\(\)\'d code on line ([0-9]+)%', $data, $matches) == 1) {
    throw new Exception("\"$errorPath\", line " . $matches[3]
                        . ': ' . $matches[1]);
  }
  throw new Exception('eval() returned FALSE after outputting: ' . $shortData);
}
//____________________

/* Private helper function: Output errors during template execution */
function xhtmlTemplate_getContentOrEval_errhandler(
    $errno, $errstr, $errfile, $errline) {
  syslog(LOG_ERR, "xhtmlTemplate_getContentOrEval_errhandler: $errfile LINE $errline: $errstr");
  $errfile = str_replace(MODULES . '/', '', $errfile);
  $errstr = str_replace(MODULES . '/', '', $errstr);
  if (preg_match("/eval\(\)'d code/", $errfile) == 1)
    throw new XhtmlTemplate_getContentOrEvalException(
      "line $errline: $errstr");
  else
    throw new XhtmlTemplate_getContentOrEvalException(
      "$errfile, line $errline: $errstr");
//   $errfile = str_replace(MODULES . '/', '', $errfile);
//   $errfile = str_replace("eval()'d code", 'template', $errfile);
//   echo "$errfile, line $errline: $errstr";
}
//______________________________________________________________________

/* Helper function: Return the default template, optionally with additional
   content (typically an error message) in its body. */
function /*string*/ xhtmlTemplate_defaultTemplate(
    $defaultTemplateContent = '') {
  return '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>
  <meta http-equiv="Content-Script-Type" content="text/javascript"/>
  <meta http-equiv="Content-Style-Type" content="text/css"/>
  <title>@title@ - destrictor</title>
</head>
<body>' . $defaultTemplateContent . '
@body@
<hr/><p>Destrictor default page template</p></body>
</html>
';
#<hr/><p>Last modified: <lastmodified format="%Y-%m-%d"/>
#| Destrictor default page template</p></body>
}
//______________________________________________________________________

/* Helper function: Return TRUE if this is the first time that filename was
   supplied to the function. Uses a static array to keep track of supplied
   args. */
function /*bool*/ xhtmlTemplate_firstTime($filename) {
  static $seen = NULL;
  if ($seen === NULL) $seen = array();
  $firstTime = !array_key_exists($filename, $seen);
  $seen[$filename] = TRUE;
  return $firstTime;
}
//______________________________________________________________________

/** Search recursively through $node for DOMText nodes which contain the
    string "@sometag@". Any content in $input which is inside
    &lt;sometag&gt;&lt;/sometag&gt; tags is then inserted instead of the
    "@sometag@" string. */
function /*void*/ xhtmlTemplate_recurseThroughTemplate(DOMNode $node,
                                                       DOMDocument $input) {
  /* For some reason, we must iterate backward over the children while making
     changes. Otherwise, the DOM implementation begins to behave weirdly,
     e.g. "@sometag@" nodes re-appear in the tree even though they were
     removed from it. */
  $childNr = $node->childNodes->length;
  while (--$childNr >= 0) {
    $child = $node->childNodes->item($childNr);
    if ($child instanceof DOMElement) {
      //syslog(LOG_DEBUG, 'recurseThroughTemplate: ' . $child->tagName);
      xhtmlTemplate_recurseThroughTemplate($child, $input);
      //syslog(LOG_DEBUG, 'recurseThroughTemplate: /' . $child->tagName);
      continue;
    }
    if (!($child instanceof DOMText)) continue;

    if (preg_match('/@[a-z][a-z0-9-]*@/i', $child->data,
                   $m, PREG_OFFSET_CAPTURE) == 0) continue;
    $match = &$m[0][0]; // "@sometag@"
    $off = &$m[0][1]; // Offset of "@sometag@" in $child->data
    $sometag = substr($match, 1, -1); // Name of tag
    //syslog(LOG_DEBUG, "recurseThroughTemplate: $match $off {".$child->data."}\n");
    if ($off > 0) {
      // Split off stuff before the first "@" into new DOMText
      $child->splitText($off);
      $child = $node->childNodes->item(++$childNr);
      $off = 0;
    }
    if ($off == 0 && strlen($match) < strlen($child->data)) {
      // Split off stuff after second "@" into new DOMText
      $child->splitText(strlen($match));
      $child = $node->childNodes->item($childNr);
    }
    /* At this point, $child _only_ contains the string "@sometag@". Any text
       which preceded/followed it has been split off into sibling DOMText
       nodes. */
    xhtmlTemplate_insertTagContentBefore($child, $sometag, $input);
    //xhtmlTemplate_dumpXML($input);
    $node->removeChild($child);
    //xhtmlTemplate_dumpXML($input);
  }
}
//______________________________________________________________________

/** Find all tags named $sometag in the $input document, then import and
    insert them before $node into $node's document. */
function /*void*/ xhtmlTemplate_insertTagContentBefore(
    DOMNode $node, /*string*/ $sometag, DOMDocument $input) {
  $nodePar = $node->parentNode;
  $nodeDoc = $node->ownerDocument;
  $inelems = $input->getElementsByTagName($sometag);
  foreach ($inelems as $i) { // For each <sometag> that was found
    foreach ($i->childNodes as $ichild) // For each child of <sometag>
      // Make a deep copy of the child and insert it before $node
      $nodePar->insertBefore($nodeDoc->importNode($ichild, TRUE), $node);
  }
}
//______________________________________________________________________

/** Create an "error message" tag, a &lt;span class="destrictor-error"&gt;
    element with the supplied string as its text content. A style definition
    is also added to make the message stand out. Returns a DOMElement which
    contains the span.

    It may be argued that displaying error messages "inline" in the generated
    content is not ideal; they may be missed when the page author gives the
    newly uploaded page a short (or no...) glance, especially if the page is
    fairly long. It would be nicer if the problematic line number in the
    input document could be output on a page which only contains the error
    message. Unfortunately, we do not have line number info available. Also,
    if it were available, it would be line number info about the page content
    AFTER being piped through "HTML tidy". */
function /*DOMElement*/ xhtmlTemplate_errNode(
    DOMDocument $document, /*string*/ $errMsg) {
  global $xhtmlTemplate_errorStyle;
  $ret = $document->createElement('span', $errMsg);
  $ret->setAttribute('class', 'destrictor-error');
  $ret->setAttribute('style', $xhtmlTemplate_errorStyle);
  return $ret;
}
//______________________________________________________________________

/** Create an error message document
    @param $path CACHE-absolute path of .xhtml(-php) document for which to
    create error, without appended _private.orig or similar extension.
    @param $title Title of document, may contain markup
    @param $errMsg Error message, may contain markup
    @param $privateBody Document to include, FALSE if none. The data is
    considered private and put in a _private.err file which is not displayed
    to all site visitors. Thus, it is OK to put code listings etc. in this
    data.
    @param $bodyPreLines If TRUE, $body is escaped, put into a &lt;pre&gt; and
    line numbers are added. If FALSE, $body is inserted exactly as supplied.
    @param $extraHead Additional markup to be put inside &lt;head&gt; */
function /*string*/ xhtmlTemplate_errDocument(
    /*string*/ $path,
    /*string*/ $title, /*string*/ $errMsg, /*string*/ $privateBody,
    /*bool*/ $bodyPreLines = TRUE, /*string*/ $extraHead = '',
    /*string*/ $extraIframeHead = '') {
  global $xhtmlTemplate_errorStyle;
  if ($privateBody) {
    $extraHead .= '
<script type="text/javascript">
/*<![CDATA[*/
function onLoad() {
  /* With Konq, the correct height is not known the moment this is called, it
     is only set later on. So this does not work 100% on Konq. With my tests,
     the height that is given back is too large by a factor of about 2. */
  var iframe = document.getElementById("destrictor-iframe");
  var child = null;
  if (document.frames && document.frames["destrictor-iframe"])
    child = document.frames["destrictor-iframe"].document; // MSIE/Konq/Op8
  else
    child = iframe.contentDocument; // FFox1.5
  var h = child.documentElement.scrollHeight;
  // MSIE needed about +4 for me, otherwise there was a vert. scroll bar
  if (h > iframe.getAttribute("height"))
    iframe.height = h + 16;
}
/*]]>*/
</script>
';
  }
  /* NB using iso-8859-1 here for a reason: In any case, this is a blind
     guess about the input encoding, but utf-8 would cause PHP's parser to
     complain about any character sequences which are not valid utf-8,
     whereas any byte string is valid iso-8859-1. Not ideal. :-| */
  $ret = '<?xml version="1.0" encoding="iso-8859-1"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>' . $title . '</title>' . $extraHead . '
</head>
<body' . ($privateBody ? ' onload="javascript:onLoad();"' : '') . '>
<h1 style="' . $xhtmlTemplate_errorStyle . '">' . $title . '</h1>
<p>' . $errMsg . '</p>';
  if ($privateBody) {
    $ret .= '<p style="font-size: small;">A detailed error message follows '
      . 'below. Access to it may be restricted.</p>';
    $base = basename($path) . '_private.err';
    $ret .= "<iframe id=\"destrictor-iframe\" name=\"destrictor-iframe\" src=\"$base\" width=\"100%\" height=\"600\">\n<a href=\"$base\">Detailed error message</a>\n</iframe>\n";
  }
  $ret .= '</body></html>';
  //____________________

  // Create the detailed error page which will be displayed in the iframe
  if ($privateBody) {
    if ($bodyPreLines) {
      $lines = explode("\n", $privateBody);
      $privateBody = "\n<pre>";
      foreach ($lines as $i => $l)
        $privateBody .= sprintf("%5d: %s\n", $i + 1, htmlspecialchars($l));
      $privateBody .= '</pre>';
    }
    $privateBody = '<?xml version="1.0" encoding="iso-8859-1"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>' . $extraIframeHead . '
<title>' . $title . '</title>' . $extraIframeHead . '
<meta name="Robots" content="noindex,nofollow" />
</head>
<body>' . $privateBody . '
<p>' . $errMsg . '</p></body></html>';
    file_put_contents(CACHE . dirname($path) . '/' . $base, $privateBody);
  }
  //file_put_contents('/tmp/io', $ret);
  return $ret;
}
//______________________________________________________________________

/** Convenience function: Make the supplied element have the given CSS
    class. If the element does not yet have a 'class' attribute, it is
    created (class="$className"). Otherwise, the new class is appended to the
    class value (class="foo bar $className"). There is no check for duplicate
    classes, and $className is assumed to be a valid class name. */
function /*void*/ xhtmlTemplate_addElementClass(DOMElement $elem,
                                                /*string*/ $className) {
  $prevClass = $elem->getAttribute('class');
  if ($prevClass != '') $prevClass .= ' ';
  $elem->setAttribute('class', $prevClass . $className);
}

?>