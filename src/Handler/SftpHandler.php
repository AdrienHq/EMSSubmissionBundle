<?php

declare(strict_types=1);

namespace EMS\SubmissionBundle\Handler;

use EMS\FormBundle\Submission\AbstractHandler;
use EMS\FormBundle\Submission\FailedHandleResponse;
use EMS\FormBundle\Submission\HandleRequestInterface;
use EMS\FormBundle\Submission\HandleResponseInterface;
use EMS\SubmissionBundle\FilesystemFactoryInterface;
use EMS\SubmissionBundle\Request\SftpRequest;
use EMS\SubmissionBundle\Response\ResponseTransformer;
use EMS\SubmissionBundle\Response\SftpHandleResponse;
use EMS\SubmissionBundle\Twig\TwigRenderer;

final class SftpHandler extends AbstractHandler
{
    /** @var FilesystemFactoryInterface */
    private $filesystemFactory;
    /** @var ResponseTransformer */
    private $responseTransformer;
    /** @var TwigRenderer */
    private $twigRenderer;

    public function __construct(
        FilesystemFactoryInterface $filesystemFactory,
        ResponseTransformer $responseTransformer,
        TwigRenderer $twigRenderer
    ) {
        $this->filesystemFactory = $filesystemFactory;
        $this->responseTransformer = $responseTransformer;
        $this->twigRenderer = $twigRenderer;
    }

    public function handle(HandleRequestInterface $handleRequest): HandleResponseInterface
    {
        try {
            $endPoint = $this->twigRenderer->renderEndpointJSON($handleRequest);
            $files = $this->twigRenderer->renderMessageBlockJSON($handleRequest, 'files');
            $sftpRequest = new SftpRequest($endPoint, $files);

            $sftp = $this->filesystemFactory->create($sftpRequest->getAdapter());
            /** @var array<array{path: string, contents: string}> $transportedFiles */
            $transportedFiles = [];

            foreach ($sftpRequest->getFiles() as $file) {
                $result = $sftp->put($file['path'], $file['contents']);

                if ($result) {
                    $transportedFiles[] = $file;
                }
            }

            $handleResponse = new SftpHandleResponse($sftpRequest, $transportedFiles);

            return $this->responseTransformer->transform($handleRequest, $handleResponse);
        } catch (\Exception $exception) {
            return new FailedHandleResponse(\sprintf('Submission failed, contact your admin. (%s)', $exception->getMessage()));
        }
    }
}
