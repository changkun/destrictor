# `destrictor`

`destrictor` is a CMS that was oritinally written by [Richard Atterer](http://atterer.org/`destrictor`), 
which takes a fundamentally different approach 
to some technical aspects of content generation. 
Its aim is to get maximum performance by not building pages when they are fetched, 
but only whenever their content is edited.

Content is not stored in a database, but an SVN repository. 
The CMS is written in PHP, but it does not execute any of its code 
when individual pages are fetched. 
Instead, `destrictor` is called from an SVN post-commit hook (and releated hooks) 
when content changes. It works out what has changed, regenerates the appropriate HTML files, 
and writes them to a "cache" directory, 
which is served by Apache or another web server as static content.

So-called dependencies play a role in making the generation work. 
For example, one of the supplied modules is responsible for generating a menu on each page. 
If the title of a page appears in the navigation menu on other pages, 
then the content of these other pages needs to be regenerated whenever the first page changes. 
The dependency system takes care of this, and also takes dependency loops into account.

The above description may sound as if only static HTML web pages can be generated, 
but this is not the case. The output can also include PHP code snippets, 
from which the system will generate “static PHP files”. As a result, 
only your PHP code will execute when the respective page is fetched, 
and not any of `destrictor`'s code.

This CMS has been in use on a production website for a few years. 
Still, I think a lot of additional work would be required to make it fully-featured.

## About this repository

This repository migrates the [original `destrictor`](http://atterer.org/`destrictor`) that
written by PHP5 to PHP7, and removes the connection between
`destrictor` itself and its served website, i.e. a website that managed by `destrictor`
can be access completely by an irrlevant address.

## Requirements

- Apache 2.4 and above
- PHP 7 and above
- php-dom, php-mbstring

## Getting start

To use `destrictor` as your CMS:

1. place `destrictor` to a public observable Apache folder (e.g. `/www/var/html`);
2. configure your folders in `config.php`;
3. access `destrictor` through your browser and follow the installation instructions.

## License

GNU GPLv3