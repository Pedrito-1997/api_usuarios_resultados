<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Result;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ApiResultController
 *
 * @package App\Controller
 *
 * @Route(
 *     path=ApiResultsController::RUTA_API,
 *     name="api_results_"
 * )
 */
class ApiResultsController extends AbstractController
{
    public const RUTA_API = '/api/v1/results';

    /** @var EntityManagerInterface $entityManager */
    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    /**
     * Summary: Returns all results
     * Notes: Returns all results from the system that the user has access to.
     *
     * @param Request $request
     * @return  Response
     * @Route(
     *     ".{_format}",
     *     defaults={"_format": null},
     *     requirements={
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_GET },
     *     name="cget"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="Invalid credentials."
     * )
     */
    public function cgetAction(Request $request): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $results = $this->entityManager
                ->getRepository(Result::class)
                ->findAll();
        } else {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                "`Forbidden`: you don't have permission to access"
            );
        }
        $format = Utils::getFormat($request);

        // No hay resultados?
        if (empty($results)) {
            $message = new Message(Response::HTTP_NOT_FOUND, Response::$statusTexts[404]);
            return Utils::apiResponse(
                $message->getCode(),
                ['message' => $message],
                $format
            );
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ['results' => $results],
            $format
        );
    }

    /**
     * Summary: Returns a result based on a single ID
     * Notes: Returns the result if the user has access to get it.
     *
     * @param Request $request
     * @param int $resultId Result id
     * @return Response
     * @Route(
     *     "/{resultId}.{_format}",
     *     defaults={ "_format": null },
     *     requirements={
     *          "resultId": "\d+",
     *          "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_GET },
     *     name="get"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="Invalid credentials."
     * )
     */
    public function getAction(Request $request, int $resultId): Response
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_USER')) {
            $result = $this->entityManager
                ->getRepository(Result::class)
                ->findOneBy(['id' => $resultId]);

            if (!$this->isGranted('ROLE_ADMIN') && !empty($result) && $result->getUser()->getId() !== $this->getUser()->getId()) {
                throw new HttpException(
                    Response::HTTP_FORBIDDEN,
                    "`Forbidden`: you don't have permission to access"
                );
            }
        } else {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                "`Forbidden`: you don't have permission to access"
            );
        }
        $format = Utils::getFormat($request);

        if (empty($result)) {
            $message = new Message(Response::HTTP_NOT_FOUND, Response::$statusTexts[404]);
            return Utils::apiResponse(
                $message->getCode(),
                ['message' => $message],
                $format
            );
        }

        return Utils::apiResponse(
            Response::HTTP_OK,
            ['result' => $result],
            $format
        );
    }

    /**
     * Summary: Deletes a Result
     * Notes: Deletes the result identified by &#x60;resultId&#x60;.
     *
     * @param Request $request
     * @param int $resultId Result id
     * @return  Response
     * @Route(
     *     "/{resultId}.{_format}",
     *     defaults={"_format": null},
     *     requirements={
     *          "resultId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_DELETE },
     *     name="delete"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="Invalid credentials."
     * )
     */
    public function deleteAction(Request $request, int $resultId): Response
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_USER')) {
            $format = Utils::getFormat($request);

            /** @var Result $result */
            $result = $this->entityManager
                ->getRepository(Result::class)
                ->findOneBy(['id' => $resultId]);

            if (!$this->isGranted('ROLE_ADMIN') && !empty($result) && $result->getUser()->getId() !== $this->getUser()->getId()) {
                throw new HttpException(
                    Response::HTTP_FORBIDDEN,
                    "`Forbidden`: you don't have permission to access"
                );
            }

            if (null === $result) {
                $message = new Message(Response::HTTP_NOT_FOUND, Response::$statusTexts[404]);
                return Utils::apiResponse(
                    $message->getCode(),
                    ['message' => $message],
                    $format
                );
            }

            $this->entityManager->remove($result);
            $this->entityManager->flush();

        } else {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                "`Forbidden`: you don't have permission to access"
            );
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Summary: Provides the list of HTTP supported methods
     * Notes: Return a &#x60;Allow&#x60; header with a list of HTTP supported methods.
     *
     * @param int $resultId Result id
     * @return Response
     * @Route(
     *     "/{resultId}.{_format}",
     *     defaults={"resultId" = 0, "_format": "json"},
     *     requirements={
     *          "resultId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_OPTIONS },
     *     name="options"
     * )
     */
    public function optionsAction(int $resultId): Response
    {
        $methods = $resultId
            ? [Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE]
            : [Request::METHOD_GET, Request::METHOD_POST];

        return new JsonResponse(
            null,
            Response::HTTP_OK,
            ['Allow' => implode(', ', $methods)]
        );
    }

    /**
     * Summary: Creates a result
     *
     * @param Request $request request
     * @return Response
     * @Route(
     *     ".{_format}",
     *     defaults={"_format": null},
     *     requirements={
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_POST },
     *     name="post"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="Invalid credentials."
     * )
     * @throws Exception
     */
    public function postAction(Request $request): Response
    {
        $body = $request->getContent();
        $postData = json_decode($body, true);
        $format = Utils::getFormat($request);

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_USER')) {
            if (!isset($postData['result'], $postData['userId'])) {
                $message = new Message(Response::HTTP_UNPROCESSABLE_ENTITY, Response::$statusTexts[422]);
                return Utils::apiResponse(
                    $message->getCode(),
                    ['message' => $message],
                    $format
                );
            }

            if (!$this->isGranted('ROLE_ADMIN') && $postData['userId'] !== $this->getUser()->getId()) {
                throw new HttpException(
                    Response::HTTP_FORBIDDEN,
                    "`Forbidden`: you don't have permission to access"
                );
            }

            /** @var User $user */
            $user = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['id' => $postData['userId']]);

            if (null === $user) {
                $message = new Message(Response::HTTP_BAD_REQUEST, Response::$statusTexts[400]);
                return Utils::apiResponse(
                    $message->getCode(),
                    ['message' => $message],
                    $format
                );
            }
            $result = new Result($postData['result'], $user, new DateTime('now'));

            $this->entityManager->persist($result);
            $this->entityManager->flush();

        } else {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                "`Forbidden`: you don't have permission to access"
            );
        }

        return Utils::apiResponse(
            Response::HTTP_CREATED,
            ['result' => $result],
            $format
        );
    }

    /**
     * Summary: Updates a result
     * Notes: Updates the result identified by &#x60;resultId&#x60;.
     *
     * @param Request $request request
     * @param int $resultId Result id
     * @return  Response
     * @Route(
     *     "/{resultId}.{_format}",
     *     defaults={"_format": null},
     *     requirements={
     *          "resultId": "\d+",
     *         "_format": "json|xml"
     *     },
     *     methods={ Request::METHOD_PUT },
     *     name="put"
     * )
     *
     * @Security(
     *     expression="is_granted('IS_AUTHENTICATED_FULLY')",
     *     statusCode=401,
     *     message="Invalid credentials."
     * )
     */
    public function putAction(Request $request, int $resultId): Response
    {
        $body = $request->getContent();
        $postData = json_decode($body, true);
        $format = Utils::getFormat($request);

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_USER')) {
            if (isset($postData['userId'])) {
                /** @var User $user */
                $user = $this->entityManager
                    ->getRepository(User::class)
                    ->findOneBy(['id' => $postData['userId']]);

                if (null === $user) {
                    $message = new Message(Response::HTTP_BAD_REQUEST, Response::$statusTexts[400]);
                    return Utils::apiResponse(
                        $message->getCode(),
                        ['message' => $message],
                        $format
                    );

                }
            }

            $result = $this->entityManager
                ->getRepository(Result::class)
                ->findOneBy(['id' => $resultId]);

            if (!$this->isGranted('ROLE_ADMIN') && $result->getUser()->getId() !== $this->getUser()->getId()) {
                throw new HttpException(
                    Response::HTTP_FORBIDDEN,
                    "`Forbidden`: you don't have permission to access"
                );
            }

            if (null === $result) {
                $message = new Message(Response::HTTP_BAD_REQUEST, Response::$statusTexts[400]);
                return Utils::apiResponse(
                    $message->getCode(),
                    ['message' => $message],
                    $format
                );
            }

            $result->setResult(isset($postData['result']) ? $postData['result'] : $result->getResult());
            $result->setUser(isset($postData['userId']) ? $user : $result->getUser());
            $this->entityManager->persist($result);
            $this->entityManager->flush();

        } else {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                "`Forbidden`: you don't have permission to access"
            );
        }

        return Utils::apiResponse(
            209,
            ['result' => $result],
            $format
        );
    }

}