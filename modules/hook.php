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

/** @file Hooks are an implementation of the "publisher/subscriber"
    pattern. The publisher wants to make some information available to anyone
    who is interested, and calls hook_call('someHookName',array($any,$args)).

    Any other part of the code can add itself to the hook; This happens with
    a call to hook_subscribe(). If hook_subscribe('foo', 'someHookName') is
    called (by convention, from a file called foo.php), then any call to
    hook_call('someHookName',array($any,$args)) will result in a call to
    foo_someHookName(array($any,$args)). */
//______________________________________________________________________

/* The global $hook_list variable is private to this php file, do not access
   it from outside this file. $hook_list is an array of arrays, it maps
   'someHookName' to an array $a. Each entry in $a is of the form
   $fncName=>$prio, i.e. a string with a function name maps to an integer
   value which gives the priority. */
$hook_list = array(/*string=>array(string=>int)*/);
//______________________________________________________________________

/** Subscribe to a hook; if someone calls the hook, the indicated function
    $fncName() will be called. That function should take
    exactly one argument, its type depends on the hook.
    @param $hookName Name of the hook.
    @param $fncName Name of function to call. For consistency, the format of
    the name should be equal to $module_$hookName, where $module is the
    current php file's basename. For example, if mymod.php subscribes to the
    hook "somethingHappened", then $fncName should be
    "myMod_somethingHappened".
    @param $priority Specifies <em>when</em> to call this hook compared to
    other subscribers. Subscribers with a higher (i.e. larger, more positive)
    $priority value are called first. For any two subscribers with identical
    priority, the order is undefined! The default priority is 0. */
function /*void*/ hook_subscribe(/*string*/ $hookName, /*string*/ $fncName,
                                 /*int*/ $priority = 0) {
  global $hook_list;
  if (!array_key_exists($hookName, $hook_list))
    $hook_list[$hookName] = array();
  $hook_list[$hookName][$fncName] = $priority;
  arsort($hook_list[$hookName], SORT_NUMERIC);
  //syslog(LOG_DEBUG, "hook \"$hookName\": subscribed $fncName");
}
//______________________________________________________________________

/** Call a hook, passing information to everyone who registered with that
    hook. Any subscriber to the hook can abort the call and thus prevent that
    subscribers with lower priority are called. This is only possible if $arg
    is an array. To achieve it, the subscriber must set $arg['finished']=TRUE
    (for arrays) or $arg->finished = TRUE (for objects).
    @param $hookName Name of the hook. If nobody has registered for it,
    hook_call() does nothing.
    @param $arg Argument to pass to subscribers who registered. Can be a
    single argument or an array of values, or an object. */
function /*void*/ hook_call(/*string*/ $hookName, /*mixed*/ &$arg) {
  global $hook_list;
  if (!array_key_exists($hookName, $hook_list)) return; // Nobody registered
  /* Small "=&", big difference: foreach usually creates a copy of the
     array. This can be avoided by passing it a reference to an array. This
     way, any subscriber of the hook can call hook_subscribe() to subscribe
     further functions. However, to prevent double invocations, we need to
     keep track of who we already called. */
  $hookNameList =& $hook_list[$hookName];
  $alreadyCalled = array();
  foreach ($hookNameList as $fncName => $prio) {
    if (array_key_exists($fncName, $alreadyCalled)) continue;
    $alreadyCalled[$fncName] = TRUE;
    //syslog(LOG_DEBUG, "hook \"$hookName\": Calling $fncName");
    call_user_func_array($fncName, array(&$arg));
    if ((is_array($arg) && array_key_exists('finished', $arg)
         && $arg['finished'] === TRUE)
        || (is_object($arg) && isset($arg->finished)
            && $arg->finished === TRUE)) {
      //syslog(LOG_DEBUG, "hook \"$hookName\": $fncName finished hook call");
      break;
    }
  }
}

?>