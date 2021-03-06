Envaya is a social networking platform where the "profiles" are websites 
for civil society organizations.
 
It is partly a simple, generic, multi-user content management system
and blogging platform, and partly a structured network with tools/applications
specific to civil society organizations in developing countries.

Envaya is designed to be easy-to-use by people with very limited computer
skills, so it generally favors simplicity over the flexibility or 
customizability offered by generic content management tools such as Wordpress, 
Drupal, or Google Sites. Envaya also is designed for low- bandwidth and mobile 
environments, and provides multilingual support including content translation.

For more information about Envaya's software and design principles, see
http://envaya.org/envaya/page/software

Potential ways of using Envaya's source code may include:

* Contributing code to be used on http://envaya.org, such as new website designs, 
  or applications specific to civil society organizations.
* Adapting Envaya for a different class of users and hosting a service yourself. 
  Currently this would require forking the codebase to remove the civil-society 
  specific parts and customize the tools for another class of users.
* Studying the source code for inspiration, or adapting parts of the source code 
  into unrelated projects.
* ...?

============
Installation
============
See INSTALL.txt

=======
License
=======
See LICENSE.txt

====================
Source Code Overview
====================
index.php 
    - Main entry point for most web requests (except for static files)
_css/
    - compressed CSS files served directly by web server, as well as entry point for testing CSS
_graphics/
    - Image files, served directly by web server
_media/
    - Other static files, such as JavaScript and Flash
build/
    - PHP files dynamically created by scripts/make.php
config/
    - Configuration settings. Local settings should be defined in config/local.php
engine/
    - Core PHP code, mostly autoloaded classes. engine/start.php bootstraps the rest
languages/
    - Translation strings for each supported language
mod/
    - Modules (which may be stored in a separate source control repository with different licensing terms)
      Each module is a (mostly) self-contained implementation of some feature set
      Each module's directory structure looks like:
        mod/<modulename>/{_graphics,engine,languages,schema,testcases,views,start.php}      
      mod/<modulename>/start.php is included by engine/start.php to initialize the module.
schema/
    - SQL for initializing the database
scripts/
    - Miscellaneous command line scripts
test/
    - Test scripts. test/TestSuite.php is main script
themes/
    - Metadata for page themes
vendors/
    - Third-party PHP libraries and external programs 
views/
    - PHP files loaded by the view() function (engine/lib/views.php) 
      which render parts of the output.
      Organized by viewtype, 'default' is standard HTML view
