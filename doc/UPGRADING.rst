=======================
 Upgrading Horde_Alarm
=======================

:Contact: dev@lists.horde.org

.. contents:: Contents
.. section-numbering::


This lists the API changes between releases of the package.

Upgrading to 3.0.0
==================
  - Horde_Alarm
    - Use the conservative, PSR-0 interface in lib/ to facilitate upgrading to PHP 8.x versions. This interface will be dropped in later versions.
    - Switch to more type-heavy PSR-4 interface in src/ on your next major revision. All feature development goes here.

Upgrading to 2.2.9
==================

  - Horde_Alarm

    - getErrors()

      The keys of the returned error list contains the alarm ID now, suffixed
      by a NUL character and some alarm method suffix.


Upgrading to 2.2
================

  - Horde_Alarm

    - get(), set()

      Added the 'instanceid' member to the alarm hash.

    - exists()

      Added the $instanceid parameter.


Upgrading to 2.1
================

  - Horde_Alarm

    - getErrors()

      This method has been added.
