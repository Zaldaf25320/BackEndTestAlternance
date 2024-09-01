<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route('/api/user/enterprises', name: 'get_api_user_enterprises', methods: ['GET'])]
    public function getUserEnterprises(): JsonResponse
    {
        $user = $this->getUser();
        $enterprises = $user->getEnterprises();

        $enterprisesData = [];
        if ($enterprises) {
            foreach ($enterprises as $enterprise) {
                $enterprisesData[] = [
                    'id' => $enterprise->getId(),
                    'name' => $enterprise->getName(),
                    'adress' => $enterprise->getAdress(),
                    'siren' => $enterprise->getSiren(),
                    'tva' => $enterprise->getTva(),
                ];
            }
        }
        return new JsonResponse($enterprisesData);
    }
}
