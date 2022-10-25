<?php

namespace App\Controller\Api;

use App\Entity\Enum\FontFormat;
use App\Entity\File;
use App\Entity\Font;
use App\Repository\FileRepository;
use App\Repository\FontRepository;
use App\Service\GoogleFonts;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/fonts', name: 'api_font_')]
class FontController extends AbstractController
{
    #[Route('/', name: 'index')]
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

        $criteria->orderBy([$sort => $order]);

        /** @var Font[] $fonts */
        $fonts = $fontRepository->matching($criteria);

        return $this->json([
            'fonts' => $fonts,
        ], 200, [], [
            'groups' => ['font:read'],
        ]);
    }

    #[Route('/{slug}', name: 'show')]
    #[ParamConverter('font', options: ['mapping' => ['slug' => 'slug']])]
    public function font(Font $font, ManagerRegistry $doctrine, Request $request, FileRepository $fileRepository, GoogleFonts $googleFonts): JsonResponse
    {
        $entityManager = $doctrine->getManager();

        $subsets = $request->query->get('subsets', null);

        if (!$subsets) {
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
                    $style = match (preg_replace('/\d/', '', $variantKey)) {
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

                if (!$isExistFontFilesVariant) {
                    $fontUrl = $googleFonts->fetchFontFile($font, $fontFormat->value, $variantKey, $subsets);

                    $newFontFile = new File();

                    $newFontFile->setFormat($fontFormat->value);
                    $newFontFile->setWeight(intval($variantKey));
                    $newFontFile->setStyle(match (preg_replace('/\d/', '', $variantKey)) {
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

            $wghtAxes = $font->getAxes()[array_search('wght', array_column($font->getAxes(), 'tag'))];

            if (0 < count(array_filter($variantKeys, static fn (string $variantKey) => preg_replace('/\d/', '', $variantKey) === ''))) {
                array_push($italics, 0);
            }

            if (0 < count(array_filter($variantKeys, static fn (string $variantKey) => preg_replace('/\d/', '', $variantKey) === 'i'))) {
                array_push($italics, 1);
            }

            foreach ($italics as $italic) {
                $style = match ($italic) {
                    0 => 'normal',
                    1 => 'italic',
                    default => 'normal',
                };

                $filteredFontFilesVariable = $fontFiles->filter(static function (File $fontFile) use ($style, $subsets) {
                    return $fontFile->getFormat() === FontFormat::WOFF2->value
                        && $fontFile->getWeight() === 0
                        && $fontFile->getStyle() === $style
                        && in_array($fontFile->getSubsets()[0], $subsets, true);
                });

                if ($filteredFontFilesVariable->count() < count($subsets)) {
                    $fetchedVariableFonts = $googleFonts->fetchVariableFontFile($font, $italic, $wghtAxes);

                    foreach ($fetchedVariableFonts as $fetchedVariableFont) {
                        $existFilteredFontFilesVariable = $filteredFontFilesVariable->exists(static function (int $k, File $fontFile) use ($style, $fetchedVariableFont) {
                            return $fontFile->getFormat() === FontFormat::WOFF2->value
                                && $fontFile->getWeight() === 0
                                && $fontFile->getStyle() === $style
                                && in_array($fetchedVariableFont['subset'], $fontFile->getSubsets(), true);
                        });

                        if (!$existFilteredFontFilesVariable) {
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
                return in_array($fontFile->getSubsets()[0], $subsets);
            } else {
                return $fontFileSubsets === $subsets;
            }
        });

        return $this->json([
            'font' => $font,
            'files' => $filteredFontFiles,
        ], 200, [], [
            'groups' => ['font:read', 'file:read'],
        ]);
    }
}
