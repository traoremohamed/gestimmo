<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès refusé</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh; background: #f5f5f9;">
<div class="text-center p-4">
    <h1 class="display-4 fw-bold">403</h1>
    <p class="lead">Vous n'avez pas les droits nécessaires pour accéder à cette page.</p>
    <a href="{{ url('/dashboard') }}" class="btn btn-primary">Retour au tableau de bord</a>
</div>
</body>
</html>
