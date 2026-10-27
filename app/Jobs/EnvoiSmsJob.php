<?php

namespace App\Jobs;

use App\Helpers\Audit;
use App\Helpers\Envoisms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class EnvoiSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(protected string $tel, protected string $message)
    {
    }

    public function handle(): void
    {
        if (!Envoisms::sendSMS($this->tel, $this->message)) {
            throw new RuntimeException('La passerelle SMS a refusé le message.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        Audit::logSave([
            'action' => 'ENVOI SMS',
            'code_piece' => $this->tel,
            'menu' => 'Exception',
            'etat' => 'Erreur: ' . $exception?->getMessage(),
            'objet' => 'ENVOI SMS',
        ]);
    }
}
