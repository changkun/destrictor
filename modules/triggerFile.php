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

/** @file A mechanism to allow you to trigger the regeneration of pages,
    e.g. by a cron job. */

/** @fileext .trigger

    Triggers are Destrictor's way of implementing cron jobs and regeneration
    of pages in response to other external events.

    The idea of triggers is simple: You use the dependency support,
    e.g. depend_addDependency(), to have your page recreated whenever a file
    like <tt>/daily.trigger</tt> changes. The name of a trigger file must
    always end with ".trigger". It can be located anywhere in the website,
    but usually the top-level directory is used. By convention,
    <tt>/daily.trigger</tt> is triggered by a daily cron job, but you can
    choose other names which only have meaning for your site. For example, if
    you want a page to be updated whenever your server reboots, you could
    choose the name <tt>reboot.trigger</tt> and make the page depend on it.

    What if you only want PHP code of your choice executed and not a page to
    be regenerated? In that case, you still need to commit an .xhtml-php
    file, but from within the code, you can prevent generation of HTML pages
    by setting $info['finished'] = TRUE. See xhtmlFile.php.

    An alternative for executing your PHP code is to load it as a Destrictor
    module and then to subscribe to the trigger hook, or a specific hook like
    trigger_daily.

    Causing a trigger to "fire" is achieved by attempting to make a commit to
    the trigger's name via SVN.  It does not matter what data you attempt to
    commit to the file: This module will always make the attempt fail to
    avoid that the SVN repository is cluttered with the contents of the file
    and the revisions which update it.

    Adding the trigger file in your working copy is a bit awkward, as you
    will have to delete it again afterwards (because the commit attempt will
    always fail). Thus, it is recommended you attempt to update the file with
    a command which does not require a working copy. The "svn mkdir"
    sub-command comes in handy: It performs a commit in a single command and
    it can take an URL as its argument. Example invocation:

    <tt>
    svn mkdir --username admin --password secret -m "" svn://svn.example.org/trunk/daily.trigger
    </tt>

    Only members of the "admin" group can trigger the update. Additionally,
    if a group "trigger" exists, its members can also trigger the update.

    <h3 id="example">Example PHP Code</h3>

    If the code below is inserted in an .xhtml-php file, it will cause that
    file to be regenerated once a day. This assumes that you have set up a
    cronjob which triggers <tt>/daily.trigger</tt>:

    <pre>&lt;?php depend_addDependency($info['path'], '/daily.trigger'); ?&gt;</pre>


    <h3 id="cronjob">Cronjob Setup</h3>

    The following steps are necessary to get a cronjob up and running with an
    Apache-based SVN setup, but much of it also applies to svnserve-based
    setups:

    - Create a user named "trigger", using the command<br/><tt>htpasswd
      /path/to/.htpasswd trigger secret</tt><br/>Use a more secure password
      than "secret"!!!

    - Create a group named "trigger", with the user "trigger" as the only
      member. Do this by editing <tt>/path/to/.htgroup</tt> and adding a line
      "<tt>trigger: trigger</tt>".

    - If your SVN setup uses AuthzSVNAccessFile, you also need to give
      read/write access to the repository to the "trigger" user by adding a
      line "<tt>trigger = rw</tt>" to the <tt>[/]</tt> section in
      <tt>/path/to/svn-repo/conf/authz</tt>, or wherever AuthzSVNAccessFile
      points.

    - Decide which Linux user on the local machine is going to execute the
      svn command. To avoid using root, we'll use "www-data", which (under
      Debian) is the user ID that Apache runs under. However, it can be any
      user who can write to his own home directory.

    - As that user, manually run the svn command once:<br/>
      <tt>su -c 'svn mkdir --username trigger --password secret -m "" https://example.org/svn/repo/trunk/daily.trigger' www-data</tt><br/>
      If the repository is accessed via HTTPS, you will be asked to accept
      the server's certificate. Accept it permanently to avoid the
      interactive prompt in the future.

    - To test the setup, repeat the command. It should complete without a
      prompt, the last line of output should be "OK, executing trigger."

    - Finally, put the above command into <tt>/etc/crontab</tt>, for example
      with a line like this, which executes the trigger at 5 am each
      morning:<br/>
      <tt>00 5 * * * www-data svn mkdir --username trigger --password secret -m "" https://example.org/svn/repo/trunk/daily.trigger 2>/dev/null</tt><br/>
      This is the format of Debian's cron daemon which includes a user name
      field, "www-data" in this case. Depending on your system, you may have
      to omit the user name field and use the "su -c" variant of the command
      from above. Do not forget to discard the command's output by appending
      "2>/dev/null", otherwise most installations will keep mailing the
      command's output to you every day.

   - Note that the cleartext password is now stored in
     <tt>/etc/crontab</tt>. To avoid its being seen by other users on the
     system, it is advisable to make the file readable by root
     only:<br/><tt>chmod go= /etc/crontab</tt>
*/

/** @hook trigger(SvnChange $c)
    @hook trigger_xyz(SvnChange $c)

    These hooks are called when a .trigger file is committed (or rather an
    attempt to do so is made - the actual commit always fails). For a file
    whose leafname is "xyz.trigger", the hook "trigger_xyz" is first
    called. If no subscriber of "trigger_xyz" sets $c->finished = TRUE, the
    hook "trigger" is subsequently called.
*/

hook_subscribe('predirAdded', 'triggerFile_preDirAdded');
hook_subscribe('prefileAdded_trigger', 'triggerFile_preFileAdded_trigger');

/** Called by hook. */
function /*void*/ triggerFile_preDirAdded(SvnChange $c) {
  $len = strlen('.trigger');
  if (substr($c->path, -$len) != '.trigger') return;

  auth_requireGroup('Cannot cause triggers to execute',
                    $c->author, 'admin', 'trigger');

  hook_call('trigger_' . basename($c->path, '.trigger'), $c);
  if ($c->finished !== TRUE) hook_call('trigger', $c);

  /* Note: In contrast to creation of normal directories (anyDirOrFile.php),
     we do not cause a rebuild of pages which depend on changes in the
     .trigger dir's parent directory. */
  //NO:depend_fileChanged(dirname($c->path) . '/');
  depend_fileChanged($c->path);
  /* Because we are in the pre-commit script, atExit will not be called. Call
     depend_atExit manually. */
  depend_atExit();
  throw new Exception('OK, executing trigger');
}

/** Called by hook. */
function /*void*/ triggerFile_preFileAdded_trigger(SvnChange $c) {
  auth_requireGroup('Cannot cause triggers to execute',
                    $c->author, 'admin', 'trigger');

  hook_call('trigger_' . basename($c->path, '.trigger'), $c);
  if ($c->finished !== TRUE) hook_call('trigger', $c);

  //NO:depend_fileChanged(dirname($c->path) . '/');
  depend_fileChanged($c->path);
  depend_atExit();
  throw new Exception('OK, executing trigger');
}

?>