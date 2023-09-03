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

/** @file An "include" mechanism for Destrictor-managed web pages. You can
    either include content statically (from another page in the SVN
    repository) or at request time (via a HTTP sub-request that is executed
    whenever the page is requested by a browser. */

/** @tag include

    Include part of another web page in the current page.  Remember to close
    this tag properly: Write &lt;include/&gt;, not &lt;include&gt;!

    When you use this tag, the resulting Destrictor-generated page will
    contain some PHP which fetches the given URL and outputs it instead of
    the <tt>&lt;include/&gt;</tt> tag. There are two quite different modes:
    You can either fetch data from any URL (rather inefficient as the data
    will be re-fetched for each request) or specify a relative/site-absolute
    URL.

    In either case, any relative links in the included data (usually href=""
    or src="" attribute values) are not changed in any way, so they may point
    to the wrong place. Use absolute or site-absolute ("http://..." or
    "/path") links.

    <h2>Including using a full "http://" URL</h2>

    In this mode, you can specify a full URL with a scheme of "http://",
    "https://" or "ftp://", together with strings which mark the beginning
    and end of the included area. Destrictor will then output PHP code which
    downloads and includes the URL again and again every time the page is
    requested.

    <strong>Security note:</strong> This means that your HTTP server will act
    as a HTTP client and can be made to download arbitrary URLs by users. If
    your HTTP server is granted privileges by other servers based on its IP
    address, this could be used to gain unauthorized access to pages on these
    other servers. There is also the danger that users put a high load on the
    server by requesting lots of large files to be included - however, this
    is limited somewhat by PHP's <tt>memory_limit</tt> setting, which is 8 MB
    by default.

    In this mode, the <tt>&lt;include/&gt;</tt> element takes the following
    parameters:

    <tt>src="http://example.org/path"</tt>: The URL of the page which should
    be included.

    <tt>start="string"</tt> or <tt>startafter="string"</tt>: Specifies the
    string that marks the start of the content which should be copied to the
    current Destrictor page. For <tt>start</tt>, the given string is part of
    the content that is copied. For <tt>startafter</tt>, the content to copy
    starts immediately after the string. If neither attribute is present,
    <tt>startafter="&amp;lt;body&amp;gt;"</tt> is assumed, i.e. copy the
    contents of the body tag, but not the tag itself. If the string contains
    "&lt;" or "&gt;" characters, you should escape them. It is an error to
    specify both attributes. The string is searched for from the beginning of
    the document. If the string is not found, an error message is displayed.

    <tt>end="string"</tt> or <tt>endbefore="string"</tt>: Analogous to
    <tt>start(after)</tt>, marks the end of the area to copy. If neither
    attribute is present, <tt>startafter="&amp;lt;/body&amp;gt;"</tt> is
    assumed. This string is searched for backwards from the end of the
    document.

    The run-time script may automatically perform a character set conversion
    of the included data.

    Note: An HTML comment is put into the final output page. It contains the
    URL that was specified in the src attribute.

    Note: If this tag is used on a page, Destrictor will never serve the page
    with the Content-Type "application/xhtml+xml". This is because browsers
    will just display an error message if an "application/xhtml+xml" page is
    not well-formed XML, and it is not known whether the included content is
    well-formed.

    <h2>Including using a relative/site-absolute URL</h2>

    In this mode, only the <tt>src</tt> attribute (and possibly
    <tt>delete</tt>, see further below) is allowed for the &lt;include&gt;
    element. <tt>src</tt> must be a relative URL (relative to the current
    page) or a site-absolute URL (i.e. a path from the top of the Destrictor
    site). Furthermore, it must end with a fragment ID (e.g. "#id"). Finally,
    only .xhtml and .xhtml-php files can be referenced.  Some examples:

    &lt;include src="../relative/path.xhtml#some-id"/&gt;<br>
    &lt;include src="/path/from/top/of/site.xhtml-php#some-id"/&gt;<br>
    &lt;include src="file.xhtml#some-id"/&gt;

    In addition to the tag in the includ<em>ing</em> page, an &lt;include
    id="some-id"&gt; element must also be present in the includ<em>ed</em>
    page.

    &lt;include id="..."&gt; elements can include content which contains further
    &lt;include&gt; elements.  An error is output if a loop of includes is
    generated this way. Something like <tt>src="#id"</tt> (i.e. only a
    fragment identifier) is possible to copy content to another part of the
    same page.

    If an .xhtml-php file is the source of the include, then any commit-time
    PHP code in that file is <em>not</em> re-executed whenever the include
    destination is regenerated. Any run-time PHP code in the included data is
    copied over to the current document.

    [Aside: Why does this mode not support the useful <tt>start</tt>
    etc. attributes? There are two reasons: First, the implementation of this
    feature requires that the included content is well-formed XML, which
    cannot be guaranteed with these attributes. Second (and more
    importantly), if users were allowed to include arbitrary other files,
    this could possibly lead to security problems: Some sites may have SVN
    configurations which make some parts of their content un<em>read</em>able
    by certain users. In this case, it must not be possible by default to
    include the unreadable content. Thus, in this mode no pages at all can be
    included by default. Instead, only pages which declare that they are
    inclusion sources (due to the fact that they contain an &lt;include
    id="..."&gt; element) can be included. It is assumed that the authors of
    the unreadable content will not be tricked into putting &lt;include&gt;
    tags into the data they commit...]

    <h2>Include tags specifying the source of a relative/site-absolute
    inclusion</h2>

    As described above, a non-empty &lt;include&gt; tag with an <tt>id</tt>
    attribute specifies the data that can be included by another page. An
    &lt;include&gt; element may be non-empty even though it has a
    <tt>src</tt> attribute - in this case, the content included via
    <tt>src</tt> will be inserted after the children of the element. One
    element can have both <tt>id</tt> and <tt>src</tt>, though this usually
    does not make sense. When such an element is used as an inclusion source,
    the <tt>src</tt> tag is ignored.

    Sometimes, it may be useful to <em>move</em> content from one page to
    another rather than copying it. For this reason, an &lt;include&gt;
    element can be told to delete its children when it is processed in the
    context of its own .xhtml page, by adding a <tt>delete="delete"</tt>
    attribute. (The actual value of the attribute does not matter, only its
    presence, but "delete" is recommended.)

    In all cases, the &lt;include/&gt; element itself is removed from the
    output document, only its children remain (unless <tt>delete</tt> is
    used).
*/

require_once(MODULES . '/tidy.php'); // Need this first

tidy_addTag('include', FALSE);

/* Execute this as the last thing before langTag (or xhtmlFile) writes the
   final file to CACHE. Just before that, we write a copy of the
   non-language-specific, no-commit-time-PHP document to CACHE. */
hook_subscribe('xhtmlDom', 'includeTag_xhtmlDom', -80);
//______________________________________________________________________

/** Called by hook, looks for &lt;include/&gt; elements. */
function /*void*/ includeTag_xhtmlDom(&$arr) {
  $document = $arr['result'];
  $path = $arr['path'];

  // Pass 1: Search for includes with ids. If any is found, save .include
  $tags = $document->getElementsByTagname('include');
  for ($i = $tags->length - 1; $i >= 0; --$i) {
    $tag = $tags->item($i);
    if (!$tag->hasAttribute('id')) continue;
    /* This file is an inclusion source. Write a {$path}_private.include file
       with the complete current state of the document. We subscribed to the
       xhtmlDom hook with low prio, so all other substitutions etc. should
       already have been performed. -- Note: It may have been possible here
       to use serialize() only for the include elements, but I'm a bit
       concerned that the serialized format may not be compatible with future
       versions of PHP or its libxml wrapper. */
    $document->save(CACHE . $path . '_private.include');
    break;
  }
  // Pass 2: Modify document
  includeTag_processDocument($arr, $arr['result']->documentElement, array(),
                             $path);
}
//______________________________________________________________________

/** Recurse through $node and look for include elements. If any are found,
    they are processed, i.e. replaced with the content they include. Inclusion
    loops are detected.
    @param $arr Info array as passed to xhtmlDom hook
    @param $node Element to recurse through
    @param $loopDetect Array which maps from included path to TRUE
    commit-time includes. Note that unlike $arr, the arg is copied whenever
    the function calls itself, so it only ever contains the entries for one
    path from the top of the include tree to a certain node.
    @param $path The path of the document which contains the include tag that
    is currently handled. This is initially $arr['path'], but will differ
    once the included data contains yet another include. */
function /*void*/ includeTag_processDocument(&$arr, DOMElement $node,
    /*array(string=>string)*/ $loopDetect, /*string*/ $path) {
  $children = $node->childNodes;
  for ($i = 0; $i < $children->length; ++$i) {
    $elem = $children->item($i);
    if (!($elem instanceof DOMElement)) continue;
    // Recurse first, whether it's an include or not
    includeTag_processDocument($arr, $elem, $loopDetect, $path);
    if ($elem->tagName != 'include') continue;

    dlog('includeTag', ": $path, child <" . $elem->tagName . ' src='
       . $elem->getAttribute('src') . ' id=' . $elem->getAttribute('id').'>');
    // <include> tag found
    /* Because we recursed above, no <include> element remains anywhere below
       $elem now */
    /* Distinguish the 3 different kinds of <include> elements: included:
       Usually has child nodes, has id attribute includer, request-time: src
       starts with http:// or similar, usually has one of start/startAfter
       and one of end/endBefore includer, commit-time: src does not start
       with scheme, must end with a hash. One element may be both includer
       and included. */
    $hasDeleteAttr = $elem->hasAttribute('delete');
    while ($elem->hasChildNodes()) {
      // Tag not empty - move child nodes outside unless "delete" attr present
      $firstChild = $elem->removeChild($elem->firstChild);
      if (!$hasDeleteAttr)
        $elem->parentNode->insertBefore($firstChild, $elem);
    }
    if ($elem->hasAttribute('src')) {
      // Other URL or file is included by this tag
      $src = $elem->getAttribute('src');
      if (!ctype_graph($src)) {
        $newNode = xhtmlTemplate_errNode($arr['result'],
          '<include/> tag: "src" attribute invalid');
      } else if (substr($src, 0, 7) == 'http://'
                 || substr($src, 0, 8) == 'https://'
                 || substr($src, 0, 6) == 'ftp://') {
        // Absolute URL - output PHP code
        $newNode = includeTag_requestTimeInclude($arr, $elem);
      } else {
        // Include with repository-relative or absolute path
        $newNode = includeTag_commitTimeInclude($arr, $elem, $loopDetect,
                                                $path);
      }
      if ($newNode !== NULL)
        $elem->parentNode->insertBefore($newNode, $elem);
    }
    // Delete the <include> itself, it is now empty
    $elem->parentNode->removeChild($elem);
  }
}
//______________________________________________________________________

/** Helper function for includeTag_processDocument(). The include uses an
    absolute src URL - output PHP code which downloads and includes the data
    whenever a user visits the page
    @return The PHP processing instruction (or an error message) to insert
    before $elem */
function /*NULL|DOMElement*/ includeTag_requestTimeInclude(
    /*array*/ &$arr, DOMElement $elem) {
  $document = $arr['result'];
  $hasEnd = $elem->hasAttribute('end');
  $hasEndAfter = $elem->hasAttribute('endafter');
  $newNode = NULL;
  if ($elem->hasAttribute('start') && $elem->hasAttribute('startafter')) {
    $newNode = xhtmlTemplate_errNode($document,
      '<include/> tag: "start" and "startafter" must not be used at the '
      . 'same time');
  } else if ($elem->hasAttribute('end') && $elem->hasAttribute('endbefore')) {
    $newNode = xhtmlTemplate_errNode($document,
      '<include/> tag: "end" and "endbefore" must not be used at the '
      . 'same time');
  } else {
    /* The included data may not be valid XML, so disable generation of
       .xhtml symlink - only output .html file in CACHE. */
    $arr['xmlcontent'] = FALSE;
    $src = $elem->getAttribute('src');
    $newNode = $document->createProcessingInstruction('php', 'includeTag('
      . includeTag_decodeArg($src) . ', '
      . includeTag_decodeArg($elem->getAttribute('start')) . ', '
      . includeTag_decodeArg($elem->getAttribute('startafter')) . ', '
      . includeTag_decodeArg($elem->getAttribute('end')) . ', '
      . includeTag_decodeArg($elem->getAttribute('endbefore')) . '); ');
  }
  return $newNode;
}
//______________________________________________________________________

/* Helper function: $arg is the string supplied in the start="..." and
   related attributes of the include element. This function replaces ' with
   \', which allows safe embedding into the PHP code output by the xhtmlDom
   code. Finally, the whole string is enclosed in ' before returning it.
   @param $arg The string to process */
function /*string*/ includeTag_decodeArg(/*string*/ $arg) {
  $arg = str_replace("\\", "\\\\", $arg);
  $arg = str_replace("'", "\\'", $arg);
  $arg = str_replace("<?", "<'.'?", $arg); // Necessary? Hm, better be safe...
  return "'$arg'";
}
//______________________________________________________________________

hook_subscribe('requestTimePhp', 'includeTag_requestTimePhp');

/*
  echo "<span style=\"color: #888;\">", htmlspecialchars($src), "</span>",
    "<span style=\"color: #c00;\">", htmlspecialchars($start), "</span>",
    "<span style=\"color: #c88;\">", htmlspecialchars($startAfter), "</span>",
    "<span style=\"color: #0c0;\">", htmlspecialchars($end), "</span>",
    "<span style=\"color: #8c8;\">", htmlspecialchars($endBefore), "</span>";
*/
/** Adds the PHP code for request-time includes. */
function /*void*/ includeTag_requestTimePhp(&$arr) {
  global $xhtmlTemplate_errorStyle;
  $arr['globalcode'] .= '/* includeTag.php */
function includeTag(/*string*/ $src,
                    /*string*/ $start, /*string*/ $startAfter,
                    /*string*/ $end, /*string*/ $endBefore) {
  // Fetch URL
  $fd = @fopen($src, "r");
  if ($fd === FALSE) {
    $srcx = htmlspecialchars($src);
    echo "<span style=\"' . $xhtmlTemplate_errorStyle
      . '\">Could not retrieve page: <a href=\"$srcx\">$srcx</a></span>";
    return;
  }

  // Try to find out charset of returned data
  $charset = FALSE;
  $metaData = stream_get_meta_data($fd);
  foreach ($metaData["wrapper_data"] as $h) {
    if (strtolower(substr($h, 0, 13)) != "content-type:") continue;
    foreach (explode(";", $h) as $param) {
      $param = trim($param);
      if (substr($param, 0, 8) != "charset=") continue;
      $charset = substr($param, 8);
      if ($charset[0] == "\"" && substr($charset, -1) == "\"")
        $charset = stripslashes(substr($charset, 1, -1));
      break;
    }
    break;
  }

  // Get page
  $doc = stream_get_contents($fd);
  fclose($fd);

  // Character set conversion
  if ($charset !== FALSE) {
    $docx = iconv($charset, "utf-8//TRANSLIT", $doc);
    if ($docx !== FALSE) $doc = $docx;
  }

  if ($start == "" && $startAfter == "") $startAfter = "<body>";
  if ($start != "") {
    $a = strpos($doc, $start);
    if ($a === FALSE) $a = 0;
  } else {
    $a = strpos($doc, $startAfter);
    if ($a === FALSE) $a = 0; else $a += strlen($startAfter);
  }

  if ($end == "" && $endBefore == "") $endBefore = "</body>";
  if ($end != "") {
    $b = strrpos($doc, $end);
    if ($b === FALSE) $b = strlen($doc); else $b += strlen($endBefore);
  } else {
    $b = strrpos($doc, $endBefore);
    if ($b === FALSE) $b = strlen($doc);
  }

  $srcm = str_replace("-", "%2d", $src);
  if ($charset !== FALSE) $charset = " - charset converted from \"$charset\"";
  echo "<!-- include $srcm$charset -->";
  echo substr($doc, $a, $b - $a);
  echo "<!-- /include $srcm -->";
}

';
}
//______________________________________________________________________

/** Helper function for includeTag_processDocument(). Relative (../foo) or
    site-absolute (/foo) URL - directly include the code on the page right
    now, and also declare the right dependency.

    @param $path repository-absolute path of the document in which the
    include tag occurs
    @return An error message to insert before $elem, or NULL if none */
function /*NULL|DOMElement*/ includeTag_commitTimeInclude(/*array*/ &$arr,
    DOMElement $elem, /*array*/ $loopDetect, /*string*/ $path) {
  $document = $arr['result'];
  $src = $elem->getAttribute('src');
  $n = strpos($src, '#');

  if ($n === FALSE)
    return xhtmlTemplate_errNode($document,
      '<include/> tag: Relative "src" URLs must end with "#some-id"');
  if ($n != strrpos($src, '#') || $n == strlen($src) - 1)
    return xhtmlTemplate_errNode($document,
      '<include/> tag: "src" attribute invalid');

  $id = substr($src, $n + 1);
  $srcPath = substr($src, 0, $n);
  if ($srcPath == '')
     $srcPath = $path;
  else if ($srcPath[0] != '/')
    $srcPath = dirname($path) . '/' . $srcPath;
  $srcPath = normalizePath($srcPath);
  if ($srcPath === FALSE)
    return xhtmlTemplate_errNode($document,
      '<include/> tag: Path in "src" attribute invalid');

  if (array_key_exists("$srcPath#$id", $loopDetect)) {
    // Error: A includes B and B includes A
    dlog('includeTag', ": Include loop at $srcPath#$id");
    return xhtmlTemplate_errNode($document,
                                 '<include/> tag: Detected loop of includes');
  }
  $loopDetect["$srcPath#$id"] = TRUE;

  depend_addDependency($arr['path'], $srcPath);

  /* Security: With error messages, take care not to leak info whether a
     certain file exists or not, or a certain include #id inside it. A
     malicious SVN user must not be able to use trial and error to find out
     whether e.g. a certain file exists in a part of the repository which is
     not readable for him. */
  $notFound = '<include/> tag: Source "' . $src . '" not found';
  if (!is_file(CACHE . $srcPath . '_private.include')) {
    dlog('includeTag', ": No {$srcPath}_private.include file");
    return xhtmlTemplate_errNode($document, $notFound);
  }
  // Parse source document
  $srcDoc = new DOMDocument();
  $srcDoc->substituteEntities = FALSE;
  $srcDoc->preserveWhiteSpace = TRUE;
  $srcDoc->resolveExternals = FALSE; // Or php will contact w3.org
  $ok = $srcDoc->load(CACHE . $srcPath . '_private.include');
  if ($ok === FALSE) {
    // Should never happen, as we created the file from a DOM tree earlier
    dlog('includeTag',
         ": XHTML Parser failed for {$srcPath}_private.include");
    return xhtmlTemplate_errNode($document, $notFound);
  }
  // Look for id="$id"
  $srcElem = getElementById($srcDoc, $id);
  /* Security: Tag with ID *must* be an include element, otherwise users
     could guess IDs and access documents to which they do not have SVN read
     access. */
  if ($srcElem === NULL || $srcElem->tagName != 'include') {
    dlog('includeTag', ": ID #$id is not an <include> tag");
    return xhtmlTemplate_errNode($document, $notFound);
  }

  // Inclusion source found! Copy its children from one document to another
  $srcElemImport = $document->importNode($srcElem, TRUE);
  /* Recurse through included data - only looks at $srcElemImport's children,
     not at $srcElemImport itself. $srcPath becomes the $path of the
     recursion. */
  includeTag_processDocument($arr, $srcElemImport, $loopDetect, $srcPath);
  // Add final data to our document
  while ($srcElemImport->hasChildNodes()) {
    $c = $srcElemImport->removeChild($srcElemImport->firstChild);
    $elem->parentNode->insertBefore($c, $elem);
  }
  return NULL;
}

?>