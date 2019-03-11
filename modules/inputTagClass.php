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

/** @file This file subscribes to the xhtmlDom hook. For all .xhtml
    documents, it will search through the document for &lt;input
    type="foo"&gt; tags and add a class attribute with the same name as the
    type attribute's value. For example, the above becomes &lt;input
    type="foo" class="foo"&gt; This makes it easier to differentiate the
    different types of input tags from CSS. If the input tag already has a
    class attribute, another entry is added, i.e. &lt;input class="abc"
    type="text"&gt; becomes &lt;input class="abc text" type="text"&gt;. While
    perfectly correct HTML 4, this may cause problems with the CSS
    implementations of some older browsers.

    There are several ways of defining buttons; be sure to catch them all
    with your CSS:

<pre>
&lt;button type="submit" value="button-type-submit"&gt;But-Typ-Sub&lt;/button&gt;
&lt;button type="button" value="button-type-button"&gt;But-Typ-But&lt;/button&gt;
&lt;input type="button" value="input-type-button"&gt;
&lt;input type="image" src="blah.png" alt="input-type-image"&gt;
&lt;input type="submit" value="Verschicken"&gt;&lt;/p&gt;
</pre>

 */

hook_subscribe('xhtmlDom', 'inputTagClass_xhtmlDom');

/** Called by hook, looks for &lt;input/&gt; elements. */
function /*void*/ inputTagClass_xhtmlDom(&$arr) {
  $document = $arr['result'];
  $path = $arr['path'];
  $tags = $document->getElementsByTagname('input');
  for ($i = $tags->length - 1; $i >= 0; --$i) {
    $tag = $tags->item($i);
    if (!$tag->hasAttribute('type'))
      continue; // Weird, no "type" attribute
    $type = $tag->getAttribute('type');
    $class = $tag->getAttribute('class');
    if (strpos(" $class ", " $type ") !== FALSE)
      continue; // "class" attribute already set for this tag
    if ($class == '')
      $tag->setAttribute('class', $type);
    else
      $tag->setAttribute('class', "$class $type");
  }
}

?>