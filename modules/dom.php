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
    Miscellaneous functions for anchors in documents, document
    transformations etc. This will be mostly of use to templates. */
//______________________________________________________________________

/** $doc->validate() would be necessary during page creation to make
    DOMDocument->getElementById() work. Unfortunately, we cannot really do
    this, because validate() will actually load any DTDs from the Internet,
    which means that 1) things will break if w3.org cannot be reached, and 2)
    things will get very slow because there is no caching and the DTD will be
    fetched again for each validate() call. Argh, this is stupid.
    Workaround: Use our own getElementById() function. It finds the first
    element whose "id" attribute has tthe supplied value. Strictly speaking,
    this is not the same functionality as getElementById() which searches for
    the <em>data type</em> called ID.
    @param $doc The DOMDocument to search in
    @param $id The string to look for in id attributes
    @param $node Node of subtree to restrict search to, or NULL to search
    entire document
    @return The DOMNode of the element that was found, or NULL. */
function /*DOMElement*/ getElementById(DOMDocument $doc, /*string*/ $id,
                                       DOMNode $node = NULL) {
  if ($node === NULL) return getElementById($doc, $id, $doc->documentElement);
  $children = $node->childNodes;
  for ($i = 0; $i < $children->length; ++$i) {
    $elem = $children->item($i);
    if (!($elem instanceof DOMElement)) continue;
    if ($elem->getAttribute('id') == $id) return $elem;
    $ret = getElementById($doc, $id, $elem);
    if ($ret !== NULL) return $ret;
  }
  return NULL;
}
//______________________________________________________________________

/** Normalize a CACHE-absolute path, i.e. ensure that it starts with '/' and
    does not use '/../' to go up beyond the root. Occurrences of '/./' are
    removed. Trailing slashes are preserved. FALSE is returned for invalid
    paths and also if a fragment identifier # appears at a wrong position. */
function /*string|FALSE*/ normalizePath(/*string*/ $path) {
  if ($path{0} != '/') return FALSE;
  $x = explode('/', substr($path, 1));
  $xcount = count($x);
  for ($i = 0; $i < $xcount; ++$i) {
    //echo "**** $i ****\n";print_r($x);
    if ($x[$i] == '..') {
      unset($x[$i]);
      $j = $i;
      while (true) {
        if (--$j < 0) return FALSE;
        if (isset($x[$j])) { unset($x[$j]); break; }
      }
    } else if ($x[$i] == '.' || empty($x[$i])) {
      unset($x[$i]);
    }
  }
  $ret = '/' . implode('/', $x);
  if (!empty($x) && substr($path, -1) == '/') $ret = "$ret/";

  // No / must appear after the last hash and there must only be one hash
  $lastHash = strrpos($ret, '#');
  if (strpos($ret, '#') !== $lastHash) return FALSE;
  $lastSlash = strrpos($ret, '/');
  if ($lastHash === FALSE || $lastSlash < $lastHash)
    return $ret;
  else
    return FALSE;
}
//______________________________________________________________________

/** Given two CACHE-absolute *normalized* URLs, return a relative URL such
    that putting that URL into the $baseURL document will yield a correct
    reference to the $destURL. The result will not be correct if the URLs are
    not normalized, i.e. they contain '/../' or '/./' anywhere. */
function /*string*/ relativeURL(/*string*/ $baseURL, /*string*/ $destURL) {
  if ($baseURL{0} != '/' || $destURL{0} != '/') return FALSE;
  $base = explode('/', substr($baseURL, 1));
  $dest = explode('/', substr($destURL, 1));
  $i = 0;
  $last = min(count($base), count($dest)) - 1;
  while ($i < $last && $base[$i] == $dest[$i]) {
    unset($base[$i]);
    unset($dest[$i]);
    ++$i;
  }
  $ret = str_repeat('../', count($base) - 1) // First go up
    . implode('/', $dest); // ...then down towards $dest
  if ($ret == '') return './'; else return $ret;
}
//______________________________________________________________________

/** This function is intended to be used if your website is subdivided into
    parts which should be available under a different host name or
    scheme. For example, if you want everything below /internal to be
    accessed via HTTPS and the rest via HTTP, you'd call the function with
    $sites=array('/'=>'http://site/',
    '/internal/'=>'https://site/internal/').

    The function walks through the entire document and looks for "href" and
    "src" attributes. If the current $path and the destination of the link
    belong to the same $sites entry, the link is left unchanged. Otherwise, the
    link is replaced with an absolute link which begins with the
    corresponding URL entry in $sites.

    @param $path Path of the current document
    @param $document Parse tree of the current document
    @param $sites Array whose indices are site-absolute paths which should
    end with a slash. The corresponding entry for such a path is the full URL
    to which this part of the site maps.
    @param $node Subtree of $document to process, or NULL for entire document
*/
function /*void*/ dom_crossSiteLinks(/*string*/ $path, DOMDocument $document,
    /*array(string=>string)*/ &$sites, /*DOMElement*/ $node = NULL) {
  if ($node === NULL) {
    dom_crossSiteLinks($path, $document, $sites, $document->documentElement);
    return;
  }
  $children = $node->childNodes;
  for ($i = 0; $i < $children->length; ++$i) {
    $elem = $children->item($i);
    if (!($elem instanceof DOMElement)) continue;
    dom_crossSiteLinks($path, $document, $sites, $elem); // Recurse
    //dlog("dom_crossSiteLinks: $path <".$elem->tagName);
    if ($elem->hasAttribute('src')) {
      $newAttr = dom_crossSiteLink($path, $sites, $elem->getAttribute('src'));
      if ($newAttr !== FALSE) $elem->setAttribute('src', $newAttr);
    }
    if ($elem->hasAttribute('href')) {
      $newAttr = dom_crossSiteLink($path, $sites, $elem->getAttribute('href'));
      if ($newAttr !== FALSE) $elem->setAttribute('href', $newAttr);
    }
  }
}

/** Like dom_crossSiteLinks(), but return for a single link $relLink (can be
    site-absolute like "/foo" or relative like "../x") the changed link if
    necessary. If $relLink poits to the same part of the site that $path is a
    part of, $relLink is returned unchanged. If $relLink contains a full
    absolute URL like "http://host/path", it is always returned unchanged. If
    an error occurs (e.g. $relLink points outside the site), FALSE is
    returned. $path must be site-absolute, e.g. "/x/y". */
function /*string|FALSE*/ dom_crossSiteLink(/*string*/ $path,
    /*array(string=>string)*/ &$sites, /*string*/ $relLink) {
  if (substr($relLink, 0, 7) == 'http://'
      || substr($relLink, 0, 8) == 'https://'
      || substr($relLink, 0, 6) == 'ftp://'
      || $relLink == '')
    return $relLink;
  //dlog("dom_crossSiteLink0: $path $relLink");
  if ($relLink{0} == '/') {
    $link = $relLink;
  } else {
    $link = normalizePath(dirname($path . 'x') . '/' . $relLink);
    if ($link === FALSE) return FALSE;
  }
  //dlog("dom_crossSiteLink1: $path $link");
  $p = $r = ''; // Array index for $path, $link
  foreach ($sites as $part => $fullUrl) {
    $partLen = strlen($part);
    if (substr($path, 0, $partLen) == $part && $partLen > strlen($p))
      $p = $part;
    if (substr($link, 0, $partLen) == $part && $partLen > strlen($r))
      $r = $part;
  }
  //dlog("dom_crossSiteLink2: $path=$p $link=$r");
  if ($p == $r)
    return $relLink; // Same part of site
  else
    return $sites[$r] . substr($link, strlen($r)); // Create absolute URL
}
//______________________________________________________________________

/** Given a DOMElement which must be an a tag, this function finds out
    whether the link is internal or external. Schemes other than http:,
    https: and ftp: are also considered internal. Thus, "mailto:" Links are
    considered internal.
    @param $anchor The &lt;a&gt; tag to look at
    @param $internalSites Array of strings, or NULL for none. Each string
    gives another site which is also considered "internal". Typically, you
    will include at least the name under which the site is reachable on the
    web. The string must include the scheme and a trailing "/" - for example,
    "http://example.org/" will work, but "http://example.org" or
    "example.org/" will not. The string must be all lowercase.
    @return TRUE if the link of the anchor is internal or if the anchor does
    not have a href attribute, FALSE otherwise. */
function /*bool*/ anchorType_isInternal(DOMElement $anchor,
    /*array(string)*/ $internalSites = NULL) {
  if (!$anchor->hasAttribute('href')) return TRUE;
  $href = $anchor->getAttribute('href');

  if (substr($href, 0, 7) != 'http://'
      && substr($href, 0, 8) != 'https://'
      && substr($href, 0, 6) != 'ftp://')
    return TRUE;

  if ($internalSites !== NULL) {
    foreach ($internalSites as $site) {
      if (strtolower(substr($href, 0, strlen($site))) == $site)
        return TRUE;
    }
  }
  return FALSE;
}
//______________________________________________________________________

/** Helper function: Parse the string as XML, return the associated
    DOMNode. If there is a parse error, FALSE is returned.  Note: Only the
    first element in $markup is returned. For example, if $markup is
    "&lt;x/&gt;&lt;y/&gt;", the DOMNode for &lt;x/&gt; is returned. */
function /*DOMNode*/ dom_parseXML(DOMDocument $d, /*string*/ $markup) {
  // A helper DOMDocument
  static $dd = NULL;
  if ($dd === NULL) {
    $dd = new DOMDocument();
    $dd->substituteEntities = FALSE;
    $dd->preserveWhiteSpace = TRUE;
    $dd->resolveExternals = FALSE; // Or php will contact w3.org
  }
  /* Build a small XHTML document, so libxml knows that the implied encoding
     is UTF-8. Furthermore, with that document, occurrences of entities
     (e.g. &auml;) do not cause loadXML() to return FALSE. */
  $miniDocument = 
    '<?xml version="1.0" encoding="utf-8"?>'
    . '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
    . '<html xmlns="http://www.w3.org/1999/xhtml">' . $markup . '</html>';
  //$miniDocument = "<div>$markup</div>";
  if ($dd->loadXML($miniDocument) === FALSE) return FALSE;
  return $d->importNode($dd->documentElement->firstChild, TRUE);
}
//______________________________________________________________________

/** Helper function: Remove extraneous "xmlns" and "xmlns:default" attributes
    from a node and its children. libxml, which is used by PHP's XML DOM
    functions, tends to add these whenever elements are copied from one
    document to another. To behave 100% correctly, this function should check
    whether the respective namespace is actually used anywhere in the
    sub-tree. We take an easier approach and only remove
    xmlns="http://www.w3.org/1999/xhtml". */
function /*void*/ dom_ripXhtmlNS(DOMDocument $doc,
                                 DOMNode $node = NULL) {
  if ($node === NULL) $node = $doc->documentElement;
  $children = $node->childNodes;
  for ($i = 0; $i < $children->length; ++$i) {
    $elem = $children->item($i);
    if (!($elem instanceof DOMElement)) continue;
    // To be standards-compliant, the html elem needs the xmlns
    if ($elem->tagName != 'html') {
      // Remove xmlns="http:..." and xmlns:default="http:..."
      $elem->removeAttributeNS('http://www.w3.org/1999/xhtml', '');
      $elem->removeAttributeNS('http://www.w3.org/1999/xhtml', 'default');
    }
    dom_ripXhtmlNS($doc, $elem);
  }
}
