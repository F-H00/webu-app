<?php

namespace spawnApp\Services;

use Doctrine\DBAL\Exception;
use spawnApp\Database\SeoUrlTable\SeoUrlEntity;
use spawnApp\Database\SeoUrlTable\SeoUrlRepository;
use spawnApp\Database\SeoUrlTable\SeoUrlTable;
use spawnCore\Custom\Gadgets\ClassInspector;
use spawnCore\Custom\Gadgets\MethodInspector;
use spawnCore\Custom\Gadgets\UUID;
use spawnCore\Custom\Throwables\DatabaseConnectionException;
use spawnCore\Custom\Throwables\WrongEntityForRepositoryException;
use spawnCore\Database\Criteria\Criteria;
use spawnCore\Database\Criteria\Filters\AndFilter;
use spawnCore\Database\Criteria\Filters\EqualsFilter;
use spawnCore\Database\Criteria\Filters\InvalidFilterValueException;
use spawnCore\Database\Entity\EntityCollection;
use spawnCore\Database\Entity\InvalidRepositoryInteractionException;
use spawnCore\Database\Entity\RepositoryException;
use spawnCore\Database\Helpers\DatabaseConnection;
use spawnCore\Database\Helpers\DatabaseHelper;
use spawnCore\ServiceSystem\Service;
use spawnCore\ServiceSystem\ServiceContainer;
use spawnCore\ServiceSystem\ServiceContainerProvider;
use spawnCore\ServiceSystem\ServiceTags;

class SeoUrlManager {

    protected SeoUrlRepository $seoUrlRepository;
    protected ServiceContainer $serviceContainer;

    public function __construct(
        SeoUrlRepository $seoUrlRepository
    )
    {
        $this->seoUrlRepository = $seoUrlRepository;
        $this->serviceContainer = ServiceContainerProvider::getServiceContainer();
    }

    public function getSeoUrls(bool $ignoreLocked = false, int $limit = 9999, int $offset = 0): EntityCollection {
        $criteria = new Criteria();
        if($ignoreLocked) {
            $criteria->addFilter(new EqualsFilter('locked', 0));
        }

        return $this->seoUrlRepository->search($criteria, $limit, $offset);
    }

    public function getNumberAvailableSeoUrls(bool $ignoreLocked = false): int {
        $queryBuilder = DatabaseConnection::getConnection()->createQueryBuilder();

        $stmt = $queryBuilder
            ->select('COUNT(*) as count')
            ->from(SeoUrlTable::TABLE_NAME);
        if($ignoreLocked) {
           $stmt->where('locked = 0');
        }

        return $stmt->executeQuery()->fetchAssociative()['count'];
    }

    /**
     * @param string $controller
     * @param string $method
     * @return SeoUrlEntity|null
     * @throws DatabaseConnectionException
     * @throws InvalidFilterValueException
     * @throws RepositoryException
     */
    public function getSeoUrl(string $controller, string $method) {
        return $this->seoUrlRepository->search(
            new Criteria(new AndFilter(
                new EqualsFilter('controller', $controller),
                new EqualsFilter('action', $method)
            ))
        )->first();
    }

    /**
     * @param SeoUrlEntity $seoUrlEntity
     * @throws Exception
     * @throws WrongEntityForRepositoryException
     * @throws DatabaseConnectionException
     */
    public function saveSeoUrlEntity(SeoUrlEntity $seoUrlEntity): void {
        $this->seoUrlRepository->upsert($seoUrlEntity);
    }


    /**
     *  This part is used for "bin/console modules:refresh-actions"
     * @param bool $removeStaleEntries
     * @return array
     * @throws DatabaseConnectionException
     * @throws Exception
     * @throws RepositoryException
     * @throws WrongEntityForRepositoryException
     * @throws InvalidRepositoryInteractionException
     */
    public function refreshSeoUrlEntries(bool $removeStaleEntries = true) {
        /** @var EntityCollection $registeredSeoUrls */
        $registeredSeoUrls = $this->getSeoUrls();
        /** @var ClassInspector[string] $availableControllers */
        $availableControllers = $this->getEveryController();

        $result = [
            'added' => 0
        ];
        // Add controller actions, that have no entry in the database yet
        foreach($availableControllers as $controllerServiceId => $inspectedController) {
            foreach($inspectedController->getLoadedMethods() as $inspectedMethod) {
                $isNew = true;

                /** @var SeoUrlEntity $registeredSeoUrl */
                foreach($registeredSeoUrls->getArray() as $registeredSeoUrl) {
                    if( $registeredSeoUrl->getController() == $controllerServiceId &&
                        $registeredSeoUrl->getAction() == $inspectedMethod->getMethodName())
                    {
                        $isNew = false;
                        break;
                    }
                }

                if($isNew) {

                    $this->seoUrlRepository->upsert(
                        new SeoUrlEntity(
                            $inspectedMethod->getTag('route', ''),
                            $controllerServiceId,
                            $inspectedMethod->getMethodName(),
                            $inspectedMethod->getParameters(),
                            $inspectedMethod->getTag('locked', false),
                            true,
                        )
                    );
                    $result['added']++;
                }

            }
        }



        if($removeStaleEntries) {
            //remove the old actions from $registeredSeoUrls
            $result['removed'] = 0;

            /** @var SeoUrlEntity $registeredSeoUrl */
            foreach($registeredSeoUrls->getArray() as $registeredSeoUrl) {
                $isInUse = false;

                /**
                 * @var string $controllerServiceId
                 * @var ClassInspector $inspectedController
                 */
                foreach($availableControllers as $controllerServiceId => $inspectedController) {
                    foreach($inspectedController->getLoadedMethods() as $inspectedMethod) {
                        if( $registeredSeoUrl->getController() == $controllerServiceId &&
                            $registeredSeoUrl->getAction() == $inspectedMethod->getMethodName())
                        {
                            $isInUse = true;
                            break;
                        }
                    }
                }

                if(!$isInUse) {
                    $this->seoUrlRepository->delete([
                        'id' => UUID::hexToBytes($registeredSeoUrl->getId())
                    ]);
                    $result['removed']++;
                }
            }

        }

        return $result;
    }

    protected function getEveryController(): array {
        /** @var Service[] $controllerServices */
        $controllerServices = $this->getEveryControllerService();
        $list = [];

        foreach($controllerServices as $serviceId => $controllerService) {
            $controller = new ClassInspector($controllerService->getClass(), function(MethodInspector $element) {
                $isMagicMethod = str_starts_with($element->getMethodName(), '__');
                $isControllerActionMethod = str_ends_with($element->getMethodName(), 'Action');
                $isPublic = $element->isPublic();
                return (!$isMagicMethod && $isControllerActionMethod && $isPublic);
            });
            $controller->set('serviceId', $serviceId);

            $list[$serviceId] = $controller;
        }

        return $list;
    }

    protected function getEveryControllerService(): array {
        return $this->serviceContainer->getServicesByTags(
            [
                ServiceTags::BASE_CONTROLLER,
                ServiceTags::BACKEND_CONTROLLER,
            ]
        );
    }

}