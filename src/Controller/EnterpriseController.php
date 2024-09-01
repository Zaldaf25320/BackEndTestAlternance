<?php

namespace App\Controller;

use App\Entity\Enterprise;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EnterpriseController extends AbstractController
{
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $entityManager;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, EntityManagerInterface $entityManager)
    {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->apiKey = 'fe3e6693-5b1c-4222-be66-935b1cf222e8';
    }


    #[Route('/api/enterprise/register', name: 'register_enterprise', methods: ['POST'])]
    public function registerEnterprise(Request $request): JsonResponse
    {

        $data = json_decode($request->getContent(), true);
        $siret = $data['siret'] ?? null;
        $user = $this->getUser();
        if (!$siret) {
            return new JsonResponse(['message' => 'SIRET manquant'], JsonResponse::HTTP_BAD_REQUEST);
        }
        try {
            $response = $this->httpClient->request('GET', 'https://api.insee.fr/api-sirene/3.11/siret/' . $siret , [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-INSEE-Api-Key-Integration' => $this->apiKey,
                ],
            ]);
            if ($response->getStatusCode() !== 200) {
                return new JsonResponse(['message' => 'Entreprise non trouvée'], JsonResponse::HTTP_NOT_FOUND);
            }

            $enterpriseData = $response->toArray();

            $addressParts = [];

            if (!empty($enterpriseData['etablissement']['adresseEtablissement']['numeroVoieEtablissement'])) {
                $addressParts[] = $enterpriseData['etablissement']['adresseEtablissement']['numeroVoieEtablissement'];
            }
            if (!empty($enterpriseData['etablissement']['adresseEtablissement']['indiceRepetitionEtablissement'])) {
                $addressParts[] = $enterpriseData['etablissement']['adresseEtablissement']['indiceRepetitionEtablissement'];
            }
            if (!empty($enterpriseData['etablissement']['adresseEtablissement']['typeVoieEtablissement'])) {
                $addressParts[] = $enterpriseData['etablissement']['adresseEtablissement']['typeVoieEtablissement'];
            }
            if (!empty($enterpriseData['etablissement']['adresseEtablissement']['libelleVoieEtablissement'])) {
                $addressParts[] = $enterpriseData['etablissement']['adresseEtablissement']['libelleVoieEtablissement'];
            }
            if (!empty($enterpriseData['etablissement']['adresseEtablissement']['codePostalEtablissement'])) {
                $addressParts[] = $enterpriseData['etablissement']['adresseEtablissement']['codePostalEtablissement'];
            }
            if (!empty($enterpriseData['etablissement']['adresseEtablissement']['libelleCommuneEtablissement'])) {
                $addressParts[] = $enterpriseData['etablissement']['adresseEtablissement']['libelleCommuneEtablissement'];
            }

            $completeAddress = implode(' ', $addressParts);

            $enterprise = new Enterprise();
            $enterprise->setName($enterpriseData['etablissement']['uniteLegale']['denominationUniteLegale'] ?? '');
            $enterprise->setAdress($completeAddress);
            $enterprise->setSiren($enterpriseData['etablissement']['siren'] ?? '');
            $enterprise->setTva($this->calculateVatNumber($enterpriseData['etablissement']['siren'] ?? ''));
            $enterprise->setUserD($user);

            $this->entityManager->persist($enterprise);
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Entreprise enregistrée avec succès',
                'enterprise' => [
                    'id' => $enterprise->getId(),
                    'name' => $enterprise->getName(),
                    'adress' => $enterprise->getAdress(),
                    'siren' => $enterprise->getSiren(),
                    'tva' => $enterprise->getTva(),
                ]
                ]);
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/enterprise/{id}', name: 'api_update_enterprise', methods: ['PUT'])]
    public function updateEnterprise(int $id, Request $request): JsonResponse
    {
        $enterprise = $this->entityManager->getRepository(Enterprise::class)->find($id);

        if (!$enterprise) {
            throw new NotFoundHttpException('Entreprise non trouvée');
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $enterprise->setName($data['name']);
        }
        if (isset($data['adress'])) {
            $enterprise->setAdress($data['adress']);
        }
        if (isset($data['siren'])) {
            $enterprise->setSiren($data['siren']);
        }
        if (isset($data['tva'])) {
            $enterprise->setTva($data['tva']);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Entreprise modifier avec succès',
            'enterprise' => [
                'id' => $enterprise->getId(),
                'name' => $enterprise->getName(),
                'adress' => $enterprise->getAdress(),
                'siren' => $enterprise->getSiren(),
                'tva' => $enterprise->getTva(),
            ]
        ]);
    }

    #[Route('/api/enterprise/{id}', name: 'api_delete_enterprise', methods: ['DELETE'])]
    public function deleteEnterprise(int $id): JsonResponse
    {
        $enterprise = $this->entityManager->getRepository(Enterprise::class)->find($id);

        if (!$enterprise) {
            throw new NotFoundHttpException('Entreprise non trouvée');
        }

        $this->entityManager->remove($enterprise);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Entreprise supprimer avec succès']);
    }

    private function calculateVatNumber(string $siren): string
    {
        $vatKey = (12 + 3 * ($siren % 97)) % 97;
        return 'FR' . str_pad((string)$vatKey, 2, '0', STR_PAD_LEFT) . $siren;
    }
}
