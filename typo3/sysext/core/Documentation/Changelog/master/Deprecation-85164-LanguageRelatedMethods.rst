.. include:: ../../Includes.txt

==============================================
Deprecation: #85164 - Language related methods
==============================================

See :issue:`85164`

Description
===========

Various methods related to site language handling have been deprecated:

* :php:`TYPO3\CMS\Info\Controller\TranslationStatusController->getSystemLanguages()`
* :php:`TYPO3\CMS\Backend\View\PageLayoutView->languageFlag()`

These properties have been deprecated:

* :php:`TYPO3\CMS\Backend\View\PageLayoutView->languageIconTitles`
* :php:`TYPO3\CMS\Backend\View\PageLayoutView->translateTools`


Impact
======

Calling one of the above methods logs deprecation error level messages.


Affected Installations
======================

Instances with extensions calling one of the above methods.


Migration
=========

Above calls can often be substituted using the :php:`Site` object that is always
initialized during core bootstrap. In backend HTTP use cases, the object can be retrieved
using code like this::

    $currentSite = $request->getAttribute('site');
    $availableLanguages = $currentSite->getAvailableLanguages($this->getBackendUser(), false, $this->id);


.. index:: PHP-API, FullyScanned