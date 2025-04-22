<?php

declare(strict_types=1);


namespace Tests\Acceptance;

use Codeception\Attribute\DataProvider;
use Codeception\Util\HttpCode;
use Tests\Support\AcceptanceTester;

final class PageIsOKCest
{
    private const SITEMAPS_ENV_VAR = 'SITEMAP_URLS';
    private const SITEMAP_LOAD_RETRIES = 3;
    private const SITEMAP_LOAD_RETRY_DELAY_SECONDS = 3;
    private static array $sitemapLoadAttempts = [];

    public function _before(AcceptanceTester $I): void
    {
        // Code here will be executed before each test.
    }

    #[DataProvider('pageProvider')]
    public function pageIsOK(AcceptanceTester $I, \Codeception\Example $example): void
    {   
        $I->amOnUrl($example[0]);
        
        if( !$I->tryToSeeResponseCodeIs(HttpCode::OK) ) {
            $I->seeResponseCodeIs(HttpCode::GONE); // Allow 410 response code
        }

        $I->dontSee('A view rendering issue has occurred');
        $I->dontSeeInSource('<!-- Date component: Invalid date -->');
    }
    
    protected function pageProvider(): array
    {
        $sitemaps = $this->getSitemaps();
        $urls = [];
        
        foreach ($sitemaps as $sitemap) {
            foreach ($this->getUrlsFromSitemap($sitemap) as $urlFromSitemap) {
                $urls[] = $urlFromSitemap . '?pw_test';
            }
        }

        return array_map(fn($url) => [$url], $urls);
    }

    protected function getUrlsFromSitemap(string $sitemapUrl): array
    {
        $sitemap = @simplexml_load_file($sitemapUrl);

        if( $sitemap === false ) {
            
            if(count(array_keys(self::$sitemapLoadAttempts, $sitemapUrl)) < self::SITEMAP_LOAD_RETRIES) {
                self::$sitemapLoadAttempts[] = $sitemapUrl;
                sleep(self::SITEMAP_LOAD_RETRY_DELAY_SECONDS);
                return $this->getUrlsFromSitemap($sitemapUrl);
            }

            throw new \Exception('Failed to load sitemap: ' . $sitemapUrl);
        }

        $urls = [];
        foreach ($sitemap->url as $url) {
            $urls[] = (string)$url->loc;
        }

        if( empty($urls) ) {
            
            if(count(array_keys(self::$sitemapLoadAttempts, $sitemapUrl)) < self::SITEMAP_LOAD_RETRIES) {
                self::$sitemapLoadAttempts[] = $sitemapUrl;
                sleep(self::SITEMAP_LOAD_RETRY_DELAY_SECONDS);
                return $this->getUrlsFromSitemap($sitemapUrl);
            }

            throw new \Exception('No URLs found in sitemap: ' . $sitemapUrl);
        }

        return $urls;
    }

    protected function getSitemaps(): array
    {
        if( getenv(self::SITEMAPS_ENV_VAR) === false ) {
            throw new \Exception('No sitemaps provided, please provide a comma separated list of sitemaps in the '.self::SITEMAPS_ENV_VAR.' environment variable');
        }

        $sitemaps = explode(',', getenv(self::SITEMAPS_ENV_VAR) ?: '');
        $sitemaps = array_map('trim', $sitemaps);
        $sitemaps = array_filter($sitemaps);
        $sitemaps = array_filter($sitemaps, fn($sitemap) => filter_var($sitemap, FILTER_VALIDATE_URL));

        return $sitemaps;
    }
}
