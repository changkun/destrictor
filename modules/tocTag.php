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

/** @file Create a table of contents on web pages. */

/** @tag toc

    Creates a table of contents. Remember to close this tag properly: Write
    &lt;toc/&gt;, not &lt;toc&gt;!

    The tag is replaced with a &lt;ul class="toc"&gt; which contains list
    entries and nested lists. The entries correspond to the structure
    generated by &lt;h1&gt; etc. headings.

    The algorithm takes care not to include a single top-level list
    entry. For example, if the document contains just one &lt;h1&gt; at the
    top of the page and several &lt;h2&gt; headings, only these &lt;h2&gt;
    headings are listed.

    id attributes are auto-generated if they are not present. If you want all
    language versions to use the same id for the same heading, put the
    language tags <em>inside</em> the heading tag, for example like
    this:<br/>
    <tt>&lt;h1&gt;&lt;de&gt;Deutsch&lt;/de&gt;&lt;en&gt;English&lt;/en&gt;&lt;/h1&gt;</tt>

    You can prevent a heading from being included in the table of contents by
    giving it a class of "notoc". Example: &lt;h1 class="notoc"&gt;. The
    attribute is a space-separated list, so if you need to specify another
    class, use something like<br/>
    <tt>&lt;h1 class="otherclass notoc"&gt;</tt>

    Additionally, you can prevent a heading from being included in the table
    of contents by using the toc tag's "id-prefix" attribute. If it is
    present, the TOC will only contain headings whose ID starts with the
    given string. For example, the output of <tt>&lt;toc
    id-prefix="toc-"/&gt;</tt> will include the heading <tt>&lt;h3
    id="toc-foo"&gt;</tt>, but not <tt>&lt;h3 id="x"&gt;</tt> or
    <tt>&lt;h3&gt;</tt>.
*/

require_once(MODULES . '/tidy.php'); // Need this first

tidy_addTag('toc', TRUE, TRUE);

hook_subscribe('xhtmlDom', 'tocTag_xhtmlDom');

/** Called by hook, looks for &lt;toc/&gt; elements. */
function /*void*/ tocTag_xhtmlDom(&$arr) {
  $document = $arr['result'];
  $path = $arr['path'];

  $tags = $document->getElementsByTagname('toc');
  if ($tags->length == 0) return;

  for ($i = $tags->length - 1; $i >= 0; --$i) {
    $tag = $tags->item($i);
    $newNode = NULL;
    if ($tag->hasChildNodes()) {
      $newNode = xhtmlTemplate_errNode($document,
                                       '<toc/> tag must be empty');
    } else {
      if ($tag->hasAttribute('id-prefix'))
        $prefix = $tag->getAttribute('id-prefix');
      else
        $prefix = NULL;
      $newNode = tocTag_createToc($document, $prefix);
    }
    $tag->parentNode->replaceChild($newNode, $tag);
  }
}
//______________________________________________________________________

/** Create a complete table of entries, in the form of a &lt;ul
    class="toc"&gt; DOMNode with entries and nested lists. If more than one
    language is used in the document, the &lt;ul&gt; contains a number of
    language elements each of which contains the complete menu &lt;ul&gt; for
    that language.

    When a heading &lt;ht&gt; is found, it is appended as a child to the node
    of the last preceding &lt;hs&gt; where s&lt;t. For example, a &lt;h3&gt;
    will become a child of any &lt;h2&gt; or &lt;h1&gt; preceding it,
    whichever comes first during the backward search. If there is no
    preceding heading, the new heading is added at the top level. This
    algorithm means that it is possible for different headings to appear at
    the same depth in the output tree.

    If $prefix is a non-empty string, it gives the necessary ID prefix for
    inclusion of headings in the TOC. For "", all headings are included. */
function /*DOMNode*/ tocTag_createToc(DOMDocument $doc,
                                      /*string*/ $prefix = "") {
  $headings = tocTag_getHeadings($doc, NULL, $prefix);
  //syslog(LOG_DEBUG, "tocTag_createToc: " . count($headings) . " headings");
  $headLang = array(); // for each entry in $headings, language(s)
  $allLang = array(); // Union of all $headLang entries
  foreach ($headings as $i => $h) {
    $headLang[$i] = langTag_langOfElem($h);
    //syslog(LOG_DEBUG, "tocTag_createToc: " . $h->tagName . ' '
    //       . print_r($headLang[$i], TRUE));
    if ($headLang[$i] !== FALSE)
      $allLang = $allLang + $headLang[$i]; // Array union
  }
  //syslog(LOG_DEBUG, "tocTag_createToc: " . print_r($allLang, TRUE));

  // Create div container
  $ul = $doc->createElement('ul');
  $ul->setAttribute('class', 'toc');

  if (empty($allLang)) {
    // Create one global list
    tocTag_appendTocList($doc, $ul, $headings, $headLang, FALSE);
    tocTag_pruneTocTop($ul);
  } else {
    // Create one list for each language
    foreach ($allLang as $l => $trueVal) {
      //syslog(LOG_DEBUG, "tocTag_createToc: create lang $l");
      $lang = $doc->createElement($l);
      $ul->appendChild($doc->createTextNode("\n"));
      $ul->appendChild($lang);
      tocTag_appendTocList($doc, $lang, $headings, $headLang, $l);
      tocTag_pruneTocTop($lang);
    }
  }

  return $ul;
}
//____________________

/* Private helper function: Append to $parentNode a series of <li> tags,
   possibly with nested <ul>s, for language $lang. Uses the supplied list of
   heading elements and heading languages. */
function /*DOMElement*/ tocTag_appendTocList(DOMDocument $doc,
    DOMElement $parentNode, /*array(DOMElement)*/ $headings,
    /*array(array(string=>))*/ $headLang, /*string*/ $lang) {
  $headingLi = array(); // For each $headings entry, ref to the <li> DOMElem
  $prevI = NULL; // During loop: $i of previous loop iteration

  foreach ($headings as $i => $h) {
    // Go through all headings, skip those not in the required language
    if ($lang !== FALSE && $headLang[$i] !== FALSE
        && !array_key_exists($lang, $headLang[$i]))
      continue;
    //syslog(LOG_DEBUG, "tocTag_appendTocList: lang=$lang #$i: ".$h->textContent);

    // Where to append the new <li>?
    $appendTo = tocTag_appendToWhere($doc, $parentNode, $headings, $headLang,
                                     $headingLi, $prevI, $i, $h);
    $prevI = $i;

    // Create a <li> for the new entry
    $li = $doc->createElement('li');
    $headingLi[$i] = $li;
    $appendTo->appendChild($li);
    $a = $doc->createElement('a');
    $li->appendChild($a);
    // If necessary, autogenerate an id for the heading
    if (!$h->hasAttribute('id')) {
      $autoId = tocTag_createId($doc, $h);
      $h->setAttribute('id', $autoId);
    }
    $a->setAttribute('href', '#' . $h->getAttribute('id'));
    // Clone heading and copy over children to the TOC entry
    $hClone = $h->cloneNode(TRUE);
    if ($lang !== FALSE) langTag_filterLang($hClone, $lang);
    while ($hClone->firstChild !== NULL) {
      $c = $hClone->removeChild($hClone->firstChild);
      /* Unfortunately, at this point a "default:" prefix is added to any
         DOMElements by libxml. Later on, this is removed again, but the
         respective namespace declaration -
         xmlns:default="http://www.w3.org/1999/xhtml" - will stay in the
         final output document. */
      $a->appendChild($c);
      //syslog(LOG_DEBUG, "tocTag_appendTocList: +".str_replace("\n", '\n', xhtmlFile_saveXML($c)));
    }
  }
}
//____________________

/* Private helper function: Find spot in the TOC tree of <ul> and <li>
   elements where the new entry for $h needs to be inserted. */
function /*DOMElement*/ tocTag_appendToWhere(DOMDocument $doc,
    DOMElement $parentNode, /*array(int=>DOMElement)*/ $headings,
    /*array(array(string=>))*/ $headLang,
    /*array(int=>DOMElement)*/ $headingLi, /*int*/ $prevI, /*int*/ $i,
    DOMElement $h) {
  /* Find <ul> where to append the next <li> entry, make $appendTo a
     reference to it: From previously inserted <li>, go upward until we
     either reach $parentNode (=> append to $parentNode) or we reach a <li>
     whose heading (e.g. <h1>) is higher-order than the one we are going to
     insert (e.g. <h2>). */
  if ($prevI === NULL) {
    $appendTo = $parentNode;
  } else {
    //syslog(LOG_DEBUG, "tocTag_appendTocList: ".print_r($headingLi,TRUE));
    $prevLi = $headingLi[$prevI];
    $hNum = intval(substr($h->tagName, 1)); // $hNum = 2 for <h2>
    $hh = $headings[$prevI];
    // $hhNum = 1 if previously inserted TOC entry was for <h1> heading
    $hhNum = intval(substr($hh->tagName, 1));
    if ($hhNum < $hNum) {
      // Need to append a <ul> to the previously inserted <li>
      $appendTo = $doc->createElement('ul');
      $prevLi->appendChild($appendTo);
    } else {
      // Go upward until we find the <ul> to append to
      $appendTo = $prevLi;
      while (TRUE) {
        $appendTo = $appendTo->parentNode; // Go from <li> to parent <ul>
        if ($appendTo === $parentNode) break;
        $hh = $headings[array_search($appendTo->parentNode, $headingLi,
                                     TRUE)];
        $hhNum = intval(substr($hh->tagName, 1)); // $hhNum = 1 for <h1>
        if ($hhNum < $hNum) break;
        $appendTo = $appendTo->parentNode; // Go from <ul> to parent <li>
      }
    }
  }
  return $appendTo;
}
//____________________

/* Private helper function: Ensure top level of menu has at least 2 entries.
   $node is expected to contain >=1 <li> elements.  If it contains exactly
   one <li> whose last child is a <ul>, replace that <li> with all the
   children of the <ul>. Repeat this step until there are >1 entries. */
function /*void*/ tocTag_pruneTocTop(DOMElement $node) {
  while (TRUE) {
    if ($node->childNodes->length != 1
        || !($node->firstChild instanceof DOMElement)
        || $node->firstChild->tagName != 'li')
      return;
    $li = $node->firstChild;
    if ($li->lastChild === NULL
        || !($li->lastChild instanceof DOMElement)
        || $li->lastChild->tagName != 'ul'
        || $li->lastChild->childNodes->length < 1)
      return;
    $ul = $li->lastChild;
    // Move entries out of <ul>
    while ($ul->firstChild !== NULL) {
      $c = $ul->removeChild($ul->firstChild);
      $node->appendChild($c);
    }
    $node->removeChild($li);
  }
}
//______________________________________________________________________

/** Helper function: Recurse through the document and return an array of
    DOMNode references which are headings, i.e. one of the elements
    &lt;h1&gt; through &lt;h6&gt;. Do not include headings whose class
    attribute contains "notoc".
    @param $doc The document to search through.
    @param $node NULL to search the entire document, otherwise the subtree of
    $doc to restrict the search to.
    @param $ret Array to append results to, or NULL to return a newly created
    array. */
function /*array(DOMNOde)*/ tocTag_getHeadings(DOMDocument $doc,
    DOMNode $node = NULL, $prefix = NULL, &$ret = NULL) {
  if ($node === NULL)
    return tocTag_getHeadings($doc, $doc->documentElement, $prefix, $ret);
  if ($ret === NULL) $ret = array();

//   if ($node instanceof DOMElement)
//     syslog(LOG_DEBUG, "tocTag_getHeadings: " . $node->tagName);

  $children = $node->childNodes;
  for ($i = 0; $i < $children->length; ++$i) {
    $elem = $children->item($i);
    if (!($elem instanceof DOMElement)) continue;
    $elemClass = ' ' . $elem->getAttribute('class') . ' ';
    if (strlen($elem->tagName) == 2
        && ($elem->tagName{0} == 'h' || $elem->tagName{0} == 'H')
        && ctype_digit($elem->tagName{1})
        && $elem->tagName{1} >= 1 && $elem->tagName{1} <= 6
        && preg_match('/\snotoc\s/', $elemClass) == 0
        && substr($elem->getAttribute('id'), 0, strlen($prefix))
           == $prefix) {
      $ret[] = $elem;
      //syslog(LOG_DEBUG, "tocTag_getHeadings: HEAD " . $elem->tagName);
    }
    tocTag_getHeadings($doc, $elem, $prefix, $ret);
  }
  return $ret;
}
//______________________________________________________________________

/** Create a unique id for the $node. The returned id string is guaranteed
    not to be in use in the document. It only consists of characters which
    are valid for IDs. */
function /*string*/ tocTag_createId(DOMDocument $doc, DOMElement $node) {
  /* Use textContent as a basis. However, insert a space between the
     textContent of any direct children */
  $id = '';
  $children = $node->childNodes;
  for ($i = 0; $i < $children->length; ++$i) {
    $c = $children->item($i);
    if ($c instanceof DOMNode) $id .= ' ' . $c->textContent;
  }
  // Remove space, lowercase
  $id = trim($id);
  //syslog(LOG_DEBUG,'ID1 '.$id);
  // Keep length sane
  $id = substr($id, 0, 40);
  //syslog(LOG_DEBUG,'ID2 '.$id);
  // Various substitutions. Slight bug: Assumes ISO-8859-1/UTF-8 encoding
  $repl = array("\n" => '-', ' ' => '-', "\t" => '-',
                'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
                'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
                '�' => 'ae', '�' => 'oe', '�' => 'ue', '�' => 'ss',
                '�' => 'Ae', '�' => 'Oe', '�' => 'Ue');
  foreach ($repl as $search => $replacement)
    $id = str_replace($search, $replacement, $id);
  $id = strtolower($id);
  //syslog(LOG_DEBUG,'ID3 '.$id);
  // Remove various stuff at start of string
  if (preg_match('/^([0-9]+[.)]?)?-*(a|the|eine?|der|die|das)-/', $id, $m)
      == 1) $id = substr($id, strlen($m[0]));
  //syslog(LOG_DEBUG,'ID4 '.$id);
  // Eliminate any non-standard characters, turn multiple into just one '-'
  $id = preg_replace('/[^a-z0-9_.]+/', '-', $id);
  if ($id{0} == '-') $id = substr($id, 1);
  // Must start with character
  if (!ctype_alpha($id{0})) $id = 'id-' . $id;

  $idLen = strlen($id);
  if ($id{$idLen - 1} == '-') $id = substr($id, 0, $idLen - 1);

  $suffix = '';
  while (TRUE) {
    //syslog(LOG_DEBUG,'ID5 '.$id.$suffix);
    if (getElementById($doc, $id . $suffix) === NULL)
      return $id . $suffix;
    ++$suffix;
  }
}

?>