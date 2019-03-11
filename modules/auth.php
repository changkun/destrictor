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

/** @file Functions for working with the .htgroup files used by Apache. */

/** Load a file containing group data (GROUPFILE by default) and return the
    contents. The keys of the returned array are the group names. The value
    for each key is another array. It contains an entry of the form
    username=&gt;TRUE for each member of the group. Returns FALSE on
    error. */
function /*array(string=>array(string=>TRUE))*/ auth_loadGroupFile(
    /*string*/ $groupFile = GROUPFILE) {
  if (!is_file($groupFile)) return FALSE;
  $g = array(); // group_name_string=>array(member_name_string=>TRUE)
  // Load existing group file
  $groupData = file_get_contents($groupFile);
  if ($groupData === FALSE) return FALSE;
  $groupLines = explode("\n", $groupData);
  foreach ($groupLines as $l) {
    $n = strpos($l, ': ');
    if ($n === FALSE) continue;
    $group = substr($l, 0, $n);
    if (!array_key_exists($group, $g)) $g[$group] = array();
    foreach (explode(' ', substr($l, $n + 2)) as $u)
      if ($u != '') $g[$group][$u] = TRUE;
  }
  return $g;
}
//______________________________________________________________________

/** Convenience function, intended to be called from a "pre"-commit hook,
    e.g. "preFileAdded_foo". Will throw an exception if the named author is
    not a member of the given group, or of the two given groups.

    @param $errorMsg Message to append to default error string. Usually
    something like "Cannot perform action X". There is no need to include
    the required group name, as this function will prepend a string like
    "$author is not a member of group $groupName: "
    @param $author Name of the user
    @param $groupName Name of the group of which $author must be a member,
    such as "admin"
    @param $groupName2 Name of alternative group of which $author may be a
    member for this function to succeed. If NULL, $groupName is the only
    allowed group
    @param $groupFile Alternative filename of group file, if it is not
    GROUPFILE */
function /*void*/ auth_requireGroup(/*string*/ $errorMsg, /*string*/ $author,
    /*string*/ $groupName, /*string*/ $groupName2 = NULL,
    /*string*/ $groupFile = GROUPFILE) {
  if ($author === NULL || $author == '')
    throw new Exception('auth_requireGroup: Bug: $author is NULL');
  $groups = auth_loadGroupFile($groupFile);
  if ($groups === FALSE) { // Do not leak filename of GROUPFILE
    $e = 'Could not load group information from GROUPFILE';
    if ($errorMsg == '') $e .= '.'; else $e .= ': ' . $errorMsg;
    throw new Exception($e);
  }
  // Try $groupName
  if (array_key_exists($groupName, $groups)
      && array_key_exists($author, $groups[$groupName])) return; // All OK!
  // Try $groupName2
  if ($groupName2 !== NULL
      && array_key_exists($groupName2, $groups)
      && array_key_exists($author, $groups[$groupName2])) return; // All OK!

  // Error
  if ($groupName2 === NULL) {
    $e = "\"$author\" is not a member of group \"$groupName\"";
  } else {
    $e = "\"$author\" is not a member of group \"$groupName\" or "
      . "group \"$groupName2\"";
  }
  if ($errorMsg == '') $e .= '.'; else $e .= ': ' . $errorMsg;
  throw new Exception($e);
}

?>