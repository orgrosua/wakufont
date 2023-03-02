<?php

declare(strict_types=1);

namespace App\Controller\Tool;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tool/google/woff2', name: 'tool_google_woff2_')]
class GoogleWoffTwoController extends AbstractController
{
    #[Route('/compress', name: 'compress', methods: ['POST'])]
    public function compress(Request $request): JsonResponse|StreamedResponse
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');

        /**
         * TODO: use symfony validator component to validate file
         */
        if (! $uploadedFile) {
            return $this->json([
                'status' => 'error',
                'errors' => 'No file uploaded',
            ], Response::HTTP_BAD_REQUEST);
        } elseif (! str_contains($uploadedFile->getMimeType(), 'font') || $uploadedFile->getClientOriginalExtension() !== 'ttf') {
            return $this->json([
                'status' => 'error',
                'errors' => 'The file is not a font file and/or not a ttf file',
            ], Response::HTTP_BAD_REQUEST);
        }

        $process = new Process([
            'woff2_compress',
            $uploadedFile->getPathname(),
        ]);

        $process->run();

        if (! $process->isSuccessful()) {
            return $this->json([
                'status' => 'error',
                'errors' => $process->getErrorOutput(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $compressedFile = $uploadedFile->getPath() . '/' . $uploadedFile->getFilename() . '.woff2';

        $response = new StreamedResponse(function () use ($compressedFile) {
            readfile($compressedFile);
        });

        $filename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME) . '.woff2';

        $response->headers->set('Content-Type', 'font/woff2');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
