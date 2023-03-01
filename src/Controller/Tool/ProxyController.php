<?php

declare(strict_types=1);

namespace App\Controller\Tool;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tool/proxy', name: 'tool_proxy_')]
class ProxyController extends AbstractController
{
    #[Route('/stream', name: 'stream', methods: ['GET'])]
    public function stream_file(Request $request): StreamedResponse|JsonResponse
    {
        $url = $request->query->get('url');

        if (empty($url)) {
            return new JsonResponse([
                'error' => 'url is empty',
            ], Response::HTTP_NOT_FOUND);
        } elseif (! filter_var($url, FILTER_VALIDATE_URL)) {
            return new JsonResponse([
                'error' => 'url is not valid',
            ], Response::HTTP_BAD_REQUEST);
        } elseif (! str_starts_with($url, 'https://fonts.gstatic.com')) {
            return new JsonResponse([
                'error' => 'url is not from google fonts',
            ], Response::HTTP_FORBIDDEN);
        }

        $filename = basename($url);

        $fp = fopen($url, 'rb');

        $response = new StreamedResponse(function () use ($fp) {
            fpassthru($fp);
        });

        foreach (get_headers($url, true) as $k => $v) {
            $response->headers->set($k, $v);
        }

        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
