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

/** @file Handling of navigation menus. This file contains the code for
    creating the navigation tree (automatically or via user-supplied .navmenu
    files) and outputting the navigation information (using the
    &lt;navmenu/&gt; and &lt;breadcrumb/&gt; tags). */

/** @tag navmenu
    A &lt;navmenu/&gt; tag is substituted with a &lt;div&gt; with one or two
    children. The first one, which may not be present, is a &lt;ul
    class="breadcrumb"&gt;, the second (always present) a &lt;ul
    class="nested"&gt;.

    A range of attributes of the navmenu tag is copied to the &lt;div&gt;. By
    default, a class="navmenu" attribute is also added. See the definition of
    navmenuTag_copyAttributes() for details.

    Hierarchical menus can become deeply nested, and usually once there are
    more than three different levels (menu item, sub-item, sub-sub-item), the
    menu becomes too long, difficult to scan by humans ("is that item
    indented once or twice - what is its parent item?") and tends to overflow
    the horizontal space that is available on the page for it.

    For this reason, the &lt;navmenu/&gt; tag supports "graceful degradation"
    of the tree-like menu structure into a breadcrumb-like linear
    structure. By default, if there are more than 3 levels (use something
    like &lt;navmenu maxlevels="5"/&gt; to override this), only the tree for
    the three bottommost levels is generated fully. This is most easily
    visible with an example:

    If this is the full tree:
    <pre>
    * menu-item
      * sub-menu
        * sub-sub
          * and-another
            * yet-another
    * another-toplevel
    </pre>

    ...then with the default maximum of three levels the &lt;navmenu/&gt; tag
    will only output three nested &lt;ul&gt; lists:

    <pre>
    * menu-item 
    * sub-menu
&nbsp;
    * sub-sub
      * and-another
        * yet-another
    </pre>

    Note how "menu-item", "sub-menu" and "sub-sub" now appear at the same
    depth. In the output XHTML, the &lt;ul class="breadcrumb"&gt; contains
    "menu-item" and "sub-menu", the remaining entries are inside the &lt;ul
    class="nested"&gt;.

    Also note that "another-toplevel" has disappeared - only the chain of
    ancestors of the current document ("yet-another" in the example) remains
    in the menu.

    An interesting variant is to use maxlevels="1". In that case, the menu is
    split into two parts: One describes the path to the current menu level,
    the other the entries at the current level.

    navmenu supports another attribute: &lt;navmenu prunetop="1"/&gt; will
    not output any entries in the given number (1 in this case) top levels of
    the hierarchy. The default is 0, i.e. output the whole tree. With the
    example menu above, using prunetop="1" means that the entry "menu-item"
    would be omitted from the output and that "sub-menu" would be at the top
    of the &lt;ul&gt;. */
//______________________________________________________________________

/** @tag breadcrumb
    A &lt;breadcrumb/&gt; tag is substituted with a &lt;div&gt;. A range of
    attributes of the breadcrumb tag is copied to the &lt;div&gt;. By
    default, a class="breadcrumb" attribute is also added. See the definition
    of navmenuTag_copyAttributes() for details.

    A breadcrumb trail looks like "Home > Category > Whatever" with links for
    each word. The words are taken from the menu entries, they show the
    direct path from the homepage to the current page.

    The following attributes are supported by &lt;breadcrumb/&gt;:

    - <tt>minlevels="2"</tt> - the minimum number of entries in the
    breadcrumb trail before the &lt;div&gt; appears. With the default value
    of 2, no breadcrumb trail would appear on the homepage, it would only
    appear on "Home > Category" and lower pages.

    - <tt>src="/absolute/image/url.gif" alt="&amp;gt;"</tt> - Image to insert
    between any two entries, and alt text for the image. If the src attribute
    is not present, no image is inserted. If the alt attribute is not
    present, a default of alt="&gt;" is assumed. A space character is
    inserted before and after the &lt;img&gt; tag.

    - <tt>alt="&amp;gt;"</tt> - Text to insert between any two entries. If
    only the alt attribute (and no src attribute) is present, the alt text is
    simply inserted, with a space character before and after it. */
//______________________________________________________________________

/** @fileext .navmenu, .navmenu-php
    An index.navmenu file describes the menu entries which are used to create
    the menu for all pages in the same directory as the index.navmenu. The
    file is a text file which consists of one line for each menu entry. On
    the line, the relative URL of the entry is followed by one space and the
    HTML description of the entry. For example:

    <pre>
    ./ Overview
    news.xhtml &lt;em&gt;News!&lt;/em&gt;
    details.xhtml View details
    </pre>

    It is assumed that the file is UTF-8 encoded. If your editor does not
    support UTF-8, use e.g. "&amp;ouml;" for a "&ouml;" character.

    There are several different kinds of .navmenu files:

    - Non-executable .navmenu files and executable .navmenu-php files with
      PHP code - <a href="#navmenu-php">see below</a>
      <!-- /* -->

    - index.navmenu(-php) files, which supply the menu entries for a
      directory

    - <i>filename.xhtml</i>.navmenu(-php) files, which supply the menu
      entries only for a single .xhtml or .xhtml-php file

    - subtree.navmenu(-php) files, which supply the menu entries for a
      directory and (recursively) all child directories it contains. The
      subtree.navmenu-php is the more useful one, it allows you to filter all
      navmenu data below the current directory.

    Exceptions to the above format of the file: If the description is "UP"
    (for example, the line reads "../../ UP"), the URL is the relative URL of
    the parent menu. Actually, the URL can be that of a directory, of a file
    in the directory or directly of another .navmenu file. Be careful not to
    create loops, e.g. with entries like "./ UP". Note that using this
    feature will often result in non-intuitive behaviour of the menu
    structure - for example, when clicking on a link which is associated with
    the "UP" entry, the menu on the new page may look completely
    different. If no "UP" entry is present in the .navmenu file, an entry of
    "../ UP" is assumed, i.e. look for the parent menu items in the parent
    directory.

    If any line in a menu file reads "TOP", this has special meaning: The
    current directory is considered the top-level menu, i.e. its entries are
    the site's main navigation entries. The TOP declaration is not necessary
    in the SVN repository's top-level directory, it is automatically assumed
    there. The "TOP" line thus overrides the default "../ UP" setting with
    the information "there is no parent menu".

    .navmenu files may also contain comment lines: All lines whose first
    character is a "#" (hash sign) are ignored.
    <!-- /* -->

    It is possible to override the menu information for individual files:
    Apart from menu files named "index.navmenu", you can also add files
    called e.g. "foo.xhtml.navmenu-php" or "foo.xhtml.navmenu" which will
    ONLY be used during the menu generation of foo.xhtml. The "UP"
    declaration is the only way to append child menu entries to a non-index
    file. For example, if the file x.xhtml has the child y.xhtml,
    x.xhtml.navmenu needs to have two lines, one reading "./ UP" and one for
    y.xhtml, e.g. "y.xhtml Y-File".

    <h3>Overview of Menu Generation</h3>

    If a menu is needed for a certain directory, it is generated using the
    following algorithm:

    1) A submenu.navmenu-php file is searched for in the current
    directory and then successively in all directories above it, up to the
    top of the CACHE directory. As soon as one is found, it is assumed to
    contain PHP code and executed to output suitable menu data. Thus, the
    subtree file is used for <em>every</em> file anywhere below the directory
    containg the subtree file, and all .navmenu or .navmenu-php files inside
    it are ignored!

    2) If an index.navmenu-php file is present in the current directory, it
    is executed to output menu data.

    3) If an index.navmenu file is present, it is assumed to contain menu
    data.

    4) If no index.navmenu-php is present, the menu is automatically
    generated by scanning the directory for .xhtml(-php) files and
    directories, and the basename of the file is used as the
    description. "index.xhtml(-php)" is a special case here, it is considered
    a member of the parent directory. For example, if a directory contains
    the files "index.xhtml", "ignored.html" (NB no .xhtml extension!),
    "news.xhtml", "foo-bar.xhtml" and a directory "somedir", the following
    menu is generated. The order of the lines is determined by sorting the
    descriptions alphabetically:

    <pre>
    foo-bar.xhtml Foo-bar
    news.xhtml News
    somedir/ Somedir
    </pre>

    <h3 id="navmenu-php">Executable navmenu-php files</h3>

    For .navmenu-php files, the data is executed with eval(), see
    navmenuTag_getMenuData() and xhtmlTemplate_getContentOrEval(). The $info
    array passed to the eval()ed code contains:<br>

    - 'navmenuPath' => CACHE-absolute path of .navmenu-php file (without
    _private.orig extensions)

    - 'path' => CACHE-absolute path of the .xhtml file for which the menu is
    being generated

    - 'dir' => CACHE-absolute path of the directory containing 'path'

    - 'curPath' => If an UP directive directly specifies a .navmenu(-php)
    file, this is equal to 'navmenuPath'. Otherwise, it is the CACHE-absolute
    path of the directory to be indexed, WITH a trailing slash.

    - 'curDir' => CACHE-absolute path of the directory containing 'curPath',
    without trailing slash. In contrast to 'curPath', this is always a
    directory.

    Note that a .navmenu-php may be executed repeatedly during the menu
    generation for a single document, once for each nesting level of the
    menu. When the code is executed, the current directory is set to the
    directory inside CACHE which is also specified as 'curDir'.

    In the case of subtree.navmenu-php, it is often not desirable to have the
    data from index.navmenu(-php) files completely ignored. Thus, there is a
    mechanism through which the subtree file can indicate that it should be
    skipped during menu generation for a directory, and that destrictor
    should look for index.navmenu(-php) instead. To make this happen, the
    subtree file must simply not create any output, e.g. it can exit via
    "return;". (Alternatively, it can of course load and manually output the
    content of other navmenu files.)

    If you only want to use your subtree.navmenu-php in the case where
    <em>no</em> index.navmenu(-php) is present, use the following code at the
    start of the file:

    <pre>
&lt;?php
if (depend_dependencyExists($info['path'],
                            $info['curDir'] . '/index.navmenu-php')
    || depend_dependencyExists($info['path'],
                               $info['curDir'] . '/index.navmenu'))
  return;
?&gt;
    </pre>

    <h3>A "Home" Link at the top level of the menu hierarchy</h3>

    You may want the following feature: The homepage should have its own link
    at the top of the generated menu, but it should not be the only link at
    the top of the hierarchy. For example, while the site organization would
    suggest the following menu -

    <pre>
    - Home
      - About
      - Download
      - Contact
    </pre>

    the menu should actually be displayed like this:

    <pre>
    - Home
    - About
    - Download
    - Contact
    </pre>

    One possibility is simply to put a link to ./ (for "Home") into the
    toplevel index.navmenu file. This will give the latter menu
    layout. However, it has the disadvantage that "Home" will no longer
    appear correctly in the output of &lt;breadcrumb/&gt;: In the first case,
    &lt;breadcrumb/&gt; will produce the usually desired output of "Home &gt;
    Download" on the download page, in the second case it will only be
    "Download".

    The solution to this problem involves the following steps:

    - Create a hierarchy above the top directory's level. For example, this
      is possible by adding the line "top.navmenu UP" to the file
      index.navmenu. The file top.navmenu only consists of a single line
      "./ Home".

    - Prevent the new toplevel "Home" from being output by &lt;navmenu/&gt;
      using its prunetop attribute, like this: &lt;navmenu prunetop="1"/&gt;

    - Now add another entry for the home page at the level below the top, by
      also adding the line "./ Home" to the file index.navmenu.
*/
//______________________________________________________________________

require_once(MODULES . '/tidy.php'); // Need this first

/* This tag used to be called just <menu/>, but HTML tidy will remove that
   tag from any HTML given to it, considering it a "deprecated tag". */
tidy_addTag('navmenu', TRUE);
tidy_addTag('breadcrumb', TRUE);

/* When a .navmenu file is committed, actually put a .navmenu_private.orig
   file into CACHE, to prevent public access to the file contents. The .orig
   extension is not registered with Apache, so the file will not be
   considered during MultiViews content negotiation. */
hook_subscribe('fileAdded_navmenu', 'navmenuTag_fileAdded_navmenu');
hook_subscribe('fileAdded_navmenu-php', 'navmenuTag_fileAdded_navmenu');
hook_subscribe('fileUpdated_navmenu', 'navmenuTag_fileUpdated_navmenu');
hook_subscribe('fileUpdated_navmenu-php', 'navmenuTag_fileUpdated_navmenu');

/** Called by hook, causes dependencies to be regenerated. */
function navmenuTag_fileAdded_navmenu(SvnChange $c) {
  // Two dependency changes: The dir listing changes and the new file "changes"
  depend_fileChanged(dirname($c->path) . '/');
  depend_fileChanged($c->path);
  $f = CACHE . $c->path . '_private.orig';
  svn_catToFile($c->path, $f);
  $c->finished = TRUE;
}
/** Called by hook, causes dependencies to be regenerated. */
function navmenuTag_fileUpdated_navmenu(SvnChange $c) {
  depend_fileChanged($c->path);
  $f = CACHE . $c->path . '_private.orig';
  svn_catToFile($c->path, $f);
  $c->finished = TRUE;
}
//______________________________________________________________________

/** Given the absolute URL of a page ($baseURL) and the contents of an anchor
    on that page (absolute or relative), return the normalized absolute URL
    of the link destination. None of the arguments needs to be normalized. */
function /*string*/ navmenuTag_absoluteURL(/*string*/ $baseURL,
                                           /*string*/ $anchor) {
  if ($baseURL{0} != '/') return FALSE;
  if ($anchor{0} == '/')
    $b = $anchor;
  else if ($anchor{0} == '#')
    $b = $baseURL . $anchor;
  else
    $b = dirname($baseURL . 'x') . '/' . $anchor;
  return normalizePath($b);
}
//______________________________________________________________________

class MenuTree {
  // Data for entry: '<a href="$path">$desc</a>'
  public /*string*/ $path; // Equivalent to site-absolute URL
  public /*string*/ $desc; // Description for link, may contain markup
  public /*array(MenuTree)*/ $child; // or NULL if leaf
  public /*string*/ $class; // class attribute, or NULL if none

  function __construct($path, $desc) {
    $this->path = $path;
    $this->desc = $desc;
    $this->child = NULL;
    $this->class = NULL;
  }
}
//________________________________________

/** Return an array of MenuTree objects for the directory which contains the
    object at $path. This does not return the entire website's navigation
    structure, only the part of it which is obtained by going up to the top
    from $path.

    $path='/' will not give what you expect; use '/index.xhtml' instead. In
    general, this function distinguishes between paths which end in a
    filename (/a/b/index.xhtml) and URL-like paths which end in a slash
    (/a/b/). In the first case, /a/b/index.navmenu will be the lowest-level
    navmenu file, in the second case it will be /a/index.navmenu.

    Throws an Exception in case of errors. */
function /*array(MenuTree)*/ navmenuTag_getMenu(/*string*/ $path) {
  /* Records a reference to the last array(MenuTree) that we created while
     going upward through the site. */
  $lastChildMenu = NULL;
  $lastPath = NULL;
  $menuPathsSeen = array(); // To avoid inf. loops, record .navmenu files seen
  $regenPath = $path; // $regenPath: Original file path, for dep info

  while (TRUE) { // For each menu nesting level
    dlog('navmenuTag', "_getMenu: menu for $path");
    //____________________

    // 1) Obtain .navmenu data in $menuData
    try {
      $menuData = navmenuTag_getMenuData($path, $navmenuPath, $regenPath);
      dlog('navmenuTag', "_getMenu: ----------------------- $path");
    } catch (Exception $e) {
      throw new Exception("While creating navmenu: " . $e->getMessage());
    }
    //____________________

    // Remove UTF-8 byte order mark
    if (substr($menuData, 0, 3) == "\xef\xbb\xbf")
      $menuData = substr($menuData, 3);

    /* 2) Scan menu data in $menuData. Format is a relative URL followed by a
       single space followed (until end of line) by an HTML description. */
    $menu = array();
    $lineNr = 0;
    $up = '../'; // relative URL of parent document
    $menuTree = NULL;
    foreach (explode("\n", $menuData) as $line) {
      ++$lineNr;
      $line = rtrim($line, "\r");
      if (empty($line) || $line{0} == '#') continue;
      if ($line == 'TOP') { $up = ''; continue; }
      $n = strpos($line, ' ');
      if ($n === FALSE || $n == 0)
        throw new Exception("Error during navmenu generation in "
          . "\"$navmenuPath\", line $lineNr of menu data: No space separator");
      $url = substr($line, 0, $n);
      $desc = substr($line, $n + 1);
      if (trim($desc) == 'UP') {
        $up = $url;
        continue;
      }
      $itemPath = navmenuTag_absoluteURL($navmenuPath, $url);
      if ($itemPath === FALSE)
        throw new Exception("Error during navmenu generation in "
          . "\"$navmenuPath\", line $lineNr of menu data: URL of .navmenu "
          . "file and menu entry URL \"$url\" cannot be combined to form "
          . "a legal URL");
      //dlog('navmenuTag', "_getMenu: " . dirname($navmenuPath)
      //     . "|$url = $itemPath");
      $menuTree = new MenuTree($itemPath, $desc);
      // We must allow >1 menu entries with identical URLs
      if (array_key_exists($itemPath, $menu))
        $menu[] = $menuTree;
      else
        $menu[$itemPath] = $menuTree;
      dlog('navmenuTag', "_getMenu: new menu entry for ".$menuTree->desc);
    }
    //____________________

    /* 3) Append child menu to its parent item. This means some guesswork, as
       the user only supplies a pointer (via UP) to the entire parent menu,
       he does not identify the menu item to which the child should be
       attached. FIXME: Fragment identifiers like "foo.xhtml#section1" are
       <!-- /* --> not taken into account, will be treated as URLs which are
       completely different from "foo.xhtml". FIXME: "foo.xhtml.xhtml-php"
       can also be viewed with the URL "foo.xhtml" with MultiViews, treat
       them as related. */
    while ($lastChildMenu !== NULL) { // will never loop
      if (array_key_exists($lastPath, $menu)) {
        $menu[$lastPath]->child = $lastChildMenu;
        break;
      }
      $lastDirPath = dirname($lastPath . 'x') . '/';
      if (array_key_exists($lastDirPath, $menu)) {
        $menu[$lastDirPath]->child = $lastChildMenu;
        break;
      }
      $append = NULL;
      foreach ($lastChildMenu as $itemPath => $m)
        if (array_key_exists($itemPath, $menu)) $append = $menu[$itemPath];
      if ($append !== NULL) {
        $append->child = $lastChildMenu;
        break;
      }
      if ($menuTree !== NULL) {
        // If something fishy is going on, just append child to last entry
        syslog(LOG_DEBUG, 'navmenuTag_getMenu: Cannot find a parent '
               . 'menu entry for "' . $lastPath
               . '", appending child menu to "' . $menuTree->desc . '"');
        //foreach ($menu as $p => $m)
        //  syslog(LOG_DEBUG, "navmenuTag_getMenu:      $p => $m");
        $menuTree->child = $lastChildMenu;
      }
      break;
    }
    //____________________

    // 4) Go up one step in menu hierarchy
    if (empty($up)) return $menu;
    //dlog('navmenuTag', "_getMenu: Up to ".$navmenuPath.' + '.$up);
    $lastPath = $path;
    $path = navmenuTag_absoluteURL($navmenuPath, $up);
    dlog('navmenuTag', "_getMenu: Going up to $path");
    if ($path == FALSE) return $menu;
    if (array_key_exists($path, $menuPathsSeen))
      throw new Exception("Error during navmenu generation in "
        . "\"$navmenuPath\": Infinite loop of \"UP\" declarations detected.");
    $menuPathsSeen[$path] = TRUE;
    // Never insert an empty menu array into the tree
    if (!empty($menu)) $lastChildMenu = $menu;
  }
}
//________________________________________

/** Return the menu data for the given path. $path is CACHE-absolute. For a
    path which ends in ".navmenu", only the file is checked. An Exception
    with an error message is thrown if it does not exist, or if there is
    another problem (e.g. parse error of .navmenu-php code).

    For a path in the form /dir/foo.xhtml, the function first tries
    /dir/foo.xhtml.navmenu, then /dir/index.navmenu. If neither one is found,
    a menu is automatically generated from all .xhtml files and
    subdirectories found inside /dir (except for index.xhtml).

    $navmenuPath is overwritten with the actual path of the file,
    e.g. "/dir/index.navmenu". In the case of the auto-generated menu, a fake
    "autoindex.navmenu" name is returned, e.g. "/dir/autoindex.navmenu" for a
    $path value of "/dir/". In the case of subtree-generated menu, a fake
    "subtree.navmenu" name is returned which (important!) is not in the
    directory containing the subtree.navmenu-php file, but in the directory
    for which the subtree file was told to generate data.

    @param $path Path for which to return the menu data. Append a '/' to
    directory names
    @param $navmenuPath Output parameter: CACHE-absolute path of file whose
    data is actually returned, without _private.orig extensions
    @param $regenPath CACHE-absolute path of file in which the menu data will
    eventually end up; most of the time, an .xhtml file. This is needed for
    creating the correct dependency information. Pass NULL to prevent
    generation of dep info. */
function /*string*/ navmenuTag_getMenuData(/*string*/ $path, &$navmenuPath,
                                           /*string*/ $regenPath) {
  $info = array('path' => $regenPath,
                'dir' => dirname($regenPath),
                'curPath' => $path,
                'curDir' => dirname($path . 'x'));
  dlog('navmenuTag', "_getMenuData: Creating menu for $regenPath, current menu level $path");
  // Via UP, nonsensical paths can be supplied, flag them as errors here
  $chdir = CACHE . dirname($path . 'x');
  if (!is_dir($chdir))
    throw new Exception("Menu file \"$path\" not found "
                        . "(non-existent directory)");
  // Chdir once here rather than passing an arg to getContentOrEval()
  chdir($chdir);

  // Directly return contents of .navmenu file, or error
  if (substr($path, -12) == '.navmenu-php'
      || substr($path, -8) == '.navmenu') {
    if (!depend_dependencyExists($regenPath, "$path"))
      throw new Exception("Menu file \"$path\" not found");
    $info['navmenuPath'] = $navmenuPath = $path;
    return xhtmlTemplate_getContentOrEval(
      CACHE . $navmenuPath . '_private.orig', $info);
  }

  // It is possible to override index.navmenu using foo.xhtml.navmenu
  if (substr($path, -1) != '/') {
    dlog('navmenuTag', "_getMenuData:   Trying $path.navmenu(-php)");
    if (depend_dependencyExists($regenPath, "$path.navmenu-php")) {
      $info['navmenuPath'] = $navmenuPath = "$path.navmenu-php";
      dlog('navmenuTag', "_getMenuData:   OK, found $navmenuPath");
      return xhtmlTemplate_getContentOrEval(
        CACHE . $navmenuPath . '_private.orig', $info);
    } else if (depend_dependencyExists($regenPath, "$path.navmenu")) {
      $navmenuPath = "$path.navmenu";
      dlog('navmenuTag', "_getMenuData:   OK, found $navmenuPath");
      return xhtmlTemplate_getContentOrEval( // Important: Turn <? into &lt;?
        CACHE . $navmenuPath . '_private.orig', $info);
    }
  }

  // Dir name for current object ($path may end with '/' for a dir)
  $dirname = dirname($path . 'x');
  $dir = $dirname; // only for scanning upward
  if ($dirname == '/') $dirname = '';

  // Scan upward for subtree.navmenu-php only (not .navmenu!)
  while (TRUE) {
    if ($dir == '/') $dir = '';
    dlog('navmenuTag', "_getMenuData:   Trying $dir/subtree.navmenu-php");
    if (depend_dependencyExists($regenPath, "$dir/subtree.navmenu-php")) {
      $info['navmenuPath'] = $navmenuPath = "$dir/subtree.navmenu-php";
      $navmenuData = xhtmlTemplate_getContentOrEval(
        CACHE . "$dir/subtree.navmenu-php_private.orig", $info);
      // If .navmenu-php returns empty data, switch back to .navmenu/dir scan
      if ($navmenuData !== '') {
        dlog('navmenuTag', "_getMenuData:   OK, $navmenuPath returned menu data");
        // Make $navmenuPath include the right directory
        $navmenuPath = "$dirname/subtree.navmenu";
        return $navmenuData;
      }
      dlog('navmenuTag', "_getMenuData:   $navmenuPath created no output");
      break;
    }
    if ($dir == '') break;
    $dir = dirname($dir);
  }

  // Try index.navmenu-php
  dlog('navmenuTag', "_getMenuData:   Trying $dirname/index.navmenu(-php)");
  if (depend_dependencyExists($regenPath, "$dirname/index.navmenu-php")) {
    $info['navmenuPath'] = $navmenuPath = "$dirname/index.navmenu-php";
    dlog('navmenuTag', "_getMenuData:   OK, found $navmenuPath");
    $navmenuData = xhtmlTemplate_getContentOrEval(
      CACHE . "$dirname/index.navmenu-php_private.orig", $info);
    if ($navmenuData != '') return $navmenuData;
  }

  // By default, index.navmenu is used
  if (depend_dependencyExists($regenPath, "$dirname/index.navmenu")) {
    $navmenuPath = "$dirname/index.navmenu";
    dlog('navmenuTag', "_getMenuData:   OK, found $navmenuPath");
    return xhtmlTemplate_getContentOrEval( // Important: Turn <? into &lt;?
      CACHE . "$dirname/index.navmenu_private.orig", $info);
  }
  
  // No .navmenu file found, so scan dir and create our own
  dlog('navmenuTag', "_getMenuData:   No navmenu data found, creating dir listing");
  depend_addDependency($regenPath, $dirname . '/');
  $menuData = array();
  $navmenuPath = $dirname . '/autoindex.navmenu';
  $dh = opendir(CACHE . $dirname);
  if ($dh) {
    while ($dh && FALSE !== ($f = readdir($dh))) {
      if ($f == '.' || $f == '..') continue;
      $ff = htmlspecialchars(strtoupper($f{0}) . substr($f, 1));
      if (is_dir(CACHE . $dirname . '/' . $f)) {
        $menuData["$f/"] = $ff;
        continue;
      }
      # ae &#228, oe &#246, ue &#252, Ae &#196, Oe &#214, Ue &#220
      /* Important: Do not create an entry for index.xhtml - its entry belongs
         to the parent directory's menu. */
      if ($f == 'index' || substr($f, 0, 6) == 'index.') continue;
      static $ext = '.xhtml_private.orig';
      static $extPhp = '.xhtml-php_private.orig';
      static $privOrig = '_private.orig';
      if (substr($f, -strlen($extPhp)) == $extPhp) {
        $menuData[substr($f, 0, -strlen($privOrig))] =
          substr($ff, 0, -strlen($extPhp));
      } else if (substr($f, -strlen($ext)) == $ext) {
        $menuData[substr($f, 0, -strlen($privOrig))] =
          substr($ff, 0, -strlen($ext));
      }
    }
    closedir($dh);
  }
  asort($menuData);
  $ret = '';
  foreach ($menuData as $url => $desc)
    $ret .= "$url $desc\n";
  return $ret;
}
//______________________________________________________________________

/** Given a menu structure, return its depth - 1, i.e. the maximum number of
    objects which are traversed from the root to the lowest leaf. For
    example, if $menu only consists of entries whose "child" field is NULL,
    the returned value is 1. If $menu is NULL, FALSE or empty, 0 is
    returned. */
function /*int*/ navmenuTag_menuDepth(/*array(MenuTree)*/ $menu) {
  if ($menu === NULL || $menu === FALSE || empty($menu)) return 0;
  $n = 0;
  foreach ($menu as $menuTree) {
    if ($menuTree->child === NULL) continue;
    $nn = navmenuTag_menuDepth($menuTree->child);
    if ($n < $nn) $n = $nn;
  }
  return ++$n;
}
//______________________________________________________________________

hook_subscribe('xhtmlDom', 'navmenuTag_xhtmlDom');

/** Called by hook, looks for &lt;navmenu/&gt; and &lt;breadcrumb/&gt; tags,
    replaces them with the appropriate markup. */
function navmenuTag_xhtmlDom(&$arr) {
  $document = $arr['result'];
  $path = $arr['path'];

  $tags = $document->getElementsByTagname('navmenu');
  $tagsb = $document->getElementsByTagname('breadcrumb');
  if ($tags->length == 0 && $tagsb->length == 0) return;

  $errMsg = ''; $menu = NULL;
  // Create menu
  try { $menu = navmenuTag_getMenu($path); }
  catch (Exception $e) { $errMsg = $e->getMessage(); }
  $menuDepth = navmenuTag_menuDepth($menu);

  for ($i = $tags->length - 1; $i >= 0; --$i) {
    $tag = $tags->item($i);
    if ($errMsg) {
      // Menu could not be generated, just output error message instead
      $newNode = xhtmlTemplate_errNode($document, $errMsg);
    } else if ($tag->hasChildNodes()) {
      $newNode = xhtmlTemplate_errNode($document,
                                       '<navmenu/> tag must be empty');
    } else {
      $navmenu = $menu;
      $prunetop = 0;
      if ($tag->hasAttribute('prunetop')) {
        $prunetop = max(0, intval($tag->getAttribute('prunetop')));
        $navmenu = navmenuTag_pruneTop($menu, $prunetop);
      }
      $maxlevels = 3;
      if ($tag->hasAttribute('maxlevels'))
        $maxlevels = max(1, intval($tag->getAttribute('maxlevels')));
      // Recursively create <ul> tree, return a <div>
      $newNode = navmenuTag_xhtmlTrimmedMenuTreeFor($document, $navmenu, $path,
                                          $menuDepth - $prunetop - $maxlevels);
      // Add class="navmenu", copy attributes like id, style, onclick
      navmenuTag_copyAttributes($tag, $newNode, 'navmenu');
    }
    $tag->parentNode->replaceChild($newNode, $tag);
  }
  //____________________

  if ($tagsb->length == 0) return;

  if ($errMsg) {
    // Breadcrumb could not be generated, just output error message instead
    for ($i = $tagsb->length - 1; $i >= 0; --$i) {
      $tag = $tagsb->item($i);
      $newNode = $document->createElement('div', $errMsg);
      navmenuTag_copyAttributes($tag, $newNode, 'breadcrumb');
      $tag->parentNode->replaceChild($newNode, $tag);
    }
    return;
  }

  for ($i = $tagsb->length - 1; $i >= 0; --$i) {
    $tag = $tagsb->item($i);

    $navmenu = $menu;
    if ($tag->hasAttribute('prunetop')) {
      $navmenu = navmenuTag_pruneTop($menu,
           max(0, intval($tag->getAttribute('prunetop'))));
    }
    $breadcrumb = navmenuTag_xhtmlBreadcrumbFor($document, $navmenu, $path,
                                                NULL);
    $minlevels = 2;
    if ($tag->hasAttribute('minlevels'))
      $minlevels = max(1, intval($tag->getAttribute('minlevels')));
    // If too few levels, just delete the <breadcrumb/>
    $breadcrumbLen = ($breadcrumb === NULL ?
                      0 : $breadcrumb->childNodes->length);
    if ($breadcrumbLen < $minlevels) {
      $tag->parentNode->removeChild($tag);
      continue;
    }
    if ($tag->hasChildNodes()) {
      $newNode = xhtmlTemplate_errNode($document,
                                       '<breadcrumb/> tag must be empty');
      $tag->parentNode->replaceChild($newNode, $tag);
      continue;
    }

    // Replace <breadcrumb/> with <div class="breadcrumb">
    $newNode = $document->createElement('div');
    navmenuTag_copyAttributes($tag, $newNode, 'breadcrumb');
    $tag->parentNode->replaceChild($newNode, $tag);
    if ($tag->hasAttribute('alt'))
      $alt = $tag->getAttribute('alt');
    else
      $alt = '>';
    $img = FALSE;
    if ($tag->hasAttribute('src')) {
      $img = $document->createElement('img');
      $img->setAttribute('src', relativeURL($path, $tag->getAttribute('src')));
      $img->setAttribute('alt', $alt);
    }
    // Copy over $breadcrumb's children
    for ($j = 0; $j < $breadcrumbLen; ++$j) {
      $breadcrumbChild = $breadcrumb->childNodes->item($j);
      $newNode->appendChild($breadcrumbChild->cloneNode(TRUE));
      if ($j == $breadcrumbLen - 1) break;
      $newNode->appendChild($document->createTextNode(' '));
      if ($img)
        $newNode->appendChild($img->cloneNode(TRUE));
      else
        $newNode->appendChild($document->createTextNode($alt));
      $newNode->appendChild($document->createTextNode(' '));
    }
  }
}
//______________________________________________________________________

/** Given a menu structure, remove the $n top levels of the hierarchy. It is
    assumed that the menu structure only contains one deep path, i.e. at each
    level, only one of the child menu entries has further sub-entries. If $n
    levels cannot be removed because the menu is not that deep, then the
    lowest level is returned, it consists only of child-less menu entries. */
function /*array(MenuTree)*/ navmenuTag_pruneTop(/*array(MenuTree)*/ $menu,
                                                 /*int*/ $n) {
  $m = $menu;
  for ($i = 0; $i < $n; ++$i) {
    $mChild = NULL;
    foreach ($m as $menuTree) {
      if ($menuTree->child !== NULL) {
        $mChild = $menuTree->child;
        break;
      }
    }
    if ($mChild === NULL) return $m;
    $m = $mChild;
  }
  return $m;
}
//______________________________________________________________________

/** Copy a range of frequently used attributes from $srcNode to
    $destNode. The idea is that $srcNode is an extension tag
    (e.g. &lt;navmenu/&gt;) and $destNode the XHTML generated by the tag
    (e.g. a &lt;div&gt; for the navmenu).

    The following attributes are copied if they are present in $srcNode: id,
    style, title, lang, dir, onclick, ondblclick, onmousedown, onmouseup,
    onmouseover, onmousemove, onmouseout, onkeypress, onkeydown, onkeyup,
    onload

    A class="$defaultClass" attribute is also added to $destNode. This can be
    overriden by specifying a different class attribute in the $srcNode. If
    the resulting value of the attribute is empty, no class attribute is
    added to $destNode at all. Thus, no attribute is added if $srcNode
    contains class="", and no attribute is added if $defaultClass is
    empty. */
function /*void*/ navmenuTag_copyAttributes(DOMElement $srcNode,
    DOMElement $destNode, /*string*/ $defaultClass = '') {
  static $attrList = array('id', 'style', 'title', 'lang', 'dir', 'onclick',
                           'ondblclick', 'onmousedown', 'onmouseup',
                           'onmouseover', 'onmousemove', 'onmouseout',
                           'onkeypress', 'onkeydown', 'onkeyup', 'onload');
  foreach ($attrList as $attr) {
    if ($srcNode->hasAttribute($attr))
      $destNode->setAttribute($attr, $srcNode->getAttribute($attr));
  }
  if ($srcNode->hasAttribute('class')) {
    $classVal = $srcNode->getAttribute('class');
    if ($classVal != '') $destNode->setAttribute('class', $classVal);
  } else if ($defaultClass != '') {
    $destNode->setAttribute('class', $defaultClass);
  }
}
//______________________________________________________________________

/** Return DOM subtree for given menu structure. The returned DOMElement is
    always a &lt;div&gt; whose only child is a &lt;ul
    class="nested"&gt;. This is for consistency with
    navmenuTag_xhtmlTrimmedMenuTreeFor() below.
    @param $d The document to create the tree for
    @param $menu The menu structure
    @param $path The path of the "current" page, or NULL if not applicable.
    @param $atTop Used internally, always pass TRUE */
function /*DOMElement*/ navmenuTag_xhtmlMenuTreeFor(
    DOMDocument $d, /*array(MenuTree)*/ $menu, /*string*/ $path,
    /*bool*/ $atTop = TRUE) {
  $ul = $d->createElement('ul');
  //$ul->setAttribute('depth', navmenuTag_menuDepth($menu));
  foreach ($menu as $menuTree) {
    // Append to the <ul> something like <li><a href="./">Foo</a></li>
    $a = navmenuTag_menuAnchor($d, $menuTree, $path);
    $li = $d->createElement('li');
    $li->appendChild($a);
    $ul->appendChild($li);
    // Recurse into child menus
    if ($menuTree->child !== NULL)
      $li->appendChild(navmenuTag_xhtmlMenuTreeFor($d, $menuTree->child, $path,
                                               FALSE));
  }
  if (!$atTop) return $ul;
  // Finished - now create a wrapping <div> and add class="nested" to top <ul>
  $ul->setAttribute('class', 'nested');
  $div = $d->createElement('div');
  $div->appendChild($ul);
  return $div;
}
//______________________________________________________________________

/** Return DOM subtree for given menu structure. The returned DOMElement is
    always a &lt;div&gt; with one or two children. The first one, which may
    not be present, is a &lt;ul class="breadcrumb"&gt;, the second (always
    present) a &lt;ul class="nested"&gt;. If $skipTopLevels is 0, the entire
    tree is output, fully nested. For a value of 1, the first hierarchy level
    is cut off and one top-level entry put into the &lt;ul
    class="breadcrumb"&gt;. For a value of 2, the breadcrumb list
    additionally contains the first breadcrumb entry's child, etc.

    @param $d The document to create the tree for
    @param $menu The menu structure
    @param $path The path of the "current" page, or NULL if not applicable.
    @param $skipTopLevels Number of nesting levels to suppress before the
    whole tree is output. A non-positive value will cause the whole tree to
    be output. */
function /*DOMElement*/ navmenuTag_xhtmlTrimmedMenuTreeFor(
    DOMDocument $d, /*array(MenuTree)*/ $menu, /*string*/ $path,
    /*int*/ $skipTopLevels) {
  --$skipTopLevels;
  if ($skipTopLevels < 0)
    return navmenuTag_xhtmlMenuTreeFor($d, $menu, $path);

  foreach ($menu as $menuTree) {
    if ($menuTree->child === NULL) continue;
    $div = navmenuTag_xhtmlTrimmedMenuTreeFor($d, $menuTree->child, $path,
                                          $skipTopLevels);
    if ($div === NULL) continue;
    $a = navmenuTag_menuAnchor($d, $menuTree, $path);
    $li = $d->createElement('li');
    $li->appendChild($a);
    if ($div->childNodes->length == 1) {
      // Add a <ul class="breadcrumb"> as the <div>'s first child
      $ulBread = $d->createElement('ul');
      $ulBread->setAttribute('class', 'breadcrumb');
      $div->insertBefore($ulBread, $div->firstChild);
    }
    $ulBread = $div->firstChild;
    $ulBread->insertBefore($li, $ulBread->firstChild);
    return $div;
  }
  return NULL;
}
//______________________________________________________________________

/** Output a "breadcrumb trail" of links, in the form of a &lt;div
    class="breadcrumb"&gt; which contains a number of anchor tags which are
    separated by the contents of $separatorNode (pass NULL to use no
    separating markup). The tags for $separatorNode itself will not appear in
    the output, only its content. Comparable to
    navmenuTag_xhtmlTrimmedMenuTreeFor() with a high $skipTopLevels value. */
function /*DOMElement*/ navmenuTag_xhtmlBreadcrumbFor(
    DOMDocument $d, /*array(MenuTree)*/ $menu, /*string*/ $path,
    /*DOMNode|NULL*/ $separatorNode) {
  /* Works with arbitrary trees, not just ones that only have one path to the
     current document in them */
  foreach ($menu as $menuTree) {
    $menuPath = $menuTree->path;
    if (substr($menuTree->path, -1) == '/') $menuPath .= 'index';
    if ($path == $menuTree->path
        || $path == $menuPath
        || substr($path, 0, strlen($menuPath) + 1) == $menuPath . '.') {
      // We have found the right branch of the menu, start generating
      $div = $d->createElement('div');
      $div->setAttribute('class', 'breadcrumb');
      $a = navmenuTag_menuAnchor($d, $menuTree, $path);
      $div->appendChild($a);
      return $div;
    }
    if ($menuTree->child === NULL) continue;
    $div = navmenuTag_xhtmlBreadcrumbFor($d, $menuTree->child, $path,
                                      $separatorNode);
    if ($div === NULL) continue;
    $a = navmenuTag_menuAnchor($d, $menuTree, $path);
    if ($separatorNode !== NULL && $separatorNode->hasChildNodes()) {
      for ($i = $separatorNode->childNodes->length - 1; $i >= 0; --$i) {
        $child = $separatorNode->childNodes->item($i);
        $div->insertBefore($child->cloneNode(TRUE), $div->firstChild);
      }
    }
    $div->insertBefore($a, $div->firstChild);
    return $div;
  }
  return NULL;
}
//______________________________________________________________________

/** Helper function: For the given MenuTree object, return a DOMElement node
    with an &lt;a&gt; tag for the menu entry. $path is the path of the
    "current" page, it is used to create correct relative links and to
    highlight the anchor via class="curpage" if the menu entry is the entry
    for the current page. */
function /*DOMElement*/ navmenuTag_menuAnchor(DOMDocument $d,
    MenuTree $menuTree, /*string*/ $path) {
  $a = NULL;
  dlog('navmenuTag', "_menuAnchor: " . $menuTree->desc);
  if (strpos($menuTree->desc, '<') === FALSE) {
    // No markup in description, can handle this faster without parsing
    $a = $d->createElement('a', $menuTree->desc);
  } else {
    $a = dom_parseXML($d, '<a>' . $menuTree->desc . '</a>');
    if ($a === FALSE) // Parsing markup failed, just include it verbatim
      $a = $d->createElement('a', $menuTree->desc);
  }
  $a->setAttribute('href', relativeURL($path, $menuTree->path));

  $menuPath = $menuTree->path;
  if (substr($menuTree->path, -1) == '/') $menuPath .= 'index';
  if ($path == $menuTree->path
      || $path == $menuPath
      || substr($path, 0, strlen($menuPath) + 1) == $menuPath . '.') {
    $a->setAttribute('class', 'curpage');
  }
  return $a;
}

?>