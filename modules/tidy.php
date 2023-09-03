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

/** @file Convert HTML (which can be full of errors) to valid XHTML using <a
    href="http://tidy.sourceforge.net/">HTML Tidy</a>. */

/* Private: Lists of tags, each int=>string, i.e. index to tag name
   (all-lowercase recommended) */
$tidy_InlineTags = array();
$tidy_EmptyTags = array();
$tidy_BlocklevelTags = array();
//$tidy_PreTags = array();

/** Add information about a new tag. Otherwise, if tidy doesn't know about a
    tag, it will give an error.
    @param $tagName Name of the tag, e.g. "foo" for &lt;foo/&gt; elements
    @param $isEmpty TRUE if the element must not contain any other tags/content
    @param $isInline TRUE if the element is inline (like e.g. &lt;span&gt;),
    FALSE if it is block-level (like e.g. &lt;div&gt;) */
function /*void*/ tidy_addTag(/*string*/ $tagName, /*boolean*/ $isEmpty = TRUE,
                              /*boolean*/ $isInline = TRUE,
                              /*boolean*/ $isBlocklevel = TRUE) {
  global $tidy_EmptyTags, $tidy_InlineTags, $tidy_BlocklevelTags;
  if ($isEmpty)
    $tidy_EmptyTags[] = $tagName;
  if ($isInline)
    $tidy_InlineTags[] = $tagName;
  if ($isBlocklevel)
    $tidy_BlocklevelTags[] = $tagName;
}
//______________________________________________________________________

/** Return the information that was added using tidy_addTag().
    @return An array of strings, each of which is a tag name like "foo". */
function /*array(string)*/ tidy_tagNames() {
  global $tidy_EmptyTags, $tidy_InlineTags, $tidy_BlocklevelTags;
  $ret = array_unique($tidy_InlineTags + $tidy_EmptyTags
                      + $tidy_BlocklevelTags);
  //syslog(LOG_DEBUG, "tidy_tagNames " . print_r($ret, TRUE));
  return $ret;
}
//______________________________________________________________________

/** Convert HTML (which can be full of errors) to valid XHTML.  Does not
    modify the input if there is fully compliant XHTML header at the start,
    e.g. something like the following:

    <pre>
&lt;?xml version="1.0"?&gt;
&lt;!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"&gt;
&lt;html xmlns="http://www.w3.org/1999/xhtml"&gt;
    </pre>

    The character encoding of the page is expected to be Windows 1252 (a
    superset of ISO-8859-1). If this is not acceptible, you can also use
    UTF-8 and indicate this by using the following meta header:<br>&lt;meta
    http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"
    /&gt; Alternatively, an XML processing instruction at the start will also
    cause UTF-8 to be used: &lt;?xml version="1.0" encoding="utf-8"?&gt;

    @param $path Site-absolute URL, only used for meaningful error
    messages. Pass '' if no path info available
    @param $htmlContent Input document
    @return Output XHTML */
function /*string*/ tidy_xhtml(/*string*/ $path, /*string*/ $htmlContent) {
  global $tidy_EmptyTags, $tidy_InlineTags, $tidy_BlocklevelTags;

  /* Do not modify the input if there is fully compliant XHTML at the start.
     Something very similar to this:
     Optionally, one or more  "< ?" or "< ? php" sections.
     Then:
<?xml version="1.0" encoding="..."?>
     (the encoding attribute is optional),
     followed by (either:
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
     or the following:
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
     ) and then followed by:
<html xmlns="http://www.w3.org/1999/xhtml">
     there may be other attributes present after xmlns */
  $rePhp = '(?:<\?(?:php)?\s(?:[^?]|\?(?!>))*\?>\n?)*'; // 0 or more PHP PIs
  $reDoctype = '<\?xml\s+version\s*=\s*"\d\.\d+"(\s+encoding\s*=\s*"[^"]+")?\s*\?>\s*<!DOCTYPE\s+html\s+PUBLIC\s+"-//W3C//DTD XHTML \d\.\d (Strict|Transitional)//EN"\s+"http://www\.w3\.org/[^"\n]+\.dtd">\s*<html\s+xmlns\s*=\s*"http://www\.w3\.org/[^"\n]+"[>\s]';
  if (preg_match("%^$rePhp$reDoctype%", $htmlContent) == 1)
    return $htmlContent;

  /* Unfortunately, tidy won't always correctly guess the charset from the
     input document. We do a *very* crude check and scan for a meta
     Content-Type header to at least allow both iso-8859-1 (default) and
     UTF-8 as input. Actually, we use win1252 as input, a superset of
     iso-8859-1. */
  // Possible values: ascii, utf8, latin0, latin1, mac, win1252, ibm858
  $inEnc = 'win1252';
  if (preg_match('/<head[^>]*>.*<meta .*http-equiv=.?content-type.*charset=utf-?8.*<\/head>/is', $htmlContent) == 1) $inEnc = 'utf8';
  // Also use UTF-8 for <?xml version="1.0" encoding="utf-8"?
  $reXmlUtf8 = '%^<\?xml\s+version\s*=\s*"\d\.\d"\s+encoding\s*=\s*"utf-8"(\?|\s)%';
  if (preg_match($reXmlUtf8, $htmlContent) == 1) $inEnc = 'utf8';

  /* Remove PHP code from very start of document as tidy does not like
     that. We will re-add it later */
  $phpAtStart = $phpAtEnd = '';
  tidy_removePhp($htmlContent, $phpAtStart, $phpAtEnd);

  do {
    $escRand = mt_rand();
  } while (strpos($htmlContent, $escRand) !== FALSE);

  /* This array will be passed to tidy_commentReplace() after $htmlContent
     has been passed through tidy. That function will undo our escaping of
     parts of the input, replacing <!--$escRand $n--> with
     $commentReplace[$n]. */
  $commentReplace = array(/*int => string*/);

  /* Tidy attempts to do clever things with our special tags, such as <en/>
     etc language tags. To avoid this, we replace them with special PHP
     sections. For example, Tidy doesn't like them inside <title/> and always
     moves them inside the body. It also won't allow them between <ul> and
     the <li>s inside it. */
  global $tidy_EmptyTags, $tidy_InlineTags, $tidy_BlocklevelTags;
  $tagNames = array_unique($tidy_InlineTags + $tidy_EmptyTags
                           + $tidy_BlocklevelTags);
  $htmlContent = tidy_specialTagsPrepare($htmlContent, $tagNames, $escRand,
                                         $commentReplace);

  /* The above may have put a comment section at the start of the file to
     replace a special tag. However, if the input data does not contain a
     body tag, tidy will put the first comment at the very start of its
     output document, even before the <!DOCTYPE>. For example, if the input
     consists only of the line "<en>foo</en>", which is turned into something
     like "<!--2398457 0-->foo<!--2398457 1-->", tidy will output something
     like "<!--2398457 0--><!DOCTYPE><head/><body>foo<!--2398457
     1--></body>".  When the comment is later replaced by the special tag
     again, a lot of additional data has been inserted between the opening
     and the closing <en> tag. Fix this by checking for "<!--2398457 " at the
     start of the input. */
  //if (preg_match("/^\\s*<\\?php $escRand /", $htmlContent) == 1)
  if (preg_match("/^\\s*<!--$escRand /", $htmlContent) == 1)
    $htmlContent = '<body>' . $htmlContent;

  /* And another hack: With "< ? php", everything works as expected. With "<
     ?", tidy terminates the processing instruction on the first ">", not the
     first "? >" string. We'll temporarily replace "< ?" with
     "< ? php x29343626". */
  $htmlContent = preg_replace('/<\?(\s)/', "<?php x$escRand\$1", $htmlContent);

  // Debugging aid: Write tidy input to _private.tidy-in
  //file_put_contents(CACHE . $path . '_private.tidy-in', $htmlContent);

  $command = 'tidy --tidy-mark no --markup yes --output-xhtml yes '
    . '--doctype loose --word-2000 yes --wrap 0 '
    /* Using "--clean" leads to problems in some cases: If someone adds a
       style attribute to a custom tag, e.g. <navmenu style="foo"/>, tidy
       will create a new class and separate <style> section, The tidy output
       will be something like <navmenu class="c1"/> and a style rule of
       "navmenu.c2 { foo; }".  Oops. */
    //. '--clean yes '
    /* Does not work, as the nesting will be messed up if there are special
       tags which we replace with PHP sections: */
    // --enclose-text yes
    /* We must use output-encoding utf8 and not ascii, because otherwise
       ISO-8859-1 input characters appear in the output unchanged! According
       to the tidy docs, output-encoding utf8 is only allowed with one of the
       following for input-encoding: ascii, utf8, latin0, latin1, mac,
       win1252, ibm858 */
    . "--input-encoding $inEnc --output-encoding utf8";
  if (!empty($tidy_EmptyTags))
    $command .= ' --new-empty-tags ' . implode(',', $tidy_EmptyTags);
  if (!empty($tidy_InlineTags))
    $command .= ' --new-inline-tags ' . implode(',', $tidy_InlineTags);
  if (!empty($tidy_BlocklevelTags))
    $command .= ' --new-blocklevel-tags ' . implode(',', $tidy_BlocklevelTags);
  /* Entity references /can/ be used, but UTF-8 or &#123; char references are
     safer, see <http://schneegans.de/web/xhtml/> and
     <http://developer.mozilla.org/en/docs/XML_in_Mozilla#XHTML>. In practice
     IMHO either way is fine... */
  $command .= ' --numeric-entities yes';
  //syslog(LOG_DEBUG, "tidy: $command");


  $descriptorSpec = array(0 => array("pipe", "r"),
                          1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
  $process = proc_open($command, $descriptorSpec, $pipes, CACHE); // Run
  if (!is_resource($process)) {
    syslog(LOG_ERR, "tidy $path: Could not create process: $command");
    return '';
  }

  $txOff = 0; $txLen = strlen($htmlContent);
  $stdout = ''; $stdoutDone = FALSE;
  $stderr = ''; $stderrDone = FALSE;
  stream_set_blocking($pipes[0], 0); // Make stdin/stdout/stderr non-blocking
  stream_set_blocking($pipes[1], 0);
  stream_set_blocking($pipes[2], 0);
  if ($txLen == 0) fclose($pipes[0]);
  while (TRUE) {
    $rx = array(); // The program's stdout/stderr
    if (!$stdoutDone) $rx[] = $pipes[1];
    if (!$stderrDone) $rx[] = $pipes[2];
    $tx = array(); // The program's stdin
    if ($txOff < $txLen) $tx[] = $pipes[0];
    $ex = array();
    stream_select($rx, $tx, $ex, NULL, NULL); // Block til r/w possible
    if (!empty($tx)) {
      $txRet = fwrite($pipes[0], substr($htmlContent, $txOff, 8192));
      if ($txRet !== FALSE) $txOff += $txRet;
      if ($txOff >= $txLen) fclose($pipes[0]);
    }
    foreach ($rx as $r) {
      if ($r == $pipes[1]) {
        $stdout .= fread($pipes[1], 8192);
        if (feof($pipes[1])) { fclose($pipes[1]); $stdoutDone = TRUE; }
      } else if ($r == $pipes[2]) {
        $stderr .= fread($pipes[2], 8192);
        if (feof($pipes[2])) { fclose($pipes[2]); $stderrDone = TRUE; }
      }
    }
    if (!is_resource($process)) break;
    if ($txOff >= $txLen && $stdoutDone && $stderrDone) break;
  }
  $returnValue = proc_close($process);

  // Debugging aid: Write tidy output to _private.tidy-out
  //file_put_contents(CACHE . $path . '_private.tidy-out', $stdout);

  // $returnValue: 0=>OK, 1=>Warnings, 2=>Errors
  if ($returnValue <= 1) {
    // Fix up PI hack again
    $stdout = str_replace("<?php x$escRand", '<?', $stdout);
    // Fix up special tags which were turned into comments
    $stdout = tidy_commentReplace($stdout, $escRand, $commentReplace);
    return $phpAtStart . $stdout . $phpAtEnd;
  }
  //________________________________________

  // Create an error report

  syslog(LOG_ERR, "tidy $path: HTML->XHTML conversion failed");

  /* Re-add PHP code at end. Ideally, we should also prepend the original
     $phpAtStart, but then line numbers as output by tidy won't match. */
  if ($phpAtStart != '') $phpAtStart = '<?php ...removed code... ?>';
  $htmlContent = $phpAtStart . $htmlContent . $phpAtEnd;
  $tidyPos = array(); // [line,col] =>
  $n = strpos($stderr, 'To learn more about HTML Tidy');
  if ($n !== FALSE) $stderr = substr($stderr, 0, $n); // Remove rest of output after this
  while ($stderr != '' && substr($stderr, -1) == "\n")
    $stderr = substr($stderr, 0, -1);
  // Stderr output from tidy
  $errMsg = '<p>';
  $errLines = explode("\n", $stderr);
  foreach ($errLines as $l) {
    if (preg_match('/^line ([0-9]+) column ([0-9]+) - (Error:)?/i', $l, $m)
        == 0) {
      $errMsg .= htmlspecialchars($l) . "<br />\n";
      continue;
    }
    $lineNr = intval($m[1]);
    $tidyPos[$lineNr][intval($m[2])] = '';
    if (empty($m[3])) $attr = ''; else $attr = ' class="e"';
    $errMsg .= "<a href=\"#l$lineNr\"$attr>" . htmlspecialchars($l)
            . "</a><br />\n";
  }
  $errMsg .= "</p>\n";

  $body = '<pre>' . htmlspecialchars($stdout) . '<hr />';
  // Input document
  $lines = explode("\n", $htmlContent);
  foreach ($lines as $i => $l) {
    $lineNr = $i + 1;
    if (!array_key_exists($lineNr, $tidyPos)) {
      $body .= sprintf("%8d: %s\n", $lineNr, htmlspecialchars($l));
      continue;
    }
    $body .= sprintf("<span class=\"e\"><a id=\"l%d\">%8d</a>: ",
                     $lineNr, $lineNr);
    for ($col = 1; $col <= strlen($l); ++$col) {
      $char = htmlspecialchars($l[$col - 1]);
      if (array_key_exists($col, $tidyPos[$lineNr]))
        $body .= "<span class=\"x\">$char</span>";
      else
        $body .= $char;
    }
    $body .= "</span>\n";
  }
  $body .= '</pre>';

  return xhtmlTemplate_errDocument(
    $path,
    'Conversion from HTML to XHTML failed',
    '<a href="http://tidy.sourceforge.net/">Tidy</a> was unable to repair the HTML code.', $errMsg . $body, FALSE, '', "
<style type=\"text/css\">
.e { background-color: #fdd; }
.x { background-color: #faa; }
</style>");
}
//______________________________________________________________________

/* Remove any "< ? php" or "< ?" sections from the very start of
   $htmlContent. All argument variables are modified. The original value of
   $htmlContent can be reconstructed by concatenating the results. */
function /*void */ tidy_removePhp(/*string*/ &$htmlContent,
    /*string*/ &$phpAtStart, /*string*/ &$phpAtEnd) {

  // Quickly catch the common case that no code is present
  if (strpos($htmlContent, '<?') === FALSE) {
    $phpAtStart = '';
    $phpAtEnd = '';
    return;
  }

  $rePhp = '(?:<\?(?:php)?\s(?:[^?]|\?(?!>))*\?>\n*)*'; // 0 or more PHP PIs
  preg_match("%^$rePhp%", $htmlContent, $m);
  $phpAtStart = $m[0];
  preg_match("%$rePhp\$%", $htmlContent, $m, 0, strlen($phpAtStart));
  $phpAtEnd = $m[0];
  $htmlContent = substr($htmlContent,
    strlen($phpAtStart),
    strlen($htmlContent) - strlen($phpAtStart) - strlen($phpAtEnd));

  /* This regexp somehow does not work. With some input, PHP crashed. At
     other times, it didn't match stuff properly. Maybe I just don't know
     how to write regexps... */
//   preg_match("%^($rePhp)((?Us).*)($rePhp)\$%", $htmlContent, $m);
//   $phpAtStart = $m[1];
//   $htmlContent = $m[2];
//   $phpAtEnd = $m[3];
  //syslog(LOG_DEBUG, 'tidy_removePhp: ' . strlen($phpAtStart) . '+'
  //       . strlen($htmlContent) . '+' . strlen($phpAtEnd));
}
//______________________________________________________________________

/* Helper function: Tidy attempts to do clever things with our special tags,
   such as <en/> etc language tags. To avoid this, we replace them with
   special comment sections which only contain an ID. We also need to replace
   all existent comments with special comment sections - otherwise, if a
   special tag were commented out, we would create a nested comment. */
function /*string*/ tidy_specialTagsPrepare(/*string*/ $htmlContent,
    /*array(string)*/ $tagNames, /*string*/ $escRand,
    /*array*/ &$commentReplace) {
  $reTag = implode('|', $tagNames);
  $reComment = '(?Us:<!--.*-->)';
  $tagCount = preg_match_all("%$reComment|</?(?:$reTag)(?:\s[^>]*)?>%",
                             $htmlContent, $m, PREG_OFFSET_CAPTURE);
  if ($tagCount == 0)
    return $htmlContent;

  /* $mm is an array whose entries are arrays with 2 entries. $mm[0][0] is
     the string of the 1st tag, $mm[0][1] its offset in $htmlContent. */
  $mm = $m[0];
  $mm[] = array('', strlen($htmlContent)); // Append
  $newHtml = substr($htmlContent, 0, $mm[0][1]);
  for ($i = 0; $i < $tagCount; ++$i) {
    // Skip comments - and in the process, do *not* process any tags inside
    if (substr($mm[$i][0], 0, 4) == '<!--') {
      $newHtml .= $mm[$i][0];
    } else {
      // Replace <lastmodified/> with < ? php 837001496 6 ? >
      //$newHtml .= "<?php $escRand " . count($commentReplace) . '?'.'>';
      $newHtml .= "<!--$escRand " . count($commentReplace) . '-->';
      $commentReplace[] = $mm[$i][0];
    }
    $afterMatchOff = $mm[$i][1] + strlen($mm[$i][0]);
    $newHtml .= substr($htmlContent, $afterMatchOff,
                       $mm[$i+1][1] - $afterMatchOff);
  }
  return $newHtml;
}
//______________________________________________________________________

/* In the supplied string $tidyData (output of tidy), replace all occurrences
   of < ?php $escRand $n? > with $commentReplace[$n]. */
function /*string*/ tidy_commentReplace(
    /*string*/ $tidyData, /*string*/ $escRand, /*array*/ &$commentReplace) {
  $out = '';
  $off = 0;
  // PHP causes trouble if you have the following commit-time code:
  // < ? php echo "<en>foo</en>"; ? >
  //$escStr = "<"."?php $escRand ";
  //$escEnd = '?'.'>';
  $escStr = "<!--$escRand ";
  $escEnd = '-->';
  $escStrLen = strlen($escStr);
  $escEndLen = strlen($escEnd);
  while (TRUE) {
    $nextOff = strpos($tidyData, $escStr, $off);
    if ($nextOff === FALSE) break;
    // Append characters before comment match
    $out .= substr($tidyData, $off, $nextOff - $off);
    $nextEnd = strpos($tidyData, $escEnd, $nextOff + $escStrLen);
    if ($nextEnd === FALSE) {
      syslog(LOG_ERR, "tidy_commentReplace: $escEnd not found");
      return $tidyData; // Should Not Happen(tm)
    }
    // For debugging: Append comment
    //$out .= substr($tidyData, $nextOff, $nextEnd - $nextOff + $escEndLen);
    // Append $commentReplace entry
    $out .= $commentReplace[substr($tidyData, $nextOff + $escStrLen,
                                   $nextEnd - $nextOff - $escStrLen)];
    $off = $nextEnd + $escEndLen;
  }
  $out .= substr($tidyData, $off);
  return $out;
}

?>