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

/** @file Creation of multi-language variants of a page. */

/** @tag en

    Tags for content in multiple languages, e.g. &lt;en/&gt; for English,
    &lt;de/&gt; for German etc. Using these tags causes multiple variants of
    a page to be generated, one for each language. The setting of LANGUAGES
    in config.php gives a list of language codes to support, e.g. "de en" for
    German and English. For all configured languages, this module supports
    tags with the same name (e.g. &lt;de&gt; and &lt;en&gt;). Use these tags
    as follows:

    - For content which should be equal for all languages (or which has not
      been translated), do not use the special tags.

    - For content which differs, provide one version for each language,
      enclosed in the appropriate tags. For example:<br/>
      &lt;p&gt;&lt;en&gt;Content&lt;/en&gt;&lt;de&gt;Inhalt&lt;/de&gt;&lt;/p&gt;<br/>
      Or you can do the same for whole paragraphs:<br/>
      &lt;en&gt;&lt;p&gt;Content&lt;/p&gt;&lt;/en&gt;<br/>
      &lt;de&gt;&lt;p&gt;Inhalt&lt;/p&gt;&lt;/de&gt;

    - If content should be used for more than one language, you can add the
      language code in the opening tag: &lt;en de=""&gt;X&lt;/en&gt; will be
      displayed on both the English and German page. Note that &lt;de
      en=""&gt;X&lt;/en&gt; is incorrect; the closing tag name must be
      identical to the opening one. IMPORTANT: You must write correct XML,
      i.e. &lt;en de<strong>=""</strong>&gt;X&lt;/en&gt;. The actual value of
      the attribute is ignored.

    - Nesting language tags is not allowed and will result in an error.

    - Pitfall: If you have a long bilingual page whose content is entirely
      enclosed inside &lt;en&gt; and &lt;de&gt; tags, and you now append a
      small note inside &lt;it&gt;, then this means that from now on, an
      Italian version will be generated, and it'll consist _only_ of the text
      inside the &lt;it&gt;! In contrast to this, before the &lt;it&gt; was
      added, anyone with an Italian browser would see the English or German
      version. Solution: The moment you add the first &lt;it&gt; tag, you
      must go through the document and add "it" to other language tags whose
      content should appear in the Italian version; e.g. turn all
      &lt;en&gt;&lt;/en&gt; elements into &lt;en it&gt;&lt;/en&gt; to use the
      English text for the Italian version.

    - Template files will usually contain text for all supported
      languages. This means that by default, ALL language variants will be
      generated for ALL pages, which (as described above) is a pitfall. To
      work around the problem, one can add the attribute
      <tt>ignore=""</tt> to all language tags (except maybe for those
      of one language) in the template, e.g. &lt;en
      ignore=""&gt;X&lt;/en&gt; Generation of a particular language
      variant will not take place if ALL tags for that language are marked
      "ignore". Thus, if only the tags from the template remain in the final
      XHTML+languagetags, the language will not be generated. If the inserted
      document contains additional, unmarked tags, the language WILL be
      generated.
      <br/>
      NOTE: Usually, in your template file you should not add ignore=""
      to the tags of your site's default language. Often, the template will
      contain some text in variants for all languages, for example
      "&lt;en&gt;Last modified&lt;/en&gt;&lt;de&gt;Letzte
      &Auml;nderung&lt;/de&gt;". If all these variants are marked "ignore",
      none of them will be included for input documents which do not contain
      language tags.
      <br/>
      The actual value of the "ignore" attribute is ignored, the system only
      checks whether it is present or not.

    Whenever the system decides that a page is available in several different
    languages, several copies of it are created, each with the content of
    just one language remaining. The different versions are written to CACHE
    with special filenames which are interpreted by Apache MultiViews. For
    example, MultiViews will deduce from the filenames "index.html.de" and
    "index.html.en" that the URL "index.html" is available in two languages,
    English and German. Which version is sent to a browser depends on the
    browser settings. Additionally, a cookie can be set which overrides the
    browser setting. (See the example select-language.php)

    When creating a language-specific version of a page, the code adds a
    lang="de" or similar attribute to the &lt;html&gt; element. Furthermore,
    it adds a class="lang-de" attribute - this is useful if
    language-dependent code should work with all browsers, because some
    (notably MSIE6) do not support the attribute selector [lang|="de"] or
    (MSIE7) the :lang(de) CSS selector.

*/

require_once(MODULES . '/tidy.php'); // Need this first

// Register language tags
foreach (explode(' ', LANGUAGES) as $lang)
  tidy_addTag($lang, FALSE/*non-empty tag*/,
              TRUE/*is inline*/, TRUE/*is blocklevel*/);

// Call near the end, but still before xhtmlFile_xhtmlDom
hook_subscribe('xhtmlDom', 'langTag_xhtmlDom', -90);
//________________________________________

hook_subscribe('fileDependUpdated_xhtml',
               'langTag_fileDependUpdated_xhtml', 50); // high prio
hook_subscribe('fileDependUpdated_xhtml-php',
               'langTag_fileDependUpdated_xhtml', 50); // high prio

/** xhtmlFile_fileDependUpdated_xhtml() will do the main work of regenerating
    the .xhtml page after a change to one of its dependencies. Before it does
    this, we must remove the old language-specific files. We will regenerate
    those files later when xhtmlFile_fileDependUpdated_xhtml() calls
    xhtmlFile_filter() which in turn calls our langTag_xhtmlDom() via the
    xhtmlDom hook. */
function /*void*/ langTag_fileDependUpdated_xhtml(/*array('path'=>)*/ $arr) {
  if (LANGUAGES == '') return;
  // Delete all language-specific files with XHTML content
  anyDirOrFile_performAutoDel($arr['path'], 'langTag', FALSE, FALSE);
}
//______________________________________________________________________

/** If the file contains at least one language tag like &lt;de&gt;, enable
    writing of language-specific files. In this case, no file "foo.xhtml" is
    created. Instead a file "foo.xhtml.xx" is created for each language code
    xx which appears in the document. IMPORTANT: Some care must be taken with
    this behaviour to avoid that Apache gives back responses like "300
    Multiple Choices" or "406 Not Acceptable" for some browser
    configurations. It is recommended to use the Apache config options
    "ForceLanguagePriority Prefer Fallback" and "LanguagePriority en fr de",
    where the list after "LanguagePriority" should contain all languages in
    use on the site, with the first being the default language. */
function /*void*/ langTag_xhtmlDom(&$arr) {
  $document = $arr['result'];
  $path = $arr['path'];

  dom_ripXhtmlNS($document);
  // Debugging aid: Write post-xhtmlDom data to _private.afterphp
  //file_put_contents(CACHE . $path . '_private.langtag', $document->saveXML());

  /* First pass: Find all language tags and remove them from the
     document. However, allow to re-add their contents back later: If an "en"
     element contains 3 children, then replace the element with 3 zero-length
     TextNode objects, and for each former child record a reference to its
     respective TextNode. */
  $languages = explode(' ', LANGUAGES);
  $orig = array(); // array(string langCode => array(int => origNodeRef))
  $origCount = array(); // langCode => int; Nr of non-<xx ignore=""> tags
  $repl = array(); // array(string langCode => array(int => textNodeRef))
  foreach ($languages as $lang) $origCount[$lang] = 0;
  foreach ($languages as $lang) {
    // Get list of <xx> elements for each language "xx"
    $tags = $document->getElementsByTagname($lang);
    // Some <xx> elements found
    for ($i = $tags->length - 1; $i >= 0; --$i) {
      $tag = $tags->item($i);
      // Give error if <xx> tags are nested
      if (langTag_isNestedInside($tag, $languages)) {
        $errNode = xhtmlTemplate_errNode($document, '"<' . $tag->tagName
          . '>" tag is nested inside another language tag, this is not '
          . 'possible');
        $tag->parentNode->replaceChild($errNode, $tag);
        continue;
      }
      if (!$tag->hasAttribute('ignore')) {
        ++$origCount[$lang];
        foreach ($languages as $l)
          if ($tag->hasAttribute($l)) ++$origCount[$l];
      }
      // Replace each <xx> tag with a number of empty replacement tags
      langTag_replaceLangTag($document, $tag, $orig, $repl, $languages);
    }
  } // end cycling through all configured languages for "xx"

  /* If <xx> tags found, let xhtmlFile_xhtmlDom write just one version by
     returning without setting $arr['finished'] = TRUE */
  $nonIgnoredTags = 0;
  foreach ($languages as $lang) $nonIgnoredTags += $origCount[$lang];
  if ($nonIgnoredTags == 0) return;

  /* Second pass: For each language, 1) re-add that language's tags, 2)
     create XHTML output and write to file, 3) disable tags again.  This
     behaviour would get us into trouble if entries are later added to the
     list of languages. For example, if a page has all its content in
     &lt;en&gt; and &lt;de&gt; variants, then also switching on "fr" would
     make the French version of the page completely empty. Consequently, do
     not write a file if it turns out during step 1 that there are no tags
     for this language in this document. 300 or 406 errors are prevented by
     our Apache config. */
  $docElem = $arr['result']->documentElement;
  $bodyElem = $docElem->firstChild;
  while ($bodyElem != NULL) { // Find <body> element
    if ($bodyElem instanceof DOMElement && $bodyElem->tagName == 'body') {
      $origHtmlClass = $bodyElem->getAttribute('class');
      break;
    }
    $bodyElem = $bodyElem->nextSibling;
  }
  foreach ($languages as $lang) {
    if ($origCount[$lang] == 0) {
      /* Skip languages which aren't present on the page, i.e. create no
         extra version. However, create symlinks e.g. from de-de to de. */
      if (strlen($lang) == 5 && $lang{2} == '-')
        xhtmlFile_link($path, substr($lang, 0, 2), $lang, 'langTag');
      continue; 
    }

    // Make contents of <$lang> visible
    langTag_toggleLangTags($document, $orig, $repl, $lang);

    /* Modify document: <html> becomes
       <html lang="en" xml:lang="en">, to <body> add class="lang-en" */
    $docElem->setAttribute('lang', $lang);
    if ($bodyElem != NULL)
      xhtmlTemplate_addElementClass($bodyElem, "lang-$lang");

    // Create page and write to cache
    $xhtmlFinal = $arr['result']->saveXML();
    xhtmlFile_write($path, $xhtmlFinal, $lang, 'langTag',
                    $arr['xmlcontent'] !== FALSE);

    // Reset class
    if ($bodyElem != NULL)
      $bodyElem->setAttribute('class', $origHtmlClass);
    // Make contents of <$lang> invisible again
    langTag_toggleLangTags($document, $orig, $repl, $lang);
  }

  $arr['finished'] = TRUE; // Do not process further subscribers, we are last
}
//______________________________________________________________________

/* Private helper function: Remove children of $tag (which is a language tag
   like &lt;de&gt;) and put references to them into $orig. Replace $tag with
   as many empty TextNodes as $tag had children, and put references to those
   into $repl. */
function langTag_replaceLangTag(/*DOMDocument*/ &$document,
    /*string*/ &$tag, &$orig, &$repl, /*array(string)*/ &$languages) {
  $childCount = $tag->childNodes->length;
  if ($childCount == 0) {
    // Huh, empty element?! Just remove it forever, do not record anything
    $tag->parentNode->removeChild($tag);
    return;
  }

  /* Collect all languages to which this <xx> tag applies. Usually, this will
     just be "xx", but for <xx yy=""> it will be "xx" and "yy". */
  $lang = $tag->tagName;
  $l = array($lang); // Init with "xx"
  foreach ($languages as $lang)
    if ($tag->hasAttribute($lang)) $l[] = $lang; // Add any "yy"

  for ($j = $childCount - 1; $j >= 0; --$j) {
    // Remove child from <xx>, record reference(s) to it
    $child = $tag->removeChild($tag->childNodes->item($j));
    foreach ($l as $lang) $orig[$lang][] = $child;
  }
  
  // Replace the now empty <xx> with the 1st replacement node
  $parent = $tag->parentNode;
  $replTag = $document->createTextNode('');
  if ($parent->replaceChild($replTag, $tag) === FALSE) {
    /* Just in case future versions of PHP's DOM don't allow text nodes
       everywhere, try an empty comment if the above failed. */
    $replTag = $document->createComment('');
    $parent->replaceChild($replTag, $tag);
  }
  
  // Record reference to 1st replacement node
  foreach ($l as $lang) $repl[$lang][] = $replTag;
  
  // Before the 1st replacement node, insert ($childCount-1) more nodes
  for ($j = $childCount - 1; $j > 0; --$j) {
    $tag = $replTag;
    $replTag = $document->createTextNode('');
    $ret = $parent->insertBefore($replTag, $tag);
    if ($ret !== FALSE) {
      $replTag = $ret;
    } else {
      $replTag = $document->createComment('');
      $replTag = $parent->insertBefore($replTag, $tag);
    }
    // ...and record reference(s) to each one
    foreach ($l as $lang) $repl[$lang][] = $replTag;
  } // end adding replacement nodes
}
//______________________________________________________________________

/* Private helper function: Toggle visibility of all content in a given
   language. */
function /*void*/ langTag_toggleLangTags(&$document, &$orig, &$repl, $lang) {
  /*
  $origCount = count($orig, COUNT_RECURSIVE);
  $replCount = count($repl, COUNT_RECURSIVE);
  if ($origCount !== $origCount) {
    $origC = count($orig); $replC = count($repl);
    trigger_error("langTag: BUG, orig ($origC,$origCount) != "
                  . "repl ($replC,$replCount)");
  }
  */
  foreach ($orig[$lang] as $i => $nodeA) {
    // $nodeA is not currently in $document, $nodeB is. Swap the two.
    $nodeB = $repl[$lang][$i];
    $nodeB->parentNode->replaceChild($nodeA, $nodeB);
    $orig[$lang][$i] = $nodeB;
    $repl[$lang][$i] = $nodeA;

    //$new = addslashes($nodeA->textContent);
    //$old = addslashes($nodeB->textContent);
    //syslog(LOG_DEBUG, "langTag: lang=$lang, new=\"$new\", old=\"$old\"");
  }
}
//______________________________________________________________________

/* Helper function: Return TRUE iff any of the parents of $elem is a tag in
   the supplied list.
   @para $elem The element to search upward from.
   @para $nodeNames An array of tag names to search for. Each array value
   is a tag name, such as "body".
   @return TRUE if any of the ancestors of $elem is a tag listed in
   $nodeNames. */
function /*bool*/ langTag_isNestedInside(DOMElement $elem,
                                         /*array(=>string)*/ $nodeNames) {
  while (TRUE) {
    $elem = $elem->parentNode;
    if ($elem === NULL) return FALSE;
    if (isset($elem->tagName)
        && array_search($elem->tagName, $nodeNames) !== FALSE)
      return TRUE;
  }
}
//______________________________________________________________________

/** Helper function: For the given DOMElement, go upwards in the DOM tree
    until a language tag is found. Then return the list of languages of that
    language tag, i.e. the language for which the DOMElement is
    displayed. Return FALSE if the DOMElement is not nested inside any
    language tag. Language tags must not be nested, so the result will only
    have >1 entries for a language tag like &lt;de en=""&gt; Each result
    array entry maps from the language string to TRUE */
function /*array(string=>TRUE)*/ langTag_langOfElem(DOMElement $elem) {
  $languages = explode(' ', LANGUAGES);
  while (TRUE) {
    $elem = $elem->parentNode;
    if ($elem === NULL) return FALSE;
    if (isset($elem->tagName)
        && array_search($elem->tagName, $languages) !== FALSE)
      break;
  }
  // Found language tag $elem
  $ret = array($elem->tagName => TRUE);
  foreach ($languages as $l)
    if ($elem->hasAttribute($l)) $ret[$l] = TRUE;
  return $ret;
}
//______________________________________________________________________

/** Create language-specific version of the tree below $node for the given
    $lang. This is done by modifying the tree below $node. All language
    elements which do not match $lang are deleted. All language elements
    which match $lang are themselves removed, but their content is kept in
    the tree. $node itself must not be a language tag.

    Note: For efficiency reasons, the language code in this file does not
    actually use this function, as that would require that we clone the
    entire document tree before generating each language version. This
    function does allow language tags to be nested. */
function /*void*/ langTag_filterLang(DOMElement $node, /*string*/ $lang) {
  static $languages = NULL;
  if ($languages == NULL) $languages = array_flip(explode(' ', LANGUAGES));

  $children = $node->childNodes;
  for ($i = $children->length - 1; $i >= 0; --$i) {
    $elem = $children->item($i);
    if (!($elem instanceof DOMElement)) continue;
    //syslog(LOG_DEBUG, "langTag_filterLang: recurse <".$elem->tagName.'>');
    langTag_filterLang($elem, $lang); // NB $node children may change here
    //syslog(LOG_DEBUG, "langTag_filterLang: recurse </".$elem->tagName.'>');
    if (!array_key_exists($elem->tagName, $languages)) continue;

    // Found a language tag
    //syslog(LOG_DEBUG, "langTag_filterLang: lang <" . $elem->tagName . '>');
    if ($elem->tagName == $lang || $elem->hasAttribute($lang)) {
      //syslog(LOG_DEBUG, "langTag_filterLang:   keeping content");
      /* Keep the element's content; move it before the element, then delete
         the element. */
      while ($elem->firstChild !== NULL) {
        $c = $elem->removeChild($elem->firstChild);
        $node->insertBefore($c, $elem);
      }
    }
    // Delete the element
    $node->removeChild($elem);
    //syslog(LOG_DEBUG, "langTag_filterLang: lang </" . $elem->tagName.'>');
  }
}

?>