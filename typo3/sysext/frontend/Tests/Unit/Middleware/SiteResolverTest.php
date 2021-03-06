<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Frontend\Tests\Unit\Middleware;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Middleware\SiteResolver;
use TYPO3\CMS\Frontend\Tests\Functional\SiteHandling\Fixtures\PhpError;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class SiteResolverTest extends UnitTestCase
{
    /**
     * @var bool Reset singletons created by subject
     */
    protected $resetSingletonInstances = true;

    /**
     * @var SiteFinder|AccessibleObjectInterface
     */
    protected $siteFinder;

    protected $siteFoundRequestHandler;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        // Make global object available, however it is not actively used
        $GLOBALS['TSFE'] = new \stdClass();
        $this->siteFinder = $this->getAccessibleMock(SiteFinder::class, ['dummy'], [], '', false);

        // A request handler which expects a site to be found.
        $this->siteFoundRequestHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var Site $site */
                /** @var SiteLanguage $language */
                $site = $request->getAttribute('site', false);
                $language = $request->getAttribute('language', false);
                if ($site && $language) {
                    return new JsonResponse(
                        [
                            'site' => $site->getIdentifier(),
                            'language-id' => $language->getLanguageId(),
                            'language-base' => $language->getBase(),
                            'rootpage' => $GLOBALS['TSFE']->domainStartPage
                        ]
                    );
                }
                return new NullResponse();
            }
        };

        $cacheManagerProphecy = $this->prophesize(CacheManager::class);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerProphecy->reveal());
    }

    /**
     * Expect a URL handed in, as a request. This URL does not have a GET parameter "id"
     * Then the site handling gets triggered, and the URL is taken to resolve a site.
     *
     * This case tests against a site with no domain or scheme, and successfully finds it.
     *
     * @test
     */
    public function detectASingleSiteWhenProperRequestIsGiven()
    {
        $incomingUrl = 'https://a-random-domain.com/mysite/';
        $siteIdentifier = 'full-site';
        $this->siteFinder->_set('sites', [
            $siteIdentifier => new Site($siteIdentifier, 13, [
                'base' => '/mysite/',
                'languages' => [
                    0 => [
                        'languageId' => 0,
                        'locale' => 'fr_FR.UTF-8',
                        'base' => '/'
                    ]
                ]
            ])
        ]);

        $request = new ServerRequest($incomingUrl, 'GET');
        $subject = new SiteResolver(new SiteMatcher($this->siteFinder));
        $response = $subject->process($request, $this->siteFoundRequestHandler);
        if ($response instanceof NullResponse) {
            $this->fail('No site configuration found in URL ' . $incomingUrl . '.');
        } else {
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->assertEquals($siteIdentifier, $result['site']);
            $this->assertEquals(0, $result['language-id']);
            $this->assertEquals('/mysite/', $result['language-base']);
        }
    }

    /**
     * Scenario with two sites
     * Site 1: /
     * Site 2: /mysubsite/
     *
     * The result should be that site 2 is resolved by the router when calling
     *
     * www.random-result.com/mysubsite/you-know-why/
     *
     * @test
     */
    public function detectSubsiteInsideNestedUrlStructure()
    {
        $incomingUrl = 'https://www.random-result.com/mysubsite/you-know-why/';
        $this->siteFinder->_set('sites', [
            'outside-site' => new Site('outside-site', 13, [
                'base' => '/',
                'languages' => [
                    0 => [
                        'languageId' => 0,
                        'locale' => 'fr_FR.UTF-8',
                        'base' => '/'
                    ]
                ]
            ]),
            'sub-site' => new Site('sub-site', 15, [
                'base' => '/mysubsite/',
                'languages' => [
                    0 => [
                        'languageId' => 0,
                        'locale' => 'fr_FR.UTF-8',
                        'base' => '/'
                    ]
                ]
            ]),
        ]);

        $request = new ServerRequest($incomingUrl, 'GET');
        $subject = new SiteResolver(new SiteMatcher($this->siteFinder));
        $response = $subject->process($request, $this->siteFoundRequestHandler);
        if ($response instanceof NullResponse) {
            $this->fail('No site configuration found in URL ' . $incomingUrl . '.');
        } else {
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->assertEquals('sub-site', $result['site']);
            $this->assertEquals(0, $result['language-id']);
            $this->assertEquals('/mysubsite/', $result['language-base']);
        }
    }

    public function detectSubSubsiteInsideNestedUrlStructureDataProvider()
    {
        return [
            'matches second site' => [
                'https://www.random-result.com/mysubsite/you-know-why/',
                'sub-site',
                14,
                '/mysubsite/'
            ],
            'matches third site' => [
                'https://www.random-result.com/mysubsite/micro-site/oh-yes-you-do/',
                'subsub-site',
                15,
                '/mysubsite/micro-site/'
            ],
            'matches a subsite in first site' => [
                'https://www.random-result.com/products/pampers/',
                'outside-site',
                13,
                '/'
            ],
        ];
    }

    /**
     * Scenario with three sites
     * Site 1: /
     * Site 2: /mysubsite/
     * Site 3: /mysubsite/micro-site/
     *
     * The result should be that site 2 is resolved by the router when calling
     *
     * www.random-result.com/mysubsite/you-know-why/
     *
     * and site 3 when calling
     * www.random-result.com/mysubsite/micro-site/oh-yes-you-do/
     *
     * @test
     * @dataProvider detectSubSubsiteInsideNestedUrlStructureDataProvider
     */
    public function detectSubSubsiteInsideNestedUrlStructure($incomingUrl, $expectedSiteIdentifier, $expectedRootPageId, $expectedBase)
    {
        $this->siteFinder->_set('sites', [
            'outside-site' => new Site('outside-site', 13, [
                'base' => '/',
                'languages' => [
                    0 => [
                        'languageId' => 0,
                        'locale' => 'fr_FR.UTF-8',
                        'base' => '/'
                    ]
                ]
            ]),
            'sub-site' => new Site('sub-site', 14, [
                'base' => '/mysubsite/',
                'languages' => [
                    0 => [
                        'languageId' => 0,
                        'locale' => 'fr_FR.UTF-8',
                        'base' => '/'
                    ]
                ]
            ]),
            'subsub-site' => new Site('subsub-site', 15, [
                'base' => '/mysubsite/micro-site/',
                'languages' => [
                    0 => [
                        'languageId' => 0,
                        'locale' => 'fr_FR.UTF-8',
                        'base' => '/'
                    ]
                ]
            ]),
        ]);

        $request = new ServerRequest($incomingUrl, 'GET');
        $subject = new SiteResolver(new SiteMatcher($this->siteFinder));
        $response = $subject->process($request, $this->siteFoundRequestHandler);
        if ($response instanceof NullResponse) {
            $this->fail('No site configuration found in URL ' . $incomingUrl . '.');
        } else {
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->assertEquals($expectedSiteIdentifier, $result['site']);
            $this->assertEquals($expectedRootPageId, $result['rootpage']);
            $this->assertEquals($expectedBase, $result['language-base']);
        }
    }

    public function detectProperLanguageByIncomingUrlDataProvider()
    {
        return [
            'matches second site' => [
                'https://www.random-result.com/mysubsite/you-know-why/',
                'sub-site',
                14,
                2,
                '/mysubsite/'
            ],
            'matches second site in other language' => [
                'https://www.random-result.com/mysubsite/it/you-know-why/',
                'sub-site',
                14,
                2,
                '/mysubsite/'
            ],
            'matches second site because third site language prefix did not match' => [
                'https://www.random-result.com/mysubsite/micro-site/oh-yes-you-do/',
                'sub-site',
                14,
                2,
                '/mysubsite/'
            ],
            'matches third site' => [
                'https://www.random-result.com/mysubsite/micro-site/ru/oh-yes-you-do/',
                'subsub-site',
                15,
                13,
                '/mysubsite/micro-site/ru/'
            ],
            /**
             * This case does not work, as no language prefix is defined.
            'matches a subsite in first site' => [
                'https://www.random-result.com/products/pampers/',
                'outside-site',
                13,
                0,
                '/'
            ],
             */
            'matches a subsite with translation in first site' => [
                'https://www.random-result.com/fr/products/pampers/',
                'outside-site',
                13,
                1,
                '/fr/'
            ],
        ];
    }

    /**
     * Scenario with three one site and three languages
     * Site 1: /
     *     Language 0: /en/
     *     Language 1: /fr/
     * Site 2: /mysubsite/
     *     Language: 2: /
     * Site 3: /mysubsite/micro-site/
     *     Language: 13: /ru/
     *
     * @test
     * @dataProvider detectProperLanguageByIncomingUrlDataProvider
     */
    public function detectProperLanguageByIncomingUrl($incomingUrl, $expectedSiteIdentifier, $expectedRootPageId, $expectedLanguageId, $expectedBase)
    {
        $this->siteFinder->_set('sites', [
            'outside-site' => new Site('outside-site', 13, [
                'base' => '/',
                'languages' => [
                    0 => [
                        'languageId' => 0,
                        'locale' => 'en_US.UTF-8',
                        'base' => '/en/'
                    ],
                    1 => [
                        'languageId' => 1,
                        'locale' => 'fr_CA.UTF-8',
                        'base' => '/fr/'
                    ]
                ]
            ]),
            'sub-site' => new Site('sub-site', 14, [
                'base' => '/mysubsite/',
                'languages' => [
                    2 => [
                        'languageId' => 2,
                        'locale' => 'it_IT.UTF-8',
                        'base' => '/'
                    ]
                ]
            ]),
            'subsub-site' => new Site('subsub-site', 15, [
                'base' => '/mysubsite/micro-site/',
                'languages' => [
                    13 => [
                        'languageId' => 13,
                        'locale' => 'ru_RU.UTF-8',
                        'base' => '/ru/'
                    ]
                ]
            ]),
        ]);

        $request = new ServerRequest($incomingUrl, 'GET');
        $subject = new SiteResolver(new SiteMatcher($this->siteFinder));
        $response = $subject->process($request, $this->siteFoundRequestHandler);
        if ($response instanceof NullResponse) {
            $this->fail('No site configuration found in URL ' . $incomingUrl . '.');
        } else {
            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->assertEquals($expectedSiteIdentifier, $result['site']);
            $this->assertEquals($expectedRootPageId, $result['rootpage']);
            $this->assertEquals($expectedLanguageId, $result['language-id']);
            $this->assertEquals($expectedBase, $result['language-base']);
        }
    }

    /**
     * @test
     */
    public function checkIf404IsSiteLanguageIsDisabledInFrontend()
    {
        $this->siteFinder->_set('sites', [
            'mixed-site' => new Site('mixed-site', 13, [
                'base' => '/',
                'errorHandling' => [
                    [
                        'errorCode' => 404,
                        'errorHandler' => 'PHP',
                        'errorPhpClassFQCN' => PhpError::class
                    ]
                ],
                'languages' => [
                    0 => [
                        'languageId' => 0,
                        'locale' => 'en_US.UTF-8',
                        'base' => '/en/',
                        'enabled' => false
                    ],
                    1 => [
                        'languageId' => 1,
                        'locale' => 'fr_CA.UTF-8',
                        'base' => '/fr/',
                        'enabled' => true
                    ]
                ]
            ]),
        ]);

        // Reqest to default page
        $request = new ServerRequest('https://twenty.one/en/pilots/', 'GET');
        $subject = new SiteResolver(new SiteMatcher($this->siteFinder));
        $response = $subject->process($request, $this->siteFoundRequestHandler);
        $this->assertEquals(404, $response->getStatusCode());

        $request = new ServerRequest('https://twenty.one/fr/pilots/', 'GET');
        $subject = new SiteResolver(new SiteMatcher($this->siteFinder));
        $response = $subject->process($request, $this->siteFoundRequestHandler);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
