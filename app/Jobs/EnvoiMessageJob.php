<?php

namespace App\Jobs;

use App\Models\Agence;
use App\Services\Integration\NotificationService;
use App\Support\AgenceCourante;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envoi e-mail / SMS / WhatsApp en file d'attente, avec la configuration de l'agence émettrice.
 * Chaque tentative est journalisée (envois) ; un échec n'est pas relancé automatiquement.
 */
class EnvoiMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $canal,
        public string $destinataire,
        public string $message,
        public ?string $sujet = null,
        public ?string $nom = null,
        public ?string $modele = null,
        public ?int $agenceId = null,
        public ?string $objetType = null,
        public int|string|null $objetId = null,
        public ?int $userId = null,
        public array $documents = [],
    ) {
    }

    public function handle(AgenceCourante $courante): void
    {
        $envoyer = fn () => NotificationService::envoyer($this->canal, $this->destinataire, $this->message, $this->sujet, $this->nom, $this->modele,
            ['type' => $this->objetType, 'id' => $this->objetId, 'user_id' => $this->userId], null, $this->documents);

        $agence = $this->agenceId ? Agence::find($this->agenceId) : null;
        $agence ? $courante->pour($agence, $envoyer) : $envoyer();
    }
}
