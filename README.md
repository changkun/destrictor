# `destrictor`

`destrictor` is a CMS that is oritinally written by [Richard Atterer](http://atterer.org/destrictor),
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

This repository migrates the [original `destrictor`](http://atterer.org/destrictor)
which

- migrates from PHP5 to PHP8+
- migrates from Apache 2.2 to Apache 2.4+
- eliminates the connection of `destrictor` and its served website, i.e. a website that managed by `destrictor`
can be access completely by an irrlevant address;
- UTF-8 native support

## Requirements

- Apache 2.4+
- SVN 1.10+
- PHP 8+
- [php-dom](http://php.net/manual/en/book.dom.php), [php-mbstring](http://php.net/manual/en/book.mbstring.php)
- [tidy](http://tidy.sourceforge.net/)

## Quick start

To use `destrictor` as your CMS:

1. place `destrictor` to a public observable Apache folder (e.g. `/www/var/html`);
2. configure your folders in `config.php`, you only need to set `CACHE`/`REPOSITORY`/`WEBSITEPORT`;
3. access `destrictor` through your browser and follow the installation instructions.

## Design Reason

### Why not migrate to Nginx?

`destrictor` is desgined as a CMS for multi users, therefore the system must provide
access control to different people. Nginx is less convenient than Apache2 since
we are using `.htaccess` for the level of folder authorizations which can be precisely
controled.

### Why not migrate to Git?

`destrictor` has been use for production more than 10 years. From years of
practices and observations, SVN is a better decision than Git since it support sub-directory checkout.
One can simply checkout a needed folder without checkout a entire repository.
Over more than 10 years of using `destrictor`, the volume of the repository is more than 50GB+,
which is impossible to be checkout entirely if a new website user arrives.
Although one can use tools like git-svn, it will increase the complexity of environment and deployment.

## License

GNU GPLv3