Gravity Flow
==============================

[![Build Status](https://travis-ci.org/gravityflow/gravityflow.svg?branch=master)](https://travis-ci.org/gravityflow/gravityflow)

Gravity Flow is a commercial plugin for WordPress which provides a Workflow platform for forms created in Gravity Forms.

This repository is a development version of Gravity Flow intended to facilitate communication with developers. It is not stable and not intended for installation on production sites.

Bug reports and pull requests are welcome.

## Installation Instructions
The only thing you need to do to get this development version working is clone this repository into your plugins directory and activate script debug mode. If you try to use this plugin without script mode on the scripts and styles will not load and it will not work properly.

To enable script debug mode just add the following line to your wp-config.php file:

define( 'SCRIPT_DEBUG', true );

## Support
If you'd like to receive the stable release version, automatic updates and support please purchase a license here: https://gravityflow.io. 

We cannot provide support to anyone without a valid license.

## Test Suites

The integration tests can be installed from the terminal using:

    bash tests/bin/install.sh [DB_NAME] [DB_USER] [DB_PASSWORD] [DB_HOST]


If you're using VVV you can use this command:

	bash tests/bin/install.sh wordpress_unit_tests root root localhost

The acceptance tests require a bit more configuration:
 
1. composer install
2. Download and start either PhantomJS or Selenium.
3. Copy codeception-sample.yml to codeception.yml and adjust it to point to your test site. Warning: the database will cleaned before each run.
4. ./vendor/bin/codecept run


## Documentation
User Guides, FAQ, Walkthroughs and Developer Docs: http://docs.gravityflow.io

Class documentation: http://codex.gravityflow.io

## Translations
If you'd like to translate Gravity Flow into your language please create a free account here:

https://www.transifex.com/projects/p/gravityflow/

## Credits
Contributors:

* Steve Henty @stevehenty
* Richard Wawrzyniak @richardW8k
* Jake Jackson @gravitypdf

Thank you also to all the translators for doing such a great job of keeping up with the updates:

* Samuel Aguilera @samuelaguilera (samuelaguilera.com) - Spanish
* FX Bénard @fxbenard (fxbenard.com) - French
* Erik van Beek @ErikvanBeek (erikvanbeek.nl) - Dutch
* Alexander (Anticisco) Gladkov - Russian
* Edi Weigh (makeii.com) - Chinese

Thank you to all my colleagues at Rocketgenius, Zack Katz (GravityView), Naomi C Bush (Gravity Plus) and Jake Jackson (Gravity PDF) for all their collaboration, support and encouragement.

Copyright 2015-2016 Steven Henty
