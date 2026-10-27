<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Role;
use Illuminate\Http\Request;

/**
 * Consultation du journal d'audit (schéma audit).
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::visiblesPour()->orderBy('name')->get();
        $agenceId = app(\App\Support\AgenceCourante::class)->id();

        $logs = Log::query()
            // chaque agence ne voit que ses traces ; le super administrateur hors agence voit toute la plateforme
            ->when(!$request->user()->estSuperAdmin() || $agenceId, fn ($q) => $q->where('agence_id', $agenceId ?? 0))
            ->when($request->filled('date_debut'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_debut))
            ->when($request->filled('date_fin'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_fin))
            ->when($request->filled('ip'), fn ($q) => $q->where('ip_addr_log', 'like', '%' . $request->ip . '%'))
            ->when($request->filled('module'), fn ($q) => $q->where('menu_log', 'like', '%' . $request->module . '%'))
            ->when($request->filled('utilisateur'), fn ($q) => $q->where('utilisateur_log', 'like', '%' . $request->utilisateur . '%'))
            ->when($request->filled('privilege'), fn ($q) => $q->where('role_log', $request->privilege))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('audit.index', compact('logs', 'roles'));
    }
}
