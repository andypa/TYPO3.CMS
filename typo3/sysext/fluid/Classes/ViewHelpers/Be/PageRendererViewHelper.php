<?php
namespace TYPO3\CMS\Fluid\ViewHelpers\Be;

/*                                                                        *
 * This script is backported from the TYPO3 Flow package "TYPO3.Fluid".   *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 *  of the License, or (at your option) any later version.                *
 *                                                                        *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * View helper which allows you to create extbase based modules in the style of TYPO3 default modules.
 *
 * = Examples =
 *
 * <code title="All options">
 * <f:be.pageRenderer pageTitle="foo" loadExtJs="true" loadExtJsTheme="false" extJsAdapter="jQuery" enableExtJsDebug="true" loadJQuery="true" includeCssFiles="0: '{f:uri.resource(path:\'Css/Styles.css\')}'" includeJsFiles="0: '{f:uri.resource(path:\'JavaScript/Library1.js\')}', 1: '{f:uri.resource(path:\'JavaScript/Library2.js\')}'" addJsInlineLabels="{0: 'label1', 1: 'label2'}" />
 * </code>
 * <output>
 *
 * Custom CSS file EXT:your_extension/Resources/Public/Css/styles.css and
 * JavaScript files EXT:your_extension/Resources/Public/JavaScript/Library1.js and EXT:your_extension/Resources/Public/JavaScript/Library2.js
 * will be loaded, plus ExtJS and jQuery and some inline labels for usage in JS code.
 * </output>
 */
class PageRendererViewHelper extends AbstractViewHelper {

	/**
	 * @var PageRenderer
	 */
	protected $pageRenderer;

	/**
	 * @param PageRenderer $pageRenderer
	 */
	public function injectPageRenderer(PageRenderer $pageRenderer) {
		$this->pageRenderer = $pageRenderer;
	}

	/**
	 * Render start page with \TYPO3\CMS\Backend\Template\DocumentTemplate and pageTitle
	 *
	 * @param string $pageTitle title tag of the module. Not required by default, as BE modules are shown in a frame
	 * @param bool $loadExtJs specifies whether to load ExtJS library. Defaults to FALSE
	 * @param bool $loadExtJsTheme whether to load ExtJS "grey" theme. Defaults to FALSE
	 * @param bool $enableExtJsDebug if TRUE, debug version of ExtJS is loaded. Use this for development only
	 * @param bool $loadJQuery whether to load jQuery library. Defaults to FALSE
	 * @param array $includeCssFiles List of custom CSS file to be loaded
	 * @param array $includeJsFiles List of custom JavaScript file to be loaded
	 * @param array $addJsInlineLabels Custom labels to add to JavaScript inline labels
	 * @param array $includeRequireJsModules List of RequireJS modules to be loaded
	 * @return void
	 */
	public function render($pageTitle = '', $loadExtJs = FALSE, $loadExtJsTheme = TRUE, $enableExtJsDebug = FALSE, $loadJQuery = FALSE, $includeCssFiles = NULL, $includeJsFiles = NULL, $addJsInlineLabels = NULL, $includeRequireJsModules = NULL) {
		if ($pageTitle) {
			$this->pageRenderer->setTitle($pageTitle);
		}
		if ($loadExtJs) {
			$this->pageRenderer->loadExtJS(TRUE, $loadExtJsTheme);
			if ($enableExtJsDebug) {
				$this->pageRenderer->enableExtJsDebug();
			}
		}
		if ($loadJQuery) {
			$this->pageRenderer->loadJquery(NULL, NULL, PageRenderer::JQUERY_NAMESPACE_DEFAULT_NOCONFLICT);
		}
		// Include custom CSS and JS files
		if (is_array($includeCssFiles) && count($includeCssFiles) > 0) {
			foreach ($includeCssFiles as $addCssFile) {
				$this->pageRenderer->addCssFile($addCssFile);
			}
		}
		if (is_array($includeJsFiles) && count($includeJsFiles) > 0) {
			foreach ($includeJsFiles as $addJsFile) {
				$this->pageRenderer->addJsFile($addJsFile);
			}
		}
		if (is_array($includeRequireJsModules) && count($includeRequireJsModules) > 0) {
			foreach ($includeRequireJsModules as $addRequireJsFile) {
				$this->pageRenderer->loadRequireJsModule($addRequireJsFile);
			}
		}
		// Add inline language labels
		if (is_array($addJsInlineLabels) && count($addJsInlineLabels) > 0) {
			$extensionKey = $this->controllerContext->getRequest()->getControllerExtensionKey();
			foreach ($addJsInlineLabels as $key) {
				$label = LocalizationUtility::translate($key, $extensionKey);
				$this->pageRenderer->addInlineLanguageLabel($key, $label);
			}
		}
	}

}
