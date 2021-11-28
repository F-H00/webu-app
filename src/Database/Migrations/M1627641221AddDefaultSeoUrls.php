<?php

namespace spawnApp\Database\Migrations;

use spawn\system\Core\Base\Helper\DatabaseHelper;
use spawn\system\Core\base\AbstractMigration;
use spawn\system\Core\Services\ServiceContainerProvider;
use spawnApp\Database\SeoUrlTable\SeoUrlEntity;
use spawnApp\Database\SeoUrlTable\SeoUrlRepository;

class M1627641221AddDefaultSeoUrls extends AbstractMigration {
    
    public static function getUnixTimestamp(): int
    {
        //Do not edit this!
        return 1627641221;
    }

    function run(DatabaseHelper $dbHelper)
    {
        /** @var SeoUrlRepository $seoUrlRepository */
        $seoUrlRepository = ServiceContainerProvider::getServiceContainer()->getServiceInstance('system.repository.seo_urls');


        $entity = new SeoUrlEntity('/', 'system.fallback.404', 'error404Action', [], false, true);
        $seoUrlRepository->upsert($entity);

        $entity = new SeoUrlEntity('/backend', 'system.backend.base', 'homeAction', [],  true, true);
        $seoUrlRepository->upsert($entity);

        $entity = new SeoUrlEntity('/backend/seo_config/overview', 'system.backend.seo_url_config', 'seoUrlOverviewAction', [], true, true);
        $seoUrlRepository->upsert($entity);

        $entity = new SeoUrlEntity('/backend/seo_config/edit/{ctrl}/{action}', 'system.backend.seo_url_config', 'seoUrlEditAction', [], true, true);
        $seoUrlRepository->upsert($entity);

        $entity = new SeoUrlEntity('/backend/seo_config/edit/submit/{ctrl}/{action}', 'system.backend.seo_url_config', 'seoUrlEditSubmitAction',  [], true, true);
        $seoUrlRepository->upsert($entity);
    }

}
