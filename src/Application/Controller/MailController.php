<?php
declare(strict_types=1);

namespace Application\Controller;

use Application\Command\SavePostDataToJsonCommand;
use Application\Command\SendConversionToMailCommand;
use Application\Exception\HoneyPotFoundException;
use Application\Exception\Invalid\InvalidDateException;
use Application\Exception\Invalid\InvalidHostSchemaException;
use Application\Exception\NotFound\RequestHeaderNotFoundException;
use Domain\Host;
use Infrastructure\HostFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Infrastructure\Symfony\Controller\SymfonyController;

final class MailController extends SymfonyController
{
    /**
     * @Route("/", methods={"GET"})
     *
     * @return Response
     */
    public function index() : Response
    {
        return new Response('Created by Zbigniew @Ferror Malcherczyk');
    }

    /**
     * @Route("/", methods={"POST"})
     *
     * @param Request $request
     *
     * @throws HoneyPotFoundException
     * @throws InvalidHostSchemaException
     * @throws InvalidDateException
     * @throws RequestHeaderNotFoundException
     *
     * @return Response
     */
    public function saveToJson(Request $request) : Response
    {
        $host = HostFactory::fromRequest($request);

        if (!$host->isSecure()) {
            throw new InvalidHostSchemaException('Host schema must be https');
        }

        if ($request->get('_gotcha')) {
            throw new HoneyPotFoundException();
        }

        if ($this->isOnWhitelist($host)) {
            $this->handle(
                new SavePostDataToJsonCommand(
                    $host,
                    $request->request->all(),
                    $this->getParameter('kernel.project_dir') . '/data/content/'
                )
            );

            if ($request->get('_next')) {
                return new RedirectResponse($request->get('_next'), 302);
            }

            return new JsonResponse([], 204);
        }

        return new JsonResponse(['error' => 'Not on whitelist'], 400);
    }

    /**
     * @Route("/{uuid}", methods={"POST"})
     *
     * @param Request $request
     * @param string $uuid
     *
     * @throws HoneyPotFoundException
     * @throws InvalidHostSchemaException
     * @throws RequestHeaderNotFoundException
     *
     * @return Response
     */
    public function saveToMail(Request $request, string $uuid) : Response
    {
        $host = HostFactory::fromRequest($request);

        if (!$host->isSecure()) {
            throw new InvalidHostSchemaException('Host schema must be https');
        }

        if ($request->get('_gotcha')) {
            throw new HoneyPotFoundException();
        }

        if ($this->isOnWhitelist($host)) {
            $this->handle(
                new SendConversionToMailCommand(
                    $host,
                    $request->request->all(),
                    $this->getParameter('sendgrid.email_address'),
                    $this->renderView('email.html.twig')
                )
            );

            return new JsonResponse([], 204);
        }

        return new JsonResponse(['error' => 'Not on whitelist'], 400);
    }

    /**
     * @Route("/contacts", methods={"POST"})
     *
     * @return Response
     */
    public function createSendGridContact() : Response
    {
        return new Response();
    }

    private function isOnWhitelist(Host $host) : bool
    {
        $file = file_get_contents($this->getParameter('kernel.project_dir') . '/data/whitelist.json');

        return in_array((string) $host, json_decode($file, true), true);
    }
}
