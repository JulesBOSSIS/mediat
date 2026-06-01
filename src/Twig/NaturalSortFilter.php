<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class NaturalSortFilter extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('natural_sort', [$this, 'naturalSort']),
        ];
    }

    /**
     * Trie un tableau de manière naturelle en utilisant natsort()
     * Préserve les clés du tableau
     */
    public function naturalSort($collection, $key = null): array
    {
        // Convertir les collections Doctrine en array
        if (is_object($collection) && method_exists($collection, 'toArray')) {
            $array = $collection->toArray();
        } elseif (!is_array($collection)) {
            return [];
        } else {
            $array = $collection;
        }

        // Créer une copie du tableau
        $sortable = $array;

        // Si une clé est spécifiée, trier par cette clé (pour les objets)
        if ($key !== null) {
            usort($sortable, function ($a, $b) use ($key) {
                $valueA = is_object($a) ? $a->{'get' . ucfirst($key)}() : $a[$key];
                $valueB = is_object($b) ? $b->{'get' . ucfirst($key)}() : $b[$key];

                return strnatcmp((string)$valueA, (string)$valueB);
            });
        } else {
            // Tri simple sur les valeurs
            natsort($sortable);
        }

        return $sortable;
    }
}
