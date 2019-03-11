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

    "phpdoc"-style generation of documentation from the Destrictor sources.

    This file is not used directly by the code, you are expected to call it
    from a PHP file in your webspace.
*/


/** Output HTML (not XHTML, probably not valid HTML either) code with
    documentation. The returned string should be enclosed in a HTML document,
    it does not contain a body tag.

    @param $key The page to return. An empty string will return the main
    index page, and keys like "func.documentation", "file.includeTag" or
    "tag.lastmodified" will return sub-pages.
    @param $linkPrefix String to prepend to all href="" attributes. For
    example, you may want to pass "index.php?id=" to make the links point
    back to your index.php file. */
function /*string*/ documentation_page(/*string*/ $key = '',
    /*string*/ $linkPrefix = '') {
  $x = documentation_create();
  $linkPrefix = htmlspecialchars($linkPrefix);

  // Index page
  if ($key == '') {
    // Create lists of file extensions (fileext), tags (tag) and files (file)
    ksort($x);
    $fileexts = ''; $tags = ''; $files = '';
    foreach ($x as $id => $link) {
      if (substr($id, 0, 9) == '>fileext.') {
        $id = htmlspecialchars(substr($id, 9));
        $fileexts .= "  <li><a href=\"${linkPrefix}fileext.$id\">.$id</a></li>\n";
      } else if (substr($id, 0, 5) == '>tag.') {
        $id = htmlspecialchars(substr($id, 5));
        $tags .= "  <li><a href=\"${linkPrefix}tag.$id\">&lt;$id/&gt;</a></li>\n";
      } else if (substr($id, 0, 6) == '>file.') {
        $id = htmlspecialchars(substr($id, 6));
        $files .= "  <li><a href=\"${linkPrefix}file.$id\">$id</a></li>\n";
      }
    }
    $html = "<h1>Destrictor Documentation</h1>\n\n"
      . "<p>File extensions with special meaning:</p>\n"
      . "<ul class=\"compact\">\n$fileexts</ul>\n\n"
      . "<p>Special tags in .xhtml(-php) and .template(-php) files:</p>\n"
      . "<ul class=\"compact\">\n$tags</ul>\n\n"
      . "<p>Destrictor source code files:</p>\n"
      . "<ul class=\"compact\">\n$files</ul>\n\n";
    return $html;
  }
  //____________________

  // Normal HTML page
  if (array_key_exists(".$key", $x)) {
    if (array_key_exists("#$key", $x) && count($x["#$key"]) > 0) {
      // Output TOC, floated to right
      $s = "<p class=\"destrictor-doc destrictor-toc\" "
        . "style=\"padding: 1em 0em 1em 1em; width: 50%; float: right;\">"
        . "On this page:";
      foreach ($x["#$key"] as $id => $label)
        $s .= "<br/>\n<a href=\"#$id\">$label</a>";
      $s .= "</p>\n\n";
    }
    return $s . str_replace('href="$', 'href="' . $linkPrefix, $x[".$key"]);
  }
  //____________________

  // ".func.dlog" not present, but ">func.dlog" is - output link
  if (array_key_exists(">$key", $x)) {
    $linkx = htmlspecialchars($x[">$key"]);
    return "<h1>Page Not Found</h1>\n\n<p>See here instead:<br/>"
      . "<a href=\"$linkPrefix$linkx\">$linkx</a></p>\n\n";
  }

  return "<h1>Page Not Found</h1>\n\n";
}
//______________________________________________________________________

/** Return the array which contains the entire documentation. Parse through
    the CORE and MODULES directories and find all php files and parse them,
    creating the documentation from scratch.

    The array contains entries of type ".$someid" => "HTML code", i.e. the
    key of entries which are meant to be served to the user is prefixed with
    a "." character.

    There are also entries named "#$someid" => array($id=>$label). If
    ".$someid" is the HTML code, then "#$someid" contains a list of ID values
    defined in that HTML, together with a label for each ID value. For
    example, for HTML like "&lt;a id="foo"&gt;bar&lt;/a&gt;", the array may
    contain an entry "foo"=>"bar". The "#" entries can be used to create a
    table of contents for the ".$someid" page.

    Finally, there are many entries named "&gt;$someid". They provide a link
    destination for $someid. For example, the key "&gt;func.dlog" points to a
    string "file.log.php#func.dlog". For tags/fileexts, the entries are more
    boring, e.g. "tag.toc"=>"tag.toc". */
function /*array*/ documentation_create() {
  $x = array('filesize' => array(/*string leafname => int size*/),
             'filemod' => array(/*string leafname => int mtime*/),
             );
  $filesize =& $x['filesize'];
  $filemod =& $x['filemod'];

  $filenames = array_merge(glob(CORE . '/*.php'), glob(MODULES . '/*.php'));
  foreach ($filenames as $f) {
    $statInfo = stat($f);
    $leafname = basename($f);
    $leafnamex = htmlspecialchars($leafname);
    $filesize[$leafname] = $statInfo['size'];
    $filemod[$leafname] = $statInfo['mtime'];
    $x[">file.$leafname"] = "file.$leafname";
    $c = file_get_contents($f);
    $x[".file.$leafname"] = "<h1 id=\"file.$leafnamex\">File $leafnamex"
      . "</h1>\n\n";
    $x["#file.$leafname"] = array(); /* IDs and ID labels for this file */
    if (preg_match('%/\*\*\s*@file\s+((?U).*\S)\s*\*/%s', $c, $m) == 1) {
      // Found @file
      $x[".file.$leafname"] .= documentation_paragraphs($m[1]);
    }
    // Scan for / * * through the whole document
    $off = 0;
    // 1 Match: @someletters
    $atWordRe = '(?:@([a-z]+)\s*)?';
    // 0 Matches: Normal /* style comment
    $commentRe = '((?Us)/\*.*\*/)';
    // 3 Matches: A PHP function declaration
    $fncRe = 'function\s+(?:' . $commentRe . '\s+)?'
      . '([a-zA-Z][a-zA-Z0-9_]*)\s*\(((?Us).*)\)\s*{';
    // A / * * comment, optionally followed by a function decl
    $docRe = '%/\*\*\s*' . $atWordRe . '((?Us).*\S)\s*\*/(\s*' . $fncRe.')?%';
    while (preg_match($docRe, $c, $m, PREG_OFFSET_CAPTURE, $off) == 1) {
      //echo "<p>Found in $leafnamex:</p><pre>".htmlspecialchars($m[0][0]).'</pre>';
      $atWord = $m[1][0];
      if ($atWord == 'tag')
        documentation_createTag($x, $leafname, $m[2][0]);
      else if ($atWord == 'fileext')
        documentation_createFileext($x, $leafname, $m[2][0]);
      else if ($m[3][0] != '')
        documentation_createFunction($x, $leafname, $atWord . $m[2][0],
                                     $m[4][0], $m[5][0], $m[6][0]);
      // TODO: record subscribers and callers of hooks
      // TODO: add link to: xhtmlFile.php; hook xhtmlDom; x_fnc();
      $off = $m[0][1] + strlen($m[0][0]);
    }
  }

  // Create extra links for function names
  documentation_funcLinks($x);

  return $x;
}
//______________________________________________________________________

/** Given the contents of a phpdoc comment block, return HTML
    code. Primarily, this inserts &lt;p&gt; tag between paragraphs, and turns
    "- " at the start of paragraphs into &lt;li&gt; entries. */
function /*string*/ documentation_paragraphs(/*string*/ $s) {
  $s = "<p>$s</p>";
  // Add <p> whenever there is an empty line in the input
  // Unfixed problem: This will also happen within <pre>
  $s = preg_replace('%\s*\n(\s*\n)+\s*%', "</p>\n<p>", $s);
  // If a <p> starts with "- ", assume it is a bullet, create a list
  $s = preg_replace('%<p>- (.*)</p>%Us', '<ul><li>$1</li></ul>', $s);
  // Pull together adjacent <ul> lists
  $s = str_replace("</li></ul>\n<ul><li>", "</li>\n<li>", $s);
  // @param becomes a definition list: <dl>, <dt>, <dd>
  $s = preg_replace('%\s*@param\s(\S+)\s((?Us).*)\s*(?=</p>|\s*@(param|return))%',
                    "<dl>\n<dt>\$1</dt>\n<dd>\$2</dd>\n</dl>\n", $s);
  $s = preg_replace('%(\s*)@return\s+((?Us).*)\s*(?=</p>|\s*@(param|return))%',
                    "\$1<dl>\n<dt><i>Return value</i></dt>\n<dd>\$2</dd>\n</dl>\n", $s);
  $s = str_replace("</dd>\n</dl>\n<dl>\n<dt>", "</dd>\n<dt>", $s);
  /* Remove the <p> that surrounds <pre> paragraphs. Also remove whitespace
     before the closing </pre> */
  while (preg_match('%(^|\n)<p>((?U)<pre>.*)\s*</pre></p>(\n|$)%s', $s, $m,
                    PREG_OFFSET_CAPTURE) == 1) {
    $s = substr($s, 0, $m[0][1]) . "\n" . $m[2][0] . "</pre>\n"
      . substr($s, $m[0][1] + strlen($m[0][0]));
  }
  // We often have PHP array mappings "a => b" in the code - escape the >
  // Similarly, we often have $someObject->doSomething()
  $s = preg_replace('%(=|[^-]-)>%', '$1&gt;', $s);
  return $s;
}
//______________________________________________________________________

// @tag found
function documentation_createTag(/*array()*/ &$x, /*string*/ $leafname,
                                 /*string*/ $s) {
  $n = preg_match('/^([a-zA-Z][a-zA-Z0-9-_]*)\s*(.*)/s', $s, $m);
  if ($n == 0) return;
  $tagname = $m[1];
  $leafnamex = htmlspecialchars($leafname);
  $x[".tag.$tagname"] = "<p style=\"padding: 1em 0em 1em 1em; "
    . "width: 25%; float: right;\">File: "
    . "<a href=\"\$file.$leafnamex\">$leafnamex</a></p>"
    . "<h1 id=\"tag.$tagname\">&lt;$tagname/&gt; Tag</h1>\n\n"
    . documentation_paragraphs($m[2]);
  $x[">tag.$tagname"] = "tag.$tagname";
  $x[".file.$leafname"] .= "<p>This file provides the "
    . "<a href=\"\$tag.$tagname\">&lt;$tagname/&gt;</a> tag.</p>";
}
//______________________________________________________________________

// @fileext found
function documentation_createFileext(/*array()*/ &$x, /*string*/ $leafname,
                                     /*string*/ $s) {
  $n = preg_match('/^([a-zA-Z0-9._-]+)([a-zA-Z0-9._, -]*)\s*(.*)/s',
                  $s, $m);
  if ($n == 0) return;
  $ext = $m[1] . $m[2]; // Can contain space , .
  $extn = $m[1]; // For links and id=""
  if ($extn{0} == '.') $extn = substr($extn, 1);
  $leafnamex = htmlspecialchars($leafname);
  $x[".fileext.$extn"] = "<p class=\"destrictor-doc destrictor-extlink\" "
    . "style=\"padding: 1em 0em 1em 1em; width: 25%; float: right;\">File: "
    . "<a href=\"\$file.$leafnamex\">$leafnamex</a></p>"
    . "<h1 id=\"fileext.$extn\">$ext File Extension</h1>\n\n"
    . documentation_paragraphs($m[3]);
  $x[">fileext.$extn"] = "fileext.$extn";
  $x[".file.$leafname"] .= "<p>This file provides the "
    . "<a href=\"\$fileext.$extn\">$ext</a> file extension.</p>";
}
//______________________________________________________________________

// Function definition found in PHP code, and it's preceded with / * * comment
function documentation_createFunction(/*array()*/ &$x, /*string*/ $leafname,
    /*string*/ $phpDoc, /*string*/ $retType, /*string*/ $fncName,
    /*string*/ $args) {
  $argsx = htmlspecialchars($args);
  $argsx = str_replace('/*', '<i>', $argsx);
  $argsx = str_replace('*/', '</i>', $argsx);
  //$x[".file.$leafname"] .= "<h3 id=\"func.$fncName\">$fncName()</h3>\n<p>";
  $x[".file.$leafname"] .= "\n\n<hr/>\n<p id=\"func.$fncName\">";
  if ($retType != '')
    $x[".file.$leafname"] .= '<i>' . htmlspecialchars(substr($retType, 2, -2))
      . '</i> ';
  $x[".file.$leafname"] .= "<strong>$fncName</strong>($argsx);</p>\n"
    . documentation_paragraphs($phpDoc);

  // Create TOC entry
  $x["#file.$leafname"]["func.$fncName"] = "$fncName()";

  // Create link destination entry
  $x[">func.$fncName"] = "file.$leafname#func.$fncName";
}
//______________________________________________________________________

// After docs have been generated, add an anchor around "functionName()"
function documentation_funcLinks(/*array()*/ &$x) {
  foreach ($x as $id => $html) {
    if ($id{0} != '.') continue; // Only work on HTML content
    $n = preg_match_all('/\b(\w+)\(\)/', $html, $m, PREG_OFFSET_CAPTURE);
    if ($n == 0) continue;
    $x[$id] = '';
    $off = 0;
    for ($i = 0; $i < $n; ++$i) {
      // Copy part before match
      $x[$id] .= substr($html, $off, $m[0][$i][1] - $off);
      $off = $m[0][$i][1] + strlen($m[0][$i][0]); // Advance $off after ")"
      $funcId = '>func.' . $m[1][$i][0]; // ">func.dlog" for a "dlog()" match
      if (array_key_exists($funcId, $x)) {
        $x[$id] .= '<a class="destrictor-func" href="$'
          . htmlspecialchars($x[$funcId]) . '">' . $m[0][$i][0] . '</a>';
      } else {
        $x[$id] .= $m[0][$i][0];
      }
    }
    //print_r($m);
    $x[$id] .= substr($html, $off); // Copy part after last match
  }
}

?>