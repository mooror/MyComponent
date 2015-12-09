MyComponent Extra for MODx Revolution
=======================================


**Original Author:** Bob Ray <http://bobsguides.com> [Bob's Guides](http://bobsguides.com)
**Edited By:** Ben Blake

Documentation is available at [Bob's Guides](http://bobsguides.com/mycomponent-tutorial.html)

MyComponent provides a development environment for creating and distributing Extras
for MODX Revolution.


To create a new Extra with MyComponent, you edit a single project config. file and run the various MyComponent utilities, either directly from their source files or from a UI in the MODX Manager.

Based on the config. file, MyComponent creates all the files and MODX objects necessary for your Extra. The files are automatically placed in the correct directories. In addition, MyComponent will write your lexicon files and the build.transport.php file necessary to create a MODX Transport Package you can submit to the MODX repository for distribution. MyComponent will also create all the resolvers necessary to establish the correct relationships between the objects in your extra (e.g., TV/Template, Resource/Parent, Plugin/Event, etc.).

Editors Note
=======================================

The original MyComponent modx extra was meant to be used through a special UI on the frontend and while it could technically
be used from the commandline it was hard to get things working. I forked this repository in hopes of making this amazing extra easier to use from the commandline.
