<?php

namespace App\Enums\Concerns;

/**
 * Libellés et couleurs Bootstrap des statuts (méthodes LIBELLES / COULEURS de l'enum).
 */
trait AvecLibelle
{
    public function libelle(): string
    {
        return static::LIBELLES[$this->value] ?? $this->value;
    }

    public function couleur(): string
    {
        return static::COULEURS[$this->value] ?? 'secondary';
    }

    /**
     * @return array<string, string> valeur => libellé, pour les listes déroulantes
     */
    public static function options(): array
    {
        return array_combine(array_column(static::cases(), 'value'), array_map(fn ($c) => $c->libelle(), static::cases()));
    }

    /**
     * @return string[]
     */
    public static function valeurs(): array
    {
        return array_column(static::cases(), 'value');
    }
}
