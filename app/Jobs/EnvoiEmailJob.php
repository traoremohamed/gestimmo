<?php

namespace App\Jobs;

use App\Helpers\Audit;
use App\Helpers\Email;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EnvoiEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $email,
        protected string $nom,
        protected string $messages,
        protected string $sujet,
        protected string $titre,
        protected array $cc = [],
    ) {
    }

    public function handle(): void
    {
        Email::get_envoimailTemplate($this->email, $this->nom, $this->messages, $this->sujet, $this->titre, $this->cc);
    }

    public function failed(?Throwable $exception): void
    {
        Audit::logSave([
            'action' => 'ENVOI MAIL',
            'code_piece' => $this->email,
            'menu' => 'Exception',
            'etat' => 'Erreur: ' . $exception?->getMessage(),
            'objet' => 'ENVOI MAIL',
        ]);
    }
}
