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

/** @file Put a message "Last modified by User Name on 2006-12-31" on a
    page. Mostly useful in template files. */

/** @tag lastmodified
    Support for a &lt;lastmodified&gt; tag which is substituted with
    information on the last change to the document: A timestamp and the user
    who made the change. The optional "format" attribute gives the format in
    which the info is output, the default format string is "%Y-%m-%d (%U)"
    which is turned into e.g. "1999-12-13 (Someuser)". Possible escape codes:

    Special extensions:

      %U - username, with '.' or '%' characters turned into spaces and
      initial characters made uppercase. For example, a username "joe.bloggs"
      will be output as "Joe Bloggs"

      %v - SVN revision number, of the revision which last changed the file

    Standard codes:

      %a - abbreviated weekday name according to the current locale

      %A - full weekday name according to the current locale

      %b - abbreviated month name according to the current locale

      %B - full month name according to the current locale

      %c - preferred date and time representation for the current locale

      %C - century number (the year divided by 100 and truncated to an integer, range 00 to 99)

      %d - day of the month as a decimal number (range 01 to 31)

      %D - same as %m/%d/%y

      %e - day of the month as a decimal number, a single digit is preceded by a space (range ' 1' to '31')

      %g - like %G, but without the century.

      %G - The 4-digit year corresponding to the ISO week number (see
      %V). This has the same format and value as %Y, except that if the ISO
      week number belongs to the previous or next year, that year is used
      instead.

      %h - same as %b

      %H - hour as a decimal number using a 24-hour clock (range 00 to 23)

      %I - hour as a decimal number using a 12-hour clock (range 01 to 12)

      %j - day of the year as a decimal number (range 001 to 366)

      %m - month as a decimal number (range 01 to 12)

      %M - minute as a decimal number

      %n - newline character

      %p - either `am' or `pm' according to the given time value, or the
      corresponding strings for the current locale

      %r - time in a.m. and p.m. notation

      %R - time in 24 hour notation

      %S - second as a decimal number

      %t - tab character

      %T - current time, equal to %H:%M:%S

      %u - weekday as a decimal number [1,7], with 1 representing Monday

      %V - The ISO 8601:1988 week number of the current year as a decimal
      number, range 01 to 53, where week 1 is the first week that has at
      least 4 days in the current year, and with Monday as the first day of
      the week. (Use %G or %g for the year component that corresponds to the
      week number for the specified timestamp.)

      %W - week number of the current year as a decimal number, starting with
      the first Monday as the first day of the first week

      %w - day of the week as a decimal, Sunday being 0

      %x - preferred date representation for the current locale without the
      time

      %X - preferred time representation for the current locale without the
      date

      %y - year as a decimal number without a century (range 00 to 99)

      %Y - year as a decimal number including the century

      %Z or %z - time zone or name or abbreviation

      %% - a literal `%' character
 */

require_once(MODULES . '/tidy.php'); // Need this first

tidy_addTag('lastmodified', TRUE, TRUE);

hook_subscribe('xhtmlDom', 'lastmodifiedTag_xhtmlDom');

/** Called by hook, looks for &lt;lastmodified/&gt; elements. */
function lastmodifiedTag_xhtmlDom(&$arr) {
  $document = $arr['result'];
  $path = $arr['path'];

  $tags = $document->getElementsByTagname('lastmodified');
  if ($tags->length == 0) return;

  $info = svn_info($path);
  // Look for a string like ': 2006-01-29 22:05:05 +0100 (' in the output
  $found = preg_match(
    '/: (20\d\d)-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d) ([+-]\d\d\d\d) \(/',
    //  1year    2month 3day   4hour  5min   6sec   7timezone
    $info, $m);
  if ($found == 0)
    $timestamp = time(); // Something went wrong; take current time
  else
    $timestamp = gmmktime($m[4],$m[5],$m[6], $m[2],$m[3],$m[1]);
  // mktime args: hour min sec month day year  is_dst

  // Username
  $found = preg_match('/^Last Changed Author: *(.*)$/m', $info, $m);
  if ($found == 0)
    $user = "unknown";
  else
    $user = $m[1];
  // Turn user "joe.bloggs" into "Joe Bloggs"
  $user = str_replace('.', ' ', $user);
  $user = str_replace('%', ' ', $user);
  $user[0] = strtoupper($user[0]);
  $n = 0;
  while (TRUE) {
    $n = strpos($user, ' ', $n);
    if ($n === FALSE || $n == strlen($user) - 1) break;
    ++$n;
    $user[$n] = strtoupper($user[$n]);
  }

  // Revision
  $found = preg_match('/^Last Changed Rev: *(\d+)$/m', $info, $m);
  if ($found == 0) $rev = '?'; else $rev = $m[1];

  for ($i = $tags->length - 1; $i >= 0; --$i) {
    $tag = $tags->item($i);
    $newNode = NULL;
    if ($tag->hasChildNodes()) {
      $newNode = xhtmlTemplate_errNode($document,
                                       '<lastmodified/> tag must be empty');
    } else {
      $format = $tag->getAttribute('format');
      if (empty($format)) $format = '%Y-%m-%d (%U)';
      $format = str_replace('%U', $user, $format);
      $format = str_replace('%v', $rev, $format);
      $newNode = $document->createTextNode(gmstrftime($format, $timestamp));
    }
    $tag->parentNode->replaceChild($newNode, $tag);
  }
}

?>