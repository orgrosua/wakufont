<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Enum\FontFormat;
use App\Entity\File;
use App\Entity\Font;
use App\Repository\FileRepository;
use App\Repository\FontRepository;
use App\Service\GoogleFonts;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/fonts', name: 'api_font_')]
class FontController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, FontRepository $fontRepository): JsonResponse
    {
        $criteria = new Criteria();

        $sort = match ($request->query->get('sort')) {
            'name' => 'family',
            'update' => 'modified_at',
            'popularity' => 'popularity',
            default => 'family',
        };

        $order = match ($request->query->get('order')) {
            'asc' => 'ASC',
            'desc' => 'DESC',
            default => 'ASC',
        };

        $criteria->orderBy([
            $sort => $order,
        ]);

        /** @var Font[] $fonts */
        $fonts = $fontRepository->matching($criteria);

        $response = $this->json([
            'fonts' => $fonts,
        ], 200, [], [
            'groups' => ['font:read'],
        ]);

        // cache publicly for 3600 seconds
        $response->setPublic();
        $response->setMaxAge(3600);

        // (optional) set a custom Cache-Control directive
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    #[Route('/{slug}', name: 'show')]
    public function font(
        #[MapEntity(mapping: [
            'slug' => 'slug',
        ], class: Font::class)]
        Font $font,
        ManagerRegistry $doctrine,
        Request $request,
        FileRepository $fileRepository,
        GoogleFonts $googleFonts
    ): JsonResponse {
        $entityManager = $doctrine->getManager();

        $subsets = $request->query->get('subsets', null);

        if (! $subsets) {
            $subsets = in_array('latin', $font->getSubsets(), true)
                ? ['latin']
                : [$font->getSubsets()[0]];
        } else {
            $querySubsets = explode(',', $subsets);
            $subsets = array_intersect($font->getSubsets(), $querySubsets);
        }

        $subsets = array_unique($subsets);
        sort($subsets);

        $variantKeys = $font->getVariants();

        $fontFiles = $font->getFiles();

        $fontFormats = FontFormat::cases();

        foreach ($variantKeys as $variantKey) {
            foreach ($fontFormats as $fontFormat) {
                $isExistFontFilesVariant = $fontFiles->exists(static function (int $k, File $fontFile) use ($variantKey, $fontFormat, $subsets) {
                    $weight = intval($variantKey);
                    $style = match (preg_replace('/\d/', '', (string) $variantKey)) {
                        'i' => 'italic',
                        'o' => 'oblique',
                        default => 'normal',
                    };

                    $fontFileSubsets = array_unique($fontFile->getSubsets());
                    sort($fontFileSubsets);

                    return $fontFile->getFormat() === $fontFormat->value
                        && $fontFile->getWeight() === $weight
                        && $fontFile->getStyle() === $style
                        && $fontFileSubsets === $subsets;
                });

                if (! $isExistFontFilesVariant) {
                    $fontUrl = $googleFonts->fetchFontFile($font, $fontFormat->value, $variantKey, $subsets);

                    $newFontFile = new File();

                    $newFontFile->setFormat($fontFormat->value);
                    $newFontFile->setWeight(intval($variantKey));
                    $newFontFile->setStyle(match (preg_replace('/\d/', '', (string) $variantKey)) {
                        'i' => 'italic',
                        'o' => 'oblique',
                        default => 'normal',
                    });
                    $newFontFile->setSubsets($subsets);
                    $newFontFile->setUrl($fontUrl);

                    $font->addFile($newFontFile);
                    $entityManager->persist($newFontFile);
                }
            }
        }

        if ($font->getIsSupportVariable()) {
            $italics = [];

            if (count(array_filter($variantKeys, static fn (string $variantKey) => preg_replace('/\d/', '', $variantKey) === '')) > 0) {
                array_push($italics, 0);
            }

            if (count(array_filter($variantKeys, static fn (string $variantKey) => preg_replace('/\d/', '', $variantKey) === 'i')) > 0) {
                array_push($italics, 1);
            }

            foreach ($italics as $italic) {
                $style = match ($italic) {
                    0 => 'normal',
                    1 => 'italic',
                    default => 'normal',
                };

                $filteredFontFilesVariable = $fontFiles->filter(static fn (File $fontFile) => $fontFile->getFormat() === FontFormat::WOFF2->value
                    && $fontFile->getWeight() === 0
                    && $fontFile->getStyle() === $style
                    && in_array($fontFile->getSubsets()[0], $subsets, true));

                if ($filteredFontFilesVariable->count() < count($subsets)) {
                    $fetchedVariableFonts = $googleFonts->fetchVariableFontFile($font, $italic, $font->getAxes());

                    foreach ($fetchedVariableFonts as $fetchedVariableFont) {
                        $existFilteredFontFilesVariable = $filteredFontFilesVariable->exists(static fn (int $k, File $fontFile) => $fontFile->getFormat() === FontFormat::WOFF2->value
                            && $fontFile->getWeight() === 0
                            && $fontFile->getStyle() === $style
                            && in_array($fetchedVariableFont['subset'], $fontFile->getSubsets(), true));

                        if (! $existFilteredFontFilesVariable) {
                            $newFontFile = new File();

                            $newFontFile->setFormat(FontFormat::WOFF2->value);
                            $newFontFile->setWeight(0);
                            $newFontFile->setStyle($style);
                            $newFontFile->setSubsets([$fetchedVariableFont['subset']]);
                            $newFontFile->setUrl($fetchedVariableFont['url']);
                            $newFontFile->setUnicodeRange($fetchedVariableFont['unicodeRange']);

                            $font->addFile($newFontFile);
                            $entityManager->persist($newFontFile);
                        }
                    }
                }
            }
        }

        $entityManager->flush();

        $filteredFontFiles = $fontFiles->filter(static function (File $fontFile) use ($subsets) {
            $fontFileSubsets = array_unique($fontFile->getSubsets());
            sort($fontFileSubsets);

            if ($fontFile->getWeight() === 0) {
                return in_array($fontFile->getSubsets()[0], $subsets, true);
            }
            return $fontFileSubsets === $subsets;
        });

        $response = $this->json([
            'font' => $font,
            'files' => $filteredFontFiles,
        ], 200, [], [
            'groups' => ['font:read', 'file:read'],
        ]);

        // cache publicly for 3600 seconds
        $response->setPublic();
        $response->setMaxAge(3600);

        // (optional) set a custom Cache-Control directive
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }
}
